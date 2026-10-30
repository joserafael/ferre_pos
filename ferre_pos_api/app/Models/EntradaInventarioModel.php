<?php

namespace App\Models;

use CodeIgniter\Model;

class EntradaInventarioModel extends Model
{
    protected $table            = 'entradas_inventario';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'numero_documento',
        'proveedor_nombre',
        'moneda_ingreso',
        'tasa_cambio_id',
        'tasa_aplicada',
        'total_usd',
        'total_ves',
        'observacion',
        'usuario_id',
        'created_at',
    ];
}
