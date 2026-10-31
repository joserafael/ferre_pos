<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BimonetarioMathTest extends CIUnitTestCase
{
    public function testCalculoDinámicoPrecioBolivares(): void
    {
        $precioUsd   = 12.50;
        $tasaCambio  = 42.5000;
        $precioVes   = round($precioUsd * $tasaCambio, 2);

        $this->assertSame(531.25, $precioVes);
    }

    public function testPrecisionDecimalesInventarioFraccionado(): void
    {
        // Ferretería: Venta de cable por metro o clavos por kilo (3 decimales)
        $stockInicial  = 100.500;
        $venta1        = 2.350; // metros
        $venta2        = 0.150; // 150 gramos o cm
        $stockRestante = round($stockInicial - $venta1 - $venta2, 3);

        $this->assertSame(98.000, $stockRestante);
    }

    public function testFactorConversionCompraVenta(): void
    {
        // 1 ROLLO = 100 Metros
        $cantidadCompradaRollos = 5;
        $factorConversion       = 100.000;
        $stockIngresadoMetros   = round($cantidadCompradaRollos * $factorConversion, 3);

        $this->assertSame(500.000, $stockIngresadoMetros);

        // Costo por rollo: 50 USD -> Costo por metro: 0.50 USD
        $costoRolloUsd = 50.00;
        $costoMetroUsd = round($costoRolloUsd / $factorConversion, 2);
        $this->assertSame(0.50, $costoMetroUsd);
    }

    public function testPagoMixtoBimonetario(): void
    {
        $totalVentaUsd = 100.00;
        $tasa          = 42.5000;

        // Pagos: 50 USD en efectivo + resto en Pago Móvil (VES)
        $pagoUsd = 50.00;
        $restoUsd = $totalVentaUsd - $pagoUsd;
        $pagoVes = round($restoUsd * $tasa, 2);

        $this->assertSame(2125.00, $pagoVes);

        $montoEquivalenteUsd = round($pagoUsd + ($pagoVes / $tasa), 2);
        $this->assertSame($totalVentaUsd, $montoEquivalenteUsd);
    }
}

