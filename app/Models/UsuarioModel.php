<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table         = 'usuario';
    protected $primaryKey    = 'id_usuario';
    protected $returnType    = 'array';
    protected $allowedFields = ['contrasena'];

    /** Busca una cuenta activa, valida su contraseña, crea un token y devuelve los datos públicos de sesión. */
    public function verificarLoginApi(string $usuario, string $password): array|false
    {
        // 1. Buscar el usuario activo y su rol usando el nombre recibido en el login.
        $user = $this->select('usuario.id_usuario, usuario.usuario, usuario.contrasena, usuario.id_rol, rol.nombre AS rol_nombre')
            ->join('rol', 'rol.id_rol = usuario.id_rol')
            ->where('usuario.usuario', $usuario)
            ->where('usuario.estado', 'ACTIVO')
            ->first();

        if ($user === null) {
            return false;
        }

        // 2. Verificar hashes modernos y aceptar una vez contraseñas antiguas aún sin hash.
        $passwordInfo = password_get_info($user['contrasena']);
        $isHashed = $passwordInfo['algoName'] !== 'unknown';
        $passwordIsValid = $isHashed
            ? password_verify($password, $user['contrasena'])
            : hash_equals($user['contrasena'], $password);

        if (! $passwordIsValid) {
            return false;
        }

        // 3. Proteger la contraseña heredada después de comprobarla correctamente.
        if (! $isHashed) {
            if (! $this->update((int) $user['id_usuario'], ['contrasena' => password_hash($password, PASSWORD_DEFAULT)])) {
                throw new \RuntimeException('No fue posible proteger la contraseña heredada.');
            }
        }

        // 4. Crear el token de sesión y devolver solo los datos públicos del usuario.
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
