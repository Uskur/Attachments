<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

$routes = Router::createRouteBuilder("/");
$routes->plugin(
    'Uskur/Attachments',
    ['path' => '/attachments'],
    function (RouteBuilder $routes) {
        $routes->fallbacks(DashedRoute::class);
    }
);

$routes->connect('/file/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'file']);
$routes->connect('/image/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'image']);
