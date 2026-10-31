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
final class VentasApiTest extends CIUnitTestCase
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

    public function testCajeroPuedeCrearVentaMultipago(): void
    {
        $productoModel = new ProductoModel();
        $prodAntes     = $productoModel->find(1);
        $stockAntes    = (float) $prodAntes['stock_actual']; // 300.000 metros

        // Producto 1: precio_venta_usd = 0.85, iva = 16% -> 0.85 * 10 metros = 8.50 + 1.36 iva = 9.86 USD
        // Tasa activa = 42.5000
        // Total USD = 9.86 USD -> en VES = 419.05 VES
        // Pago: 5.00 USD en efectivo (metodo 2) + 210.00 VES en Pago Móvil (metodo 7) = 5.00 + 4.94 = 9.94 USD
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'cliente_documento' => 'V-15222333',
            'cliente_nombre'    => 'José Gregorio',
            'items'             => [
                [
                    'producto_id' => 1,
                    'cantidad'    => 10.000,
                ],
            ],
            'pagos'             => [
                [
                    'metodo_pago_id'         => 2, // EFECTIVO_USD
                    'monto_original'         => 5.00,
                    'referencia_transaccion' => '',
                ],
                [
                    'metodo_pago_id'         => 7, // PAGO_MOVIL
                    'monto_original'         => 210.00,
                    'referencia_transaccion' => 'REF-987654',
                ],
            ],
        ]))->post('api/ventas');

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('COMPLETADA', $json['data']['estado']);
        $this->assertSame('V-15222333', $json['data']['cliente_documento']);
        $this->assertNotEmpty($json['data']['numero_factura']);

        // Stock reducido en 10 metros
        $prodDespues = $productoModel->find(1);
        $this->assertSame(round($stockAntes - 10.000, 3), (float) $prodDespues['stock_actual']);
    }

    public function testVentaRechazadaPorPagoInsuficiente(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'cliente_documento' => 'V-12345678',
            'items'             => [
                [
                    'producto_id' => 1,
                    'cantidad'    => 10.000,
                ],
            ],
            'pagos'             => [
                [
                    'metodo_pago_id' => 2, // USD
                    'monto_original' => 1.00, // Insuficiente (total es ~9.86)
                ],
            ],
        ]))->post('api/ventas');

        $result->assertStatus(422);
    }

    public function testAdminPuedeAnularVentaYRestaurarStock(): void
    {
        $productoModel = new ProductoModel();
        $prodInicial   = $productoModel->find(2);
        $stockInicial  = (float) $prodInicial['stock_actual'];

        // 1. Crear venta
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'cliente_documento' => 'V-17888999',
            'items'             => [
                [
                    'producto_id' => 2,
                    'cantidad'    => 2.500, // Kg de clavos
                ],
            ],
            'pagos'             => [
                [
                    'metodo_pago_id'         => 2,
                    'monto_original'         => 50.00,
                    'referencia_transaccion' => '',
                ],
            ],
        ]))->post('api/ventas');

        $ventaId = json_decode($createRes->getJSON(), true)['data']['id'];

        $prodDespuesVenta = $productoModel->find(2);
        $this->assertSame(round($stockInicial - 2.500, 3), (float) $prodDespuesVenta['stock_actual']);

        // 2. Admin anula la venta
        $anularRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'motivo' => 'Devolución de cliente por error de material',
        ]))->post("api/ventas/{$ventaId}/anular");

        $anularRes->assertStatus(200);

        // 3. Stock restaurado
        $prodRestaurado = $productoModel->find(2);
        $this->assertSame($stockInicial, (float) $prodRestaurado['stock_actual']);
    }

    public function testCajeroNoPuedeAnularVenta(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->cajeroToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'motivo' => 'Intento cajero',
        ]))->post('api/ventas/1/anular');

        $result->assertStatus(403);
    }
}
