<?php

namespace App\Models;

use CodeIgniter\Model;

class VentaPagoModel extends Model
{
    protected $table            = 'venta_pagos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'venta_id',
        'metodo_pago_id',
        'moneda',
        'monto_original',
        'tasa_aplicada',
        'monto_equivalente_usd',
        'referencia_transaccion',
        'created_at',
    ];
}
