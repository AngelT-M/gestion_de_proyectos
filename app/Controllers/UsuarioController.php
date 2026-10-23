<?php

namespace App\Controllers;

use App\Models\UsuarioModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class UsuarioController extends BaseController
{
    use ResponseTrait;

    private UsuarioModel $modelo;

    public function __construct()
    {
        $this->modelo = model(UsuarioModel::class);
    }

    public function mostrar(): ResponseInterface
    {
        return $this->respond($this->modelo->mostrar());
    }

    public function buscar(int $id): ResponseInterface
    {
        $datos = $this->modelo->buscar($id);

        if ($datos === null) {
            return $this->failNotFound("Usuario no encontrado con el ID {$id}");
        }

        return $this->respond($datos);
    }

    private function cuerpo(): ?array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) && $json !== [] && ! array_is_list($json) ? $json : null;
    }

    public function crear(): ResponseInterface
    {
        $datos = $this->cuerpo();
        if ($datos === null) {
            return $this->failValidationErrors('El cuerpo debe ser un objeto JSON no vacío.');
        }

        $id = $this->modelo->crear($datos);

        if ($id === false) {
            return $this->failServerError('No fue posible crear el usuario.');
        }

        return $this->respondCreated(['id_usuario' => $id]);
    }

    public function actualizar(int $id): ResponseInterface
    {
        if ($this->modelo->buscar($id) === null) {
            return $this->failNotFound("Usuario no encontrado con el ID {$id}");
        }

        $datos = $this->cuerpo();
        if ($datos === null) {
            return $this->failValidationErrors('El cuerpo debe ser un objeto JSON no vacío.');
        }

        if (! $this->modelo->actualizar($id, $datos)) {
            return $this->failValidationErrors('No se proporcionaron campos válidos para actualizar.');
        }

        return $this->respond($this->modelo->buscar($id));
    }

    public function eliminar(int $id): ResponseInterface
    {
        if ($this->modelo->buscar($id) === null) {
            return $this->failNotFound("Usuario no encontrado con el ID {$id}");
        }

        $this->modelo->eliminar($id);

        return $this->respondNoContent();
    }
}
