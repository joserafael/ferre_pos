<?php

namespace App\Models;

use CodeIgniter\Model;

class VentaDetalleModel extends Model
{
    protected $table            = 'venta_detalles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'venta_id',
        'producto_id',
        'unidad_venta_id',
        'cantidad',
        'precio_unitario_usd',
        'precio_unitario_ves',
        'subtotal_usd',
        'subtotal_ves',
    ];
}
