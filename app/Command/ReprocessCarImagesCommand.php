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
        $this->setDescription('Backfill: re-compress existing car images on disk and normalize them to JPEG');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing or deleting any files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Reprocessing existing car images...</info>');
        $this->carImportService->reprocessExistingImages($output, (bool) $input->getOption('dry-run'));
        return Command::SUCCESS;
    }
}
