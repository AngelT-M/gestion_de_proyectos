<?php

namespace App\Models;

use CodeIgniter\Model;

class TokenAccesoModel extends Model
{
    protected $table         = 'token_acceso';
    protected $primaryKey    = 'id_token';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'id_usuario',
        'token',
        'fecha_creacion',
        'fecha_expiracion',
        'activo',
    ];

    private function normalizar(array $fila): array
    {
        foreach (['id_token', 'id_usuario', 'activo'] as $campo) {
            if (isset($fila[$campo])) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        return $fila;
    }

    public function mostrar(): array
    {
        $filas = $this->select([
            'id_token',
            'id_usuario',
            'fecha_creacion',
            'fecha_expiracion',
            'activo',
        ])->findAll();

        return array_map([$this, 'normalizar'], $filas);
    }

    public function buscar(int $id): ?array
    {
        $fila = $this->select([
            'id_token',
            'id_usuario',
            'fecha_creacion',
            'fecha_expiracion',
            'activo',
        ])->find($id);

        return $fila === null ? null : $this->normalizar($fila);
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

    public function crearToken(int $idUsuario): string|false
    {
        $token = bin2hex(random_bytes(32));
        $id = $this->insert([
            'id_usuario'       => $idUsuario,
            'token'            => hash('sha256', $token),
            'fecha_creacion'   => gmdate('Y-m-d H:i:s'),
            'fecha_expiracion' => gmdate('Y-m-d H:i:s', time() + 7 * 86400),
            'activo'           => 1,
        ]);

        return $id === false ? false : $token;
    }

    public function usuarioPorToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $fila = db_connect()->table('token_acceso')
            ->select('token_acceso.id_token, token_acceso.token, usuario.id_usuario, usuario.usuario, usuario.id_rol, rol.nombre AS rol_nombre')
            ->join('usuario', 'usuario.id_usuario = token_acceso.id_usuario')
            ->join('rol', 'rol.id_rol = usuario.id_rol')
            ->whereIn('token_acceso.token', [$hash, $token])
            ->where('token_acceso.activo', 1)
            ->where('token_acceso.fecha_expiracion >', gmdate('Y-m-d H:i:s'))
            ->where('usuario.estado', 'ACTIVO')
            ->get()
            ->getRowArray();

        if ($fila === null) {
            return null;
        }

        if ($fila['token'] === $token) {
            $actualizado = $this->update((int) $fila['id_token'], ['token' => $hash]);
            if (! $actualizado) {
                log_message('error', 'No fue posible actualizar al formato hash un token de acceso heredado.');
            }
        }

        return [
            'id_usuario' => (int) $fila['id_usuario'],
            'usuario'    => $fila['usuario'],
            'id_rol'     => (int) $fila['id_rol'],
            'rol_nombre' => $this->normalizarRol($fila['rol_nombre']),
        ];
    }

    private function normalizarRol(string $rol): string
    {
        return match (strtoupper(trim($rol))) {
            'ADMINISTRADOR', 'ADMIN' => 'ADMINISTRACION',
            default => strtoupper(trim($rol)),
        };
    }

    public function revocarToken(string $token): bool
    {
        return $this->where('token', hash('sha256', $token))
            ->set(['activo' => 0])
            ->update();
    }
}
