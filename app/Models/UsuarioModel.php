<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table         = 'usuario';
    protected $primaryKey    = 'id_usuario';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nombre',
        'usuario',
        'correo',
        'contrasena',
        'id_rol',
        'estado',
    ];

    public function verificarLoginApi(string $usuario, string $password): array|false
    {
        $user = $this->select('usuario.*, rol.nombre AS rol_nombre')
            ->join('rol', 'rol.id_rol = usuario.id_rol')
            ->where('usuario.usuario', $usuario)
            ->where('usuario.estado', 'ACTIVO')
            ->first();

        if ($user === null) {
            return false;
        }

        $passwordInfo = password_get_info($user['contrasena']);
        $isHashed = $passwordInfo['algoName'] !== 'unknown';
        $passwordIsValid = $isHashed
            ? password_verify($password, $user['contrasena'])
            : hash_equals($user['contrasena'], $password);

        if (! $passwordIsValid) {
            return false;
        }

        if (! $isHashed) {
            if (! $this->update((int) $user['id_usuario'], ['contrasena' => password_hash($password, PASSWORD_DEFAULT)])) {
                throw new \RuntimeException('No fue posible proteger la contraseña heredada.');
            }
        }

        $token = model(TokenAccesoModel::class)->crearToken((int) $user['id_usuario']);
        if ($token === false) {
            throw new \RuntimeException('No fue posible guardar el token de acceso.');
        }

        return [
            'token' => $token,
            'usuario' => [
                'id_usuario' => (int) $user['id_usuario'],
                'usuario'    => $user['usuario'],
                'id_rol'     => (int) $user['id_rol'],
                'rol_nombre' => match (strtoupper(trim($user['rol_nombre']))) {
                    'ADMINISTRADOR', 'ADMIN' => 'ADMINISTRACION',
                    default => strtoupper(trim($user['rol_nombre'])),
                },
            ],
        ];
    }
}
