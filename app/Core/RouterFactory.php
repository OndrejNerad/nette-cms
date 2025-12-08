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

        $router->addRoute('en/<url .+>', [
            'presenter' => 'StaticPage',
            'action'     => 'default',
            'lang'       => 'en',
        ]);

        $router->addRoute('en', [
            'presenter' => 'StaticPage',
            'action'     => 'default',
            'lang'       => 'en',
            'url'       => null,
        ]);

        $router->addRoute('<url .*>', [
            'presenter' => 'StaticPage',
            'action'     => 'default',
            'lang'       => 'cs',
        ]);

        $router->addRoute('', [
            'presenter' => 'StaticPage',
            'action'     => 'default',
            'lang'       => 'en',
            'url'       => null,
        ]);

        return $router;
    }
}