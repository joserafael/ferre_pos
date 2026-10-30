<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table            = 'productos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'codigo_barra',
        'sku',
        'nombre',
        'categoria_id',
        'unidad_compra_id',
        'unidad_venta_id',
        'factor_conversion',
        'costo_usd',
        'precio_venta_usd',
        'porcentaje_iva',
        'exento_iva',
        'stock_actual',
        'stock_minimo',
        'activo',
    ];

    public function getConRelaciones(?string $search = null, ?int $categoriaId = null, bool $soloActivos = false): array
    {
        $tasaModel  = new TasaCambioModel();
        $tasaActiva = $tasaModel->getActiva();
        $tasaVes    = $tasaActiva ? (float) $tasaActiva['tasa_ves'] : 1.0000;

        $builder = $this->db->table('productos p')
            ->select('p.*, c.nombre as categoria_nombre, uc.codigo as unidad_compra_codigo, uc.nombre as unidad_compra_nombre, uv.codigo as unidad_venta_codigo, uv.nombre as unidad_venta_nombre, uv.permite_decimales')
            ->join('categorias c', 'c.id = p.categoria_id', 'left')
            ->join('unidades_medida uc', 'uc.id = p.unidad_compra_id', 'left')
            ->join('unidades_medida uv', 'uv.id = p.unidad_venta_id', 'left');

        if ($soloActivos) {
            $builder->where('p.activo', 1);
        }

        if ($categoriaId) {
            $builder->where('p.categoria_id', $categoriaId);
        }

        if (!empty($search)) {
            $builder->groupStart()
                ->like('p.nombre', $search)
                ->orLike('p.sku', $search)
                ->orLike('p.codigo_barra', $search)
                ->groupEnd();
        }

        $rows = $builder->orderBy('p.nombre', 'ASC')->get()->getResultArray();

        foreach ($rows as &$r) {
            $r['id']                = (int) $r['id'];
            $r['categoria_id']      = (int) $r['categoria_id'];
            $r['unidad_compra_id']  = (int) $r['unidad_compra_id'];
            $r['unidad_venta_id']   = (int) $r['unidad_venta_id'];
            $r['factor_conversion'] = (float) $r['factor_conversion'];
            $r['costo_usd']         = (float) $r['costo_usd'];
            $r['precio_venta_usd']  = (float) $r['precio_venta_usd'];
            $r['precio_venta_ves']  = round((float) $r['precio_venta_usd'] * $tasaVes, 2);
            $r['costo_ves']         = round((float) $r['costo_usd'] * $tasaVes, 2);
            $r['porcentaje_iva']    = (float) $r['porcentaje_iva'];
            $r['exento_iva']        = (int) $r['exento_iva'];
            $r['stock_actual']      = (float) $r['stock_actual'];
            $r['stock_minimo']      = (float) $r['stock_minimo'];
            $r['permite_decimales'] = (int) $r['permite_decimales'];
            $r['activo']            = (int) $r['activo'];
        }

        return $rows;
    }
}
