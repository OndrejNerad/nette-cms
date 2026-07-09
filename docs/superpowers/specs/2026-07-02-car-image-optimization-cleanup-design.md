# Car Import — Image Optimization & Inactive Cleanup Design

**Date:** 2026-07-02
## Solution Overview

1. Resize + recompress every downloaded image to JPEG before writing to disk.
2. Before writing a car's new image set, delete any of that car's existing files that aren't part of the freshly fetched set.
3. After a full import pass completes, delete any car (row + images) whose `externalId` wasn't seen anywhere in that pass.
4. Add a `--dry-run` flag and a one-off backfill command to safely apply this to already-imported production data.

WebP output is an explicitly deferred follow-up (see Out of Scope) — this round ships JPEG only.

## Architecture

### 1. Image optimization

**Dockerfile**: add the `gd` extension (currently only `intl mysqli pdo pdo_mysql` are installed):
```
apt-get install -y libjpeg-dev libpng-dev \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install gd ...
```

**`CarImportService::downloadImages()`**: after fetching raw bytes and before writing, load with `Nette\Utils\Image::fromString($data)`, resize, and re-encode as JPEG using the same API `SocialImportService.php` already demonstrates (`$image->resize(...)`, `$image->save($file, $quality, Image::JPEG)`).

Constants alongside the existing `API_URL`/`API_TOKEN` pattern:
```php
private const IMAGE_MAX_DIMENSION = 1400; // px, longest side
private const IMAGE_QUALITY       = 78;   // JPEG quality
```

Resize call uses `Image::SHRINK_ONLY` so images already smaller than 1400px are never upscaled. All output files are normalized to `.jpg` regardless of the source extension (source may be `.jpg`/`.png`/etc.; we always re-encode to JPEG now), so the stored filename becomes `{externalId}_{index}.jpg`.

If `Image::fromString()`/resize fails on a corrupt/unsupported download, catch and fall back to writing the original bytes as-is (with a warning logged), rather than losing the photo entirely.

### 2. Orphan cleanup on every import

Still inside `downloadImages()`, before writing the new set for a given `externalId`:

1. `glob($dir . $externalId . '_*')` to find all files currently on disk for this car.
2. Compute the new filenames about to be written (`{externalId}_{index}.jpg` for each index in the incoming `$s3Urls`).
3. Delete any existing file for this `externalId` that isn't in the new filename set.

This runs unconditionally whenever `downloadImages()` is called (i.e. on every upsert where the feed hash changed, which is the only place it's currently invoked with a live image list) — so a car whose photo count shrinks from 12 to 8 no longer leaves 4 stale files behind.

### 3. Detect & remove cars no longer in the feed

**`CarImportService::import()`** (`app/Service/CarImportService.php:26-72`):
- Accumulate every `externalId` seen into `$seenExternalIds[] = (string) $item['id'];` inside the existing `foreach ($response['results'] as $item)` loop — both on the "unchanged, skip" branch and the upsert branch.
- After the `do…while` loop exits normally (i.e. execution reaches the line after the loop without an exception having propagated out — `fetchPage()` throwing on a failed page must abort the whole run and skip cleanup, since a partial `$seenExternalIds` would otherwise look like cars were removed):
  ```php
  if (!empty($seenExternalIds)) {
      $this->cleanupRemovedCars($seenExternalIds, $output);
  }
  ```
  The `empty()` guard prevents wiping every car in the (should-never-happen) case the feed returns zero results across all pages.

**New `CarsRepository` method**: `findNotIn(array $externalIds): ICollection` — cars whose `externalId` is not in the given list.

**New `cleanupRemovedCars()` in `CarImportService`**:
- For each car returned by `findNotIn()`: delete its image files (`glob($dir . $car->externalId . '_*')` + `unlink`), then `$this->orm->cars->remove($car)`.
- Flush once after the loop.
- Log the count removed to `$output`.
- Nextras cascades the `car_equipment` pivot rows automatically on removal, since `Car::$equipment` is the `isMain=true` side of the m:m relationship — no manual pivot cleanup needed.

Deletion is immediate (no soft-delete/grace period): a car absent from a full feed pass is deleted outright, row and images. The 3x/daily import cadence combined with the "only cleans up after a fully successful pass" guard is judged sufficient protection against feed flakiness; `upsert()` already handles a car reappearing later as a normal fresh insert.

### 4. Safety: dry-run flag

`ImportCarsCommand` gets a `--dry-run` option, threaded through to `CarImportService::import()`. In dry-run mode:
- Image optimization/orphan-cleanup still don't write/delete anything for images.
- `cleanupRemovedCars()` logs which cars/files *would* be deleted instead of deleting them.
- No DB writes at all.

This lets the first production run be verified before trusting it with real deletes.

### 5. Backfill command for already-imported data

New command, e.g. `app:reprocess-car-images`, since existing production images/cars predate this change and won't be touched by it otherwise (a car whose feed hash is unchanged only gets `downloadImages()` re-run today if `$car->images` is empty — see `import()` lines 43-54):
- Walks every file under `www/images/cars/`, runs it through the same resize/recompress step in place, replacing oversized originals.
- Runs `cleanupRemovedCars()` once against current production state (supports `--dry-run` too).

## Edge Cases

- **Corrupt/unreadable downloaded image**: caught, original bytes written as fallback, warning logged — a photo should never be silently lost due to a resize failure.
- **Partial import failure** (network error mid-pagination): `$seenExternalIds` only has a subset of the real feed; cleanup is skipped entirely for that run since it never reaches the post-loop cleanup call. No cars are deleted based on incomplete data.
- **Feed returns zero cars across all pages**: `$seenExternalIds` stays empty, cleanup step doesn't run at all — no risk of wiping the whole table.
- **Car reappears after being deleted**: handled identically to a brand-new car — `upsert()` inserts fresh row + downloads images.

## Out of Scope (this round)

- **WebP output** — explicitly deferred as a fast-follow once JPEG re-encoding is proven in production. Revisit `IMAGE_QUALITY`/format constants at that point.
- Multiple responsive image sizes / srcset generation (only one resized size is produced).
- Soft-delete / grace period for removed cars.
- CDN/image-proxy based serving.
