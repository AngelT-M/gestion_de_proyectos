<?php

namespace App\Models;

use CodeIgniter\Model;

class RolModel extends Model
{
    protected $table         = 'rol';
    protected $primaryKey    = 'id_rol';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'descripcion'];

    private function formatear(array $fila): array
    {
        if (isset($fila['id_rol'])) {
            $fila['id_rol'] = (int) $fila['id_rol'];
        }

        return $fila;
    }

    public function mostrar(): array
    {
        $filas = $this->select(['id_rol', 'nombre', 'descripcion'])->findAll();

        return array_map([$this, 'formatear'], $filas);
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
