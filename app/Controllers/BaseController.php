<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseController extends Controller
{
    /** Envuelve los datos y metadatos en el formato JSON de éxito usado por la API. */
    protected function respuestaExitosa(mixed $datos, int $estado = 200, ?array $meta = null): ResponseInterface
    {
        $respuesta = ['exito' => true, 'datos' => $datos];

        if ($meta !== null) {
            $respuesta['meta'] = $meta;
        }

        return $this->response->setStatusCode($estado)->setJSON($respuesta);
    }

    /** Construye una respuesta JSON de error y agrega detalles cuando están disponibles. */
    protected function respuestaError(string $mensaje, int $estado, array $errores = []): ResponseInterface
    {
        $respuesta = ['exito' => false, 'mensaje' => $mensaje];

        if ($errores !== []) {
            $respuesta['errores'] = $errores;
        }

        return $this->response->setStatusCode($estado)->setJSON($respuesta);
    }

    /** Lee y decodifica el cuerpo HTTP; devuelve un objeto JSON asociativo o null si no es válido. */
    protected function cuerpoJson(): ?array
    {
        $cuerpo = $this->request->getBody();
        if (! is_string($cuerpo)) {
            return null;
        }

        $json = json_decode($cuerpo, true);

        return is_array($json) && $json !== [] && ! array_is_list($json) ? $json : null;
    }

    /** Lee del encabezado de autenticación el usuario que el filtro adjuntó a la petición. */
    protected function usuarioApi(): ?array
    {
        $usuario = json_decode($this->request->getHeaderLine('X-Authenticated-User'), true);

        return is_array($usuario) ? $usuario : null;
    }

    /** Interpreta el rol del usuario autenticado para aplicar permisos administrativos. */
    protected function esAdministrador(?array $usuario): bool
    {
        return ($usuario['rol_nombre'] ?? null) === 'ADMINISTRACION';
    }

    /** Lee y valida page/per_page de la URL; devuelve valores enteros o null si son inválidos. */
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

    /** Ejecuta las reglas sobre los datos recibidos y devuelve errores o un arreglo vacío. */
    protected function erroresValidacion(array $datos, array $reglas): array
    {
        $validacion = service('validation');
        $validacion->setRules($reglas);

        return $validacion->run($datos) ? [] : $validacion->getErrors();
    }
}
