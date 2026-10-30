<?php

namespace App\Models;

use CodeIgniter\Model;

class TasaCambioModel extends Model
{
    protected $table            = 'tasas_cambio';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'tasa_ves',
        'fuente',
        'activa',
        'usuario_id',
        'created_at',
    ];

    public function getActiva(): ?array
    {
        $row = $this->where('activa', 1)->orderBy('id', 'DESC')->first();
        if (!$row) {
            $row = $this->orderBy('id', 'DESC')->first();
        }
        if ($row) {
            $row['id']       = (int) $row['id'];
            $row['tasa_ves'] = (float) $row['tasa_ves'];
            $row['activa']   = (int) $row['activa'];
        }
        return $row;
    }
}
