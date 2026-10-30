<?php

namespace App\Controllers\Api;

use App\Models\MovimientoInventarioModel;
use App\Models\ProductoModel;
use App\Models\TasaCambioModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProductoController extends ApiController
{
    public function index(): ResponseInterface
    {
        $search      = $this->request->getGet('q');
        $categoriaId = $this->request->getGet('categoria_id') ? (int) $this->request->getGet('categoria_id') : null;
        $soloActivos = $this->request->getGet('activos') !== null ? (bool) $this->request->getGet('activos') : false;

        $model     = new ProductoModel();
        $productos = $model->getConRelaciones($search, $categoriaId, $soloActivos);

        return $this->apiResponse($productos, 'Catálogo de productos obtenido.');
    }

    public function show($id = null): ResponseInterface
    {
        $model     = new ProductoModel();
        $productos = $model->getConRelaciones();
        $producto  = null;

        foreach ($productos as $p) {
            if ($p['id'] === (int) $id) {
                $producto = $p;
                break;
            }
        }

        if (!$producto) {
            return $this->apiError('Producto no encontrado.', 404, 'NOT_FOUND');
        }

        $kardex = \Config\Database::connect()->table('movimientos_inventario m')
            ->select('m.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = m.usuario_id', 'left')
            ->where('m.producto_id', (int) $id)
            ->orderBy('m.id', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();

        $producto['ultimos_movimientos'] = $kardex;

        return $this->apiResponse($producto, 'Detalle del producto obtenido.');
    }

    public function create(): ResponseInterface
    {
        $payload = $this->getRequestPayload();

        $sku              = strtoupper(trim((string) ($payload['sku'] ?? '')));
        $codigoBarra      = trim((string) ($payload['codigo_barra'] ?? '')) ?: null;
        $nombre           = trim((string) ($payload['nombre'] ?? ''));
        $categoriaId      = (int) ($payload['categoria_id'] ?? 0);
        $unidadCompraId   = (int) ($payload['unidad_compra_id'] ?? 0);
        $unidadVentaId    = (int) ($payload['unidad_venta_id'] ?? 0);
        $factorConversion = isset($payload['factor_conversion']) ? (float) $payload['factor_conversion'] : 1.000;
        $costoUsd         = isset($payload['costo_usd']) ? (float) $payload['costo_usd'] : 0.00;
        $precioVentaUsd   = isset($payload['precio_venta_usd']) ? (float) $payload['precio_venta_usd'] : 0.00;
        $porcentajeIva    = isset($payload['porcentaje_iva']) ? (float) $payload['porcentaje_iva'] : 16.00;
        $exentoIva        = !empty($payload['exento_iva']) ? 1 : 0;
        $stockInicial     = isset($payload['stock_actual']) ? (float) $payload['stock_actual'] : 0.000;
        $stockMinimo      = isset($payload['stock_minimo']) ? (float) $payload['stock_minimo'] : 0.000;

        if ($sku === '' || $nombre === '' || $categoriaId <= 0 || $unidadCompraId <= 0 || $unidadVentaId <= 0) {
            return $this->apiError('SKU, nombre, categoría, unidad de compra y unidad de venta son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        if ($factorConversion <= 0) {
            return $this->apiError('El factor de conversión debe ser mayor a cero.', 422, 'VALIDATION_ERROR');
        }

        if ($precioVentaUsd <= 0) {
            return $this->apiError('El precio de venta en USD debe ser mayor a cero.', 422, 'VALIDATION_ERROR');
        }

        $model = new ProductoModel();
        if ($model->where('sku', $sku)->first()) {
            return $this->apiError('Ya existe un producto con el SKU indicado.', 422, 'DUPLICATE_SKU');
        }

        $unidadVenta = (new UnidadMedidaModel())->find($unidadVentaId);
        if (!$unidadVenta) {
            return $this->apiError('La unidad de venta seleccionada no existe.', 422, 'INVALID_UNIT');
        }

        if ((int) $unidadVenta['permite_decimales'] === 0 && fmod($stockInicial, 1.0) !== 0.0) {
            return $this->apiError('La unidad de venta seleccionada (' . $unidadVenta['codigo'] . ') no permite cantidades decimales.', 422, 'DECIMALS_NOT_ALLOWED');
        }

        $auth       = $this->getAuthUser();
        $tasaActiva = (new TasaCambioModel())->getActiva();
        $tasaVes    = $tasaActiva ? (float) $tasaActiva['tasa_ves'] : 1.0000;

        $db = \Config\Database::connect();
        $db->transStart();

        $productoId = $model->insert([
            'codigo_barra'      => $codigoBarra,
            'sku'               => $sku,
            'nombre'            => $nombre,
            'categoria_id'      => $categoriaId,
            'unidad_compra_id'  => $unidadCompraId,
            'unidad_venta_id'   => $unidadVentaId,
            'factor_conversion' => round($factorConversion, 3),
            'costo_usd'         => round($costoUsd, 2),
            'precio_venta_usd'  => round($precioVentaUsd, 2),
            'porcentaje_iva'    => $exentoIva ? 0.00 : round($porcentajeIva, 2),
            'exento_iva'        => $exentoIva,
            'stock_actual'      => round($stockInicial, 3),
            'stock_minimo'      => round($stockMinimo, 3),
            'activo'            => 1,
        ], true);

        if ($stockInicial > 0) {
            $kardexModel = new MovimientoInventarioModel();
            $kardexModel->insert([
                'producto_id'        => $productoId,
                'tipo_movimiento'    => 'ENTRADA_COMPRA',
                'referencia_id'      => 0,
                'cantidad'           => round($stockInicial, 3),
                'stock_anterior'     => 0.000,
                'stock_resultante'   => round($stockInicial, 3),
                'costo_unitario_usd' => round($costoUsd, 2),
                'costo_unitario_ves' => round($costoUsd * $tasaVes, 2),
                'tasa_aplicada'      => $tasaVes,
                'observacion'        => 'Inventario inicial al crear producto',
                'usuario_id'         => $auth['uid'] ?? 1,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error al registrar el producto en la base de datos.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->show($productoId)->setStatusCode(201);
    }

    public function update($id = null): ResponseInterface
    {
        $model = new ProductoModel();
        $prod  = $model->find($id);

        if (!$prod) {
            return $this->apiError('Producto no encontrado.', 404, 'NOT_FOUND');
        }

        $payload          = $this->getRequestPayload();
        $sku              = strtoupper(trim((string) ($payload['sku'] ?? $prod['sku'])));
        $codigoBarra      = array_key_exists('codigo_barra', $payload) ? (trim((string) $payload['codigo_barra']) ?: null) : $prod['codigo_barra'];
        $nombre           = trim((string) ($payload['nombre'] ?? $prod['nombre']));
        $categoriaId      = (int) ($payload['categoria_id'] ?? $prod['categoria_id']);
        $unidadCompraId   = (int) ($payload['unidad_compra_id'] ?? $prod['unidad_compra_id']);
        $unidadVentaId    = (int) ($payload['unidad_venta_id'] ?? $prod['unidad_venta_id']);
        $factorConversion = isset($payload['factor_conversion']) ? (float) $payload['factor_conversion'] : (float) $prod['factor_conversion'];
        $costoUsd         = isset($payload['costo_usd']) ? (float) $payload['costo_usd'] : (float) $prod['costo_usd'];
        $precioVentaUsd   = isset($payload['precio_venta_usd']) ? (float) $payload['precio_venta_usd'] : (float) $prod['precio_venta_usd'];
        $exentoIva        = isset($payload['exento_iva']) ? (int) (bool) $payload['exento_iva'] : (int) $prod['exento_iva'];
        $porcentajeIva    = isset($payload['porcentaje_iva']) ? (float) $payload['porcentaje_iva'] : (float) $prod['porcentaje_iva'];
        $stockMinimo      = isset($payload['stock_minimo']) ? (float) $payload['stock_minimo'] : (float) $prod['stock_minimo'];
        $activo           = isset($payload['activo']) ? (int) (bool) $payload['activo'] : (int) $prod['activo'];

        if ($sku === '' || $nombre === '') {
            return $this->apiError('SKU y nombre son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        if ($factorConversion <= 0 || $precioVentaUsd <= 0) {
            return $this->apiError('El factor de conversión y el precio de venta USD deben ser mayores a cero.', 422, 'VALIDATION_ERROR');
        }

        $dup = $model->where('sku', $sku)->where('id !=', $id)->first();
        if ($dup) {
            return $this->apiError('Otro producto ya utiliza ese SKU.', 422, 'DUPLICATE_SKU');
        }

        // Nota: stock_actual NO se modifica directamente por update para garantizar el Kardex Inmutable
        $model->update($id, [
            'codigo_barra'      => $codigoBarra,
            'sku'               => $sku,
            'nombre'            => $nombre,
            'categoria_id'      => $categoriaId,
            'unidad_compra_id'  => $unidadCompraId,
            'unidad_venta_id'   => $unidadVentaId,
            'factor_conversion' => round($factorConversion, 3),
            'costo_usd'         => round($costoUsd, 2),
            'precio_venta_usd'  => round($precioVentaUsd, 2),
            'porcentaje_iva'    => $exentoIva ? 0.00 : round($porcentajeIva, 2),
            'exento_iva'        => $exentoIva,
            'stock_minimo'      => round($stockMinimo, 3),
            'activo'            => $activo,
        ]);

        return $this->show($id);
    }

    public function delete($id = null): ResponseInterface
    {
        $model = new ProductoModel();
        $prod  = $model->find($id);

        if (!$prod) {
            return $this->apiError('Producto no encontrado.', 404, 'NOT_FOUND');
        }

        $model->update($id, ['activo' => 0]);

        return $this->apiResponse(['id' => (int) $id, 'activo' => 0], 'Producto desactivado exitosamente.');
    }
}
