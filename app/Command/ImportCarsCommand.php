<?php declare(strict_types=1);

namespace App\Command;

use App\Service\CarImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting car import...</info>');
        $this->carImportService->import($output);
        return Command::SUCCESS;
    }

    // PUVODNI METODA
//    public function run(): void
//    {
//        $this->carImportService->import();
//    }
}