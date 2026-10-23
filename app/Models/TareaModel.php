<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class TareaModel extends Model
{
    protected $table         = 'tarea';
    protected $primaryKey    = 'id_tarea';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'titulo',
        'descripcion',
        'id_proyecto',
        'id_categoria',
        'prioridad',
        'estado',
        'fecha_limite',
    ];

    private function formatear(array $fila): array
    {
        foreach (['id_tarea', 'id_proyecto', 'id_categoria'] as $campo) {
            if (isset($fila[$campo])) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        return $fila;
    }

    public function mostrar(): array
    {
        $filas = $this->select([
            'id_tarea',
            'titulo',
            'descripcion',
            'id_proyecto',
            'id_categoria',
            'prioridad',
            'estado',
            'fecha_limite',
        ])->findAll();

        return array_map([$this, 'formatear'], $filas);
    }

    public function paginaProyecto(int $idProyecto, int $pagina, int $porPagina, string $busqueda = '', ?string $estado = null): array
    {
        $total = $this->consultaProyecto($idProyecto, $busqueda, $estado)->countAllResults();
        $filas = $this->consultaProyecto($idProyecto, $busqueda, $estado)
            ->get($porPagina, ($pagina - 1) * $porPagina)
            ->getResultArray();

        return [
            'datos' => array_map([$this, 'formatear'], $filas),
            'total' => $total,
        ];
    }

    public function buscarEnProyecto(int $idProyecto, int $idTarea): ?array
    {
        $fila = db_connect()->table($this->table)
            ->select([
                'id_tarea',
                'titulo',
                'descripcion',
                'id_proyecto',
                'id_categoria',
                'prioridad',
                'estado',
                'fecha_limite',
            ])
            ->where('id_proyecto', $idProyecto)
            ->where('id_tarea', $idTarea)
            ->get()
            ->getRowArray();

        return $fila === null ? null : $this->formatear($fila);
    }

    private function consultaProyecto(int $idProyecto, string $busqueda, ?string $estado): BaseBuilder
    {
        $consulta = db_connect()->table($this->table)
            ->select([
                'id_tarea',
                'titulo',
                'descripcion',
                'id_proyecto',
                'id_categoria',
                'prioridad',
                'estado',
                'fecha_limite',
            ])
            ->where('id_proyecto', $idProyecto)
            ->orderBy('id_tarea', 'ASC');

        if ($busqueda !== '') {
            $consulta->groupStart()
                ->like('titulo', $busqueda)
                ->orLike('descripcion', $busqueda)
                ->groupEnd();
        }

        if ($estado !== null && $estado !== '') {
            $consulta->where('estado', $estado);
        }

        return $consulta;
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

    public function actualizarEnProyecto(int $idProyecto, int $idTarea, array $datos): bool
    {
        $valores = array_intersect_key($datos, array_flip($this->allowedFields));

        return $valores !== []
            && db_connect()->table($this->table)
                ->where('id_proyecto', $idProyecto)
                ->where('id_tarea', $idTarea)
                ->update($valores);
    }

    public function eliminar(int $id): bool
    {
        return $this->delete($id);
    }

    public function eliminarEnProyecto(int $idProyecto, int $idTarea): bool
    {
        return db_connect()->table($this->table)
            ->where('id_proyecto', $idProyecto)
            ->where('id_tarea', $idTarea)
            ->delete();
    }
}
