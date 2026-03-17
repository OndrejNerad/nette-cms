<?php declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        // Car routes — must be before the catch-all
        $router->addRoute('cs/nabidka-vozidel/<detailUrl>', ['presenter' => 'Car', 'action' => 'detail', 'lang' => 'cs']);
        $router->addRoute('cs/nabidka-vozidel', ['presenter' => 'Car', 'action' => 'list', 'lang' => 'cs']);
        $router->addRoute('en/car-listings/<detailUrl>', ['presenter' => 'Car', 'action' => 'detail', 'lang' => 'en']);
        $router->addRoute('en/car-listings', ['presenter' => 'Car', 'action' => 'list', 'lang' => 'en']);

        // Catch-all static pages
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