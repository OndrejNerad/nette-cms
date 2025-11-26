<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
	use Nette\StaticClass;

    public static function createRouter(): RouteList
    {
        $router = new RouteList;
        $router->addRoute('/', 'StaticPage:default');
        $router->addRoute('<url>', 'StaticPage:default');

        bdump($router);

        return $router;
    }
}
