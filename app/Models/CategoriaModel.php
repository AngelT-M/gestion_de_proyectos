<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table         = 'categoria';
    protected $primaryKey    = 'id_categoria';
    protected $returnType    = 'array';

    /** Filtra las categorías activas y devuelve sus datos con identificadores normalizados. */
    public function activas(): array
    {
        $filas = $this->select(['id_categoria', 'nombre', 'descripcion', 'estado'])
            ->where('estado', 'ACTIVA')
            ->findAll();

        return array_map(static function (array $fila): array {
            $fila['id_categoria'] = (int) $fila['id_categoria'];

            return $fila;
        }, $filas);
    }

    /** Comprueba en la base de datos si la categoría indicada existe y está activa. */
    public function estaActiva(int $id): bool
    {
        return $this->where('id_categoria', $id)
            ->where('estado', 'ACTIVA')
            ->countAllResults() > 0;
    }
}
