<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\GoogleReview\GoogleReview;
use App\Model\Orm;
use DateTimeImmutable;

class GoogleReviewsImportService
{
    private const PLACE_ID  = 'ChIJDfaFk8qVC0cRMXo1rHDtm8Y';
    private const API_KEY   = 'AIzaSyD2l6Kgr1BNE1CFXuOdVYLZkEyQSwcQJMA';
    private const API_URL   = 'https://maps.googleapis.com/maps/api/place/details/json';
    private const IMAGE_DIR = __DIR__ . '/../../www/images/reviews/';
    private const IMAGE_URL = '/images/reviews/';

    public function __construct(
        private readonly Orm $orm,
    ) {
        if (!is_dir(self::IMAGE_DIR)) {
            mkdir(self::IMAGE_DIR, 0755, true);
        }
    }

    public function import(): void
    {
        $reviews  = $this->fetchReviews();
        $imported = 0;

        foreach ($reviews as $data) {
            // Places API nemá reviewId – jako unikátní klíč použijeme MD5(author_url + time)
            $externalId = md5($data['author_url'] . $data['time']);

            if ($this->orm->googleReviews->getByExternalId($externalId) !== null) {
                continue;
            }

            $review              = new GoogleReview();
            $review->externalId  = $externalId;
            $review->authorName  = $data['author_name'];
            $review->authorPhoto = isset($data['profile_photo_url'])
                ? $this->downloadImage($data['profile_photo_url'], $externalId)
                : null;
            $review->rating      = (int) $data['rating'];
            $review->text        = $data['text'] ?? null;
            $review->url         = $data['author_url'];
            $review->publishedAt = new DateTimeImmutable('@' . $data['time']);

            $this->orm->persistAndFlush($review);
            $imported++;
        }

        echo "Import done. $imported new review(s) saved.\n";
    }
    private function downloadImage(string $url, string $externalId): ?string
    {
        $ext      = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $externalId . '.' . $ext;
        $dest     = self::IMAGE_DIR . $filename;

        if (file_exists($dest)) {
            return self::IMAGE_URL . $filename;
        }

        $data = @file_get_contents($url);

        if ($data === false) {
            echo "Warning: failed to download author photo from $url\n";
            return null;
        }

        file_put_contents($dest, $data);

        return self::IMAGE_URL . $filename;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReviews(): array
    {
        $url = self::API_URL . '?' . http_build_query([
                'place_id'     => self::PLACE_ID,
                'fields'       => 'reviews',
                'language'     => 'cs',
                'reviews_sort' => 'newest',
                'key'          => self::API_KEY,
            ]);

        $response = file_get_contents($url);

        if ($response === false) {
            throw new \RuntimeException('Google Places API request failed.');
        }

        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (($json['status'] ?? '') !== 'OK') {
            throw new \RuntimeException(
                'Google Places API error: ' . ($json['status'] ?? 'unknown') .
                ' – ' . ($json['error_message'] ?? 'no details')
            );
        }

        return $json['result']['reviews'] ?? [];
    }
}