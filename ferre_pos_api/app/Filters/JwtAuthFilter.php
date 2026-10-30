<?php

namespace App\Filters;

use App\Libraries\JwtLib;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (empty($authHeader) || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'   => 401,
                    'error'    => 'UNAUTHORIZED',
                    'messages' => 'Token de autenticación no proporcionado.',
                    'data'     => null,
                ]);
        }

        try {
            $jwt     = new JwtLib();
            $payload = $jwt->decode($matches[1]);
            // Adjuntamos el usuario autenticado globalmente para los controladores
            $request->user = $payload;

            // Validación de rol si el filtro recibe argumentos (ej. jwt:ADMIN)
            if (!empty($arguments)) {
                $rolUsuario      = strtoupper((string) ($payload['rol'] ?? ''));
                $rolesPermitidos = array_map('strtoupper', $arguments);
                if (!in_array($rolUsuario, $rolesPermitidos, true)) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'   => 403,
                            'error'    => 'FORBIDDEN',
                            'messages' => 'Acceso denegado: el rol ' . $rolUsuario . ' solo tiene permiso para ver y realizar ventas.',
                            'data'     => null,
                        ]);
                }
            }
        } catch (Throwable $e) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'   => 401,
                    'error'    => 'INVALID_TOKEN',
                    'messages' => $e->getMessage(),
                    'data'     => null,
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
