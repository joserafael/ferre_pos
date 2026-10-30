<?php

namespace App\Controllers\Api;

use App\Models\MetodoPagoModel;
use App\Models\MovimientoInventarioModel;
use App\Models\ProductoModel;
use App\Models\TasaCambioModel;
use App\Models\UnidadMedidaModel;
use App\Models\VentaDetalleModel;
use App\Models\VentaModel;
use App\Models\VentaPagoModel;
use CodeIgniter\HTTP\ResponseInterface;

class VentaController extends ApiController
{
    public function index(): ResponseInterface
    {
        $estado = $this->request->getGet('estado');
        $q      = $this->request->getGet('q');

        $db      = \Config\Database::connect();
        $builder = $db->table('ventas v')
            ->select('v.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = v.usuario_id', 'left')
            ->orderBy('v.id', 'DESC')
            ->limit(100);

        if (!empty($estado)) {
            $builder->where('v.estado', strtoupper($estado));
        }

        if (!empty($q)) {
            $builder->groupStart()
                ->like('v.numero_factura', $q)
                ->orLike('v.cliente_documento', $q)
                ->orLike('v.cliente_nombre', $q)
                ->groupEnd();
        }

        $ventas = $builder->get()->getResultArray();

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
        }

        return $this->apiResponse($ventas, 'Listado de ventas obtenido.');
    }

    public function show($id = null): ResponseInterface
    {
        $db    = \Config\Database::connect();
        $venta = $db->table('ventas v')
            ->select('v.*, u.nombre as usuario_nombre')
            ->join('usuarios u', 'u.id = v.usuario_id', 'left')
            ->where('v.id', (int) $id)
            ->get()
            ->getRowArray();

        if (!$venta) {
            return $this->apiError('Factura no encontrada.', 404, 'NOT_FOUND');
        }

        $detalles = $db->table('venta_detalles d')
            ->select('d.*, p.sku, p.codigo_barra, p.nombre as producto_nombre, uv.codigo as unidad_codigo, uv.nombre as unidad_nombre')
            ->join('productos p', 'p.id = d.producto_id', 'left')
            ->join('unidades_medida uv', 'uv.id = d.unidad_venta_id', 'left')
            ->where('d.venta_id', (int) $id)
            ->get()
            ->getResultArray();

        $pagos = $db->table('venta_pagos vp')
            ->select('vp.*, mp.codigo as metodo_codigo, mp.nombre as metodo_nombre')
            ->join('metodos_pago mp', 'mp.id = vp.metodo_pago_id', 'left')
            ->where('vp.venta_id', (int) $id)
            ->get()
            ->getResultArray();

        $venta['id']            = (int) $venta['id'];
        $venta['tasa_aplicada'] = (float) $venta['tasa_aplicada'];
        $venta['subtotal_usd']  = (float) $venta['subtotal_usd'];
        $venta['impuesto_usd']  = (float) $venta['impuesto_usd'];
        $venta['descuento_usd'] = (float) $venta['descuento_usd'];
        $venta['total_usd']     = (float) $venta['total_usd'];
        $venta['subtotal_ves']  = (float) $venta['subtotal_ves'];
        $venta['impuesto_ves']  = (float) $venta['impuesto_ves'];
        $venta['descuento_ves'] = (float) $venta['descuento_ves'];
        $venta['total_ves']     = (float) $venta['total_ves'];
        $venta['vuelto_usd']    = (float) $venta['vuelto_usd'];
        $venta['vuelto_ves']    = (float) $venta['vuelto_ves'];
        $venta['detalles']      = $detalles;
        $venta['pagos']         = $pagos;

        return $this->apiResponse($venta, 'Detalle de factura obtenido.');
    }

    public function create(): ResponseInterface
    {
        $payload          = $this->getRequestPayload();
        $clienteDocumento = strtoupper(trim((string) ($payload['cliente_documento'] ?? 'V-00000000')));
        $clienteNombre    = trim((string) ($payload['cliente_nombre'] ?? 'CONSUMIDOR FINAL'));
        $descuentoUsd     = max(0.00, round((float) ($payload['descuento_usd'] ?? 0.00), 2));
        $vueltoUsd        = max(0.00, round((float) ($payload['vuelto_usd'] ?? 0.00), 2));
        $vueltoVes        = max(0.00, round((float) ($payload['vuelto_ves'] ?? 0.00), 2));
        $items            = $payload['items'] ?? [];
        $pagos            = $payload['pagos'] ?? [];

        if (!is_array($items) || empty($items)) {
            return $this->apiError('El carrito de venta no puede estar vacío.', 422, 'EMPTY_CART');
        }

        if (!is_array($pagos) || empty($pagos)) {
            return $this->apiError('Debe registrar al menos un método de pago.', 422, 'EMPTY_PAYMENTS');
        }

        $tasaActiva = (new TasaCambioModel())->getActiva();
        if (!$tasaActiva) {
            return $this->apiError('No existe una tasa de cambio activa para facturar.', 422, 'NO_ACTIVE_RATE');
        }

        $tasaId       = (int) $tasaActiva['id'];
        $tasaAplicada = round((float) $tasaActiva['tasa_ves'], 4);

        $productoModel = new ProductoModel();
        $unidadModel   = new UnidadMedidaModel();
        $metodoModel   = new MetodoPagoModel();

        $subtotalUsd     = 0.00;
        $impuestoUsd     = 0.00;
        $itemsValidados  = [];

        foreach ($items as $idx => $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            $cantidad   = isset($item['cantidad']) ? round((float) $item['cantidad'], 3) : 0.000;

            if ($productoId <= 0 || $cantidad <= 0) {
                return $this->apiError("Ítem #" . ($idx + 1) . ": producto y cantidad deben ser válidos y mayores a cero.", 422, 'VALIDATION_ERROR');
            }

            $prod = $productoModel->find($productoId);
            if (!$prod || (int) $prod['activo'] !== 1) {
                return $this->apiError("El producto ID {$productoId} no existe o está inactivo.", 404, 'PRODUCT_NOT_FOUND');
            }

            $unidadVenta = $unidadModel->find((int) $prod['unidad_venta_id']);
            if ($unidadVenta && (int) $unidadVenta['permite_decimales'] === 0 && fmod($cantidad, 1.0) !== 0.0) {
                return $this->apiError(
                    "El producto '{$prod['nombre']}' se vende por {$unidadVenta['nombre']} ({$unidadVenta['codigo']}) y no permite cantidades fraccionadas/decimales.",
                    422,
                    'DECIMALS_NOT_ALLOWED'
                );
            }

            $stockActual = (float) $prod['stock_actual'];
            if ($cantidad > $stockActual) {
                return $this->apiError(
                    "Stock insuficiente para '{$prod['nombre']}'. Disponible: {$stockActual}, Solicitado: {$cantidad}.",
                    422,
                    'INSUFFICIENT_STOCK'
                );
            }

            $precioUnitarioUsd = round((float) $prod['precio_venta_usd'], 2);
            $precioUnitarioVes = round($precioUnitarioUsd * $tasaAplicada, 2);
            $lineaSubtotalUsd  = round($cantidad * $precioUnitarioUsd, 2);
            $lineaSubtotalVes  = round($lineaSubtotalUsd * $tasaAplicada, 2);

            $ivaPorcentaje    = (int) $prod['exento_iva'] === 1 ? 0.00 : (float) $prod['porcentaje_iva'];
            $lineaImpuestoUsd = round($lineaSubtotalUsd * ($ivaPorcentaje / 100), 2);

            $subtotalUsd += $lineaSubtotalUsd;
            $impuestoUsd += $lineaImpuestoUsd;

            $itemsValidados[] = [
                'producto'            => $prod,
                'unidad_venta_id'     => (int) $prod['unidad_venta_id'],
                'cantidad'            => $cantidad,
                'precio_unitario_usd' => $precioUnitarioUsd,
                'precio_unitario_ves' => $precioUnitarioVes,
                'subtotal_usd'        => $lineaSubtotalUsd,
                'subtotal_ves'        => $lineaSubtotalVes,
            ];
        }

        $subtotalUsd = round($subtotalUsd, 2);
        $impuestoUsd = round($impuestoUsd, 2);
        $totalUsd    = max(0.00, round(($subtotalUsd + $impuestoUsd) - $descuentoUsd, 2));

        $subtotalVes  = round($subtotalUsd * $tasaAplicada, 2);
        $impuestoVes  = round($impuestoUsd * $tasaAplicada, 2);
        $descuentoVes = round($descuentoUsd * $tasaAplicada, 2);
        $totalVes     = round($totalUsd * $tasaAplicada, 2);

        // Validar pagos multipago (USD y VES combinados)
        $pagosValidados    = [];
        $totalPagadoEquivUsd = 0.00;

        foreach ($pagos as $idx => $pago) {
            $metodoId      = (int) ($pago['metodo_pago_id'] ?? 0);
            $montoOriginal = isset($pago['monto_original']) ? round((float) $pago['monto_original'], 2) : 0.00;
            $referencia    = trim((string) ($pago['referencia_transaccion'] ?? ''));

            if ($metodoId <= 0 || $montoOriginal <= 0) {
                return $this->apiError("Pago #" . ($idx + 1) . ": método de pago y monto mayor a cero son obligatorios.", 422, 'INVALID_PAYMENT');
            }

            $metodo = $metodoModel->find($metodoId);
            if (!$metodo || (int) $metodo['activo'] !== 1) {
                return $this->apiError("El método de pago ID {$metodoId} no es válido.", 422, 'INVALID_PAYMENT_METHOD');
            }

            if ((int) $metodo['requiere_referencia'] === 1 && $referencia === '') {
                return $this->apiError(
                    "El método de pago '{$metodo['nombre']}' ({$metodo['codigo']}) requiere obligatoriamente número de referencia o lote.",
                    422,
                    'REFERENCE_REQUIRED'
                );
            }

            $monedaMetodo = $metodo['moneda'];
            $montoEquivUsd = $monedaMetodo === 'USD'
                ? $montoOriginal
                : round($montoOriginal / $tasaAplicada, 2);

            $totalPagadoEquivUsd += $montoEquivUsd;

            $pagosValidados[] = [
                'metodo_pago_id'         => $metodoId,
                'moneda'                 => $monedaMetodo,
                'monto_original'         => $montoOriginal,
                'tasa_aplicada'          => $tasaAplicada,
                'monto_equivalente_usd'  => $montoEquivUsd,
                'referencia_transaccion' => $referencia !== '' ? $referencia : null,
            ];
        }

        if (round($totalPagadoEquivUsd, 2) + 0.02 < $totalUsd) {
            $faltanteUsd = round($totalUsd - $totalPagadoEquivUsd, 2);
            return $this->apiError(
                "El monto pagado ($" . number_format($totalPagadoEquivUsd, 2) . " USD equiv.) es menor al total de la factura ($" . number_format($totalUsd, 2) . " USD). Faltan $" . number_format($faltanteUsd, 2) . " USD.",
                422,
                'INSUFFICIENT_PAYMENT'
            );
        }

        // Si no enviaron vuelto explícito pero pagaron de más, calculamos vuelto automático
        $excedenteUsd = max(0.00, round($totalPagadoEquivUsd - $totalUsd, 2));
        if ($vueltoUsd == 0.00 && $vueltoVes == 0.00 && $excedenteUsd > 0) {
            $vueltoUsd = $excedenteUsd;
        }

        $auth = $this->getAuthUser();
        $now  = date('Y-m-d H:i:s');
        $db   = \Config\Database::connect();

        $db->transStart();

        // Generar correlativo de factura
        $ultimoId      = (int) ($db->table('ventas')->selectMax('id')->get()->getRowArray()['id'] ?? 0);
        $numeroFactura = sprintf('FAC-%06d', $ultimoId + 1);

        $ventaModel   = new VentaModel();
        $detalleModel = new VentaDetalleModel();
        $pagoModel    = new VentaPagoModel();
        $kardexModel  = new MovimientoInventarioModel();

        $ventaId = $ventaModel->insert([
            'numero_factura'    => $numeroFactura,
            'cliente_documento' => $clienteDocumento ?: 'V-00000000',
            'cliente_nombre'    => $clienteNombre ?: 'CONSUMIDOR FINAL',
            'tasa_cambio_id'    => $tasaId,
            'tasa_aplicada'     => $tasaAplicada,
            'subtotal_usd'      => $subtotalUsd,
            'impuesto_usd'      => $impuestoUsd,
            'descuento_usd'     => $descuentoUsd,
            'total_usd'         => $totalUsd,
            'subtotal_ves'      => $subtotalVes,
            'impuesto_ves'      => $impuestoVes,
            'descuento_ves'     => $descuentoVes,
            'total_ves'         => $totalVes,
            'vuelto_usd'        => $vueltoUsd,
            'vuelto_ves'        => $vueltoVes,
            'estado'            => 'COMPLETADA',
            'usuario_id'        => $auth['uid'] ?? 1,
            'created_at'        => $now,
        ], true);

        foreach ($itemsValidados as $item) {
            $prod            = $item['producto'];
            $stockAnterior   = (float) $prod['stock_actual'];
            $stockResultante = round($stockAnterior - $item['cantidad'], 3);

            $detalleModel->insert([
                'venta_id'            => $ventaId,
                'producto_id'         => (int) $prod['id'],
                'unidad_venta_id'     => $item['unidad_venta_id'],
                'cantidad'            => $item['cantidad'],
                'precio_unitario_usd' => $item['precio_unitario_usd'],
                'precio_unitario_ves' => $item['precio_unitario_ves'],
                'subtotal_usd'        => $item['subtotal_usd'],
                'subtotal_ves'        => $item['subtotal_ves'],
            ]);

            $productoModel->update((int) $prod['id'], [
                'stock_actual' => $stockResultante,
            ]);

            $kardexModel->insert([
                'producto_id'        => (int) $prod['id'],
                'tipo_movimiento'    => 'SALIDA_VENTA',
                'referencia_id'      => $ventaId,
                'cantidad'           => $item['cantidad'],
                'stock_anterior'     => $stockAnterior,
                'stock_resultante'   => $stockResultante,
                'costo_unitario_usd' => (float) $prod['costo_usd'],
                'costo_unitario_ves' => round((float) $prod['costo_usd'] * $tasaAplicada, 2),
                'tasa_aplicada'      => $tasaAplicada,
                'observacion'        => "Venta POS Factura {$numeroFactura} ({$clienteNombre})",
                'usuario_id'         => $auth['uid'] ?? 1,
                'created_at'         => $now,
            ]);
        }

        foreach ($pagosValidados as $pago) {
            $pago['venta_id']   = $ventaId;
            $pago['created_at'] = $now;
            $pagoModel->insert($pago);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error crítico de base de datos al procesar la venta.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->show($ventaId)->setStatusCode(201);
    }

    public function anular($id = null): ResponseInterface
    {
        $ventaModel = new VentaModel();
        $venta      = $ventaModel->find($id);

        if (!$venta) {
            return $this->apiError('Factura no encontrada.', 404, 'NOT_FOUND');
        }

        if ($venta['estado'] === 'ANULADA') {
            return $this->apiError('Esta factura ya fue anulada previamente.', 422, 'ALREADY_CANCELLED');
        }

        $payload = $this->getRequestPayload();
        $motivo  = trim((string) ($payload['motivo'] ?? 'Anulación de factura en POS'));

        $detalleModel  = new VentaDetalleModel();
        $productoModel = new ProductoModel();
        $kardexModel   = new MovimientoInventarioModel();
        $detalles      = $detalleModel->where('venta_id', (int) $id)->findAll();
        $auth          = $this->getAuthUser();
        $now           = date('Y-m-d H:i:s');
        $tasaAplicada  = (float) $venta['tasa_aplicada'];

        $db = \Config\Database::connect();
        $db->transStart();

        $ventaModel->update($id, ['estado' => 'ANULADA']);

        foreach ($detalles as $det) {
            $prod = $productoModel->find((int) $det['producto_id']);
            if (!$prod) {
                continue;
            }

            $cantidad        = (float) $det['cantidad'];
            $stockAnterior   = (float) $prod['stock_actual'];
            $stockResultante = round($stockAnterior + $cantidad, 3);

            $productoModel->update((int) $prod['id'], [
                'stock_actual' => $stockResultante,
            ]);

            $kardexModel->insert([
                'producto_id'        => (int) $prod['id'],
                'tipo_movimiento'    => 'ANULACION_VENTA',
                'referencia_id'      => (int) $id,
                'cantidad'           => $cantidad,
                'stock_anterior'     => $stockAnterior,
                'stock_resultante'   => $stockResultante,
                'costo_unitario_usd' => (float) $prod['costo_usd'],
                'costo_unitario_ves' => round((float) $prod['costo_usd'] * $tasaAplicada, 2),
                'tasa_aplicada'      => $tasaAplicada,
                'observacion'        => "Anulación {$venta['numero_factura']}: {$motivo}",
                'usuario_id'         => $auth['uid'] ?? 1,
                'created_at'         => $now,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->apiError('Error al anular la factura y revertir el inventario.', 500, 'DB_TRANSACTION_ERROR');
        }

        return $this->show($id);
    }
}
