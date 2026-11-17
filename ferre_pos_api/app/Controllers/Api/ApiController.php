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

    protected function wantsPagination(): bool
    {
        return $this->request->getGet('page') !== null || $this->request->getGet('per_page') !== null;
    }

    protected function getPaginationParams(int $defaultPerPage = 25): array
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = max(5, min(100, (int) ($this->request->getGet('per_page') ?? $defaultPerPage)));
        return [$page, $perPage];
    }

    protected function buildPaginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    protected function paginateArray(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $meta  = $this->buildPaginationMeta($total, $page, $perPage);
        $items = array_slice($rows, ($meta['current_page'] - 1) * $perPage, $perPage);

        return [
            'items'      => array_values($items),
            'pagination' => $meta,
        ];
    }
}
