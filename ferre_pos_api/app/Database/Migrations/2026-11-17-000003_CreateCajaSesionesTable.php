<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCajaSesionesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['ABIERTA', 'CERRADA'],
                'default'    => 'ABIERTA',
            ],
            'monto_apertura_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'monto_apertura_ves' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'tasa_apertura' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
            ],
            'ventas_cantidad' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_ventas_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'total_ventas_ves' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'esperado_efectivo_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'esperado_efectivo_ves' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => '0.00',
            ],
            'contado_efectivo_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
            ],
            'contado_efectivo_ves' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
            ],
            'diferencia_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
            ],
            'diferencia_ves' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
            ],
            'desglose_metodos_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'observacion_apertura' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'observacion_cierre' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'abierta_at' => [
                'type' => 'DATETIME',
            ],
            'cerrada_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['usuario_id', 'estado']);
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('caja_sesiones', true);

        // Vincular cada venta a la sesión de caja en la que se emitió
        $this->forge->addColumn('ventas', [
            'caja_sesion_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'usuario_id',
            ],
        ]);
        $this->db->query('ALTER TABLE ventas ADD CONSTRAINT ventas_caja_sesion_id_foreign FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones(id) ON DELETE SET NULL ON UPDATE RESTRICT');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE ventas DROP FOREIGN KEY ventas_caja_sesion_id_foreign');
        $this->forge->dropColumn('ventas', 'caja_sesion_id');
        $this->forge->dropTable('caja_sesiones', true);
    }
}
