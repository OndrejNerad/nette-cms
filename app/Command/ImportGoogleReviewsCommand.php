<?php declare(strict_types=1);

namespace App\Command;

use App\Service\GoogleReviewsImportService;

class ImportGoogleReviewsCommand
{
    public function __construct(
        private readonly GoogleReviewsImportService $googleReviewsImportService,
    ) {}

    public function run(): void
    {
        $this->googleReviewsImportService->import();
    }
}