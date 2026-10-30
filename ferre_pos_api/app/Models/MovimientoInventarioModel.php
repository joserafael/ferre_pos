<?php

namespace App\Models;

use CodeIgniter\Model;

class MovimientoInventarioModel extends Model
{
    protected $table            = 'movimientos_inventario';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'producto_id',
        'tipo_movimiento',
        'referencia_id',
        'cantidad',
        'stock_anterior',
        'stock_resultante',
        'costo_unitario_usd',
        'costo_unitario_ves',
        'tasa_aplicada',
        'observacion',
        'usuario_id',
        'created_at',
    ];
}
