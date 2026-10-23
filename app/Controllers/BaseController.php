<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    protected function respuestaExitosa(mixed $datos, int $estado = 200, ?array $meta = null): ResponseInterface
    {
        $respuesta = ['exito' => true, 'datos' => $datos];

        if ($meta !== null) {
            $respuesta['meta'] = $meta;
        }

        return $this->response->setStatusCode($estado)->setJSON($respuesta);
    }

    protected function respuestaError(string $mensaje, int $estado, array $errores = []): ResponseInterface
    {
        $respuesta = ['exito' => false, 'mensaje' => $mensaje];

        if ($errores !== []) {
            $respuesta['errores'] = $errores;
        }

        return $this->response->setStatusCode($estado)->setJSON($respuesta);
    }

    protected function cuerpoJson(): ?array
    {
        $cuerpo = $this->request->getBody();
        if (! is_string($cuerpo)) {
            return null;
        }

        $json = json_decode($cuerpo, true);

        return is_array($json) && $json !== [] && ! array_is_list($json) ? $json : null;
    }

    protected function usuarioApi(): ?array
    {
        $usuario = json_decode($this->request->getHeaderLine('X-Authenticated-User'), true);

        return is_array($usuario) ? $usuario : null;
    }

    protected function esAdministrador(?array $usuario): bool
    {
        return ($usuario['rol_nombre'] ?? null) === 'ADMINISTRACION';
    }

    protected function parametrosPaginacion(): ?array
    {
        $pagina = $this->request->getGet('page') ?? '1';
        $porPagina = $this->request->getGet('per_page') ?? '20';

        if (! is_scalar($pagina) || ! is_scalar($porPagina)) {
            return null;
        }

        $pagina = filter_var($pagina, FILTER_VALIDATE_INT);
        $porPagina = filter_var($porPagina, FILTER_VALIDATE_INT);

        if (
            $pagina === false
            || $pagina < 1
            || $porPagina === false
            || $porPagina < 1
            || $porPagina > 100
            || $pagina > intdiv(PHP_INT_MAX, $porPagina)
        ) {
            return null;
        }

        return ['pagina' => $pagina, 'por_pagina' => $porPagina];
    }

    protected function erroresValidacion(array $datos, array $reglas): array
    {
        $validacion = service('validation');
        $validacion->setRules($reglas);

        return $validacion->run($datos) ? [] : $validacion->getErrors();
    }
}
