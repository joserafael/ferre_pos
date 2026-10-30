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

    // Endpoints accesibles para CAJERO y ADMIN (solo consulta para vender y registro de venta/cliente)
    $routes->group('', ['filter' => 'jwt'], static function (RouteCollection $routes) {
        $routes->get('auth/me', 'AuthController::me');

        // Catálogos de solo lectura requeridos por el POS
        $routes->get('categorias', 'CategoriaController::index');
        $routes->get('unidades-medida', 'CatalogoController::unidades');
        $routes->get('metodos-pago', 'CatalogoController::metodosPago');
        $routes->get('productos', 'ProductoController::index');
        $routes->get('productos/(:num)', 'ProductoController::show/$1');

        // Clientes (búsqueda por Cédula/RIF y registro durante la venta)
        $routes->get('clientes', 'ClienteController::index');
        $routes->get('clientes/buscar', 'ClienteController::buscar');
        $routes->post('clientes', 'ClienteController::create');
        $routes->put('clientes/(:num)', 'ClienteController::update/$1');

        // Ventas (el cajero solo puede ver sus ventas y crear nuevas ventas)
        $routes->get('ventas', 'VentaController::index');
        $routes->get('ventas/(:num)', 'VentaController::show/$1');
        $routes->post('ventas', 'VentaController::create');
    });

    // Endpoints exclusivos de ADMINISTRADOR (prohibidos para CAJERO)
    $routes->group('', ['filter' => 'jwt:ADMIN'], static function (RouteCollection $routes) {
        $routes->get('dashboard', 'DashboardController::index');

        // Tasas de cambio (USD/VES)
        $routes->get('tasas', 'TasaCambioController::index');
        $routes->post('tasas', 'TasaCambioController::create');

        // Gestión de Categorías y Unidades
        $routes->post('categorias', 'CategoriaController::create');
        $routes->put('categorias/(:num)', 'CategoriaController::update/$1');
        $routes->delete('categorias/(:num)', 'CategoriaController::delete/$1');
        $routes->post('unidades-medida', 'CatalogoController::crearUnidad');

        // Gestión de Productos (Crear, Editar, Eliminar)
        $routes->post('productos', 'ProductoController::create');
        $routes->put('productos/(:num)', 'ProductoController::update/$1');
        $routes->delete('productos/(:num)', 'ProductoController::delete/$1');

        // Inventario (Compras bimonetarias, Ajustes y Kardex Inmutable)
        $routes->get('inventario/entradas', 'InventarioController::entradas');
        $routes->get('inventario/entradas/(:num)', 'InventarioController::showEntrada/$1');
        $routes->post('inventario/entradas', 'InventarioController::crearEntrada');
        $routes->post('inventario/ajustes', 'InventarioController::crearAjuste');
        $routes->get('inventario/kardex', 'InventarioController::kardex');

        // Anulación de facturas
        $routes->post('ventas/(:num)/anular', 'VentaController::anular/$1');

        // Gestión de Cajeros e Histórico de Ventas por Cajero
        $routes->get('cajeros', 'CajeroController::index');
        $routes->post('cajeros', 'CajeroController::create');
        $routes->put('cajeros/(:num)', 'CajeroController::update/$1');
        $routes->get('cajeros/(:num)/ventas', 'CajeroController::ventas/$1');
    });
});
