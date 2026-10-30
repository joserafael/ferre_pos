<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ProductoEntity extends Entity
{
    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [
        'id'                => 'integer',
        'categoria_id'      => 'integer',
        'unidad_compra_id'  => 'integer',
        'unidad_venta_id'   => 'integer',
        'factor_conversion' => 'float',
        'costo_usd'         => 'float',
        'precio_venta_usd'  => 'float',
        'porcentaje_iva'    => 'float',
        'exento_iva'        => 'boolean',
        'stock_actual'      => 'float',
        'stock_minimo'      => 'float',
        'activo'            => 'boolean',
    ];
}
