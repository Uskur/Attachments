<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;
Router::extensions(['json']);

Router::plugin(
    'Uskur/Attachments',
    ['path' => '/attachments'],
    function (RouteBuilder $routes) {
        $routes->fallbacks(DashedRoute::class);
    }
);

Router::connect('/file/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'file']);
Router::connect('/image/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'image']);