<?php

namespace App\Controllers;

use App\Models\ProyectosModel;
use App\Models\TareaModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProyectosController extends BaseController
{
    private ProyectosModel $modelo;

    /** Carga el modelo que concentra las consultas y escrituras de proyectos. */
    public function __construct()
    {
        $this->modelo = model(ProyectosModel::class);
    }

    /** Valida filtros y paginación, limita resultados según el usuario y devuelve datos con metadatos. */
    public function mostrar(): ResponseInterface
    {
        // 1. Validar la paginación y los filtros enviados en la URL.
        $paginacion = $this->parametrosPaginacion();
        if ($paginacion === null) {
            return $this->respuestaError('page y per_page deben ser enteros positivos; per_page no puede superar 100.', 400);
        }

        $busqueda = $this->request->getGet('q') ?? '';
        $estado = $this->request->getGet('estado');
        if (
            ! is_string($busqueda)
            || strlen($busqueda) > 100
            || ($estado !== null && $estado !== '' && (! is_string($estado) || ! in_array($estado, ['PENDIENTE', 'EN_PROCESO', 'FINALIZADO'], true)))
        ) {
            return $this->respuestaError('Los filtros de búsqueda no son válidos.', 400);
        }

        // 2. El modelo limita al usuario normal a sus propios proyectos; administración ve todos.
        $usuario = $this->usuarioApi();
        $resultado = $this->modelo->pagina(
            $paginacion['pagina'],
            $paginacion['por_pagina'],
            trim($busqueda),
            $estado,
            $this->esAdministrador($usuario) ? null : (int) ($usuario['id_usuario'] ?? 0),
        );

        // 3. Devolver los resultados junto con los metadatos de la página.
        return $this->respuestaExitosa($resultado['datos'], 200, [
            'pagina'     => $paginacion['pagina'],
            'por_pagina' => $paginacion['por_pagina'],
            'total'      => $resultado['total'],
        ]);
    }

    /** Busca el proyecto y comprueba acceso por rol o propiedad antes de devolver sus datos. */
    public function buscar(int $id): ResponseInterface
    {
        // 1. Buscar por ID y ocultar tanto lo inexistente como lo que el usuario no puede gestionar.
        $datos = $this->modelo->buscar($id);

        if ($datos === null || ! $this->puedeGestionar($datos)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        return $this->respuestaExitosa($datos);
    }

    /** Valida el JSON y permisos, comprueba datos relacionados y pide al modelo crear el proyecto. */
    public function crear(): ResponseInterface
    {
        // 1. Leer un objeto JSON y rechazar campos que no forman parte del proyecto.
        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        // 2. Fijar el propietario para usuarios normales; un administrador puede asignarlo.
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

        // 3. Validar los campos y comprobar que el propietario exista antes de insertar.
        $errores = $this->validarProyecto($datos, true);
        if ($errores !== []) {
            return $this->respuestaError('Los datos del proyecto no son válidos.', 422, $errores);
        }

        if (model(UsuarioModel::class)->find((int) $datos['id_usuario']) === null) {
            return $this->respuestaError('El usuario indicado no existe.', 422);
        }

        if (array_key_exists('fecha_fin', $datos) && $datos['fecha_fin'] === '') {
            $datos['fecha_fin'] = null;
        }

        // 4. Insertar y responder con el ID generado.
        $id = $this->modelo->crear($datos);

        if ($id === false) {
            return $this->respuestaError('No fue posible crear el proyecto.', 500);
        }

        return $this->respuestaExitosa(['id_proyecto' => $id], 201);
    }

    /** Verifica acceso, valida cambios y los actualiza con permisos de administrador o propietario. */
    public function actualizar(int $id): ResponseInterface
    {
        // 1. Comprobar existencia y autorización antes de aceptar cambios.
        $actual = $this->modelo->buscar($id);
        if ($actual === null || ! $this->puedeGestionar($actual)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        // 2. Leer el JSON y permitir solo los campos modificables.
        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $permitidos = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado', 'id_usuario'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        // 3. Evitar que un usuario normal reasigne la propiedad del proyecto.
        $usuario = $this->usuarioApi();
        if (! $this->esAdministrador($usuario)) {
            unset($datos['id_usuario']);
        } elseif (isset($datos['id_usuario']) && model(UsuarioModel::class)->find((int) $datos['id_usuario']) === null) {
            return $this->respuestaError('El usuario indicado no existe.', 422);
        }

        // 4. Validar contra los datos actuales y actualizar con una condición de propietario.
        $errores = $this->validarProyecto($datos, false, $actual);
        if ($errores !== []) {
            return $this->respuestaError('Los datos del proyecto no son válidos.', 422, $errores);
        }

        if (array_key_exists('fecha_fin', $datos) && $datos['fecha_fin'] === '') {
            $datos['fecha_fin'] = null;
        }

        $actualizado = $this->esAdministrador($usuario)
            ? $this->modelo->actualizar($id, $datos)
            : $this->modelo->actualizarDeUsuario($id, (int) ($usuario['id_usuario'] ?? 0), $datos);
        if (! $actualizado) {
            return $this->respuestaError('No se proporcionaron campos válidos para actualizar.', 422);
        }

        return $this->respuestaExitosa($this->modelo->buscar($id));
    }

    /** Verifica acceso y elimina el proyecto mediante la operación adecuada para el rol del usuario. */
    public function eliminar(int $id): ResponseInterface
    {
        // 1. Solo permitir eliminar si el proyecto existe y el usuario tiene acceso.
        $proyecto = $this->modelo->buscar($id);
        if ($proyecto === null || ! $this->puedeGestionar($proyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$id}.", 404);
        }

        if (model(TareaModel::class)->tieneEnProyecto($id)) {
            return $this->respuestaError('El proyecto todavía contiene tareas; elimínalas antes de eliminar el proyecto.', 409);
        }

        // 2. Aplicar el borrado administrativo o el borrado limitado al propietario.
        $usuario = $this->usuarioApi();
        $eliminado = $this->esAdministrador($usuario)
            ? $this->modelo->eliminar($id)
            : $this->modelo->eliminarDeUsuario($id, (int) ($usuario['id_usuario'] ?? 0));
        if (! $eliminado) {
            return $this->respuestaError('No fue posible eliminar el proyecto.', 500);
        }

        return $this->response->setStatusCode(204);
    }

    /** Decide si el usuario autenticado puede gestionar el proyecto por ser admin o su propietario. */
    private function puedeGestionar(array $proyecto): bool
    {
        $usuario = $this->usuarioApi();

        return $this->esAdministrador($usuario)
            || (int) ($proyecto['id_usuario'] ?? 0) === (int) ($usuario['id_usuario'] ?? -1);
    }

    /** Construye reglas según creación o actualización y valida también el orden de fechas. */
    private function validarProyecto(array $datos, bool $crear, array $actual = []): array
    {
        $reglas = [];
        if ($crear || array_key_exists('nombre', $datos)) {
            $reglas['nombre'] = 'required|min_length[1]|max_length[120]';
        }
        if (array_key_exists('descripcion', $datos)) {
            $reglas['descripcion'] = 'permit_empty|max_length[5000]';
        }
        if ($crear || array_key_exists('fecha_inicio', $datos)) {
            $reglas['fecha_inicio'] = 'required|valid_date[Y-m-d]';
        }
        if (array_key_exists('fecha_fin', $datos)) {
            $reglas['fecha_fin'] = 'permit_empty|valid_date[Y-m-d]';
        }
        if (array_key_exists('estado', $datos)) {
            $reglas['estado'] = 'in_list[PENDIENTE,EN_PROCESO,FINALIZADO]';
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
