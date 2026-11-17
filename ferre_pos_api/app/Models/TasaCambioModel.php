<?php

namespace App\Models;

use App\Libraries\RedisService;
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
        // 1. Intentar obtener desde Redis (respuesta en sub-milisegundo)
        $cached = RedisService::getTasaActiva();
        if ($cached !== null) {
            return $cached;
        }

        // 2. Consulta en base de datos si no está en caché
        $row = $this->where('activa', 1)->orderBy('id', 'DESC')->first();
        if (!$row) {
            $row = $this->orderBy('id', 'DESC')->first();
        }
        if ($row) {
            $row['id']       = (int) $row['id'];
            $row['tasa_ves'] = (float) $row['tasa_ves'];
            $row['activa']   = (int) $row['activa'];

            // 3. Guardar en Redis por 1 hora (3600s)
            RedisService::setTasaActiva($row, 3600);
        }
        return $row;
    }
}
