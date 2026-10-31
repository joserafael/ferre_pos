<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientesTable extends Migration
{
    public function up(): void
    {
        $tableAttrs = $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'] : [];

        // 1. Tabla clientes (solo documento es obligatorio)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'documento'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefono'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'direccion'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('documento');
        $this->forge->createTable('clientes', true, $tableAttrs);

        // 2. Agregar campos de cliente a la tabla ventas
        $fields = [
            'cliente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'numero_factura',
            ],
            'cliente_telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'cliente_nombre',
            ],
            'cliente_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'cliente_telefono',
            ],
            'cliente_direccion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'cliente_email',
            ],
        ];
        $this->forge->addColumn('ventas', $fields);

        // Permitir que cliente_nombre sea NULL en ventas
        if ($this->db->DBDriver === 'MySQLi') {
            $this->forge->modifyColumn('ventas', [
                'cliente_nombre' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
            ]);
        }

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE `ventas` ADD CONSTRAINT `ventas_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        // 3. Poblar tabla clientes con los clientes existentes en ventas
        $now = date('Y-m-d H:i:s');
        $ventasExistentes = $this->db->table('ventas')
            ->select('cliente_documento, MAX(cliente_nombre) as cliente_nombre')
            ->where('cliente_documento IS NOT NULL')
            ->where('cliente_documento !=', '')
            ->groupBy('cliente_documento')
            ->get()
            ->getResultArray();

        foreach ($ventasExistentes as $v) {
            $doc = strtoupper(trim($v['cliente_documento']));
            if ($doc === '') {
                continue;
            }
            $this->db->table('clientes')->ignore(true)->insert([
                'documento'  => $doc,
                'nombre'     => $v['cliente_nombre'] ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $cliente = $this->db->table('clientes')->where('documento', $doc)->get()->getRowArray();
            if ($cliente) {
                $this->db->table('ventas')
                    ->where('cliente_documento', $v['cliente_documento'])
                    ->update(['cliente_id' => $cliente['id']]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE `ventas` DROP FOREIGN KEY `ventas_cliente_id_foreign`');
        }
        $this->forge->dropColumn('ventas', ['cliente_id', 'cliente_telefono', 'cliente_email', 'cliente_direccion']);
        $this->forge->dropTable('clientes', true);
    }
}
