<?php

namespace App\Controllers\Api;

use App\Models\UsuarioModel;
use CodeIgniter\HTTP\ResponseInterface;

class CajeroController extends ApiController
{
    public function index(): ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->query("
            SELECT
                u.id,
                u.nombre,
                u.username,
                u.email,
                u.rol,
                u.activo,
                u.created_at,
                COUNT(CASE WHEN v.estado = 'COMPLETADA' THEN 1 END) AS ventas_completadas,
                COUNT(CASE WHEN v.estado = 'ANULADA' THEN 1 END) AS ventas_anuladas,
                COALESCE(SUM(CASE WHEN v.estado = 'COMPLETADA' THEN v.total_usd ELSE 0 END), 0.00) AS total_vendido_usd,
                COALESCE(SUM(CASE WHEN v.estado = 'COMPLETADA' THEN v.total_ves ELSE 0 END), 0.00) AS total_vendido_ves,
                MAX(v.created_at) AS ultima_venta
            FROM usuarios u
            LEFT JOIN ventas v ON v.usuario_id = u.id
            GROUP BY u.id, u.nombre, u.username, u.email, u.rol, u.activo, u.created_at
            ORDER BY u.rol DESC, u.id ASC
        ")->getResultArray();

        foreach ($rows as &$r) {
            $r['id']                 = (int) $r['id'];
            $r['activo']             = (int) $r['activo'];
            $r['ventas_completadas'] = (int) $r['ventas_completadas'];
            $r['ventas_anuladas']    = (int) $r['ventas_anuladas'];
            $r['total_vendido_usd']  = (float) $r['total_vendido_usd'];
            $r['total_vendido_ves']  = (float) $r['total_vendido_ves'];
        }

        return $this->apiResponse($rows, 'Listado de cajeros y resumen de ventas obtenido.');
    }

    public function create(): ResponseInterface
    {
        $payload  = $this->getRequestPayload();
        $nombre   = trim((string) ($payload['nombre'] ?? ''));
        $username = strtolower(trim((string) ($payload['username'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $email    = trim((string) ($payload['email'] ?? ''));

        if ($nombre === '' || $username === '' || $password === '') {
            return $this->apiError('El nombre del cajero, usuario y contraseña son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        if (strlen($password) < 4) {
            return $this->apiError('La contraseña debe tener al menos 4 caracteres.', 422, 'WEAK_PASSWORD');
        }

        if ($email === '') {
            $email = $username . '@ferrepos.local';
        }

        $model = new UsuarioModel();
        if ($model->where('username', $username)->first()) {
            return $this->apiError("El nombre de usuario '{$username}' ya está en uso.", 422, 'DUPLICATE_USERNAME');
        }

        if ($model->where('email', $email)->first()) {
            return $this->apiError("El correo '{$email}' ya está registrado.", 422, 'DUPLICATE_EMAIL');
        }

        $nuevoId = $model->insert([
            'nombre'        => $nombre,
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol'           => 'CAJERO',
            'activo'        => isset($payload['activo']) ? (int) (bool) $payload['activo'] : 1,
        ], true);

        $cajero = $model->select('id, nombre, username, email, rol, activo, created_at')->find((int) $nuevoId);
        $cajero['id']     = (int) $cajero['id'];
        $cajero['activo'] = (int) $cajero['activo'];

        return $this->apiResponse($cajero, 'Cajero creado exitosamente.', 201);
    }

    public function update($id = null): ResponseInterface
    {
        $model     = new UsuarioModel();
        $existente = $model->find((int) $id);

        if (!$existente) {
            return $this->apiError('Cajero / usuario no encontrado.', 404, 'NOT_FOUND');
        }

        $payload  = $this->getRequestPayload();
        $nombre   = isset($payload['nombre']) ? trim((string) $payload['nombre']) : $existente['nombre'];
        $username = isset($payload['username']) ? strtolower(trim((string) $payload['username'])) : $existente['username'];
        $email    = isset($payload['email']) && trim((string) $payload['email']) !== ''
            ? trim((string) $payload['email'])
            : $existente['email'];
        $activo   = isset($payload['activo']) ? (int) (bool) $payload['activo'] : (int) $existente['activo'];
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($nombre === '' || $username === '') {
            return $this->apiError('El nombre y el usuario no pueden estar vacíos.', 422, 'VALIDATION_ERROR');
        }

        if ($model->where('username', $username)->where('id !=', (int) $id)->first()) {
            return $this->apiError("El usuario '{$username}' ya pertenece a otra cuenta.", 422, 'DUPLICATE_USERNAME');
        }

        if ($model->where('email', $email)->where('id !=', (int) $id)->first()) {
            return $this->apiError("El correo '{$email}' ya pertenece a otra cuenta.", 422, 'DUPLICATE_EMAIL');
        }

        $updateData = [
            'nombre'   => $nombre,
            'username' => $username,
            'email'    => $email,
            'activo'   => $activo,
        ];

        if ($password !== '') {
            if (strlen($password) < 4) {
                return $this->apiError('La nueva contraseña debe tener al menos 4 caracteres.', 422, 'WEAK_PASSWORD');
            }
            $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $model->update((int) $id, $updateData);

        $cajero = $model->select('id, nombre, username, email, rol, activo, created_at')->find((int) $id);
        $cajero['id']     = (int) $cajero['id'];
        $cajero['activo'] = (int) $cajero['activo'];

        return $this->apiResponse($cajero, 'Datos del cajero actualizados.');
    }

    public function ventas($id = null): ResponseInterface
    {
        $model  = new UsuarioModel();
        $cajero = $model->select('id, nombre, username, email, rol, activo, created_at')->find((int) $id);

        if (!$cajero) {
            return $this->apiError('Cajero no encontrado.', 404, 'NOT_FOUND');
        }

        $db     = \Config\Database::connect();
        $ventas = $db->table('ventas v')
            ->select('v.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = v.usuario_id', 'left')
            ->where('v.usuario_id', (int) $id)
            ->orderBy('v.id', 'DESC')
            ->get()
            ->getResultArray();

        $ventasCompletadas   = 0;
        $ventasAnuladas      = 0;
        $totalVendidoUsd     = 0.00;
        $totalVendidoVes     = 0.00;
        $totalItemsVendidos  = 0;

        foreach ($ventas as &$v) {
            $v['id']            = (int) $v['id'];
            $v['tasa_aplicada'] = (float) $v['tasa_aplicada'];
            $v['subtotal_usd']  = (float) $v['subtotal_usd'];
            $v['impuesto_usd']  = (float) $v['impuesto_usd'];
            $v['descuento_usd'] = (float) $v['descuento_usd'];
            $v['total_usd']     = (float) $v['total_usd'];
            $v['subtotal_ves']  = (float) $v['subtotal_ves'];
            $v['impuesto_ves']  = (float) $v['impuesto_ves'];
            $v['descuento_ves'] = (float) $v['descuento_ves'];
            $v['total_ves']     = (float) $v['total_ves'];
            $v['vuelto_usd']    = (float) $v['vuelto_usd'];
            $v['vuelto_ves']    = (float) $v['vuelto_ves'];

            $detalles = $db->table('venta_detalles d')
                ->select('d.*, p.sku, p.nombre as producto_nombre, uv.codigo as unidad_codigo')
                ->join('productos p', 'p.id = d.producto_id', 'left')
                ->join('unidades_medida uv', 'uv.id = d.unidad_venta_id', 'left')
                ->where('d.venta_id', $v['id'])
                ->get()
                ->getResultArray();

            $v['detalles'] = $detalles;

            if ($v['estado'] === 'COMPLETADA') {
                $ventasCompletadas++;
                $totalVendidoUsd += $v['total_usd'];
                $totalVendidoVes += $v['total_ves'];
                $totalItemsVendidos += count($detalles);
            } else {
                $ventasAnuladas++;
            }
        }

        $cajero['id']     = (int) $cajero['id'];
        $cajero['activo'] = (int) $cajero['activo'];

        return $this->apiResponse([
            'cajero'  => $cajero,
            'resumen' => [
                'ventas_completadas'   => $ventasCompletadas,
                'ventas_anuladas'      => $ventasAnuladas,
                'total_vendido_usd'    => round($totalVendidoUsd, 2),
                'total_vendido_ves'    => round($totalVendidoVes, 2),
                'total_lineas_vendidas' => $totalItemsVendidos,
            ],
            'ventas'  => $ventas,
        ], 'Histórico de ventas del cajero obtenido.');
    }
}
