<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CarImportService;

class ImportCarsCommand
{
    public function __construct(
        private readonly CarImportService $carImportService,
    ) {
    }

    public function run(): void
    {
        $this->carImportService->import();
    }
}