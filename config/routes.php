<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;
Router::extensions(['json']);

$routes = Router::createRouteBuilder('/');
$routes->plugin(
    'Uskur/Attachments',
    ['path' => '/attachments'],
    function (RouteBuilder $routes) {
        $routes->fallbacks(DashedRoute::class);
    }
);

$routes->plugin('Uskur/Attachments', function (RouteBuilder $routes) {
    // Routes connected here are prefixed with '/debugger' and
    // have the plugin route element set to 'DebugKit'.
    $routes->connect('/file/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'file']);
    $routes->connect('/image/*', ['plugin' => 'Uskur/Attachments', 'controller' => 'Attachments', 'action' => 'image']);
    
    $routes->fallbacks(DashedRoute::class);
});
