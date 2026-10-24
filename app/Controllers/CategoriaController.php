<?php

namespace App\Controllers;

use App\Models\CategoriaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CategoriaController extends BaseController
{
    private CategoriaModel $modelo;

    /** Carga el modelo usado para consultar las categorías activas. */
    public function __construct()
    {
        $this->modelo = model(CategoriaModel::class);
    }

    /** Solicita al modelo solo categorías activas y entrega la lista como JSON. */
    public function activas(): ResponseInterface
    {
        return $this->respuestaExitosa($this->modelo->activas());
    }

}
