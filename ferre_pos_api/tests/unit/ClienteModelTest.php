<?php

namespace Tests\Unit;

use App\Database\Seeds\InitialFerrePosSeeder;
use App\Models\ClienteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ClienteModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialFerrePosSeeder::class;

    private ClienteModel $clienteModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clienteModel = new ClienteModel();
    }

    public function testNormalizarDocumento(): void
    {
        $this->assertSame('V-12345678', ClienteModel::normalizarDocumento('  v-12345678  '));
        $this->assertSame('J-98765432-1', ClienteModel::normalizarDocumento('j-98765432-1'));
        $this->assertSame('', ClienteModel::normalizarDocumento('   '));
    }

    public function testGuardarYBuscarCliente(): void
    {
        // 1. Guardar nuevo cliente
        $cliente = $this->clienteModel->guardarPorDocumento([
            'documento' => 'V-20111222',
            'nombre'    => 'Carlos Pérez',
            'telefono'  => '04141234567',
            'email'     => 'carlos@example.com',
            'direccion' => 'Av. Bolívar, Local 1',
        ]);

        $this->assertNotEmpty($cliente);
        $this->assertSame('V-20111222', $cliente['documento']);
        $this->assertSame('Carlos Pérez', $cliente['nombre']);

        // 2. Búsqueda exacta
        $encontrado = $this->clienteModel->buscarPorDocumento('V-20111222');
        $this->assertNotNull($encontrado);
        $this->assertSame($cliente['id'], $encontrado['id']);

        // 3. Búsqueda flexible solo dígitos
        $flexible = $this->clienteModel->buscarPorDocumento('20111222');
        $this->assertNotNull($flexible);
        $this->assertSame($cliente['id'], $flexible['id']);

        // 4. Actualización al guardar con el mismo documento
        $actualizado = $this->clienteModel->guardarPorDocumento([
            'documento' => 'V-20111222',
            'nombre'    => 'Carlos Pérez Actualizado',
            'telefono'  => '04129876543',
        ]);

        $this->assertSame($cliente['id'], $actualizado['id']);
        $this->assertSame('Carlos Pérez Actualizado', $actualizado['nombre']);
        $this->assertSame('04129876543', $actualizado['telefono']);
    }

    public function testBuscarPorDocumentoInexistenteRetornaNull(): void
    {
        $res = $this->clienteModel->buscarPorDocumento('V-99999999');
        $this->assertNull($res);
    }
}

