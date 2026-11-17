<?php

namespace App\Filters;

use App\Libraries\RedisService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return;
        }

        $ip   = $request->getIPAddress();
        $path = trim($request->getUri()->getPath(), '/');

        // Configuración según el tipo de ruta
        if (str_contains($path, 'auth/login')) {
            // Protección contra ataques de fuerza bruta en login
            $limitKey    = 'login:' . md5($ip);
            $maxRequests = 15;
            $windowSecs  = 60;
        } else {
            // Límite general para la API
            $limitKey    = 'api:' . md5($ip);
            $maxRequests = 180;
            $windowSecs  = 60;
        }

        $check = RedisService::checkRateLimit($limitKey, $maxRequests, $windowSecs);

        // Guardar para adjuntar en cabeceras de respuesta
        $request->rateLimit = $check;

        if (!$check['allowed']) {
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $check['retry_after'])
                ->setHeader('X-RateLimit-Limit', (string) $check['limit'])
                ->setHeader('X-RateLimit-Remaining', (string) $check['remaining'])
                ->setJSON([
                    'status'   => 429,
                    'error'    => 'TOO_MANY_REQUESTS',
                    'messages' => 'Límite de peticiones excedido. Por favor espere ' . $check['retry_after'] . ' segundos antes de reintentar.',
                    'data'     => [
                        'retry_after' => $check['retry_after'],
                    ],
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (isset($request->rateLimit) && is_array($request->rateLimit)) {
            $check = $request->rateLimit;
            $response->setHeader('X-RateLimit-Limit', (string) ($check['limit'] ?? 180));
            $response->setHeader('X-RateLimit-Remaining', (string) ($check['remaining'] ?? 0));
        }
    }
}
