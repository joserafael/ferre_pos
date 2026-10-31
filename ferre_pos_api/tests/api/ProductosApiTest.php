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
final class ProductosApiTest extends CIUnitTestCase
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

    public function testListarProductos(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
        ])->get('api/productos');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertNotEmpty($json['data']);
        $this->assertArrayHasKey('precio_venta_ves', $json['data'][0]);
    }

    public function testVerDetalleProducto(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
        ])->get('api/productos/1');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame(1, $json['data']['id']);
        $this->assertSame('ELE-CAB-12', $json['data']['sku']);
    }

    public function testAdminPuedeCrearProducto(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'sku'               => 'TEST-HER-01',
            'nombre'            => 'Destornillador Phillips 6"',
            'categoria_id'      => 5, // Herramientas
            'unidad_compra_id'  => 1, // UND
            'unidad_venta_id'   => 1, // UND
            'factor_conversion' => 1.0,
            'costo_usd'         => 1.50,
            'precio_venta_usd'  => 3.00,
            'stock_actual'      => 20.000,
            'stock_minimo'      => 5.000,
        ]))->post('api/productos');

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('TEST-HER-01', $json['data']['sku']);
        $this->assertSame(3.00, (float) $json['data']['precio_venta_usd']);
    }

    public function testCajeroNoPuedeCrearProducto(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'sku'              => 'TEST-FAIL-01',
            'nombre'           => 'Producto Prohibido',
            'categoria_id'     => 1,
            'unidad_compra_id' => 1,
            'unidad_venta_id'  => 1,
            'precio_venta_usd' => 10.00,
        ]))->post('api/productos');

        $result->assertStatus(403);
    }

    public function testAdminPuedeActualizarProducto(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'nombre'           => 'Cable THW #12 AWG 7 Pelos Cobre Modificado',
            'categoria_id'     => 1,
            'unidad_compra_id' => 6,
            'unidad_venta_id'  => 2,
            'precio_venta_usd' => 0.95,
        ]))->put('api/productos/1');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame(0.95, (float) $json['data']['precio_venta_usd']);
        $this->assertStringContainsString('Modificado', $json['data']['nombre']);
    }

    public function testAdminPuedeEliminarProducto(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->delete('api/productos/1');

        $result->assertStatus(200);
    }
}

