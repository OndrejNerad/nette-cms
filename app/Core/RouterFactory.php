<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        $router->addRoute('<lang en|cs>/<url .+>', [
            'presenter' => 'StaticPage',
            'action'    => 'default',
        ]);

        $router->addRoute('<lang en|cs>', [
            'presenter' => 'StaticPage',
            'action'    => 'default',
            'url'       => 'default',
        ]);

        // redirect bare / to /cs
        $router->addRoute('', 'StaticPage:default', RouteList::ONE_WAY);

        return $router;
    }
}