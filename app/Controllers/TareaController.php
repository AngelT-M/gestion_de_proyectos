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

    public function __construct()
    {
        $this->modelo = model(TareaModel::class);
        $this->proyectos = model(ProyectosModel::class);
    }

    public function mostrar(int $idProyecto): ResponseInterface
    {
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $paginacion = $this->parametrosPaginacion();
        if ($paginacion === null) {
            return $this->respuestaError('page y per_page deben ser enteros positivos; per_page no puede superar 100.', 400);
        }

        $busqueda = $this->request->getGet('q') ?? '';
        $estado = $this->request->getGet('estado');
        if (! is_string($busqueda) || strlen($busqueda) > 100 || ($estado !== null && (! is_string($estado) || strlen($estado) > 30))) {
            return $this->respuestaError('Los filtros de búsqueda no son válidos.', 400);
        }

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

    public function buscar(int $idProyecto, int $idTarea): ResponseInterface
    {
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $tarea = $this->modelo->buscarEnProyecto($idProyecto, $idTarea);
        if ($tarea === null) {
            return $this->respuestaError("Tarea no encontrada con el ID {$idTarea}.", 404);
        }

        return $this->respuestaExitosa($tarea);
    }

    public function crear(int $idProyecto): ResponseInterface
    {
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $permitidos = ['titulo', 'descripcion', 'id_proyecto', 'id_categoria', 'prioridad', 'estado', 'fecha_limite'];
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos !== []) {
            return $this->respuestaError('El cuerpo contiene campos no permitidos.', 422, ['campos' => array_values($desconocidos)]);
        }

        $datos['id_proyecto'] = $idProyecto;
        $errores = $this->validarTarea($datos, true);
        if ($errores !== []) {
            return $this->respuestaError('Los datos de la tarea no son válidos.', 422, $errores);
        }

        if (isset($datos['id_categoria']) && ! model(CategoriaModel::class)->estaActiva((int) $datos['id_categoria'])) {
            return $this->respuestaError('La categoría indicada no existe o está inactiva.', 422);
        }

        $id = $this->modelo->crear($datos);
        if ($id === false) {
            return $this->respuestaError('No fue posible crear la tarea.', 500);
        }

        return $this->respuestaExitosa(['id_tarea' => $id], 201);
    }

    public function actualizar(int $idProyecto, int $idTarea): ResponseInterface
    {
        if (! $this->proyectoAccesible($idProyecto)) {
            return $this->respuestaError("Proyecto no encontrado con el ID {$idProyecto}.", 404);
        }

        $actual = $this->modelo->buscarEnProyecto($idProyecto, $idTarea);
        if ($actual === null) {
            return $this->respuestaError("Tarea no encontrada con el ID {$idTarea}.", 404);
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

        $errores = $this->validarTarea($datos, false);
        if ($errores !== []) {
            return $this->respuestaError('Los datos de la tarea no son válidos.', 422, $errores);
        }

        if (isset($datos['id_categoria']) && ! model(CategoriaModel::class)->estaActiva((int) $datos['id_categoria'])) {
            return $this->respuestaError('La categoría indicada no existe o está inactiva.', 422);
        }

        if (! $this->modelo->actualizarEnProyecto($idProyecto, $idTarea, $datos)) {
            return $this->respuestaError('No se proporcionaron campos válidos para actualizar.', 422);
        }

        return $this->respuestaExitosa($this->modelo->buscarEnProyecto($idProyecto, $idTarea));
    }

    public function eliminar(int $idProyecto, int $idTarea): ResponseInterface
    {
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

    private function validarTarea(array $datos, bool $crear): array
    {
        $reglas = [];
        if ($crear || array_key_exists('titulo', $datos)) {
            $reglas['titulo'] = 'required|min_length[1]|max_length[200]';
        }
        foreach (['descripcion' => 'permit_empty|max_length[5000]', 'prioridad' => 'permit_empty|max_length[30]', 'estado' => 'permit_empty|max_length[30]', 'fecha_limite' => 'permit_empty|valid_date[Y-m-d]'] as $campo => $regla) {
            if ($crear || array_key_exists($campo, $datos)) {
                $reglas[$campo] = $regla;
            }
        }
        if (array_key_exists('id_categoria', $datos)) {
            $reglas['id_categoria'] = 'permit_empty|integer|greater_than[0]';
        }

        return $this->erroresValidacion($datos, $reglas);
    }
}
