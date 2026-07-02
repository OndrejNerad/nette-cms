# Car Import Image Optimization & Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop car-import photos from bloating disk usage — compress every downloaded image, remove orphaned image files, and delete cars (row + images) that drop out of the feed.

**Architecture:** A new standalone `ImageOptimizerService` (pure resize/recompress, no DI dependencies beyond `nette/utils`) is used by `CarImportService::downloadImages()`, which also gains orphan-file cleanup. `CarImportService::import()` tracks every `externalId` seen during a full paginated pass and, once the pass completes successfully, deletes any car not seen. A `--dry-run` flag threads through the whole pipeline for safe verification, and a new backfill command reprocesses already-downloaded production images.

**Tech Stack:** PHP 8.1, Nette Framework (`nette/utils` `Image` class, requires the `gd` PHP extension), Nextras ORM, Symfony Console (via contributte/console).

## Global Constraints

- Max image dimension: **1400px** (longest side), never upscale smaller originals — use `Image::ShrinkOnly`.
- Output format: **JPEG only**, quality **78**. WebP is explicitly out of scope this round.
- All car image files are normalized to a `.jpg` extension regardless of source format (`{externalId}_{index}.jpg`).
- A car absent from a full successful import pass is deleted **immediately** (row + images) — no soft-delete/grace period.
- Cleanup of removed cars must only run after a full paginated import pass completes without an exception, and only if at least one car was seen (never run against an empty/partial result set).
- No automated test suite exists in this repo (no `tests/` directory despite `nette/tester` being a dev dependency, no CI). Verification in this plan uses direct execution against the `docker-compose` dev stack (`php`, `db` services) — start it with `docker compose up -d php db` before running verification steps, and `docker compose exec php <cmd>` (or `docker compose run --rm php <cmd>`) to run PHP inside the container, since `gd` will be installed only inside that container, not on the host.

---

## File Structure

| File | Change |
|---|---|
| `Dockerfile` | Add `gd` PHP extension (+ its apt build deps) |
| `app/Service/ImageOptimizerService.php` | **Create** — pure `optimize()` resize/recompress helper |
| `app/Service/CarImportService.php` | Modify — use `ImageOptimizerService` in `downloadImages()`, add orphan cleanup, add `$seenExternalIds` tracking + `cleanupRemovedCars()`, add `--dry-run` threading, add `reprocessExistingImages()` |
| `app/Model/Car/CarsRepository.php` | Modify — add `findNotIn(array $externalIds): ICollection` |
| `app/Command/ImportCarsCommand.php` | Modify — add `--dry-run` option |
| `app/Command/ReprocessCarImagesCommand.php` | **Create** — backfill command |
| `config/services.neon` | Modify — register `ReprocessCarImagesCommand` |
| `bin/console` | Modify — register `ReprocessCarImagesCommand` with the Console `Application` |

---

### Task 1: Install the `gd` PHP extension

**Files:**
- Modify: `Dockerfile`

**Interfaces:**
- Produces: a `php` container image where `php -m` lists `gd`, required by Task 2's `Nette\Utils\Image` usage.

- [ ] **Step 1: Add `gd` to the Dockerfile**

Replace the contents of `Dockerfile` with:

```dockerfile
FROM php:8.1-fpm
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    git \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install intl mysqli pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*
RUN usermod -u 1000 www-data
WORKDIR /var/www/html
```

- [ ] **Step 2: Rebuild the php container and verify the extension loaded**

Run: `docker compose build php && docker compose run --rm php php -m`
Expected: output includes a line `gd` in the module list.

- [ ] **Step 3: Commit**

```bash
git add Dockerfile
git commit -m "build: add gd PHP extension for car image optimization"
```

---

### Task 2: Create `ImageOptimizerService`

**Files:**
- Create: `app/Service/ImageOptimizerService.php`

**Interfaces:**
- Produces: `ImageOptimizerService::optimize(string $binaryData, int $maxDimension, int $quality): string` — takes raw image bytes, returns re-encoded JPEG bytes resized to fit within `$maxDimension`×`$maxDimension` (never upscaled). If the input isn't a decodable image, returns `$binaryData` unchanged.
- Consumes: `Nette\Utils\Image` (already a transitive dependency via `nette/utils`, confirmed API: `Image::fromString(string): static`, `->resize(int, int, int): static` with `Image::ShrinkOnly`, `->toString(int $type, int $quality): string`, `Nette\Utils\ImageException`).

- [ ] **Step 1: Write the class**

```php
<?php declare(strict_types=1);

namespace App\Service;

use Nette\Utils\Image;
use Nette\Utils\ImageException;

class ImageOptimizerService
{
    /**
     * Resizes an image to fit within $maxDimension x $maxDimension (never upscaling)
     * and re-encodes it as JPEG at the given quality. Returns the original bytes
     * unchanged if they can't be decoded as an image.
     */
    public function optimize(string $binaryData, int $maxDimension, int $quality): string
    {
        try {
            $image = Image::fromString($binaryData);
        } catch (ImageException) {
            return $binaryData;
        }

        $image->resize($maxDimension, $maxDimension, Image::ShrinkOnly);

        return $image->toString(Image::JPEG, $quality);
    }
}
```

- [ ] **Step 2: Verify it manually inside the dev container**

Start the stack and generate a synthetic oversized JPEG, then run it through the optimizer:

Run: `docker compose up -d php db`
Run:
```bash
docker compose exec php php -r '
require "vendor/autoload.php";
$im = imagecreatetruecolor(3000, 2000);
imagefill($im, 0, 0, imagecolorallocate($im, 120, 120, 200));
ob_start();
imagejpeg($im, null, 95);
$original = ob_get_clean();
echo "original bytes: " . strlen($original) . "\n";

$service = new App\Service\ImageOptimizerService();
$optimized = $service->optimize($original, 1400, 78);
echo "optimized bytes: " . strlen($optimized) . "\n";

[$width, $height] = getimagesizefromstring($optimized);
echo "optimized dimensions: {$width}x{$height}\n";
'
```
Expected: `optimized dimensions: 1400x933` (3000x2000 scaled so the longest side is 1400), and `optimized bytes` noticeably smaller than `original bytes`.

- [ ] **Step 3: Verify non-image input falls back safely**

Run:
```bash
docker compose exec php php -r '
require "vendor/autoload.php";
$service = new App\Service\ImageOptimizerService();
$result = $service->optimize("not an image", 1400, 78);
echo $result === "not an image" ? "PASS\n" : "FAIL\n";
'
```
Expected: `PASS`

- [ ] **Step 4: Commit**

```bash
git add app/Service/ImageOptimizerService.php
git commit -m "feat: add ImageOptimizerService for resizing/recompressing car images"
```

---

### Task 3: Compress downloaded images and clean up orphaned files

**Files:**
- Modify: `app/Service/CarImportService.php:181-207` (`downloadImages()`), constructor, class constants

**Interfaces:**
- Consumes: `ImageOptimizerService::optimize(string, int, int): string` (Task 2).
- Produces: `downloadImages()` keeps its existing signature/return type (`array` of `/images/cars/...` web paths) — callers (`import()`, `upsert()`) are unaffected by this task.

- [ ] **Step 1: Add constants and inject `ImageOptimizerService`**

In `app/Service/CarImportService.php`, add the two constants after the existing `API_TOKEN` constant, and add the new constructor dependency:

```php
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
```

- [ ] **Step 2: Rewrite `downloadImages()` to compress and clean up orphans**

Replace the existing `downloadImages()` method (currently lines 181-207) with:

```php
    private function downloadImages(string $externalId, array $s3Urls, ?OutputInterface $output = null): array
    {
        $dir = __DIR__ . '/../../www/images/cars/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $keepFilenames = [];
        $localPaths    = [];

        foreach ($s3Urls as $index => $url) {
            $filename         = $externalId . '_' . $index . '.jpg';
            $keepFilenames[]  = $filename;
            $dest             = $dir . $filename;

            if (!file_exists($dest)) {
                $data = @file_get_contents($url);
                if ($data === false) {
                    $output?->writeln("  WARNING: failed to download image $index for car #$externalId");
                    continue;
                }

                $optimized = $this->imageOptimizerService->optimize($data, self::IMAGE_MAX_DIMENSION, self::IMAGE_QUALITY);
                file_put_contents($dest, $optimized);
            }

            $localPaths[] = '/images/cars/' . $filename;
        }

        $this->removeOrphanedImages($dir, $externalId, $keepFilenames, $output);

        return $localPaths;
    }

    private function removeOrphanedImages(string $dir, string $externalId, array $keepFilenames, ?OutputInterface $output = null): void
    {
        $existingFiles = glob($dir . $externalId . '_*') ?: [];

        foreach ($existingFiles as $path) {
            if (!in_array(basename($path), $keepFilenames, true)) {
                unlink($path);
                $output?->writeln("  Removed orphaned image " . basename($path));
            }
        }
    }
```

Note: the old per-file extension detection (`pathinfo(..., PATHINFO_EXTENSION)`) is removed — every file is now written as `.jpg` since images are always re-encoded.

- [ ] **Step 3: Verify orphan cleanup and compression together**

Run:
```bash
docker compose exec php php bin/console app:import-cars
```
(This hits the real feed — run once, then inspect a car's files.)

Then pick any `externalId` that got imported (from the command output) and check its files:

Run: `docker compose exec php sh -c 'ls -la www/images/cars/ | grep "<externalId>_"'`
Expected: files named `<externalId>_0.jpg`, `<externalId>_1.jpg`, etc. — all `.jpg`, each file size well under typical multi-MB originals (a few hundred KB).

To verify orphan cleanup specifically: note the current image filenames for one car, manually create a fake extra file for it (`docker compose exec php touch www/images/cars/<externalId>_99.jpg`), re-run the import command, and confirm that fake file is gone afterward (it will be removed on the next successful re-download only if that car's `rawHash` changes — since orphan cleanup runs inside `downloadImages()`, which is only invoked when the hash changes or images are empty; this is expected behavior per the design, not a bug to fix here).

- [ ] **Step 4: Commit**

```bash
git add app/Service/CarImportService.php
git commit -m "feat: compress car images on download and remove orphaned image files"
```

---

### Task 4: Delete cars no longer present in the feed

**Files:**
- Modify: `app/Model/Car/CarsRepository.php` (add `findNotIn`)
- Modify: `app/Service/CarImportService.php` (`import()`, new `cleanupRemovedCars()`)

**Interfaces:**
- Produces: `CarsRepository::findNotIn(array $externalIds): ICollection` — all `Car` rows whose `externalId` is not in the given list.
- Produces: `CarImportService::cleanupRemovedCars(array $seenExternalIds, OutputInterface $output): void` (private, called only from `import()`).

- [ ] **Step 1: Add `findNotIn()` to `CarsRepository`**

In `app/Model/Car/CarsRepository.php`, add after `findByExternalId()`:

```php
    public function findNotIn(array $externalIds): ICollection
    {
        return $this->findBy(['externalId!=' => $externalIds]);
    }
```

(`ICollection` is already imported in this file via `use Nextras\Orm\Collection\ICollection;`.)

- [ ] **Step 2: Track seen external IDs and call cleanup after a successful pass**

In `app/Service/CarImportService.php`, modify `import()` (currently lines 26-72). Replace the whole method with:

```php
    public function import(?OutputInterface $output = null): void
    {
        $output ??= new NullOutput();

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $url             = self::API_URL;
        $imported        = 0;
        $skipped         = 0;
        $seenExternalIds = [];

        do {
            $response = $this->fetchPage($url);

            foreach ($response['results'] as $item) {
                $seenExternalIds[] = (string) $item['id'];

                $car = $this->orm->cars->findByExternalId((string) $item['id']);

                if ($car !== null) {
                    $newHash = md5(json_encode($item['listing_values'] ?? []));
                    if ($car->rawHash === $newHash) {
                        if (empty($car->images) || $car->images === '[]') {
                            $localPaths = $this->downloadImages((string) $item['id'], $item['images'] ?? [], $output);
                            $car->images = json_encode($localPaths, JSON_THROW_ON_ERROR);
                            $this->orm->cars->persistAndFlush($car);
                            $output->writeln("  Downloaded images for car #{$item['id']}");
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

            $url = $response['next'] !== null
                ? str_replace('http://', 'https://', $response['next'])
                : null;
            $output->writeln("Imported: $imported, Skipped: $skipped");

        } while ($url !== null);

        $output->writeln("Import done. Total imported: $imported, skipped: $skipped.");

        if (!empty($seenExternalIds)) {
            $this->cleanupRemovedCars($seenExternalIds, $output);
        }
    }

    private function cleanupRemovedCars(array $seenExternalIds, OutputInterface $output): void
    {
        $removedCars = $this->orm->cars->findNotIn($seenExternalIds);
        $dir         = __DIR__ . '/../../www/images/cars/';
        $count       = 0;

        foreach ($removedCars as $car) {
            $files = glob($dir . $car->externalId . '_*') ?: [];
            foreach ($files as $file) {
                unlink($file);
            }

            $this->orm->cars->remove($car);
            $count++;
        }

        if ($count > 0) {
            $this->orm->flush();
        }

        $output->writeln("Removed $count car(s) no longer present in the feed.");
    }
```

(Note: `$seenExternalIds` is only appended to inside `import()`, never reset per page, so it correctly accumulates across the whole paginated pass. If `fetchPage()` throws mid-pagination, the exception propagates out of `import()` before reaching the `cleanupRemovedCars()` call — no cleanup runs on a partial pass, as required.)

- [ ] **Step 3: Verify against the dev database**

Run: `docker compose exec php php bin/console app:import-cars`
Expected output ends with a line like `Removed 0 car(s) no longer present in the feed.` on a normal run (nothing should be removed if the feed and DB are in sync).

To verify actual removal works, temporarily insert a fake car row that can't possibly be in the real feed, then re-run:

Run:
```bash
docker compose exec php php -r '
require "vendor/autoload.php";
$container = (new App\Bootstrap)->bootCliApplication();
$orm = $container->getByType(App\Model\Orm::class);
$car = new App\Model\Car\Car();
$car->externalId = "TEST-CLEANUP-99999";
$car->images = "[]";
$car->createdAt = new DateTimeImmutable();
$car->updatedAt = new DateTimeImmutable();
$orm->cars->persistAndFlush($car);
echo "inserted test car id={$car->id}\n";
'
docker compose exec php php bin/console app:import-cars
docker compose exec php php -r '
require "vendor/autoload.php";
$container = (new App\Bootstrap)->bootCliApplication();
$orm = $container->getByType(App\Model\Orm::class);
$car = $orm->cars->findByExternalId("TEST-CLEANUP-99999");
echo $car === null ? "PASS: test car removed\n" : "FAIL: test car still present\n";
'
```
Expected: `Removed 1 car(s) no longer present in the feed.` in the import output, then `PASS: test car removed`.

- [ ] **Step 4: Commit**

```bash
git add app/Model/Car/CarsRepository.php app/Service/CarImportService.php
git commit -m "feat: delete cars and their images when no longer present in the feed"
```

---

### Task 5: Add `--dry-run` support

**Files:**
- Modify: `app/Command/ImportCarsCommand.php`
- Modify: `app/Service/CarImportService.php` (`import()`, `upsert()`, `downloadImages()`, `removeOrphanedImages()`, `cleanupRemovedCars()`)

**Interfaces:**
- Produces: `CarImportService::import(?OutputInterface $output = null, bool $dryRun = false): void` — the `$dryRun` parameter is new and defaults to `false`, so Task 4's/Task 3's call sites and any other caller of `import()` (e.g. `www/import.php`, if it calls `import()` with one argument) keep working unchanged.

- [ ] **Step 1: Add the `--dry-run` option to the command**

Replace `app/Command/ImportCarsCommand.php` with:

```php
<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CarImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCarsCommand extends Command
{
    public function __construct(
        private readonly CarImportService $carImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:import-cars');
        $this->setDescription('Import cars from external API');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing to disk or the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $output->writeln($dryRun ? '<comment>Starting car import (dry-run)...</comment>' : '<info>Starting car import...</info>');
        $this->carImportService->import($output, $dryRun);
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Thread `$dryRun` through `CarImportService`**

In `app/Service/CarImportService.php`, apply these changes:

`import()` signature and body — replace the method (built on top of Task 4's version) with:

```php
    public function import(?OutputInterface $output = null, bool $dryRun = false): void
    {
        $output ??= new NullOutput();

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $url             = self::API_URL;
        $imported        = 0;
        $skipped         = 0;
        $seenExternalIds = [];

        do {
            $response = $this->fetchPage($url);

            foreach ($response['results'] as $item) {
                $seenExternalIds[] = (string) $item['id'];

                $car = $this->orm->cars->findByExternalId((string) $item['id']);

                if ($car !== null) {
                    $newHash = md5(json_encode($item['listing_values'] ?? []));
                    if ($car->rawHash === $newHash) {
                        if (empty($car->images) || $car->images === '[]') {
                            $localPaths = $this->downloadImages((string) $item['id'], $item['images'] ?? [], $output, $dryRun);
                            if (!$dryRun) {
                                $car->images = json_encode($localPaths, JSON_THROW_ON_ERROR);
                                $this->orm->cars->persistAndFlush($car);
                            }
                            $output->writeln(($dryRun ? '  [dry-run] Would download' : '  Downloaded') . " images for car #{$item['id']}");
                        }
                        $skipped++;
                        continue;
                    }
                }

                $this->upsert($item, $dryRun);
                $imported++;
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

        if (!empty($seenExternalIds)) {
            $this->cleanupRemovedCars($seenExternalIds, $output, $dryRun);
        }
    }
```

`upsert()` — add a `bool $dryRun = false` parameter and an early return before any DB write. Replace the method signature and its final lines (everything from `$car->images = ...` to the end) with:

```php
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
        $car->rawHash             = md5(json_encode($values));
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
```

`downloadImages()` and `removeOrphanedImages()` — add `bool $dryRun = false` and skip writes/deletes:

```php
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

            if (!file_exists($dest)) {
                $data = @file_get_contents($url);
                if ($data === false) {
                    $output?->writeln("  WARNING: failed to download image $index for car #$externalId");
                    continue;
                }

                if ($dryRun) {
                    $output?->writeln("  [dry-run] Would save optimized image $filename");
                } else {
                    $optimized = $this->imageOptimizerService->optimize($data, self::IMAGE_MAX_DIMENSION, self::IMAGE_QUALITY);
                    file_put_contents($dest, $optimized);
                }
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
                    $output?->writeln("  [dry-run] Would remove orphaned image " . basename($path));
                } else {
                    unlink($path);
                    $output?->writeln("  Removed orphaned image " . basename($path));
                }
            }
        }
    }
```

`cleanupRemovedCars()` — add `bool $dryRun = false` and skip deletes:

```php
    private function cleanupRemovedCars(array $seenExternalIds, OutputInterface $output, bool $dryRun = false): void
    {
        $removedCars = $this->orm->cars->findNotIn($seenExternalIds);
        $dir         = __DIR__ . '/../../www/images/cars/';
        $count       = 0;

        foreach ($removedCars as $car) {
            $files = glob($dir . $car->externalId . '_*') ?: [];
            foreach ($files as $file) {
                if ($dryRun) {
                    $output->writeln("  [dry-run] Would delete image " . basename($file));
                } else {
                    unlink($file);
                }
            }

            if ($dryRun) {
                $output->writeln("  [dry-run] Would remove car #{$car->externalId} (no longer in feed)");
            } else {
                $this->orm->cars->remove($car);
            }

            $count++;
        }

        if (!$dryRun && $count > 0) {
            $this->orm->flush();
        }

        $output->writeln(($dryRun ? '[dry-run] Would remove' : 'Removed') . " $count car(s) no longer present in the feed.");
    }
```

- [ ] **Step 3: Verify dry-run makes no changes**

Run: `docker compose exec php php bin/console app:import-cars --dry-run`
Expected: output includes `Starting car import (dry-run)...` and any `[dry-run]` prefixed lines, with a final `Removed 0 car(s)...` or `[dry-run] Would remove N car(s)...` line — but no new/changed files under `www/images/cars/` and no DB changes.

Confirm no DB changes: capture a row count before and after.

Run:
```bash
docker compose exec php php -r '
require "vendor/autoload.php";
$container = (new App\Bootstrap)->bootCliApplication();
$orm = $container->getByType(App\Model\Orm::class);
echo "car count: " . count($orm->cars->findAll()) . "\n";
'
```
Run this once before and once after the `--dry-run` command — the counts must be identical.

- [ ] **Step 4: Commit**

```bash
git add app/Command/ImportCarsCommand.php app/Service/CarImportService.php
git commit -m "feat: add --dry-run flag to car import for safe verification"
```

---

### Task 6: Backfill command for already-downloaded production images

**Files:**
- Create: `app/Command/ReprocessCarImagesCommand.php`
- Modify: `app/Service/CarImportService.php` (new `reprocessExistingImages()`)
- Modify: `config/services.neon`
- Modify: `bin/console`

**Interfaces:**
- Consumes: `CarImportService::import(?OutputInterface, bool): void` (Task 5), `ImageOptimizerService::optimize()` (Task 2).
- Produces: `CarImportService::reprocessExistingImages(?OutputInterface $output = null, bool $dryRun = false): void`.

- [ ] **Step 1: Add `reprocessExistingImages()` to `CarImportService`**

Add this public method (e.g. directly after `import()`):

```php
    public function reprocessExistingImages(?OutputInterface $output = null, bool $dryRun = false): void
    {
        $output ??= new NullOutput();
        $dir = __DIR__ . '/../../www/images/cars/';

        $files = is_dir($dir) ? (glob($dir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []) : [];
        $count = 0;

        foreach ($files as $path) {
            $data = file_get_contents($path);
            if ($data === false) {
                continue;
            }

            $optimized = $this->imageOptimizerService->optimize($data, self::IMAGE_MAX_DIMENSION, self::IMAGE_QUALITY);

            if ($dryRun) {
                $output->writeln('  [dry-run] Would reprocess ' . basename($path) . ' (' . strlen($data) . ' -> ' . strlen($optimized) . ' bytes)');
            } else {
                file_put_contents($path, $optimized);
            }

            $count++;
        }

        $output->writeln(($dryRun ? '[dry-run] Would reprocess' : 'Reprocessed') . " $count existing image file(s).");

        $this->import($output, $dryRun);
    }
```

- [ ] **Step 2: Create the command**

```php
<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CarImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReprocessCarImagesCommand extends Command
{
    public function __construct(
        private readonly CarImportService $carImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:reprocess-car-images');
        $this->setDescription('Re-compress already-downloaded car images and run the feed cleanup once against current production data');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing to disk or the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $output->writeln($dryRun ? '<comment>Starting car image reprocess (dry-run)...</comment>' : '<info>Starting car image reprocess...</info>');
        $this->carImportService->reprocessExistingImages($output, $dryRun);
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 3: Register the command in `config/services.neon`**

In `config/services.neon`, add a line after `- App\Command\ImportCarsCommand`:

```neon
    - App\Command\ImportCarsCommand
    - App\Command\ReprocessCarImagesCommand
    - App\Command\ImportSocialItemsCommand
```

- [ ] **Step 4: Register the command in `bin/console`**

In `bin/console`, add after the `ImportCarsCommand` line:

```php
$app->add($container->getByType(App\Command\ImportCarsCommand::class));
$app->add($container->getByType(App\Command\ReprocessCarImagesCommand::class));
```

- [ ] **Step 5: Verify**

Run: `docker compose exec php php bin/console app:reprocess-car-images --dry-run`
Expected: output lists `[dry-run] Would reprocess ...` for each existing file under `www/images/cars/` (or `Would reprocess 0 existing image file(s).` if none exist yet in this environment), followed by the normal dry-run import output ending in a `Removed`/`Would remove` car-count line.

- [ ] **Step 6: Commit**

```bash
git add app/Command/ReprocessCarImagesCommand.php app/Service/CarImportService.php config/services.neon bin/console
git commit -m "feat: add backfill command to reprocess existing car images"
```

---

## Post-Plan Follow-Up (not part of this plan)

- Revisit WebP output once JPEG re-encoding is proven in production (per design doc's "Out of Scope" section).
- Run `app:reprocess-car-images` once against production after deploy to shrink already-downloaded originals.
