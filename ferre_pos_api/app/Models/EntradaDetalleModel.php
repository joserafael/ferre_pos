<?php

namespace App\Models;

use CodeIgniter\Model;

class EntradaDetalleModel extends Model
{
    protected $table            = 'entrada_detalles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'entrada_id',
        'producto_id',
        'unidad_compra_id',
        'cantidad_comprada',
        'factor_conversion_aplicado',
        'cantidad_ingresada_stock',
        'costo_compra_unitario_usd',
        'costo_compra_unitario_ves',
        'subtotal_usd',
        'subtotal_ves',
    ];
}
