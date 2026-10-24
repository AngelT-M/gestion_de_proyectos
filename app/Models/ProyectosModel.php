<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class ProyectosModel extends Model
{
    protected $table         = 'proyectos';
    protected $primaryKey    = 'id_proyecto';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'id_usuario',
    ];

    /** Normaliza los identificadores del proyecto antes de entregar sus datos al controlador. */
    private function normalizar(array $fila): array
    {
        foreach (['id_proyecto', 'id_usuario'] as $campo) {
            if (isset($fila[$campo])) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        return $fila;
    }

    /** Cuenta los resultados filtrados, obtiene la página solicitada y devuelve datos y total. */
    public function pagina(int $pagina, int $porPagina, string $busqueda = '', ?string $estado = null, ?int $idUsuario = null): array
    {
        $total = $this->consulta($busqueda, $estado, $idUsuario)->countAllResults();
        $filas = $this->consulta($busqueda, $estado, $idUsuario)
            ->get($porPagina, ($pagina - 1) * $porPagina)
            ->getResultArray();

        return [
            'datos' => array_map([$this, 'normalizar'], $filas),
            'total' => $total,
        ];
    }

    /** Construye la consulta base aplicando propietario, texto y estado cuando se proporcionan. */
    private function consulta(string $busqueda, ?string $estado, ?int $idUsuario): BaseBuilder
    {
        $consulta = db_connect()->table($this->table)->select([
            'id_proyecto',
            'nombre',
            'descripcion',
            'fecha_inicio',
            'fecha_fin',
            'estado',
            'id_usuario',
        ])->orderBy('id_proyecto', 'ASC');

        if ($idUsuario !== null) {
            $consulta->where('id_usuario', $idUsuario);
        }

        if ($busqueda !== '') {
            $consulta->groupStart()
                ->like('nombre', $busqueda)
                ->orLike('descripcion', $busqueda)
                ->groupEnd();
        }

        if ($estado !== null && $estado !== '') {
            $consulta->where('estado', $estado);
        }

        return $consulta;
    }

    /** Busca un proyecto por ID; devuelve null si no existe y normaliza los campos numéricos. */
    public function buscar(int $id): ?array
    {
        $fila = $this->find($id);

        return $fila === null ? null : $this->normalizar($fila);
    }

    /** Inserta únicamente campos permitidos y devuelve el ID generado o false si falla. */
    public function crear(array $datos): int|false
    {
        $id = $this->insert(array_intersect_key($datos, array_flip($this->allowedFields)));

        return $id === false ? false : (int) $id;
    }

    /** Actualiza campos permitidos del proyecto y devuelve si se aplicó una modificación válida. */
    public function actualizar(int $id, array $datos): bool
    {
        $valores = array_intersect_key($datos, array_flip($this->allowedFields));

        return $valores !== [] && $this->update($id, $valores);
    }

    /** Actualiza el proyecto solo si pertenece al usuario indicado, evitando modificar ajenos. */
    public function actualizarDeUsuario(int $id, int $idUsuario, array $datos): bool
    {
        $valores = array_intersect_key($datos, array_flip($this->allowedFields));

        return $valores !== []
            && db_connect()->table($this->table)
                ->where('id_proyecto', $id)
                ->where('id_usuario', $idUsuario)
                ->update($valores);
    }

    /** Elimina el proyecto por ID y devuelve el resultado de la operación de base de datos. */
    public function eliminar(int $id): bool
    {
        return $this->delete($id);
    }

    /** Elimina el proyecto solo cuando su ID y propietario coinciden con los recibidos. */
    public function eliminarDeUsuario(int $id, int $idUsuario): bool
    {
        return db_connect()->table($this->table)
            ->where('id_proyecto', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();
    }
}
