<?php

namespace Tests\Unit;

use App\Database\Seeds\InitialFerrePosSeeder;
use App\Models\TasaCambioModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class TasaCambioModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    private TasaCambioModel $tasaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasaModel = new TasaCambioModel();
    }

    public function testGetActivaRetornaTasaInicial(): void
    {
        $activa = $this->tasaModel->getActiva();

        $this->assertNotNull($activa);
        $this->assertSame(1, $activa['id']);
        $this->assertSame(42.5, (float) $activa['tasa_ves']);
        $this->assertSame(1, $activa['activa']);
    }

    public function testActualizarTasaActiva(): void
    {
        // Desactivar tasas anteriores
        $this->tasaModel->where('activa', 1)->set(['activa' => 0])->update();

        // Insertar nueva tasa
        $nuevaTasaId = $this->tasaModel->insert([
            'tasa_ves'   => 45.7500,
            'fuente'     => 'BCV Oficial',
            'activa'     => 1,
            'usuario_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $activa = $this->tasaModel->getActiva();
        $this->assertNotNull($activa);
        $this->assertSame((int) $nuevaTasaId, $activa['id']);
        $this->assertSame(45.75, (float) $activa['tasa_ves']);
    }
}

