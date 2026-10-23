<?php

namespace App\Controllers;

use App\Models\TokenAccesoModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseController
{
    public function login(): ResponseInterface
    {
        $json = $this->cuerpoJson();

        if ($json === null) {
            return $this->respuestaError('El cuerpo debe ser un objeto JSON no vacío.', 400);
        }

        $usuario = $json['usuario'] ?? null;
        $password = $json['contrasena'] ?? $json['contraseña'] ?? null;
        if (! is_string($usuario) || trim($usuario) === '' || ! is_string($password) || $password === '') {
            return $this->respuestaError('Se requieren usuario y contraseña.', 422);
        }

        try {
            $resultado = model(UsuarioModel::class)->verificarLoginApi(trim($usuario), $password);
        } catch (\RuntimeException $exception) {
            log_message('error', 'No fue posible completar el inicio de sesión: ' . $exception->getMessage());

            return $this->respuestaError('No fue posible iniciar sesión en este momento.', 500);
        }

        if ($resultado === false) {
            return $this->respuestaError('Credenciales incorrectas o usuario inactivo.', 401);
        }

        return $this->respuestaExitosa([
            'token'   => $resultado['token'],
            'tipo'    => 'Bearer',
            'usuario' => $resultado['usuario'],
        ]);
    }

    public function logout(): ResponseInterface
    {
        $authorization = $this->request->getHeaderLine('Authorization');
        preg_match('/\ABearer ([a-f0-9]{64})\z/', $authorization, $matches);

        if (! isset($matches[1]) || ! model(TokenAccesoModel::class)->revocarToken($matches[1])) {
            return $this->respuestaError('No fue posible cerrar la sesión.', 500);
        }

        return $this->respuestaExitosa(['mensaje' => 'Sesión cerrada.']);
    }
}
