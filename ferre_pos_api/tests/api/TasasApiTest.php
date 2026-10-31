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
final class TasasApiTest extends CIUnitTestCase
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

    public function testGetTasaActivaPublico(): void
    {
        $result = $this->get('api/tasas/activa');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame(200, $json['status']);
        $this->assertSame(42.5, (float) $json['data']['tasa_ves']);
    }

    public function testAdminPuedeCrearNuevaTasa(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'tasa_ves' => 45.2000,
            'fuente'   => 'BCV Oficial',
            'activa'   => 1,
        ]))->post('api/tasas');

        $result->assertStatus(201);

        // Verificar que la nueva tasa es ahora la activa
        $check = $this->get('api/tasas/activa');
        $json  = json_decode($check->getJSON(), true);
        $this->assertSame(45.2, (float) $json['data']['tasa_ves']);
    }

    public function testCajeroNoTienePermisoParaCrearTasa(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'tasa_ves' => 50.0000,
        ]))->post('api/tasas');

        $result->assertStatus(403);
    }
}

