<?php

namespace Tests\Api;

use App\Database\Seeds\InitialFerrePosSeeder;
use App\Libraries\JwtLib;
use App\Models\ProductoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class InventarioApiTest extends CIUnitTestCase
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

    public function testCrearEntradaCompraIncrementaStockYKardex(): void
    {
        $productoModel = new ProductoModel();
        $prodAntes     = $productoModel->find(1);
        $stockAntes    = (float) $prodAntes['stock_actual']; // 300.000 metros

        // Registrar compra de 2 rollos de cable (factor 100 -> 200 metros)
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'numero_documento' => 'FACT-PROV-9988',
            'proveedor_nombre' => 'Distribuidora Eléctrica Central',
            'moneda_ingreso'   => 'USD',
            'items'            => [
                [
                    'producto_id'       => 1,
                    'unidad_compra_id'  => 6, // ROLLO
                    'cantidad_comprada' => 2.000,
                    'costo_unitario'    => 50.00,
                ],
            ],
        ]))->post('api/inventario/entradas');

        $result->assertStatus(201);

        $prodDespues = $productoModel->find(1);
        $this->assertSame(round($stockAntes + 200.000, 3), (float) $prodDespues['stock_actual']);
    }

    public function testCrearAjustePositivo(): void
    {
        $productoModel = new ProductoModel();
        $prodAntes     = $productoModel->find(2);
        $stockAntes    = (float) $prodAntes['stock_actual'];

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'producto_id'     => 2,
            'tipo_movimiento' => 'AJUSTE_POSITIVO',
            'cantidad'        => 5.500,
            'observacion'     => 'Conteo físico de inventario fin de mes',
        ]))->post('api/inventario/ajustes');

        $result->assertStatus(201);

        $prodDespues = $productoModel->find(2);
        $this->assertSame(round($stockAntes + 5.500, 3), (float) $prodDespues['stock_actual']);
    }

    public function testAjusteNegativoFallaSiSuperaStock(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'producto_id'     => 2,
            'tipo_movimiento' => 'AJUSTE_NEGATIVO',
            'cantidad'        => 999999.000,
            'observacion'     => 'Ajuste excesivo',
        ]))->post('api/inventario/ajustes');

        $result->assertStatus(422);
    }

    public function testConsultarKardex(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('api/inventario/kardex');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json['data']);
    }

    public function testCajeroBloqueadoEnModuloInventario(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
        ])->get('api/inventario/kardex');

        $result->assertStatus(403);
    }
}
