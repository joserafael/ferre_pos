<?php

namespace App\Models;

use CodeIgniter\Model;

class UnidadMedidaModel extends Model
{
    protected $table            = 'unidades_medida';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'codigo',
        'nombre',
        'permite_decimales',
        'created_at',
    ];
}
