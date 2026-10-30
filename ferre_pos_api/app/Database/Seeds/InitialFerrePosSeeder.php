<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialFerrePosSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1. Usuarios
        $this->db->table('usuarios')->ignore(true)->insertBatch([
            [
                'id'            => 1,
                'nombre'        => 'Administrador General',
                'username'      => 'admin',
                'email'         => 'admin@ferrepos.local',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'rol'           => 'ADMIN',
                'activo'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 2,
                'nombre'        => 'Cajero Principal',
                'username'      => 'cajero',
                'email'         => 'caja@ferrepos.local',
                'password_hash' => password_hash('cajero123', PASSWORD_DEFAULT),
                'rol'           => 'CAJERO',
                'activo'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ]);

        // 2. Tasa de cambio activa inicial
        $tasaInicial = 42.5000;
        $this->db->table('tasas_cambio')->ignore(true)->insert([
            'id'         => 1,
            'tasa_ves'   => $tasaInicial,
            'fuente'     => 'BCV Oficial',
            'activa'     => 1,
            'usuario_id' => 1,
            'created_at' => $now,
        ]);

        // 3. Categorías
        $this->db->table('categorias')->ignore(true)->insertBatch([
            ['id' => 1, 'nombre' => 'Electricidad', 'descripcion' => 'Cables, brekers, tomacorrientes e iluminación', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nombre' => 'Plomería', 'descripcion' => 'Tuberías PVC/PPR, conexiones y grifería', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'nombre' => 'Tornillería y Fijación', 'descripcion' => 'Tornillos, clavos, tuercas, ramplugs por unidad o peso', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'nombre' => 'Construcción', 'descripcion' => 'Cemento, pego, cal, arena, mallas y cabillas', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'nombre' => 'Herramientas', 'descripcion' => 'Herramientas manuales y eléctricas', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'nombre' => 'Pinturas y Químicos', 'descripcion' => 'Pinturas, solventes, impermeabilizantes y selladores', 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 4. Unidades de Medida
        $this->db->table('unidades_medida')->ignore(true)->insertBatch([
            ['id' => 1, 'codigo' => 'UND',   'nombre' => 'Unidad',     'permite_decimales' => 0, 'created_at' => $now],
            ['id' => 2, 'codigo' => 'M',     'nombre' => 'Metro',      'permite_decimales' => 1, 'created_at' => $now],
            ['id' => 3, 'codigo' => 'KG',    'nombre' => 'Kilogramo',  'permite_decimales' => 1, 'created_at' => $now],
            ['id' => 4, 'codigo' => 'L',     'nombre' => 'Litro',      'permite_decimales' => 1, 'created_at' => $now],
            ['id' => 5, 'codigo' => 'CAJA',  'nombre' => 'Caja',       'permite_decimales' => 0, 'created_at' => $now],
            ['id' => 6, 'codigo' => 'ROLLO', 'nombre' => 'Rollo',      'permite_decimales' => 0, 'created_at' => $now],
        ]);

        // 5. Métodos de Pago (Estrictos según el skill)
        $this->db->table('metodos_pago')->ignore(true)->insertBatch([
            ['id' => 1, 'codigo' => 'EFECTIVO_VES',  'nombre' => 'Efectivo (Bs.)',   'moneda' => 'VES', 'requiere_referencia' => 0, 'activo' => 1],
            ['id' => 2, 'codigo' => 'EFECTIVO_USD',  'nombre' => 'Efectivo ($ USD)', 'moneda' => 'USD', 'requiere_referencia' => 0, 'activo' => 1],
            ['id' => 3, 'codigo' => 'PUNTO_DEBITO',  'nombre' => 'Punto Débito',     'moneda' => 'VES', 'requiere_referencia' => 1, 'activo' => 1],
            ['id' => 4, 'codigo' => 'PUNTO_CREDITO', 'nombre' => 'Punto Crédito',    'moneda' => 'VES', 'requiere_referencia' => 1, 'activo' => 1],
            ['id' => 5, 'codigo' => 'BINANCE',       'nombre' => 'Binance Pay',      'moneda' => 'USD', 'requiere_referencia' => 1, 'activo' => 1],
            ['id' => 6, 'codigo' => 'ZELLE',         'nombre' => 'Zelle',            'moneda' => 'USD', 'requiere_referencia' => 1, 'activo' => 1],
            ['id' => 7, 'codigo' => 'PAGO_MOVIL',    'nombre' => 'Pago Móvil',       'moneda' => 'VES', 'requiere_referencia' => 1, 'activo' => 1],
        ]);

        // 6. Productos de Ferretería (con soporte fraccionado y factor_conversion)
        $productos = [
            [
                'id'                => 1,
                'codigo_barra'      => '759100100001',
                'sku'               => 'ELE-CAB-12',
                'nombre'            => 'Cable THW #12 AWG 7 Pelos Cobre (Venta por Metro)',
                'categoria_id'      => 1,
                'unidad_compra_id'  => 6, // ROLLO
                'unidad_venta_id'   => 2, // M (permite decimales)
                'factor_conversion' => 100.000, // 1 ROLLO = 100 M
                'costo_usd'         => 0.55,
                'precio_venta_usd'  => 0.85,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 300.000,
                'stock_minimo'      => 50.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => 2,
                'codigo_barra'      => '759100100002',
                'sku'               => 'TOR-CLA-25',
                'nombre'            => 'Clavo de Hierro con Cabeza 2-1/2" (Venta por Kg)',
                'categoria_id'      => 3,
                'unidad_compra_id'  => 5, // CAJA
                'unidad_venta_id'   => 3, // KG (permite decimales)
                'factor_conversion' => 25.000, // 1 CAJA = 25 KG
                'costo_usd'         => 1.80,
                'precio_venta_usd'  => 2.60,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 75.000,
                'stock_minimo'      => 15.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => 3,
                'codigo_barra'      => '759100100003',
                'sku'               => 'CON-CEM-42',
                'nombre'            => 'Saco de Cemento Gris Uso General 42.5 Kg',
                'categoria_id'      => 4,
                'unidad_compra_id'  => 1, // UND
                'unidad_venta_id'   => 1, // UND
                'factor_conversion' => 1.000,
                'costo_usd'         => 7.20,
                'precio_venta_usd'  => 9.50,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 120.000,
                'stock_minimo'      => 20.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => 4,
                'codigo_barra'      => '759100100004',
                'sku'               => 'PLO-TUB-12',
                'nombre'            => 'Tubo PVC Agua Fría 1/2" x 6m Pavco',
                'categoria_id'      => 2,
                'unidad_compra_id'  => 1, // UND
                'unidad_venta_id'   => 1, // UND
                'factor_conversion' => 1.000,
                'costo_usd'         => 3.10,
                'precio_venta_usd'  => 4.75,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 60.000,
                'stock_minimo'      => 10.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => 5,
                'codigo_barra'      => '759100100005',
                'sku'               => 'HER-ESM-45',
                'nombre'            => 'Esmeril Angular 4-1/2" 850W Profesional',
                'categoria_id'      => 5,
                'unidad_compra_id'  => 1, // UND
                'unidad_venta_id'   => 1, // UND
                'factor_conversion' => 1.000,
                'costo_usd'         => 32.00,
                'precio_venta_usd'  => 45.00,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 12.000,
                'stock_minimo'      => 3.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => 6,
                'codigo_barra'      => '759100100006',
                'sku'               => 'PIN-SOL-TH',
                'nombre'            => 'Thinner Acrílico Industrial (Venta por Litro)',
                'categoria_id'      => 6,
                'unidad_compra_id'  => 4, // L
                'unidad_venta_id'   => 4, // L (permite decimales)
                'factor_conversion' => 1.000,
                'costo_usd'         => 2.10,
                'precio_venta_usd'  => 3.40,
                'porcentaje_iva'    => 16.00,
                'exento_iva'        => 0,
                'stock_actual'      => 40.000,
                'stock_minimo'      => 8.000,
                'activo'            => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        ];

        $this->db->table('productos')->ignore(true)->insertBatch($productos);

        // 7. Kardex Inicial (Movimientos de Inventario por inventario inicial)
        $movimientos = [];
        foreach ($productos as $p) {
            $movimientos[] = [
                'producto_id'        => $p['id'],
                'tipo_movimiento'    => 'ENTRADA_COMPRA',
                'referencia_id'      => 0,
                'cantidad'           => $p['stock_actual'],
                'stock_anterior'     => 0.000,
                'stock_resultante'   => $p['stock_actual'],
                'costo_unitario_usd' => $p['costo_usd'],
                'costo_unitario_ves' => round($p['costo_usd'] * $tasaInicial, 2),
                'tasa_aplicada'      => $tasaInicial,
                'observacion'        => 'Carga de inventario inicial del sistema',
                'usuario_id'         => 1,
                'created_at'         => $now,
            ];
        }
        $this->db->table('movimientos_inventario')->insertBatch($movimientos);
    }
}
