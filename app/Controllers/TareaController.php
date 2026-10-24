<?php

namespace App\Controllers;

use App\Models\CategoriaModel;
use App\Models\ProyectosModel;
use App\Models\TareaModel;
use CodeIgniter\HTTP\ResponseInterface;

class TareaController extends BaseController
{
    private TareaModel $modelo;
    private ProyectosModel $proyectos;

    /** Carga los modelos de tareas y proyectos necesarios para validar acceso y operar tareas. */
    public function __construct()
    {
        $this->modelo = model(TareaModel::class);
        $this->proyectos = model(ProyectosModel::class);
    }

    /** Comprueba acceso al proyecto, valida filtros y devuelve la página de tareas con metadatos. */
    public function mostrar(int $idProyecto): ResponseInterface
    {
        // 1. Verificar el acceso al proyecto, luego validar los filtros y la paginación.
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $paginacion = $this->parametrosPaginacion();
        if ($paginacion === null) {
            return $this->respuestaError('page y per_page deben ser enteros positivos; per_page no puede superar 100.', 400);
        }

        $busqueda = $this->request->getGet('q') ?? '';
        $estado = $this->request->getGet('estado');
        if (
            ! is_string($busqueda)
            || strlen($busqueda) > 100
            || ($estado !== null && $estado !== '' && (! is_string($estado) || ! in_array($estado, ['PENDIENTE', 'EN_PROCESO', 'COMPLETA'], true)))
        ) {
            return $this->respuestaError('Los filtros de búsqueda no son válidos.', 400);
        }

        // 2. Consultar solo tareas de este proyecto y devolver los metadatos de página.
        $resultado = $this->modelo->paginaProyecto(
            $idProyecto,
            $paginacion['pagina'],
            $paginacion['por_pagina'],
            trim($busqueda),
            $estado,
        );

        return $this->respuestaExitosa($resultado['datos'], 200, [
            'pagina'     => $paginacion['pagina'],
            'por_pagina' => $paginacion['por_pagina'],
            'total'      => $resultado['total'],
        ]);
    }

    /** Valida el acceso al proyecto, busca allí la tarea solicitada y devuelve sus datos o 404. */
    public function buscar(int $idProyecto, int $idTarea): ResponseInterface
    {
        // 1. Validar acceso al proyecto y buscar la tarea dentro de ese mismo proyecto.
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $tarea = $this->modelo->buscarEnProyecto($idProyecto, $idTarea);
        if ($tarea === null) {
            return $this->respuestaError("Tarea no encontrada con el ID {$idTarea}.", 404);
        }

        return $this->respuestaExitosa($tarea);
    }

    /** Valida acceso y JSON, fuerza el proyecto de la ruta y verifica los datos antes de insertar. */
    public function crear(int $idProyecto): ResponseInterface
    {
        // 1. Confirmar acceso al proyecto y leer el objeto JSON de la tarea.
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $permitidos = ['titulo', 'descripcion', 'id_categoria', 'prioridad', 'estado', 'fecha_limite'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        // 2. Tomar el proyecto de la ruta, nunca confiar en un ID del cuerpo enviado por el cliente.
        $datos['id_proyecto'] = $idProyecto;
        // 3. Validar los datos y exigir una categoría activa antes de insertar.
        $errores = $this->validarTarea($datos, true);
        if ($errores !== []) {
            return $this->respuestaError('Los datos de la tarea no son válidos.', 422, $errores);
        }

        if (isset($datos['id_categoria']) && ! model(CategoriaModel::class)->estaActiva((int) $datos['id_categoria'])) {
            return $this->respuestaError('La categoría indicada no existe o está inactiva.', 422);
        }

        if (array_key_exists('fecha_limite', $datos) && $datos['fecha_limite'] === '') {
            $datos['fecha_limite'] = null;
        }

        $id = $this->modelo->crear($datos);
        if ($id === false) {
            return $this->respuestaError('No fue posible crear la tarea.', 500);
        }

        return $this->respuestaExitosa(['id_tarea' => $id], 201);
    }

    /** Valida proyecto y tarea, revisa los cambios y devuelve la tarea actualizada. */
    public function actualizar(int $idProyecto, int $idTarea): ResponseInterface
    {
        // 1. Confirmar acceso y que la tarea pertenezca al proyecto indicado.
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $actual = $this->modelo->buscarEnProyecto($idProyecto, $idTarea);
        if ($actual === null) {
            return $this->respuestaError("Tarea no encontrada con el ID {$idTarea}.", 404);
        }

        // 2. Leer el JSON y permitir solo los campos que se pueden modificar.
        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $permitidos = ['titulo', 'descripcion', 'id_categoria', 'prioridad', 'estado', 'fecha_limite'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        // 3. Validar los cambios y comprobar que la nueva categoría siga activa.
        $errores = $this->validarTarea($datos, false);
        if ($errores !== []) {
            return $this->respuestaError('Los datos de la tarea no son válidos.', 422, $errores);
        }

        if (isset($datos['id_categoria']) && ! model(CategoriaModel::class)->estaActiva((int) $datos['id_categoria'])) {
            return $this->respuestaError('La categoría indicada no existe o está inactiva.', 422);
        }

        if (array_key_exists('fecha_limite', $datos) && $datos['fecha_limite'] === '') {
            $datos['fecha_limite'] = null;
        }

        if (! $this->modelo->actualizarEnProyecto($idProyecto, $idTarea, $datos)) {
            return $this->respuestaError('No se proporcionaron campos válidos para actualizar.', 422);
        }

        return $this->respuestaExitosa($this->modelo->buscarEnProyecto($idProyecto, $idTarea));
    }

    /** Valida acceso y existencia, elimina la tarea dentro del proyecto y responde sin contenido. */
    public function eliminar(int $idProyecto, int $idTarea): ResponseInterface
    {
        // 1. Verificar acceso y existencia antes de borrar dentro del proyecto.
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        if ($this->modelo->buscarEnProyecto($idProyecto, $idTarea) === null) {
            return $this->respuestaError("Tarea no encontrada con el ID {$idTarea}.", 404);
        }

        if (! $this->modelo->eliminarEnProyecto($idProyecto, $idTarea)) {
            return $this->respuestaError('No fue posible eliminar la tarea.', 500);
        }

        return $this->response->setStatusCode(204);
    }

    /** Busca el proyecto y permite acceso solo a su propietario o a un administrador. */
    private function proyectoAccesible(int $idProyecto): bool
    {
        $proyecto = $this->proyectos->buscar($idProyecto);
        if ($proyecto === null) {
            return false;
        }

        $usuario = $this->usuarioApi();

        return $this->esAdministrador($usuario)
            || (int) $proyecto['id_usuario'] === (int) ($usuario['id_usuario'] ?? -1);
    }

    /** Aplica las reglas de campos de tarea requeridos al crear y opcionales al actualizar. */
    private function validarTarea(array $datos, bool $crear): array
    {
        $reglas = [];
        if ($crear || array_key_exists('titulo', $datos)) {
            $reglas['titulo'] = 'required|min_length[1]|max_length[120]';
        }
        if (array_key_exists('descripcion', $datos)) {
            $reglas['descripcion'] = 'permit_empty|max_length[5000]';
        }
        if (array_key_exists('prioridad', $datos)) {
            $reglas['prioridad'] = 'in_list[BAJA,MEDIA,ALTA]';
        }
        if (array_key_exists('estado', $datos)) {
            $reglas['estado'] = 'in_list[PENDIENTE,EN_PROCESO,COMPLETA]';
        }
        if (array_key_exists('fecha_limite', $datos)) {
            $reglas['fecha_limite'] = 'permit_empty|valid_date[Y-m-d]';
        }
        if ($crear || array_key_exists('id_categoria', $datos)) {
            $reglas['id_categoria'] = 'required|integer|greater_than[0]';
        }

        return $this->erroresValidacion($datos, $reglas);
    }
}
