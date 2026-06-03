<?php declare(strict_types=1);

namespace App\Service;

use App\Model\Car\Car;
use App\Model\CarEquipment\CarEquipmentRepository;
use App\Model\Equipment\EquipmentItemsRepository;
use App\Model\Orm;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;

class CarImportService
{
//    private const API_URL   = 'https://automaton-be.stage.thinkeasy.cz/api/v1/listings/';
    private const API_URL   = 'https://app.automaton.cz/api/v1/listings/';
//    private const API_TOKEN = 'YdiDcv6jbjKDAanL1aZi22vvANnPmTVL-s4_D621zy-1ZnNkzgXeC_-gdImyJsgM5kg';
    private const API_TOKEN =  "hfm!tobl0a7csgk73==5os5l%urmh@%g(()-%)1%7vbjv@l4oa";

    public function __construct(
        private readonly Orm                      $orm,
        private readonly CarEquipmentRepository   $carEquipmentRepository,
        private readonly EquipmentItemsRepository $equipmentItemsRepository,
    ) {
    }

    public function import(?OutputInterface $output = null): void
    {
        $output ??= new NullOutput();

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $url      = self::API_URL;
        $imported = 0;
        $skipped  = 0;

        do {
            $response = $this->fetchPage($url);

            foreach ($response['results'] as $item) {
                $car = $this->orm->cars->findByExternalId((string) $item['id']);

                if ($car !== null) {
                    $newHash = md5(json_encode($item['listing_values'] ?? []));
                    if ($car->rawHash === $newHash) {
                        if (empty($car->images) || $car->images === '[]') {
                            $car->images = json_encode($item['images'] ?? [], JSON_THROW_ON_ERROR);
                            $output->writeln("  Updated images for car #{$item['id']}");
                        }
                        $skipped++;
                        continue;
                    }
                }

                $this->upsert($item);
                $imported++;
            }

            $this->orm->flush();
            $this->orm->clear();

            $url = $response['next'];
            $output->writeln("Imported: $imported, Skipped: $skipped");

        } while ($url !== null);

        $output->writeln("Import done. Total imported: $imported, skipped: $skipped.");
    }

    private function upsert(array $item): void
    {
        $values    = $item['listing_values'] ?? [];
        $equipment = $item['equipment']      ?? [];
        $images    = $item['images']         ?? [];

        $car = $this->orm->cars->findByExternalId((string) $item['id']) ?? new Car();

        $car->externalId          = (string) $item['id'];
        $car->description         = $values['description']           ?? null;
        $car->metDojezdu          = $values['met_dojezdu']           ?? null;
        $car->metSpotreby         = $values['met_spotreby']          ?? null;
        $car->kapAkumulatoru      = $values['kap_akumulatoru']       ?? null;
        $car->dojezd              = $values['dojezd']                ?? null;
        $car->plugIn              = $values['plug_in']               ?? null;
        $car->hmotnost            = $values['hmotnost']              ?? null;
        $car->dvere               = $values['dvere']                 ?? null;
        $car->mista               = $values['mista']                 ?? null;
        $car->spotreba            = $values['spotreba']              ?? null;
        $car->maxRychlost         = $values['max_rychlost']          ?? null;
        $car->emise               = $values['emise']                 ?? null;
        $car->zrychleni           = $values['zrychleni']             ?? null;
        $car->tMoment             = $values['t_moment']              ?? null;
        $car->vykonMotoruJednotka = $values['vykon_motoru_jednotka'] ?? null;
        $car->vykonMotoru         = $values['vykon_motoru']          ?? null;
        $car->obsahMotoru         = $values['obsah_motoru']          ?? null;
        $car->odpocet             = $values['odpocet']               ?? null;
        $car->cena                = $values['cena']                  ?? null;
        $car->rokVyroby           = isset($values['rok_vyroby'])     ? (int) $values['rok_vyroby']  : null;
        $car->tachometrJednotka   = $values['tachometr_jednotka']    ?? null;
        $car->tachometr           = isset($values['tachometr'])      ? (int) $values['tachometr']   : null;
        $car->palivo              = $values['palivo']                ?? null;
        $car->barva               = $values['barva']                 ?? null;
        $car->karoserie           = $values['karoserie']             ?? null;
        $car->provedeni           = $values['provedeni']             ?? null;
        $car->model               = $values['model']                 ?? null;
        $car->znacka              = $values['znacka']                ?? null;
        $car->stitek              = $values['stitek']                ?? null;
        $car->popisNabidky        = $values['popis_nabidky']         ?? null;
        $car->rawValues           = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $car->rawHash             = md5(json_encode($values)); // ← add this
        $car->images              = json_encode($images, JSON_THROW_ON_ERROR);

        $car->updatedAt           = new \DateTimeImmutable();

        $car->detailUrl = $this->generateDetailUrl(
            $values['znacka'] ?? '',
            $values['model'] ?? '',
            (string) $item['id'],
        );

        if (!$car->isPersisted()) {
            $car->createdAt = new \DateTimeImmutable();
        }

        // persist + flush so car gets an ID before syncEquipment
        $this->orm->cars->persistAndFlush($car);
        $this->syncEquipment($car->id, $equipment);
    }

    private function syncEquipment(int $carId, array $equipment): void
    {
        $activeKeys = [];

        foreach ($equipment as $key => $data) {
            $key   = (string) $key;
            $title = $data['title'] ?? $key;
            $value = (bool) ($data['value'] ?? false);

            $this->equipmentItemsRepository->upsert($key, $title);

            if ($value) {
                $activeKeys[] = $key;
            }
        }

        $this->carEquipmentRepository->syncForCar($carId, $activeKeys);
    }


    /**
     * @return array{count: int, next: string|null, results: array}
     */
    private function fetchPage(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", [
                    'Authorization: ' . self::API_TOKEN,
                    'Accept: application/json',
                ]),
                'timeout' => 30,
            ],
        ]);

        $body = file_get_contents($url, false, $context);

        if ($body === false) {
            throw new \RuntimeException("Failed to fetch: $url");
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function generateDetailUrl(string $znacka, string $model, string $externalId): string
    {
        $slugify = fn(string $s): string => strtolower(trim(preg_replace(
            ['/[^a-z0-9]+/i', '/-+/'],
            ['-', '-'],
            iconv('UTF-8', 'ASCII//TRANSLIT', $s)
        ), '-'));

        return $slugify($znacka) . '-' . $slugify($model) . '-' . $externalId;
    }
}