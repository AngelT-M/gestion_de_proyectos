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

    /** Genera un token aleatorio, guarda solo su hash con fechas de vigencia y devuelve el secreto una vez. */
    public function crearToken(int $idUsuario): string|false
    {
        // El token original se entrega al cliente; en la base de datos solo se guarda su hash.
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

    /** Busca el hash de un token activo y vigente y devuelve la identidad y rol del usuario. */
    public function usuarioPorToken(string $token): ?array
    {
        // Hash entrante igual al hash persistido permite validar sin almacenar el secreto.
        $hash = hash('sha256', $token);
        $fila = db_connect()->table('token_acceso')
            ->select('usuario.id_usuario, usuario.usuario, usuario.id_rol, rol.nombre AS rol_nombre')
            ->join('usuario', 'usuario.id_usuario = token_acceso.id_usuario')
            ->join('rol', 'rol.id_rol = usuario.id_rol')
            ->where('token_acceso.token', $hash)
            ->where('token_acceso.activo', 1)
            ->where('token_acceso.fecha_expiracion >', gmdate('Y-m-d H:i:s'))
            ->where('usuario.estado', 'ACTIVO')
            ->get()
            ->getRowArray();

        if ($fila === null) {
            return null;
        }

        return [
            'id_usuario' => (int) $fila['id_usuario'],
            'usuario'    => $fila['usuario'],
            'id_rol'     => (int) $fila['id_rol'],
            'rol_nombre' => $this->formatearRol($fila['rol_nombre']),
        ];
    }

    /** Uniforma el nombre de rol para que controladores y filtros usen el mismo valor. */
    private function formatearRol(string $rol): string
    {
        return match (strtoupper(trim($rol))) {
            'ADMINISTRADOR', 'ADMIN' => 'ADMINISTRACION',
            default => strtoupper(trim($rol)),
        };
    }

    /** Desactiva el token buscando por su hash y devuelve si la actualización tuvo éxito. */
    public function revocarToken(string $token): bool
    {
        return $this->where('token', hash('sha256', $token))
            ->set(['activo' => 0])
            ->update();
    }
}
