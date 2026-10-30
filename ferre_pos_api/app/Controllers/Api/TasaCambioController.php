<?php

namespace App\Controllers\Api;

use App\Models\TasaCambioModel;
use CodeIgniter\HTTP\ResponseInterface;

class TasaCambioController extends ApiController
{
    public function index(): ResponseInterface
    {
        $model     = new TasaCambioModel();
        $historial = $model->db->table('tasas_cambio t')
            ->select('t.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = t.usuario_id', 'left')
            ->orderBy('t.id', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        foreach ($historial as &$row) {
            $row['id']       = (int) $row['id'];
            $row['tasa_ves'] = (float) $row['tasa_ves'];
            $row['activa']   = (int) $row['activa'];
        }

        return $this->apiResponse([
            'activa'    => $model->getActiva(),
            'historial' => $historial,
        ], 'Tasas de cambio obtenidas.');
    }

    public function activa(): ResponseInterface
    {
        $model  = new TasaCambioModel();
        $activa = $model->getActiva();

        if (!$activa) {
            return $this->apiError('No existe una tasa de cambio activa configurada.', 404, 'NO_ACTIVE_RATE');
        }

        return $this->apiResponse($activa, 'Tasa activa obtenida.');
    }

    public function create(): ResponseInterface
    {
        $payload = $this->getRequestPayload();
        $tasaVes = isset($payload['tasa_ves']) ? (float) $payload['tasa_ves'] : 0;
        $fuente  = trim((string) ($payload['fuente'] ?? 'BCV Oficial'));

        if ($tasaVes <= 0) {
            return $this->apiError('La tasa de cambio en Bolívares debe ser mayor a cero.', 422, 'VALIDATION_ERROR');
        }

        $auth = $this->getAuthUser();
        $db   = \Config\Database::connect();

        $db->transStart();

        $db->table('tasas_cambio')->update(['activa' => 0]);

        $model = new TasaCambioModel();
        $id    = $model->insert([
            'tasa_ves'   => round($tasaVes, 4),
            'fuente'     => $fuente !== '' ? $fuente : 'BCV Oficial',
            'activa'     => 1,
            'usuario_id' => $auth['uid'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error al registrar la nueva tasa de cambio.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->apiResponse($model->getActiva(), 'Tasa de cambio actualizada exitosamente.', 201);
    }
}
