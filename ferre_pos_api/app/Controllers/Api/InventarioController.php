<?php

namespace App\Controllers\Api;

use App\Models\EntradaDetalleModel;
use App\Models\EntradaInventarioModel;
use App\Models\MovimientoInventarioModel;
use App\Models\ProductoModel;
use App\Models\TasaCambioModel;
use App\Models\UnidadMedidaModel;
use CodeIgniter\HTTP\ResponseInterface;

class InventarioController extends ApiController
{
    public function entradas(): ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('entradas_inventario e')
            ->select('e.*, u.nombre as usuario_nombre, COUNT(d.id) as total_items')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->join('entrada_detalles d', 'd.entrada_id = e.id', 'left')
            ->groupBy('e.id')
            ->orderBy('e.id', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();

        foreach ($rows as &$r) {
            $r['id']            = (int) $r['id'];
            $r['tasa_aplicada'] = (float) $r['tasa_aplicada'];
            $r['total_usd']     = (float) $r['total_usd'];
            $r['total_ves']     = (float) $r['total_ves'];
            $r['total_items']   = (int) $r['total_items'];
        }

        return $this->apiResponse($rows, 'Entradas de inventario obtenidas.');
    }

    public function showEntrada($id = null): ResponseInterface
    {
        $db      = \Config\Database::connect();
        $entrada = $db->table('entradas_inventario e')
            ->select('e.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->where('e.id', (int) $id)
            ->get()
            ->getRowArray();

        if (!$entrada) {
            return $this->apiError('Entrada de inventario no encontrada.', 404, 'NOT_FOUND');
        }

        $detalles = $db->table('entrada_detalles d')
            ->select('d.*, p.sku, p.nombre as producto_nombre, uc.codigo as unidad_compra_codigo, uv.codigo as unidad_venta_codigo')
            ->join('productos p', 'p.id = d.producto_id', 'left')
            ->join('unidades_medida uc', 'uc.id = d.unidad_compra_id', 'left')
            ->join('unidades_medida uv', 'uv.id = p.unidad_venta_id', 'left')
            ->where('d.entrada_id', (int) $id)
            ->get()
            ->getResultArray();

        $entrada['detalles'] = $detalles;

        return $this->apiResponse($entrada, 'Detalle de entrada obtenido.');
    }

    public function crearEntrada(): ResponseInterface
    {
        $payload         = $this->getRequestPayload();
        $numeroDocumento = trim((string) ($payload['numero_documento'] ?? ''));
        $proveedorNombre = trim((string) ($payload['proveedor_nombre'] ?? ''));
        $monedaIngreso   = strtoupper(trim((string) ($payload['moneda_ingreso'] ?? 'USD')));
        $observacion     = trim((string) ($payload['observacion'] ?? '')) ?: null;
        $items           = $payload['items'] ?? [];

        if ($numeroDocumento === '' || $proveedorNombre === '') {
            return $this->apiError('Número de documento y nombre del proveedor son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        if (!in_array($monedaIngreso, ['USD', 'VES'], true)) {
            return $this->apiError('La moneda de ingreso debe ser USD o VES.', 422, 'VALIDATION_ERROR');
        }

        if (!is_array($items) || empty($items)) {
            return $this->apiError('Debe incluir al menos un producto en la entrada de inventario.', 422, 'EMPTY_ITEMS');
        }

        $tasaModel  = new TasaCambioModel();
        $tasaActiva = $tasaModel->getActiva();
        if (!$tasaActiva) {
            return $this->apiError('No hay una tasa de cambio activa configurada.', 422, 'NO_ACTIVE_RATE');
        }

        $tasaAplicada = isset($payload['tasa_aplicada']) && (float) $payload['tasa_aplicada'] > 0
            ? round((float) $payload['tasa_aplicada'], 4)
            : (float) $tasaActiva['tasa_ves'];

        $auth          = $this->getAuthUser();
        $productoModel = new ProductoModel();
        $entradaModel  = new EntradaInventarioModel();
        $detalleModel  = new EntradaDetalleModel();
        $kardexModel   = new MovimientoInventarioModel();
        $now           = date('Y-m-d H:i:s');

        $totalUsd          = 0.00;
        $totalVes          = 0.00;
        $lineasProcesadas  = [];

        foreach ($items as $idx => $item) {
            $productoId       = (int) ($item['producto_id'] ?? 0);
            $cantidadComprada = isset($item['cantidad_comprada']) ? (float) $item['cantidad_comprada'] : (float) ($item['cantidad'] ?? 0);
            $costoIngresado   = isset($item['costo_unitario']) ? (float) $item['costo_unitario'] : 0.00;
            $nuevoPrecioUsd   = isset($item['precio_venta_usd']) && (float) $item['precio_venta_usd'] > 0
                ? round((float) $item['precio_venta_usd'], 2)
                : null;

            if ($productoId <= 0 || $cantidadComprada <= 0 || $costoIngresado <= 0) {
                return $this->apiError("Ítem #" . ($idx + 1) . ": producto, cantidad comprada y costo unitario deben ser mayores a cero.", 422, 'VALIDATION_ERROR');
            }

            $prod = $productoModel->find($productoId);
            if (!$prod) {
                return $this->apiError("Producto ID {$productoId} no encontrado.", 404, 'PRODUCT_NOT_FOUND');
            }

            $usarFactor = isset($item['aplicar_factor_conversion']) ? (bool) $item['aplicar_factor_conversion'] : true;
            $factor     = $usarFactor ? max(0.001, (float) $prod['factor_conversion']) : 1.000;

            $cantidadIngresadaStock = round($cantidadComprada * $factor, 3);

            if ($monedaIngreso === 'USD') {
                $costoCompraUnitarioUsd = round($costoIngresado, 2);
                $costoCompraUnitarioVes = round($costoIngresado * $tasaAplicada, 2);
            } else {
                $costoCompraUnitarioVes = round($costoIngresado, 2);
                $costoCompraUnitarioUsd = round($costoIngresado / $tasaAplicada, 2);
            }

            $subtotalItemUsd = round($cantidadComprada * $costoCompraUnitarioUsd, 2);
            $subtotalItemVes = round($cantidadComprada * $costoCompraUnitarioVes, 2);

            // Costo unitario expresado en unidad de venta para el Kardex y producto
            $costoPorUnidadVentaUsd = round($costoCompraUnitarioUsd / $factor, 2);
            $costoPorUnidadVentaVes = round($costoPorUnidadVentaUsd * $tasaAplicada, 2);

            $totalUsd += $subtotalItemUsd;
            $totalVes += $subtotalItemVes;

            $lineasProcesadas[] = [
                'producto'                   => $prod,
                'unidad_compra_id'           => (int) $prod['unidad_compra_id'],
                'cantidad_comprada'          => round($cantidadComprada, 3),
                'factor_conversion_aplicado' => round($factor, 3),
                'cantidad_ingresada_stock'   => $cantidadIngresadaStock,
                'costo_compra_unitario_usd'  => $costoCompraUnitarioUsd,
                'costo_compra_unitario_ves'  => $costoCompraUnitarioVes,
                'costo_unidad_venta_usd'     => $costoPorUnidadVentaUsd,
                'costo_unidad_venta_ves'     => $costoPorUnidadVentaVes,
                'subtotal_usd'               => $subtotalItemUsd,
                'subtotal_ves'               => $subtotalItemVes,
                'nuevo_precio_venta_usd'     => $nuevoPrecioUsd,
            ];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $entradaId = $entradaModel->insert([
            'numero_documento' => $numeroDocumento,
            'proveedor_nombre' => $proveedorNombre,
            'moneda_ingreso'   => $monedaIngreso,
            'tasa_cambio_id'   => (int) $tasaActiva['id'],
            'tasa_aplicada'    => $tasaAplicada,
            'total_usd'        => round($totalUsd, 2),
            'total_ves'        => round($totalVes, 2),
            'observacion'      => $observacion,
            'usuario_id'       => $auth['uid'] ?? 1,
            'created_at'       => $now,
        ], true);

        foreach ($lineasProcesadas as $linea) {
            $prod            = $linea['producto'];
            $stockAnterior   = (float) $prod['stock_actual'];
            $stockResultante = round($stockAnterior + $linea['cantidad_ingresada_stock'], 3);

            $detalleModel->insert([
                'entrada_id'                 => $entradaId,
                'producto_id'                => (int) $prod['id'],
                'unidad_compra_id'           => $linea['unidad_compra_id'],
                'cantidad_comprada'          => $linea['cantidad_comprada'],
                'factor_conversion_aplicado' => $linea['factor_conversion_aplicado'],
                'cantidad_ingresada_stock'   => $linea['cantidad_ingresada_stock'],
                'costo_compra_unitario_usd'  => $linea['costo_compra_unitario_usd'],
                'costo_compra_unitario_ves'  => $linea['costo_compra_unitario_ves'],
                'subtotal_usd'               => $linea['subtotal_usd'],
                'subtotal_ves'               => $linea['subtotal_ves'],
            ]);

            $updateProd = [
                'stock_actual' => $stockResultante,
                'costo_usd'    => $linea['costo_unidad_venta_usd'] > 0 ? $linea['costo_unidad_venta_usd'] : $prod['costo_usd'],
            ];
            if ($linea['nuevo_precio_venta_usd'] !== null) {
                $updateProd['precio_venta_usd'] = $linea['nuevo_precio_venta_usd'];
            }
            $productoModel->update((int) $prod['id'], $updateProd);

            $kardexModel->insert([
                'producto_id'        => (int) $prod['id'],
                'tipo_movimiento'    => 'ENTRADA_COMPRA',
                'referencia_id'      => $entradaId,
                'cantidad'           => $linea['cantidad_ingresada_stock'],
                'stock_anterior'     => $stockAnterior,
                'stock_resultante'   => $stockResultante,
                'costo_unitario_usd' => $linea['costo_unidad_venta_usd'],
                'costo_unitario_ves' => $linea['costo_unidad_venta_ves'],
                'tasa_aplicada'      => $tasaAplicada,
                'observacion'        => "Compra Doc #{$numeroDocumento} ({$proveedorNombre}) - {$linea['cantidad_comprada']} u. compra x factor {$linea['factor_conversion_aplicado']}",
                'usuario_id'         => $auth['uid'] ?? 1,
                'created_at'         => $now,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error en la transacción al registrar la entrada de inventario.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->showEntrada($entradaId)->setStatusCode(201);
    }

    public function crearAjuste(): ResponseInterface
    {
        $payload        = $this->getRequestPayload();
        $productoId     = (int) ($payload['producto_id'] ?? 0);
        $tipoMovimiento = strtoupper(trim((string) ($payload['tipo_movimiento'] ?? '')));
        $cantidad       = isset($payload['cantidad']) ? (float) $payload['cantidad'] : 0.000;
        $observacion    = trim((string) ($payload['observacion'] ?? ''));

        if ($productoId <= 0 || $cantidad <= 0 || $observacion === '') {
            return $this->apiError('Producto, cantidad mayor a cero y observación/motivo son obligatorios.', 422, 'VALIDATION_ERROR');
        }

        if (!in_array($tipoMovimiento, ['AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO'], true)) {
            return $this->apiError('El tipo de ajuste debe ser AJUSTE_POSITIVO o AJUSTE_NEGATIVO.', 422, 'INVALID_MOVEMENT_TYPE');
        }

        $productoModel = new ProductoModel();
        $prod          = $productoModel->find($productoId);
        if (!$prod) {
            return $this->apiError('Producto no encontrado.', 404, 'NOT_FOUND');
        }

        $unidadVenta = (new UnidadMedidaModel())->find((int) $prod['unidad_venta_id']);
        if ($unidadVenta && (int) $unidadVenta['permite_decimales'] === 0 && fmod($cantidad, 1.0) !== 0.0) {
            return $this->apiError("El producto se maneja en {$unidadVenta['codigo']} y no admite cantidades decimales.", 422, 'DECIMALS_NOT_ALLOWED');
        }

        $stockAnterior = (float) $prod['stock_actual'];
        if ($tipoMovimiento === 'AJUSTE_NEGATIVO' && $cantidad > $stockAnterior) {
            return $this->apiError("Stock insuficiente para realizar el ajuste negativo. Stock actual: {$stockAnterior}.", 422, 'INSUFFICIENT_STOCK');
        }

        $stockResultante = $tipoMovimiento === 'AJUSTE_POSITIVO'
            ? round($stockAnterior + $cantidad, 3)
            : round($stockAnterior - $cantidad, 3);

        $tasaActiva = (new TasaCambioModel())->getActiva();
        $tasaVes    = $tasaActiva ? (float) $tasaActiva['tasa_ves'] : 1.0000;
        $auth       = $this->getAuthUser();

        $db = \Config\Database::connect();
        $db->transStart();

        $productoModel->update($productoId, [
            'stock_actual' => $stockResultante,
        ]);

        $kardexModel  = new MovimientoInventarioModel();
        $movimientoId = $kardexModel->insert([
            'producto_id'        => $productoId,
            'tipo_movimiento'    => $tipoMovimiento,
            'referencia_id'      => null,
            'cantidad'           => round($cantidad, 3),
            'stock_anterior'     => $stockAnterior,
            'stock_resultante'   => $stockResultante,
            'costo_unitario_usd' => (float) $prod['costo_usd'],
            'costo_unitario_ves' => round((float) $prod['costo_usd'] * $tasaVes, 2),
            'tasa_aplicada'      => $tasaVes,
            'observacion'        => $observacion,
            'usuario_id'         => $auth['uid'] ?? 1,
            'created_at'         => date('Y-m-d H:i:s'),
        ], true);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error al procesar el ajuste de inventario.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->apiResponse($kardexModel->find($movimientoId), 'Ajuste de inventario registrado en el Kardex.', 201);
    }

    public function kardex(): ResponseInterface
    {
        $productoId     = $this->request->getGet('producto_id') ? (int) $this->request->getGet('producto_id') : null;
        $tipoMovimiento = $this->request->getGet('tipo_movimiento');
        $limit          = min(250, max(10, (int) ($this->request->getGet('limit') ?: 100)));

        $builder = \Config\Database::connect()->table('movimientos_inventario m')
            ->select('m.*, p.sku, p.nombre as producto_nombre, uv.codigo as unidad_venta_codigo, u.nombre as usuario_nombre')
            ->join('productos p', 'p.id = m.producto_id', 'left')
            ->join('unidades_medida uv', 'uv.id = p.unidad_venta_id', 'left')
            ->join('usuarios u', 'u.id = m.usuario_id', 'left')
            ->orderBy('m.id', 'DESC')
            ->limit($limit);

        if ($productoId) {
            $builder->where('m.producto_id', $productoId);
        }

        if (!empty($tipoMovimiento)) {
            $builder->where('m.tipo_movimiento', $tipoMovimiento);
        }

        $rows = $builder->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['id']                 = (int) $r['id'];
            $r['producto_id']        = (int) $r['producto_id'];
            $r['cantidad']           = (float) $r['cantidad'];
            $r['stock_anterior']     = (float) $r['stock_anterior'];
            $r['stock_resultante']   = (float) $r['stock_resultante'];
            $r['costo_unitario_usd'] = (float) $r['costo_unitario_usd'];
            $r['costo_unitario_ves'] = (float) $r['costo_unitario_ves'];
            $r['tasa_aplicada']      = (float) $r['tasa_aplicada'];
        }

        return $this->apiResponse($rows, 'Movimientos del Kardex obtenidos.');
    }
}
