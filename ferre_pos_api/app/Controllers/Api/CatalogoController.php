<?php

namespace App\Controllers\Api;

use App\Libraries\RedisService;
use App\Models\MetodoPagoModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CatalogoController extends ApiController
{
    public function unidades(): ResponseInterface
    {
        $cached = RedisService::getCached('unidades_medida');
        if ($cached !== null) {
            return $this->apiResponse($cached, 'Unidades de medida obtenidas (caché).');
        }

        $model = new UnidadMedidaModel();
        $rows  = $model->orderBy('id', 'ASC')->findAll();
        foreach ($rows as &$r) {
            $r['id']                = (int) $r['id'];
            $r['permite_decimales'] = (int) $r['permite_decimales'];
        }

        RedisService::setCached('unidades_medida', $rows, 3600);

        return $this->apiResponse($rows, 'Unidades de medida obtenidas.');
    }

    public function crearUnidad(): ResponseInterface
    {
        $payload          = $this->getRequestPayload();
        $codigo           = strtoupper(trim((string) ($payload['codigo'] ?? '')));
        $nombre           = trim((string) ($payload['nombre'] ?? ''));
        $permiteDecimales = isset($payload['permite_decimales']) ? (int) (bool) $payload['permite_decimales'] : 0;

        if ($codigo === '' || $nombre === '') {
            return $this->apiError('Código y nombre de la unidad son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        $model = new UnidadMedidaModel();
        if ($model->where('codigo', $codigo)->first()) {
            return $this->apiError('Ya existe una unidad de medida con ese código.', 422, 'DUPLICATE_UNIT');
        }

        $id = $model->insert([
            'codigo'            => $codigo,
            'nombre'            => $nombre,
            'permite_decimales' => $permiteDecimales,
            'created_at'        => date('Y-m-d H:i:s'),
        ], true);

        RedisService::invalidateCatalog();

        return $this->apiResponse($model->find($id), 'Unidad de medida registrada.', 201);
    }

    public function metodosPago(): ResponseInterface
    {
        $cached = RedisService::getCached('metodos_pago');
        if ($cached !== null) {
            return $this->apiResponse($cached, 'Métodos de pago obtenidos (caché).');
        }

        $model = new MetodoPagoModel();
        $rows  = $model->where('activo', 1)->orderBy('id', 'ASC')->findAll();
        foreach ($rows as &$r) {
            $r['id']                  = (int) $r['id'];
            $r['requiere_referencia'] = (int) $r['requiere_referencia'];
            $r['activo']              = (int) $r['activo'];
        }

        RedisService::setCached('metodos_pago', $rows, 3600);

        return $this->apiResponse($rows, 'Métodos de pago obtenidos.');
    }
}
