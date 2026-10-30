<?php

namespace App\Models;

use CodeIgniter\Model;

class MetodoPagoModel extends Model
{
    protected $table            = 'metodos_pago';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'codigo',
        'nombre',
        'moneda',
        'requiere_referencia',
        'activo',
    ];
}
