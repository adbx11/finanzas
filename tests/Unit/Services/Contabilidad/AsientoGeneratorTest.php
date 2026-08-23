<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Models\Ingreso;
use App\Models\Moneda;
use App\Models\Pago;
use App\Services\Contabilidad\AsientoGenerator;
use Carbon\Carbon;
use Tests\TestCase;

class AsientoGeneratorTest extends TestCase
{
    public function test_from_ingreso_balances_debe_and_haber(): void
    {
        $moneda = new Moneda(['id' => 1, 'codigo' => 'ARS', 'simbolo' => '$', 'local' => true]);
        $concepto = new Cuenta(['id' => 10, 'descripcion' => 'Ventas']);
        $destino = new Cuenta(['id' => 20, 'descripcion' => 'CA BBVA']);

        $ingreso = new Ingreso([
            'fecha' => Carbon::parse('2026-07-03'),
            'importe' => '800000.00',
            'cotizacion' => '1',
            'comentarios' => 'Test',
        ]);
        $ingreso->setRelation('moneda', $moneda);
        $ingreso->setRelation('cuentaConcepto', $concepto);
        $ingreso->setRelation('cuentaDestino', $destino);
        $ingreso->id_cuenta_concepto = 10;
        $ingreso->id_cuenta_destino = 20;
        $ingreso->id_moneda = 1;

        $asiento = (new AsientoGenerator())->fromIngreso($ingreso);

        $this->assertInstanceOf(Asiento::class, $asiento);
        $this->assertCount(2, $asiento->items);

        $totalDebe = $asiento->items->sum(fn ($i) => (float) $i->debe);
        $totalHaber = $asiento->items->sum(fn ($i) => (float) $i->haber);

        $this->assertEquals($totalDebe, $totalHaber);
        $this->assertEquals(800000.0, $totalDebe);
    }

    public function test_from_pago_balances_debe_and_haber(): void
    {
        $monedaLocal = new Moneda(['id' => 1, 'codigo' => 'ARS', 'simbolo' => '$', 'local' => true]);
        $concepto = new Cuenta(['id' => 30, 'descripcion' => 'Supermercado']);
        $origen = new Cuenta(['id' => 40, 'descripcion' => 'CA Galicia']);

        $pago = new Pago([
            'fecha' => Carbon::parse('2026-07-05'),
            'importe' => '15000.00',
            'cotizacion' => '1',
            'comentarios' => 'Compras',
        ]);
        $pago->setRelation('moneda', $monedaLocal);
        $pago->setRelation('cuentaConcepto', $concepto);
        $pago->setRelation('cuentaOrigen', $origen);
        $pago->id_cuenta_concepto = 30;
        $pago->id_cuenta_origen = 40;
        $pago->id_moneda = 1;
        $pago->cuota = null;

        $asiento = (new AsientoGenerator())->fromPago($pago);

        $totalDebe = $asiento->items->sum(fn ($i) => (float) $i->debe);
        $totalHaber = $asiento->items->sum(fn ($i) => (float) $i->haber);

        $this->assertEquals($totalDebe, $totalHaber);
        $this->assertEquals(15000.0, $totalDebe);
    }

    public function test_from_pago_negativo_invierte_debe_y_haber(): void
    {
        $monedaLocal = new Moneda(['id' => 1, 'codigo' => 'ARS', 'simbolo' => '$', 'local' => true]);
        $concepto = new Cuenta(['id' => 30, 'descripcion' => 'Supermercado']);
        $origen = new Cuenta(['id' => 40, 'descripcion' => 'CA Galicia']);

        $pago = new Pago([
            'fecha' => Carbon::parse('2026-07-05'),
            'importe' => '-15000.00',
            'cotizacion' => '1',
            'comentarios' => 'Reverso',
        ]);
        $pago->setRelation('moneda', $monedaLocal);
        $pago->setRelation('cuentaConcepto', $concepto);
        $pago->setRelation('cuentaOrigen', $origen);
        $pago->id_cuenta_concepto = 30;
        $pago->id_cuenta_origen = 40;
        $pago->id_moneda = 1;
        $pago->cuota = null;

        $asiento = (new AsientoGenerator())->fromPago($pago);

        $itemConcepto = $asiento->items->firstWhere('id_cuenta', 30);
        $itemOrigen = $asiento->items->firstWhere('id_cuenta', 40);

        $this->assertSame('0', (string) $itemConcepto->debe_origen);
        $this->assertSame('15000.00', (string) $itemConcepto->haber_origen);
        $this->assertSame('15000.00', (string) $itemOrigen->debe_origen);
        $this->assertSame('0', (string) $itemOrigen->haber_origen);

        $totalDebe = $asiento->items->sum(fn ($i) => (float) $i->debe);
        $totalHaber = $asiento->items->sum(fn ($i) => (float) $i->haber);
        $this->assertEquals($totalDebe, $totalHaber);
        $this->assertEquals(15000.0, $totalDebe);
    }

    public function test_from_pago_cuota_negativo_invierte_debe_y_haber(): void
    {
        $monedaLocal = new Moneda(['id' => 1, 'codigo' => 'ARS', 'simbolo' => '$', 'local' => true]);
        $concepto = new Cuenta(['id' => 30, 'descripcion' => 'Tarjeta Visa']);
        $origen = new Cuenta(['id' => 40, 'descripcion' => 'CA Galicia']);

        $pago = new Pago([
            'fecha' => Carbon::parse('2026-07-05'),
            'importe' => '-5000.00',
            'cotizacion' => '1',
            'comentarios' => 'Cuota reverso',
        ]);
        $pago->setRelation('moneda', $monedaLocal);
        $pago->setRelation('cuentaConcepto', $concepto);
        $pago->setRelation('cuentaOrigen', $origen);
        $pago->id_cuenta_concepto = 30;
        $pago->id_cuenta_origen = 40;
        $pago->id_moneda = 1;
        $pago->cuota = 1;

        $asiento = (new AsientoGenerator())->fromPago($pago);

        $itemConcepto = $asiento->items->firstWhere('id_cuenta', 30);
        $itemOrigen = $asiento->items->firstWhere('id_cuenta', 40);

        $this->assertSame('0', (string) $itemConcepto->debe_origen);
        $this->assertSame('5000.00', (string) $itemConcepto->haber_origen);
        $this->assertSame('5000.00', (string) $itemOrigen->debe_origen);
        $this->assertSame('0', (string) $itemOrigen->haber_origen);

        $totalDebe = $asiento->items->sum(fn ($i) => (float) $i->debe);
        $totalHaber = $asiento->items->sum(fn ($i) => (float) $i->haber);
        $this->assertEquals($totalDebe, $totalHaber);
        $this->assertEquals(5000.0, $totalDebe);
    }
}
