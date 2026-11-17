<?php

namespace App\Controllers\Api;

use App\Models\CajaSesionModel;
use App\Models\TasaCambioModel;
use CodeIgniter\HTTP\ResponseInterface;

class CajaController extends ApiController
{
    /**
     * Retorna la sesión de caja abierta del usuario autenticado (y su resumen en vivo),
     * o null si la caja se encuentra cerrada.
     */
    public function actual(): ResponseInterface
    {
        $auth   = $this->getAuthUser();
        $uid    = (int) ($auth['uid'] ?? 0);
        $model  = new CajaSesionModel();
        $sesion = $model->getSesionAbierta($uid);

        if (!$sesion) {
            return $this->apiResponse([
                'abierta' => false,
                'sesion'  => null,
                'resumen' => null,
            ], 'No hay una sesión de caja abierta actualmente.');
        }

        $resumen = $model->calcularResumenSesion($sesion);

        return $this->apiResponse([
            'abierta' => true,
            'sesion'  => $sesion,
            'resumen' => $resumen,
        ], 'Sesión de caja abierta obtenida.');
    }

    /**
     * Abre una nueva sesión de caja para el cajero/admin autenticado.
     */
    public function abrir(): ResponseInterface
    {
        $auth  = $this->getAuthUser();
        $uid   = (int) ($auth['uid'] ?? 0);
        $model = new CajaSesionModel();

        if ($model->getSesionAbierta($uid)) {
            return $this->apiError(
                'Ya posee una sesión de caja abierta. Debe cerrarla antes de abrir una nueva.',
                409,
                'CASH_REGISTER_ALREADY_OPEN'
            );
        }

        $payload       = $this->getRequestPayload();
        $aperturaUsd   = max(0.00, round((float) ($payload['monto_apertura_usd'] ?? 0.00), 2));
        $aperturaVes   = max(0.00, round((float) ($payload['monto_apertura_ves'] ?? 0.00), 2));
        $observacion   = trim((string) ($payload['observacion_apertura'] ?? ''));

        $tasaActiva = (new TasaCambioModel())->getActiva();
        if (!$tasaActiva) {
            return $this->apiError('No existe una tasa de cambio activa para abrir la caja.', 422, 'NO_ACTIVE_RATE');
        }

        $id = $model->insert([
            'usuario_id'            => $uid,
            'estado'                => 'ABIERTA',
            'monto_apertura_usd'    => $aperturaUsd,
            'monto_apertura_ves'    => $aperturaVes,
            'tasa_apertura'         => round((float) $tasaActiva['tasa_ves'], 4),
            'observacion_apertura'  => $observacion !== '' ? $observacion : null,
            'abierta_at'            => date('Y-m-d H:i:s'),
        ], true);

        $sesion  = $model->formatearSesion($model->find($id));
        $resumen = $model->calcularResumenSesion($sesion);

        return $this->apiResponse([
            'abierta' => true,
            'sesion'  => $sesion,
            'resumen' => $resumen,
        ], 'Caja abierta exitosamente.', 201);
    }

    /**
     * Realiza el arqueo bimonetario y cierra la sesión de caja abierta del usuario.
     */
    public function cerrar(): ResponseInterface
    {
        $auth  = $this->getAuthUser();
        $uid   = (int) ($auth['uid'] ?? 0);
        $model = new CajaSesionModel();

        $sesion = $model->getSesionAbierta($uid);
        if (!$sesion) {
            return $this->apiError('No tiene ninguna sesión de caja abierta para cerrar.', 404, 'NO_OPEN_CASH_REGISTER');
        }

        $payload    = $this->getRequestPayload();
        $contadoUsd = max(0.00, round((float) ($payload['contado_efectivo_usd'] ?? 0.00), 2));
        $contadoVes = max(0.00, round((float) ($payload['contado_efectivo_ves'] ?? 0.00), 2));
        $obsCierre  = trim((string) ($payload['observacion_cierre'] ?? ''));

        $db = \Config\Database::connect();
        $db->transStart();

        $resumen = $model->calcularResumenSesion($sesion);

        $diferenciaUsd = round($contadoUsd - $resumen['esperado_efectivo_usd'], 2);
        $diferenciaVes = round($contadoVes - $resumen['esperado_efectivo_ves'], 2);

        $model->update($sesion['id'], [
            'estado'                => 'CERRADA',
            'ventas_cantidad'       => $resumen['ventas_cantidad'],
            'total_ventas_usd'      => $resumen['total_ventas_usd'],
            'total_ventas_ves'      => $resumen['total_ventas_ves'],
            'esperado_efectivo_usd' => $resumen['esperado_efectivo_usd'],
            'esperado_efectivo_ves' => $resumen['esperado_efectivo_ves'],
            'contado_efectivo_usd'  => $contadoUsd,
            'contado_efectivo_ves'  => $contadoVes,
            'diferencia_usd'        => $diferenciaUsd,
            'diferencia_ves'        => $diferenciaVes,
            'desglose_metodos_json' => json_encode($resumen['desglose_metodos']),
            'observacion_cierre'    => $obsCierre !== '' ? $obsCierre : null,
            'cerrada_at'            => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error al procesar el cierre de caja.', 500, 'DB_TRANSACTION_ERROR');
        }

        $cerrada = $model->formatearSesion($model->find($sesion['id']));

        return $this->apiResponse([
            'abierta' => false,
            'sesion'  => $cerrada,
            'resumen' => $resumen,
        ], 'Caja cerrada y arqueo registrado exitosamente.');
    }

    /**
     * Historial paginado de sesiones de caja.
     * CAJERO ve únicamente sus sesiones; ADMIN puede ver todas.
     */
    public function historial(): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $uid       = (int) ($auth['uid'] ?? 0);
        $esCajero  = strtoupper((string) ($auth['rol'] ?? '')) === 'CAJERO';
        $estado    = strtoupper(trim((string) ($this->request->getGet('estado') ?? '')));
        $search    = trim((string) ($this->request->getGet('q') ?? ''));
        $usuarioId = $this->request->getGet('usuario_id') ? (int) $this->request->getGet('usuario_id') : null;

        $db    = \Config\Database::connect();
        $model = new CajaSesionModel();

        $crearBuilder = static function () use ($db, $esCajero, $uid, $usuarioId, $estado, $search) {
            $b = $db->table('caja_sesiones cs')
                ->select('cs.*, u.nombre as usuario_nombre, u.username as usuario_username')
                ->join('usuarios u', 'u.id = cs.usuario_id', 'left');

            if ($esCajero) {
                $b->where('cs.usuario_id', $uid);
            } elseif ($usuarioId) {
                $b->where('cs.usuario_id', $usuarioId);
            }

            if ($estado !== '' && in_array($estado, ['ABIERTA', 'CERRADA'], true)) {
                $b->where('cs.estado', $estado);
            }

            if ($search !== '') {
                $b->groupStart()
                    ->like('u.nombre', $search)
                    ->orLike('u.username', $search)
                    ->orLike('cs.observacion_apertura', $search)
                    ->orLike('cs.observacion_cierre', $search)
                    ->groupEnd();
            }

            return $b;
        };

        $paginar = $this->wantsPagination();
        if ($paginar) {
            [$page, $perPage] = $this->getPaginationParams(15);
            $total            = (int) $crearBuilder()->countAllResults();
            $meta             = $this->buildPaginationMeta($total, $page, $perPage);
            $offset           = ($meta['current_page'] - 1) * $perPage;
            $rows             = $crearBuilder()
                ->orderBy('cs.id', 'DESC')
                ->limit($perPage, $offset)
                ->get()
                ->getResultArray();
        } else {
            $rows = $crearBuilder()
                ->orderBy('cs.id', 'DESC')
                ->limit(100)
                ->get()
                ->getResultArray();
        }

        foreach ($rows as &$r) {
            $r = $model->formatearSesion($r);
        }

        if ($paginar) {
            return $this->apiResponse([
                'items'      => $rows,
                'pagination' => $meta,
            ], 'Historial de sesiones de caja obtenido.');
        }

        return $this->apiResponse($rows, 'Historial de sesiones de caja obtenido.');
    }

    public function show($id = null): ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('caja_sesiones cs')
            ->select('cs.*, u.nombre as usuario_nombre, u.username as usuario_username')
            ->join('usuarios u', 'u.id = cs.usuario_id', 'left')
            ->where('cs.id', (int) $id)
            ->get()
            ->getRowArray();

        if (!$row) {
            return $this->apiError('Sesión de caja no encontrada.', 404, 'NOT_FOUND');
        }

        $auth = $this->getAuthUser();
        if (strtoupper((string) ($auth['rol'] ?? '')) === 'CAJERO' && (int) $row['usuario_id'] !== (int) ($auth['uid'] ?? 0)) {
            return $this->apiError('No tiene permiso para consultar cierres de caja de otro usuario.', 403, 'FORBIDDEN');
        }

        $model  = new CajaSesionModel();
        $sesion = $model->formatearSesion($row);

        // Si sigue abierta, recalculamos resumen en vivo
        if ($sesion['estado'] === 'ABIERTA') {
            $resumen = $model->calcularResumenSesion($sesion);
            $sesion['resumen_vivo'] = $resumen;
        }

        return $this->apiResponse($sesion, 'Detalle de sesión de caja obtenido.');
    }
}
