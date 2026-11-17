<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaSesionModel extends Model
{
    protected $table            = 'caja_sesiones';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'usuario_id',
        'estado',
        'monto_apertura_usd',
        'monto_apertura_ves',
        'tasa_apertura',
        'ventas_cantidad',
        'total_ventas_usd',
        'total_ventas_ves',
        'esperado_efectivo_usd',
        'esperado_efectivo_ves',
        'contado_efectivo_usd',
        'contado_efectivo_ves',
        'diferencia_usd',
        'diferencia_ves',
        'desglose_metodos_json',
        'observacion_apertura',
        'observacion_cierre',
        'abierta_at',
        'cerrada_at',
    ];

    public function getSesionAbierta(int $usuarioId): ?array
    {
        $row = $this->where('usuario_id', $usuarioId)
            ->where('estado', 'ABIERTA')
            ->orderBy('id', 'DESC')
            ->first();

        return $row ? $this->formatearSesion($row) : null;
    }

    public function calcularResumenSesion(array $sesion): array
    {
        $db       = $this->db;
        $sesionId = (int) $sesion['id'];

        $totales = $db->table('ventas')
            ->select("
                COUNT(CASE WHEN estado = 'COMPLETADA' THEN 1 END) AS ventas_cantidad,
                COUNT(CASE WHEN estado = 'ANULADA' THEN 1 END) AS ventas_anuladas,
                COALESCE(SUM(CASE WHEN estado = 'COMPLETADA' THEN total_usd ELSE 0 END), 0.00) AS total_ventas_usd,
                COALESCE(SUM(CASE WHEN estado = 'COMPLETADA' THEN total_ves ELSE 0 END), 0.00) AS total_ventas_ves,
                COALESCE(SUM(CASE WHEN estado = 'COMPLETADA' THEN vuelto_usd ELSE 0 END), 0.00) AS vueltos_usd,
                COALESCE(SUM(CASE WHEN estado = 'COMPLETADA' THEN vuelto_ves ELSE 0 END), 0.00) AS vueltos_ves
            ")
            ->where('caja_sesion_id', $sesionId)
            ->get()
            ->getRowArray();

        $pagos = $db->table('venta_pagos vp')
            ->select('
                mp.codigo,
                mp.nombre,
                mp.moneda,
                COALESCE(SUM(vp.monto_original), 0.00) AS total_original,
                COALESCE(SUM(vp.monto_equivalente_usd), 0.00) AS total_equiv_usd
            ')
            ->join('ventas v', 'v.id = vp.venta_id', 'inner')
            ->join('metodos_pago mp', 'mp.id = vp.metodo_pago_id', 'left')
            ->where('v.caja_sesion_id', $sesionId)
            ->where('v.estado', 'COMPLETADA')
            ->groupBy('mp.id, mp.codigo, mp.nombre, mp.moneda')
            ->orderBy('mp.id', 'ASC')
            ->get()
            ->getResultArray();

        $efectivoUsdRecibido = 0.00;
        $efectivoVesRecibido = 0.00;
        $desglose            = [];

        foreach ($pagos as $p) {
            $codigo = strtoupper((string) ($p['codigo'] ?? ''));
            $orig   = round((float) $p['total_original'], 2);
            $equiv  = round((float) $p['total_equiv_usd'], 2);

            if ($codigo === 'EFECTIVO_USD') {
                $efectivoUsdRecibido += $orig;
            } elseif ($codigo === 'EFECTIVO_VES') {
                $efectivoVesRecibido += $orig;
            }

            $desglose[] = [
                'codigo'          => $codigo,
                'nombre'          => $p['nombre'],
                'moneda'          => $p['moneda'],
                'total_original'  => $orig,
                'total_equiv_usd' => $equiv,
            ];
        }

        $vueltosUsd = round((float) ($totales['vueltos_usd'] ?? 0), 2);
        $vueltosVes = round((float) ($totales['vueltos_ves'] ?? 0), 2);

        $esperadoUsd = round((float) $sesion['monto_apertura_usd'] + $efectivoUsdRecibido - $vueltosUsd, 2);
        $esperadoVes = round((float) $sesion['monto_apertura_ves'] + $efectivoVesRecibido - $vueltosVes, 2);

        return [
            'ventas_cantidad'       => (int) ($totales['ventas_cantidad'] ?? 0),
            'ventas_anuladas'       => (int) ($totales['ventas_anuladas'] ?? 0),
            'total_ventas_usd'      => round((float) ($totales['total_ventas_usd'] ?? 0), 2),
            'total_ventas_ves'      => round((float) ($totales['total_ventas_ves'] ?? 0), 2),
            'vueltos_usd'           => $vueltosUsd,
            'vueltos_ves'           => $vueltosVes,
            'efectivo_recibido_usd' => round($efectivoUsdRecibido, 2),
            'efectivo_recibido_ves' => round($efectivoVesRecibido, 2),
            'esperado_efectivo_usd' => $esperadoUsd,
            'esperado_efectivo_ves' => $esperadoVes,
            'desglose_metodos'      => $desglose,
        ];
    }

    public function formatearSesion(array $row): array
    {
        $row['id']                    = (int) $row['id'];
        $row['usuario_id']            = (int) $row['usuario_id'];
        $row['monto_apertura_usd']    = (float) $row['monto_apertura_usd'];
        $row['monto_apertura_ves']    = (float) $row['monto_apertura_ves'];
        $row['tasa_apertura']         = (float) $row['tasa_apertura'];
        $row['ventas_cantidad']       = (int) $row['ventas_cantidad'];
        $row['total_ventas_usd']      = (float) $row['total_ventas_usd'];
        $row['total_ventas_ves']      = (float) $row['total_ventas_ves'];
        $row['esperado_efectivo_usd'] = (float) $row['esperado_efectivo_usd'];
        $row['esperado_efectivo_ves'] = (float) $row['esperado_efectivo_ves'];
        $row['contado_efectivo_usd']  = $row['contado_efectivo_usd'] !== null ? (float) $row['contado_efectivo_usd'] : null;
        $row['contado_efectivo_ves']  = $row['contado_efectivo_ves'] !== null ? (float) $row['contado_efectivo_ves'] : null;
        $row['diferencia_usd']        = $row['diferencia_usd'] !== null ? (float) $row['diferencia_usd'] : null;
        $row['diferencia_ves']        = $row['diferencia_ves'] !== null ? (float) $row['diferencia_ves'] : null;
        $row['desglose_metodos']      = !empty($row['desglose_metodos_json'])
            ? (json_decode((string) $row['desglose_metodos_json'], true) ?: [])
            : [];
        unset($row['desglose_metodos_json']);

        return $row;
    }
}
