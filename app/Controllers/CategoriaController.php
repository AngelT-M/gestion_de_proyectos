<?php

namespace App\Controllers;

use App\Models\CategoriaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CategoriaController extends BaseController
{
    private CategoriaModel $modelo;

    public function __construct()
    {
        $this->modelo = model(CategoriaModel::class);
    }

    public function mostrar(): ResponseInterface
    {
        return $this->respuestaExitosa($this->modelo->mostrar());
    }

    public function activas(): ResponseInterface
    {
        return $this->respuestaExitosa($this->modelo->activas());
    }

    public function buscar(int $id): ResponseInterface
    {
        $datos = $this->modelo->buscar($id);

        if ($datos === null) {
            return $this->respuestaError("Categoría no encontrada con el ID {$id}.", 404);
        }

        return $this->respuestaExitosa($datos);
    }

    public function crear(): ResponseInterface
    {
        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $id = $this->modelo->crear($datos);

        if ($id === false) {
            return $this->respuestaError('No fue posible crear la categoría.', 500);
        }

        return $this->respuestaExitosa(['id_categoria' => $id], 201);
    }

    public function actualizar(int $id): ResponseInterface
    {
        if ($this->modelo->buscar($id) === null) {
            return $this->respuestaError("Categoría no encontrada con el ID {$id}.", 404);
        }

        $datos = $this->cuerpoJson();
        if ($datos === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        if (! $this->modelo->actualizar($id, $datos)) {
            return $this->respuestaError('No se proporcionaron campos válidos para actualizar.', 422);
        }

        return $this->respuestaExitosa($this->modelo->buscar($id));
    }

    public function eliminar(int $id): ResponseInterface
    {
        if ($this->modelo->buscar($id) === null) {
            return $this->respuestaError("Categoría no encontrada con el ID {$id}.", 404);
        }

        $this->modelo->eliminar($id);

        return $this->response->setStatusCode(204);
    }
}
