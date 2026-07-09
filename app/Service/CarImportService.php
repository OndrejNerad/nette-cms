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

    private const IMAGE_MAX_DIMENSION = 1400; // px, longest side
    private const IMAGE_QUALITY       = 78;   // JPEG quality

    public function __construct(
        private readonly Orm                      $orm,
        private readonly CarEquipmentRepository   $carEquipmentRepository,
        private readonly EquipmentItemsRepository $equipmentItemsRepository,
        private readonly ImageOptimizerService     $imageOptimizerService,
    ) {
    }

    public function import(?OutputInterface $output = null, bool $dryRun = false): void
    {
        $output ??= new NullOutput();

        if ($dryRun) {
            $output->writeln('<comment>Running in dry-run mode: no changes will be persisted.</comment>');
        }

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $url                = self::API_URL;
        $imported           = 0;
        $skipped            = 0;
        $seenExternalIds    = [];
        $dryRunProcessedIds = [];

        do {
            $response = $this->fetchPage($url);

            foreach ($response['results'] as $item) {
                $externalId        = (string) $item['id'];
                $seenExternalIds[] = $externalId;

                // in dry-run mode nothing is persisted, so a duplicate id within the
                // same run can't be found via findByExternalId; track it ourselves so
                // the preview counts don't double-count it as a fresh import.
                if ($dryRun && isset($dryRunProcessedIds[$externalId])) {
                    $skipped++;
                    continue;
                }

                $car = $this->orm->cars->findByExternalId($externalId);

                if ($car !== null) {
                    $newHash = md5(json_encode($item['listing_values'] ?? []));
                    if ($car->rawHash === $newHash) {
                        if (empty($car->images) || $car->images === '[]') {
                            $localPaths = $this->downloadImages((string) $item['id'], $item['images'] ?? [], $output, $dryRun);
                            if (!$dryRun) {
                                $car->images = json_encode($localPaths, JSON_THROW_ON_ERROR);
                                $this->orm->cars->persistAndFlush($car);
                            }
                            $message = $dryRun
                                ? "  [dry-run] would download images for car #{$item['id']}"
                                : "  Downloaded images for car #{$item['id']}";
                            $output->writeln($message);
                        }
                        $skipped++;
                        if ($dryRun) {
                            $dryRunProcessedIds[$externalId] = true;
                        }
                        continue;
                    }
                }

                $this->upsert($item, $dryRun);
                $imported++;
                if ($dryRun) {
                    $dryRunProcessedIds[$externalId] = true;
                }
            }

            if (!$dryRun) {
                $this->orm->flush();
            }
            $this->orm->clear();

            $url = $response['next'] !== null
                ? str_replace('http://', 'https://', $response['next'])
                : null;
            $output->writeln("Imported: $imported, Skipped: $skipped");

        } while ($url !== null);

        $output->writeln("Import done. Total imported: $imported, skipped: $skipped.");

        if ($seenExternalIds !== []) {
            $this->cleanupRemovedCars($seenExternalIds, $output, $dryRun);
        }
    }

    private function cleanupRemovedCars(array $seenExternalIds, OutputInterface $output, bool $dryRun = false): void
    {
        $removedCars  = $this->orm->cars->findNotIn($seenExternalIds);
        $removedCount = 0;

        foreach ($removedCars as $car) {
            if ($dryRun) {
                $output->writeln("  [dry-run] would remove car #{$car->externalId} (no longer in feed)");
                $removedCount++;
                continue;
            }

            $images = json_decode($car->images ?? '[]', true) ?: [];
            foreach ($images as $imagePath) {
                $absolutePath = __DIR__ . '/../../www' . $imagePath;
                if (is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $output->writeln("  Removing car #{$car->externalId} (no longer in feed)");
            $this->orm->cars->removeAndFlush($car);
            $removedCount++;
        }

        if ($removedCount > 0) {
            $verb = $dryRun ? 'would be removed' : 'removed';
            $output->writeln("Cleanup done. $removedCount car(s) no longer present in feed $verb.");
        }
    }

    private function upsert(array $item, bool $dryRun = false): void
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
        $car->popis               = $values['popis']                 ?? null;
        $car->vinVerejny          = $values['vin_verejny']           ?? null;
        $car->popisNabidky        = $values['popis_nabidky']         ?? null;
        $car->rawValues           = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $car->rawHash             = md5(json_encode($values)); // ← add this
        $car->images              = json_encode($this->downloadImages($car->externalId, $images, null, $dryRun), JSON_THROW_ON_ERROR);

        $car->updatedAt           = new \DateTimeImmutable();

        $car->detailUrl = $this->generateDetailUrl(
            $values['znacka'] ?? '',
            $values['model'] ?? '',
            (string) $item['id'],
        );

        if (!$car->isPersisted()) {
            $car->createdAt = new \DateTimeImmutable();
        }

        if ($dryRun) {
            return;
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

    private function downloadImages(string $externalId, array $s3Urls, ?OutputInterface $output = null, bool $dryRun = false): array
    {
        $dir = __DIR__ . '/../../www/images/cars/';
        if (!$dryRun && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $keepFilenames = [];
        $localPaths    = [];

        foreach ($s3Urls as $index => $url) {
            $filename        = $externalId . '_' . $index . '.jpg';
            $keepFilenames[] = $filename;
            $dest            = $dir . $filename;

            if ($dryRun) {
                if (!file_exists($dest)) {
                    $output?->writeln("  [dry-run] would download image $index for car #$externalId");
                }
                $localPaths[] = '/images/cars/' . $filename;
                continue;
            }

            if (!file_exists($dest)) {
                $data = @file_get_contents($url);
                if ($data === false) {
                    $output?->writeln("  WARNING: failed to download image $index for car #$externalId");
                    continue;
                }

                $optimized = $this->imageOptimizerService->optimize($data, self::IMAGE_MAX_DIMENSION, self::IMAGE_QUALITY);
                if ($optimized === null) {
                    $output?->writeln("  WARNING: could not process image $index for car #$externalId (unsupported/corrupt format)");
                    continue;
                }
                file_put_contents($dest, $optimized);
            }

            $localPaths[] = '/images/cars/' . $filename;
        }

        $this->removeOrphanedImages($dir, $externalId, $keepFilenames, $output, $dryRun);

        return $localPaths;
    }

    private function removeOrphanedImages(string $dir, string $externalId, array $keepFilenames, ?OutputInterface $output = null, bool $dryRun = false): void
    {
        $existingFiles = glob($dir . $externalId . '_*') ?: [];

        foreach ($existingFiles as $path) {
            if (!in_array(basename($path), $keepFilenames, true)) {
                if ($dryRun) {
                    $output?->writeln("  [dry-run] would remove orphaned image " . basename($path));
                    continue;
                }
                unlink($path);
                $output?->writeln("  Removed orphaned image " . basename($path));
            }
        }
    }

    public function reprocessExistingImages(?OutputInterface $output = null, bool $dryRun = false): void
    {
        $output ??= new NullOutput();
        $dir      = __DIR__ . '/../../www/images/cars/';

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $processedFiles = 0;
        $updatedCars    = 0;

        foreach ($this->orm->cars->findAll() as $car) {
            $images = json_decode($car->images ?? '[]', true) ?: [];
            if ($images === []) {
                continue;
            }

            $newImages = [];
            $changed   = false;

            foreach ($images as $imagePath) {
                $oldAbsolute = __DIR__ . '/../../www' . $imagePath;

                if (!is_file($oldAbsolute)) {
                    continue;
                }

                $data = file_get_contents($oldAbsolute);
                if ($data === false) {
                    $newImages[] = $imagePath;
                    continue;
                }

                $optimized = $this->imageOptimizerService->optimize($data, self::IMAGE_MAX_DIMENSION, self::IMAGE_QUALITY);

                if ($optimized === null) {
                    $output->writeln("  WARNING: could not process " . basename($oldAbsolute) . " (unsupported/corrupt format), leaving as-is");
                    $newImages[] = $imagePath;
                    continue;
                }

                $newFilename = pathinfo($oldAbsolute, PATHINFO_FILENAME) . '.jpg';
                $newRelative = '/images/cars/' . $newFilename;
                $newAbsolute = $dir . $newFilename;

                if ($optimized === $data && $newAbsolute === $oldAbsolute) {
                    $newImages[] = $imagePath;
                    continue;
                }

                if (!$dryRun) {
                    file_put_contents($newAbsolute, $optimized);
                    if ($newAbsolute !== $oldAbsolute) {
                        unlink($oldAbsolute);
                    }
                }

                $output->writeln(($dryRun ? '  [dry-run] would reprocess ' : '  Reprocessed ') . basename($oldAbsolute));
                $newImages[] = $newRelative;
                $changed     = true;
                $processedFiles++;
            }

            if ($changed) {
                if (!$dryRun) {
                    $car->images = json_encode($newImages, JSON_THROW_ON_ERROR);
                    $this->orm->cars->persistAndFlush($car);
                }
                $updatedCars++;
            }
        }

        $verb = $dryRun ? 'would be' : 'were';
        $output->writeln("Reprocess done. $processedFiles file(s) across $updatedCars car(s) $verb updated.");
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