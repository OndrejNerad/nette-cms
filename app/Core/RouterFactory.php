<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;

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
            'url'       => null,
        ]);

        $router->addRoute('', [
            'presenter' => 'StaticPage',
            'action'    => 'default',
            'lang'      => 'cs',
            'url'       => null,
        ]);

        return $router;
    }
}