<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFerrePosSchema extends Migration
{
    public function up(): void
    {
        $tableAttrs = $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'] : [];

        // 1. usuarios
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 120],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'rol'           => ['type' => 'ENUM', 'constraint' => ['ADMIN', 'CAJERO', 'ALMACEN'], 'default' => 'ADMIN'],
            'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('usuarios', true, $tableAttrs);

        // 2. tasas_cambio
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tasa_ves'   => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'fuente'     => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'BCV'],
            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'usuario_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('activa');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('tasas_cambio', true, $tableAttrs);

        // 3. categorias
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'activo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nombre');
        $this->forge->createTable('categorias', true, $tableAttrs);

        // 4. unidades_medida
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'codigo'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'nombre'            => ['type' => 'VARCHAR', 'constraint' => 60],
            'permite_decimales' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->createTable('unidades_medida', true, $tableAttrs);

        // 5. metodos_pago
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'codigo'              => ['type' => 'VARCHAR', 'constraint' => 40],
            'nombre'              => ['type' => 'VARCHAR', 'constraint' => 80],
            'moneda'              => ['type' => 'ENUM', 'constraint' => ['USD', 'VES']],
            'requiere_referencia' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'activo'              => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo');
        $this->forge->createTable('metodos_pago', true, $tableAttrs);

        // 6. productos
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'codigo_barra'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'sku'               => ['type' => 'VARCHAR', 'constraint' => 60],
            'nombre'            => ['type' => 'VARCHAR', 'constraint' => 180],
            'categoria_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unidad_compra_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unidad_venta_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'factor_conversion' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => '1.000'],
            'costo_usd'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'precio_venta_usd'  => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'porcentaje_iva'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '16.00'],
            'exento_iva'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'stock_actual'      => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => '0.000'],
            'stock_minimo'      => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => '0.000'],
            'activo'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey('codigo_barra');
        $this->forge->addForeignKey('categoria_id', 'categorias', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('unidad_compra_id', 'unidades_medida', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('unidad_venta_id', 'unidades_medida', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('productos', true, $tableAttrs);

        // 7. entradas_inventario
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'numero_documento' => ['type' => 'VARCHAR', 'constraint' => 60],
            'proveedor_nombre' => ['type' => 'VARCHAR', 'constraint' => 150],
            'moneda_ingreso'   => ['type' => 'ENUM', 'constraint' => ['USD', 'VES'], 'default' => 'USD'],
            'tasa_cambio_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tasa_aplicada'    => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'total_usd'        => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'total_ves'        => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'observacion'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'usuario_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('numero_documento');
        $this->forge->addForeignKey('tasa_cambio_id', 'tasas_cambio', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('entradas_inventario', true, $tableAttrs);

        // 7b. entrada_detalles
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entrada_id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'producto_id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unidad_compra_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'cantidad_comprada'         => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'factor_conversion_aplicado'=> ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'cantidad_ingresada_stock'  => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'costo_compra_unitario_usd' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'costo_compra_unitario_ves' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'subtotal_usd'              => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'subtotal_ves'              => ['type' => 'DECIMAL', 'constraint' => '14,2'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('entrada_id', 'entradas_inventario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('producto_id', 'productos', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('unidad_compra_id', 'unidades_medida', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('entrada_detalles', true, $tableAttrs);

        // 8. movimientos_inventario
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'producto_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo_movimiento'    => ['type' => 'ENUM', 'constraint' => ['ENTRADA_COMPRA', 'SALIDA_VENTA', 'ANULACION_VENTA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO']],
            'referencia_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'cantidad'           => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'stock_anterior'     => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'stock_resultante'   => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'costo_unitario_usd' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'costo_unitario_ves' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'tasa_aplicada'      => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'observacion'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'usuario_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['producto_id', 'created_at']);
        $this->forge->addForeignKey('producto_id', 'productos', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('movimientos_inventario', true, $tableAttrs);

        // 9. ventas
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'numero_factura'    => ['type' => 'VARCHAR', 'constraint' => 40],
            'cliente_documento' => ['type' => 'VARCHAR', 'constraint' => 30],
            'cliente_nombre'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'tasa_cambio_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tasa_aplicada'     => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'subtotal_usd'      => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'impuesto_usd'      => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'descuento_usd'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'total_usd'         => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'subtotal_ves'      => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'impuesto_ves'      => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'descuento_ves'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'total_ves'         => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'vuelto_usd'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'vuelto_ves'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'estado'            => ['type' => 'ENUM', 'constraint' => ['COMPLETADA', 'ANULADA'], 'default' => 'COMPLETADA'],
            'usuario_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('numero_factura');
        $this->forge->addForeignKey('tasa_cambio_id', 'tasas_cambio', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('ventas', true, $tableAttrs);

        // 10. venta_detalles
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'venta_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'producto_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'unidad_venta_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'cantidad'            => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'precio_unitario_usd' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'precio_unitario_ves' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'subtotal_usd'        => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'subtotal_ves'        => ['type' => 'DECIMAL', 'constraint' => '14,2'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('venta_id', 'ventas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('producto_id', 'productos', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('unidad_venta_id', 'unidades_medida', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('venta_detalles', true, $tableAttrs);

        // 11. venta_pagos
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'venta_id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'metodo_pago_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'moneda'                 => ['type' => 'ENUM', 'constraint' => ['USD', 'VES']],
            'monto_original'         => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'tasa_aplicada'          => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'monto_equivalente_usd'  => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'referencia_transaccion' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('venta_id', 'ventas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('metodo_pago_id', 'metodos_pago', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('venta_pagos', true, $tableAttrs);
    }

    public function down(): void
    {
        $this->forge->dropTable('venta_pagos', true);
        $this->forge->dropTable('venta_detalles', true);
        $this->forge->dropTable('ventas', true);
        $this->forge->dropTable('movimientos_inventario', true);
        $this->forge->dropTable('entrada_detalles', true);
        $this->forge->dropTable('entradas_inventario', true);
        $this->forge->dropTable('productos', true);
        $this->forge->dropTable('metodos_pago', true);
        $this->forge->dropTable('unidades_medida', true);
        $this->forge->dropTable('categorias', true);
        $this->forge->dropTable('tasas_cambio', true);
        $this->forge->dropTable('usuarios', true);
    }
}
