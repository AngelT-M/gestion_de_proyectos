<?php

namespace App\Filters;

use App\Models\TokenAccesoModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // 1. Exigir un Bearer token con el formato generado durante el login.
        $authorization = $request->getHeaderLine('Authorization');
        if (! preg_match('/\ABearer ([a-f0-9]{64})\z/', $authorization, $matches)) {
            return $this->error('Se requiere un token Bearer válido.', 401);
        }

        // 2. Buscar el usuario asociado y rechazar tokens vencidos o revocados.
        $usuario = model(TokenAccesoModel::class)->usuarioPorToken($matches[1]);
        if ($usuario === null) {
            return $this->error('El token no es válido, está vencido o fue revocado.', 401);
        }

        // 3. Pasar la identidad autenticada al controlador para aplicar permisos por propietario y rol.
        $request->removeHeader('X-Authenticated-User');
        $request->setHeader('X-Authenticated-User', json_encode($usuario));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function error(string $mensaje, int $estado): ResponseInterface
    {
        return service('response')
            ->setStatusCode($estado)
            ->setJSON(['exito' => false, 'mensaje' => $mensaje]);
    }
}
