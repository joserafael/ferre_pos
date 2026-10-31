<?php

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class SchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = \App\Database\Seeds\InitialFerrePosSeeder::class;

    public function testMigrationsAndSeedRun(): void
    {
        $this->assertTrue($this->db->tableExists('usuarios'));
        $this->assertTrue($this->db->tableExists('tasas_cambio'));
        $this->assertTrue($this->db->tableExists('categorias'));
        $this->assertTrue($this->db->tableExists('unidades_medida'));
        $this->assertTrue($this->db->tableExists('productos'));
        $this->assertTrue($this->db->tableExists('clientes'));
        $this->assertTrue($this->db->tableExists('ventas'));

        $user = $this->db->table('usuarios')->where('username', 'admin')->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertSame('ADMIN', $user['rol']);

        $tasa = $this->db->table('tasas_cambio')->where('activa', 1)->get()->getRowArray();
        $this->assertNotNull($tasa);
    }
}
