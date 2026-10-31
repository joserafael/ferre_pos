<?php

namespace Tests\Api;

use App\Database\Seeds\InitialFerrePosSeeder;
use App\Libraries\JwtLib;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class CajerosApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    private string $adminToken;
    private string $cajeroToken;

    protected function setUp(): void
    {
        parent::setUp();
        $jwt = new JwtLib();
        $this->adminToken  = $jwt->encode(['uid' => 1, 'username' => 'admin', 'rol' => 'ADMIN']);
        $this->cajeroToken = $jwt->encode(['uid' => 2, 'username' => 'cajero', 'rol' => 'CAJERO']);
    }

    public function testAdminPuedeListarCajeros(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('api/cajeros');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertNotEmpty($json['data']);
        $this->assertSame('cajero', $json['data'][0]['username']);
    }

    public function testAdminPuedeCrearCajero(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'nombre'   => 'Luis Mendoza',
            'username' => 'luism',
            'password' => 'cajero456',
            'email'    => 'luis@ferrepos.local',
        ]))->post('api/cajeros');

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('luism', $json['data']['username']);
        $this->assertSame('CAJERO', $json['data']['rol']);
    }

    public function testAdminPuedeActualizarCajero(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'nombre' => 'Cajero Principal Modificado',
            'activo' => 1,
        ]))->put('api/cajeros/2');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Cajero Principal Modificado', $json['data']['nombre']);
    }

    public function testAdminPuedeVerVentasDeCajero(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('api/cajeros/2/ventas');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('ventas', $json['data']);
        $this->assertArrayHasKey('resumen', $json['data']);
    }

    public function testCajeroBloqueadoEnGestionDeCajeros(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
        ])->get('api/cajeros');

        $result->assertStatus(403);
    }
}

