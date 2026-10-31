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
final class ClientesApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    private string $cajeroToken;

    protected function setUp(): void
    {
        parent::setUp();
        $jwt = new JwtLib();
        $this->cajeroToken = $jwt->encode(['uid' => 2, 'username' => 'cajero', 'rol' => 'CAJERO']);
    }

    public function testCrearClienteExitoso(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'documento' => 'V-19888777',
            'nombre'    => 'María Rodríguez',
            'telefono'  => '04245551234',
            'email'     => 'maria@example.com',
            'direccion' => 'Calle Los Claveles #5',
        ]))->post('api/clientes');

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame(201, $json['status']);
        $this->assertSame('V-19888777', $json['data']['documento']);
        $this->assertSame('María Rodríguez', $json['data']['nombre']);
    }

    public function testCrearClienteFallaSinDocumento(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'nombre' => 'Cliente Sin Documento',
        ]))->post('api/clientes');

        $result->assertStatus(422);
    }

    public function testBuscarClientePorDocumento(): void
    {
        // 1. Crear cliente
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'documento' => 'J-30405060-0',
            'nombre'    => 'Constructora Alfa C.A.',
        ]))->post('api/clientes');

        // 2. Buscar por endpoint
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
        ])->get('api/clientes/buscar?documento=J-30405060-0');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('J-30405060-0', $json['data']['documento']);
        $this->assertSame('Constructora Alfa C.A.', $json['data']['nombre']);
    }

    public function testActualizarCliente(): void
    {
        // 1. Crear cliente
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'documento' => 'V-25666777',
            'nombre'    => 'Pedro Gómez',
        ]))->post('api/clientes');

        $id = json_decode($createRes->getJSON(), true)['data']['id'];

        // 2. Actualizar cliente
        $updateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'nombre'   => 'Pedro A. Gómez',
            'telefono' => '04149998877',
        ]))->put("api/clientes/{$id}");

        $updateRes->assertStatus(200);
        $json = json_decode($updateRes->getJSON(), true);
        $this->assertSame('Pedro A. Gómez', $json['data']['nombre']);
        $this->assertSame('04149998877', $json['data']['telefono']);
    }
}

