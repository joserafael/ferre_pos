<?php

namespace App\Controllers\Api;

use App\Models\MetodoPagoModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CatalogoController extends ApiController
{
    public function unidades(): ResponseInterface
    {
        $model = new UnidadMedidaModel();
        $rows  = $model->orderBy('id', 'ASC')->findAll();
        foreach ($rows as &$r) {
            $r['id']                = (int) $r['id'];
            $r['permite_decimales'] = (int) $r['permite_decimales'];
        }

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

        return $this->apiResponse($model->find($id), 'Unidad de medida registrada.', 201);
    }

    public function metodosPago(): ResponseInterface
    {
        $model = new MetodoPagoModel();
        $rows  = $model->where('activo', 1)->orderBy('id', 'ASC')->findAll();
        foreach ($rows as &$r) {
            $r['id']                  = (int) $r['id'];
            $r['requiere_referencia'] = (int) $r['requiere_referencia'];
            $r['activo']              = (int) $r['activo'];
        }

        return $this->apiResponse($rows, 'Métodos de pago obtenidos.');
    }
}
