<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = (new App\Bootstrap())->bootCliApplication();
$container->getByType(App\Command\ImportCarsCommand::class)->run();
//$container->getByType(App\Command\ImportGoogleReviewsCommand::class)->run();