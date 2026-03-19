<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $routes): void {
    $routes->plugin(
        'Uskur/Attachments',
        ['path' => '/attachments'],
        function (RouteBuilder $routes): void {
            $routes->setExtensions(['json']);
            $routes->connect('/file/*', ['controller' => 'Attachments', 'action' => 'file']);
            $routes->connect('/image/*', ['controller' => 'Attachments', 'action' => 'image']);
            $routes->fallbacks(DashedRoute::class);
        }
    );
};
