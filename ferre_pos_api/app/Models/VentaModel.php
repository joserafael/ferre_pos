<?php

namespace App\Models;

use CodeIgniter\Model;

class VentaModel extends Model
{
    protected $table            = 'ventas';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'numero_factura',
        'cliente_documento',
        'cliente_nombre',
        'tasa_cambio_id',
        'tasa_aplicada',
        'subtotal_usd',
        'impuesto_usd',
        'descuento_usd',
        'total_usd',
        'subtotal_ves',
        'impuesto_ves',
        'descuento_ves',
        'total_ves',
        'vuelto_usd',
        'vuelto_ves',
        'estado',
        'usuario_id',
        'created_at',
    ];
}
