<?php

use App\Controllers\AuthController;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', ['filter' => 'cors'], static function (RouteCollection $routes): void {
    $routes->options('(:any)', static function (): void {});
    $routes->post('autenticacion/login', [AuthController::class, 'login']);

    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->post('autenticacion/logout', [AuthController::class, 'logout']);
        $routes->get('categorias/activas', 'CategoriaController::activas');

        $routes->get('proyectos', 'ProyectosController::mostrar');
        $routes->get('proyectos/(:num)', 'ProyectosController::buscar/$1');
        $routes->post('proyectos', 'ProyectosController::crear');
        $routes->match(['put', 'patch'], 'proyectos/(:num)', 'ProyectosController::actualizar/$1');
        $routes->delete('proyectos/(:num)', 'ProyectosController::eliminar/$1');

        $routes->get('proyectos/(:num)/tareas', 'TareaController::mostrar/$1');
        $routes->get('proyectos/(:num)/tareas/(:num)', 'TareaController::buscar/$1/$2');
        $routes->post('proyectos/(:num)/tareas', 'TareaController::crear/$1');
        $routes->match(['put', 'patch'], 'proyectos/(:num)/tareas/(:num)', 'TareaController::actualizar/$1/$2');
        $routes->delete('proyectos/(:num)/tareas/(:num)', 'TareaController::eliminar/$1/$2');
    });

    $routes->group('', ['filter' => 'auth:admin'], static function (RouteCollection $routes): void {
        $routes->get('categorias', 'CategoriaController::mostrar');
        $routes->get('categoria/(:num)', 'CategoriaController::buscar/$1');
        $routes->post('categoriaCrear', 'CategoriaController::crear');
        $routes->put('categoriaActualizar/(:num)', 'CategoriaController::actualizar/$1');
        $routes->delete('categoriaEliminar/(:num)', 'CategoriaController::eliminar/$1');

        $routes->get('roles', 'RolController::mostrar');
        $routes->get('rolBuscar/(:num)', 'RolController::buscar/$1');
        $routes->post('rolCrear', 'RolController::crear');
        $routes->put('rolActualizar/(:num)', 'RolController::actualizar/$1');
        $routes->delete('rolEliminar/(:num)', 'RolController::eliminar/$1');
    });
});
