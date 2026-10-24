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

    /** Normaliza los IDs numéricos de la tarea para que la API reciba enteros. */
    private function formatear(array $fila): array
    {
        foreach (['id_tarea', 'id_proyecto', 'id_categoria'] as $campo) {
            if (isset($fila[$campo])) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        return $fila;
    }

    /** Cuenta las tareas filtradas del proyecto y devuelve la página y el total encontrado. */
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

    /** Busca una tarea dentro de un proyecto específico; evita devolver tareas de otro proyecto. */
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

    /** Comprueba si el proyecto contiene tareas que impidan eliminarlo por la relación de la base de datos. */
    public function tieneEnProyecto(int $idProyecto): bool
    {
        return $this->where('id_proyecto', $idProyecto)->countAllResults() > 0;
    }

    /** Arma la consulta de tareas del proyecto y agrega filtros de texto o estado opcionales. */
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

    /** Inserta los campos permitidos de la tarea y devuelve el ID generado o false si falla. */
    public function crear(array $datos): int|false
    {
        $id = $this->insert(array_intersect_key($datos, array_flip($this->allowedFields)));

        return $id === false ? false : (int) $id;
    }

    /** Modifica la tarea solo cuando coinciden tanto el ID de tarea como el del proyecto. */
    public function actualizarEnProyecto(int $idProyecto, int $idTarea, array $datos): bool
    {
        $valores = array_intersect_key($datos, array_flip($this->allowedFields));

        return $valores !== []
            && db_connect()->table($this->table)
                ->where('id_proyecto', $idProyecto)
                ->where('id_tarea', $idTarea)
                ->update($valores);
    }

    /** Elimina la tarea únicamente si está dentro del proyecto indicado. */
    public function eliminarEnProyecto(int $idProyecto, int $idTarea): bool
    {
        return db_connect()->table($this->table)
            ->where('id_proyecto', $idProyecto)
            ->where('id_tarea', $idTarea)
            ->delete();
    }
}
