<?php

namespace App\Controllers;

use App\Models\ProyectosModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProyectosController extends BaseController
{
    private ProyectosModel $modelo;

    public function __construct()
    {
        $this->modelo = model(ProyectosModel::class);
    }

    public function mostrar(): ResponseInterface
    {
        $paginacion = $this->parametrosPaginacion();
        if ($paginacion === null) {
            return $this->respuestaError('page y per_page deben ser enteros positivos; per_page no puede superar 100.', 400);
        }

        $busqueda = $this->request->getGet('q') ?? '';
        $estado = $this->request->getGet('estado');
        if (! is_string($busqueda) || strlen($busqueda) > 100 || ($estado !== null && (! is_string($estado) || strlen($estado) > 30))) {
            return $this->respuestaError('Los filtros de búsqueda no son válidos.', 400);
        }

        $usuario = $this->usuarioApi();
        $resultado = $this->modelo->pagina(
            $paginacion['pagina'],
            $paginacion['por_pagina'],
            trim($busqueda),
            $estado,
            $this->esAdministrador($usuario) ? null : (int) ($usuario['id_usuario'] ?? 0),
        );

        return $this->respuestaExitosa($resultado['datos'], 200, [
            'pagina'     => $paginacion['pagina'],
            'por_pagina' => $paginacion['por_pagina'],
            'total'      => $resultado['total'],
        ]);
    }

    public function buscar(int $id): ResponseInterface
    {
        $datos = $this->modelo->buscar($id);

        if ($datos === null || ! $this->puedeGestionar($datos)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        return $this->respuestaExitosa($datos);
    }

    public function crear(): ResponseInterface
    {
        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $usuario = $this->usuarioApi();
        $permitidos = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado', 'id_usuario'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        if (! $this->esAdministrador($usuario)) {
            $datos['id_usuario'] = (int) ($usuario['id_usuario'] ?? 0);
        } elseif (! isset($datos['id_usuario'])) {
            $datos['id_usuario'] = (int) ($usuario['id_usuario'] ?? 0);
        }

        $errores = $this->validarProyecto($datos, true);
        if ($errores !== []) {
            return $this->respuestaError('Los datos del proyecto no son válidos.', 422, $errores);
        }

        if (model(UsuarioModel::class)->find((int) $datos['id_usuario']) === null) {
            return $this->respuestaError('El usuario indicado no existe.', 422);
        }

        $id = $this->modelo->crear($datos);

        if ($id === false) {
            return $this->respuestaError('No fue posible crear el proyecto.', 500);
        }

        return $this->respuestaExitosa(['id_proyecto' => $id], 201);
    }

    public function actualizar(int $id): ResponseInterface
    {
        $actual = $this->modelo->buscar($id);
        if ($actual === null || ! $this->puedeGestionar($actual)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $permitidos = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado', 'id_usuario'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        $usuario = $this->usuarioApi();
        if (! $this->esAdministrador($usuario)) {
            unset($datos['id_usuario']);
        } elseif (isset($datos['id_usuario']) && model(UsuarioModel::class)->find((int) $datos['id_usuario']) === null) {
            return $this->respuestaError('El usuario indicado no existe.', 422);
        }

        $errores = $this->validarProyecto($datos, false, $actual);
        if ($errores !== []) {
            return $this->respuestaError('Los datos del proyecto no son válidos.', 422, $errores);
        }

        $actualizado = $this->esAdministrador($usuario)
            ? $this->modelo->actualizar($id, $datos)
            : $this->modelo->actualizarDeUsuario($id, (int) ($usuario['id_usuario'] ?? 0), $datos);
        if (! $actualizado) {
            return $this->respuestaError('No se proporcionaron campos válidos para actualizar.', 422);
        }

        return $this->respuestaExitosa($this->modelo->buscar($id));
    }

    public function eliminar(int $id): ResponseInterface
    {
        $proyecto = $this->modelo->buscar($id);
        if ($proyecto === null || ! $this->puedeGestionar($proyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        $usuario = $this->usuarioApi();
        $eliminado = $this->esAdministrador($usuario)
            ? $this->modelo->eliminar($id)
            : $this->modelo->eliminarDeUsuario($id, (int) ($usuario['id_usuario'] ?? 0));
        if (! $eliminado) {
            return $this->respuestaError('No fue posible eliminar el proyecto.', 500);
        }

        return $this->response->setStatusCode(204);
    }

    private function puedeGestionar(array $proyecto): bool
    {
        $usuario = $this->usuarioApi();

        return $this->esAdministrador($usuario)
            || (int) ($proyecto['id_usuario'] ?? 0) === (int) ($usuario['id_usuario'] ?? -1);
    }

    private function validarProyecto(array $datos, bool $crear, array $actual = []): array
    {
        $reglas = [];
        if ($crear || array_key_exists('nombre', $datos)) {
            $reglas['nombre'] = 'required|min_length[1]|max_length[150]';
        }
        foreach (['descripcion' => 'permit_empty|max_length[5000]', 'fecha_inicio' => 'permit_empty|valid_date[Y-m-d]', 'fecha_fin' => 'permit_empty|valid_date[Y-m-d]', 'estado' => 'permit_empty|max_length[30]'] as $campo => $regla) {
            if ($crear || array_key_exists($campo, $datos)) {
                $reglas[$campo] = $regla;
            }
        }
        if (array_key_exists('id_usuario', $datos)) {
            $reglas['id_usuario'] = 'required|integer|greater_than[0]';
        }

        $errores = $this->erroresValidacion($datos, $reglas);
        $inicio = $datos['fecha_inicio'] ?? $actual['fecha_inicio'] ?? null;
        $fin = $datos['fecha_fin'] ?? $actual['fecha_fin'] ?? null;
        if (is_string($inicio) && $inicio !== '' && is_string($fin) && $fin !== '' && strtotime($fin) < strtotime($inicio)) {
            $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        return $errores;
    }
}
