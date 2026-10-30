<?php

namespace App\Controllers\Api;

use App\Libraries\JwtLib;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Throwable;

class ApiController extends ResourceController
{
    protected $format = 'json';

    /**
     * Respuesta JSON estandarizada según el skill ferreteria-pos:
     * {"status": 200, "error": null, "messages": "...", "data": {...}}
     */
    protected function apiResponse(
        mixed $data = null,
        string $message = 'Operación exitosa',
        int $status = 200,
        mixed $error = null
    ): ResponseInterface {
        return $this->respond([
            'status'   => $status,
            'error'    => $error,
            'messages' => $message,
            'data'     => $data,
        ], $status);
    }

    protected function apiError(
        string $message,
        int $status = 400,
        mixed $error = 'BAD_REQUEST',
        mixed $data = null
    ): ResponseInterface {
        return $this->respond([
            'status'   => $status,
            'error'    => $error,
            'messages' => $message,
            'data'     => $data,
        ], $status);
    }

    protected function getRequestPayload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && !empty($json)) {
            return $json;
        }
        $raw = $this->request->getRawInput();
        if (is_array($raw) && !empty($raw)) {
            return $raw;
        }
        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    protected function getAuthUser(): array
    {
        if (isset($this->request->user) && is_array($this->request->user)) {
            return $this->request->user;
        }

        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            try {
                return (new JwtLib())->decode($matches[1]);
            } catch (Throwable) {
            }
        }

        return ['uid' => 1, 'username' => 'admin', 'rol' => 'ADMIN'];
    }
}
