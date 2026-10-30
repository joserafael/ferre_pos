<?php

namespace App\Controllers\Api;

use App\Models\TasaCambioModel;
use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends ApiController
{
    public function index(): ResponseInterface
    {
        $db         = \Config\Database::connect();
        $tasaActiva = (new TasaCambioModel())->getActiva();
        $hoy        = date('Y-m-d');

        // 1. Resumen de ventas hoy
        $ventasHoy = $db->table('ventas')
            ->select('COUNT(id) as cantidad_facturas, COALESCE(SUM(total_usd), 0) as total_usd, COALESCE(SUM(total_ves), 0) as total_ves')
            ->where('estado', 'COMPLETADA')
            ->where('DATE(created_at)', $hoy)
            ->get()
            ->getRowArray();

        // 2. Resumen general acumulado
        $ventasTotales = $db->table('ventas')
            ->select('COUNT(id) as cantidad_facturas, COALESCE(SUM(total_usd), 0) as total_usd, COALESCE(SUM(total_ves), 0) as total_ves')
            ->where('estado', 'COMPLETADA')
            ->get()
            ->getRowArray();

        // 3. Desglose por método de pago (ventas completadas)
        $pagosPorMetodo = $db->table('venta_pagos vp')
            ->select('mp.codigo, mp.nombre, mp.moneda, COUNT(vp.id) as transacciones, COALESCE(SUM(vp.monto_original), 0) as total_moneda_origen, COALESCE(SUM(vp.monto_equivalente_usd), 0) as total_equivalente_usd')
            ->join('metodos_pago mp', 'mp.id = vp.metodo_pago_id')
            ->join('ventas v', 'v.id = vp.venta_id')
            ->where('v.estado', 'COMPLETADA')
            ->groupBy('mp.id')
            ->orderBy('total_equivalente_usd', 'DESC')
            ->get()
            ->getResultArray();

        // 4. Productos con stock bajo
        $bajoStock = $db->table('productos p')
            ->select('p.id, p.sku, p.nombre, p.stock_actual, p.stock_minimo, uv.codigo as unidad_venta_codigo')
            ->join('unidades_medida uv', 'uv.id = p.unidad_venta_id', 'left')
            ->where('p.activo', 1)
            ->where('p.stock_actual <= p.stock_minimo')
            ->orderBy('p.stock_actual', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();

        // 5. Valorización de inventario actual
        $valorInventario = $db->table('productos')
            ->select('COUNT(id) as total_productos, COALESCE(SUM(stock_actual * costo_usd), 0) as costo_total_usd, COALESCE(SUM(stock_actual * precio_venta_usd), 0) as venta_estimada_usd')
            ->where('activo', 1)
            ->get()
            ->getRowArray();

        // 6. Últimas 6 facturas
        $ultimasVentas = $db->table('ventas v')
            ->select('v.id, v.numero_factura, v.cliente_nombre, v.total_usd, v.total_ves, v.tasa_aplicada, v.estado, v.created_at')
            ->orderBy('v.id', 'DESC')
            ->limit(6)
            ->get()
            ->getResultArray();

        return $this->apiResponse([
            'tasa_activa'      => $tasaActiva,
            'ventas_hoy'       => [
                'cantidad_facturas' => (int) ($ventasHoy['cantidad_facturas'] ?? 0),
                'total_usd'         => (float) ($ventasHoy['total_usd'] ?? 0),
                'total_ves'         => (float) ($ventasHoy['total_ves'] ?? 0),
            ],
            'ventas_totales'   => [
                'cantidad_facturas' => (int) ($ventasTotales['cantidad_facturas'] ?? 0),
                'total_usd'         => (float) ($ventasTotales['total_usd'] ?? 0),
                'total_ves'         => (float) ($ventasTotales['total_ves'] ?? 0),
            ],
            'valor_inventario' => [
                'total_productos'    => (int) ($valorInventario['total_productos'] ?? 0),
                'costo_total_usd'    => round((float) ($valorInventario['costo_total_usd'] ?? 0), 2),
                'venta_estimada_usd' => round((float) ($valorInventario['venta_estimada_usd'] ?? 0), 2),
            ],
            'pagos_por_metodo' => $pagosPorMetodo,
            'bajo_stock'       => $bajoStock,
            'ultimas_ventas'   => $ultimasVentas,
        ], 'Indicadores del dashboard obtenidos.');
    }
}
