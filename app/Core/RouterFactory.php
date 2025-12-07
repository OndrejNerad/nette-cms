<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        // Optional: exact home page (so / is also handled nicely)
        $router->addRoute('', [
            'presenter' => 'StaticPage',
            'action'     => 'default',
            'url'        => 'home', // or whatever your home latte is called
        ]);

        // This is the magic line – catches everything except real files/folders
        $router->addRoute('<url .+>', [  // the ".+" means "at least one character"
            'presenter' => 'StaticPage',
            'action'     => 'default',
        ]);

        return $router;
    }
}
