<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table         = 'categoria';
    protected $primaryKey    = 'id_categoria';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'descripcion', 'estado'];

    private function formatear(array $fila): array
    {
        if (isset($fila['id_categoria'])) {
            $fila['id_categoria'] = (int) $fila['id_categoria'];
        }

        return $fila;
    }

    public function mostrar(): array
    {
        $filas = $this->select(['id_categoria', 'nombre', 'descripcion', 'estado'])->findAll();

        return array_map([$this, 'formatear'], $filas);
    }

    public function activas(): array
    {
        $filas = $this->select(['id_categoria', 'nombre', 'descripcion', 'estado'])
            ->whereIn('estado', ['ACTIVA', 'ACTIVO'])
            ->findAll();

        return array_map([$this, 'formatear'], $filas);
    }

    public function estaActiva(int $id): bool
    {
        return $this->where('id_categoria', $id)
            ->whereIn('estado', ['ACTIVA', 'ACTIVO'])
            ->countAllResults() > 0;
    }

    public function buscar(int $id): ?array
    {
        $fila = $this->find($id);

        return $fila === null ? null : $this->formatear($fila);
    }

    public function crear(array $datos): int|false
    {
        $id = $this->insert(array_intersect_key($datos, array_flip($this->allowedFields)));

        return $id === false ? false : (int) $id;
    }

    public function actualizar(int $id, array $datos): bool
    {
        $valores = array_intersect_key($datos, array_flip($this->allowedFields));

        return $valores !== [] && $this->update($id, $valores);
    }

    public function eliminar(int $id): void
    {
        $this->delete($id);
    }
}
