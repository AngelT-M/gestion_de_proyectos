<?php

namespace App\Controllers;

use App\Models\RolModel;
use CodeIgniter\HTTP\ResponseInterface;

class RolController extends BaseController
{
    private RolModel $modelo;

    public function __construct()
    {
        $this->modelo = model(RolModel::class);
    }

    public function mostrar(): ResponseInterface
    {
        return $this->respuestaExitosa($this->modelo->mostrar());
    }

    public function buscar(int $id): ResponseInterface
    {
        $datos = $this->modelo->buscar($id);

        if ($datos === null) {
            return $this->respuestaError("Rol no encontrado con el ID {$id}.", 404);
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
            return $this->respuestaError('No fue posible crear el rol.', 500);
        }

        return $this->respuestaExitosa(['id_rol' => $id], 201);
    }

    public function actualizar(int $id): ResponseInterface
    {
        if ($this->modelo->buscar($id) === null) {
            return $this->respuestaError("Rol no encontrado con el ID {$id}.", 404);
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
            return $this->respuestaError("Rol no encontrado con el ID {$id}.", 404);
        }

        $this->modelo->eliminar($id);

        return $this->response->setStatusCode(204);
    }
}
