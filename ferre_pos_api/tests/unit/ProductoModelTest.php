<?php

namespace Tests\Unit;

use App\Database\Seeds\InitialFerrePosSeeder;
use App\Models\ProductoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ProductoModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    private ProductoModel $productoModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productoModel = new ProductoModel();
    }

    public function testGetConRelacionesCalculaPreciosEnVES(): void
    {
        $productos = $this->productoModel->getConRelaciones();
        $this->assertNotEmpty($productos);

        $prod1 = null;
        foreach ($productos as $p) {
            if ($p['id'] === 1) {
                $prod1 = $p;
                break;
            }
        }

        $this->assertNotNull($prod1);
        $this->assertSame('Cable THW #12 AWG 7 Pelos Cobre (Venta por Metro)', $prod1['nombre']);
        $this->assertSame('Electricidad', $prod1['categoria_nombre']);
        $this->assertSame('ROLLO', $prod1['unidad_compra_codigo']);
        $this->assertSame('M', $prod1['unidad_venta_codigo']);
        $this->assertSame(1, $prod1['permite_decimales']);

        // Tasa activa inicial en Seeder es 42.5000
        // precio_venta_usd = 0.85 -> precio_venta_ves = round(0.85 * 42.5000, 2) = 36.13
        $this->assertSame(0.85, (float) $prod1['precio_venta_usd']);
        $this->assertSame(36.13, (float) $prod1['precio_venta_ves']);
    }

    public function testFiltrarPorCategoria(): void
    {
        // Categoría 1 = Electricidad
        $electricidad = $this->productoModel->getConRelaciones(null, 1);
        $this->assertNotEmpty($electricidad);

        foreach ($electricidad as $p) {
            $this->assertSame(1, $p['categoria_id']);
        }
    }

    public function testBuscarPorTermino(): void
    {
        $resultado = $this->productoModel->getConRelaciones('THW');
        $this->assertNotEmpty($resultado);
        $this->assertStringContainsString('THW', $resultado[0]['nombre']);
    }
}

