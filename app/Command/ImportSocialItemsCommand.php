<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\SocialImportService;

class ImportSocialItemsCommand
{
    public function __construct(
        private readonly SocialImportService $socialImportService,
    ) {}

    public function run(): void
    {
        $this->socialImportService->importInstagram();
    }
}