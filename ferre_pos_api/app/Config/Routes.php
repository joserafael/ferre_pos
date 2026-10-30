<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Preflight CORS global para cualquier endpoint bajo /api
$routes->options('api/(:any)', static function () {
    return service('response')
        ->setStatusCode(200)
        ->setHeader('Access-Control-Allow-Origin', '*')
        ->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization')
        ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, PATCH, DELETE');
});

$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
    // Healthcheck & Auth pública
    $routes->post('auth/login', 'AuthController::login');
    $routes->get('tasas/activa', 'TasaCambioController::activa');

    // Endpoints protegidos mediante JWT
    $routes->group('', ['filter' => 'jwt'], static function (RouteCollection $routes) {
        $routes->get('auth/me', 'AuthController::me');
        $routes->get('dashboard', 'DashboardController::index');

        // Tasas de cambio (USD/VES)
        $routes->get('tasas', 'TasaCambioController::index');
        $routes->post('tasas', 'TasaCambioController::create');

        // Categorías
        $routes->get('categorias', 'CategoriaController::index');
        $routes->post('categorias', 'CategoriaController::create');
        $routes->put('categorias/(:num)', 'CategoriaController::update/$1');
        $routes->delete('categorias/(:num)', 'CategoriaController::delete/$1');

        // Catálogos: Unidades de Medida y Métodos de Pago
        $routes->get('unidades-medida', 'CatalogoController::unidades');
        $routes->post('unidades-medida', 'CatalogoController::crearUnidad');
        $routes->get('metodos-pago', 'CatalogoController::metodosPago');

        // Productos (Inventario fraccionado y factor de conversión)
        $routes->get('productos', 'ProductoController::index');
        $routes->get('productos/(:num)', 'ProductoController::show/$1');
        $routes->post('productos', 'ProductoController::create');
        $routes->put('productos/(:num)', 'ProductoController::update/$1');
        $routes->delete('productos/(:num)', 'ProductoController::delete/$1');

        // Inventario (Compras bimonetarias, Ajustes y Kardex Inmutable)
        $routes->get('inventario/entradas', 'InventarioController::entradas');
        $routes->get('inventario/entradas/(:num)', 'InventarioController::showEntrada/$1');
        $routes->post('inventario/entradas', 'InventarioController::crearEntrada');
        $routes->post('inventario/ajustes', 'InventarioController::crearAjuste');
        $routes->get('inventario/kardex', 'InventarioController::kardex');

        // Ventas POS Bimonetarias y Multipago
        $routes->get('ventas', 'VentaController::index');
        $routes->get('ventas/(:num)', 'VentaController::show/$1');
        $routes->post('ventas', 'VentaController::create');
        $routes->post('ventas/(:num)/anular', 'VentaController::anular/$1');
    });
});
