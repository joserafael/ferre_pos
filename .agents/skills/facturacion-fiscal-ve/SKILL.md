---
name: facturacion-fiscal-ve
description: Reglas técnicas, fiscales y de arquitectura para implementar facturación legal en Venezuela (Imprenta Digital API e Impresora Fiscal) dentro de FerrePOS (CodeIgniter 4, MySQL 8, Vue 3), cumpliendo las providencias SNAT/2024/000102 y SNAT/2024/000121.
---

# Skill: Facturación Fiscal Venezuela (FerrePOS)

Este skill define los estándares obligatorios para extender **FerrePOS** con capacidades de emisión fiscal legal en Venezuela, soportando tanto **Imprenta Digital (API REST)** como **Impresora Fiscal (Spooler/SDK local)**.

## 1. Marco Normativo Obligatorio

- **Sin conexión directa al SENIAT:** El backend (`ferre_pos_api`) nunca se conecta directamente a servidores del SENIAT para emitir facturas. La emisión debe delegarse a una **Imprenta Digital Autorizada** (ej. The Factory HKA, Unidigital, CG Imprenta) o a una **Máquina Fiscal homologada**.
- **Providencias aplicables:** Cumplir con **SNAT/2024/000102** (facturación digital) y **SNAT/2024/000121** (homologación de sistemas informáticos).
- **Moneda de expresión:** Aunque el sistema opera con USD como moneda ancla y multipago bimonetario, todo documento fiscal legal debe expresar los montos en **Bolívares (VES)** a la tasa oficial BCV vigente, pudiendo reflejar el equivalente en divisas según normativa.

## 2. Reglas de Base de Datos (MySQL 8 / InnoDB)

Mantener la prohibición absoluta de `FLOAT` o `DOUBLE`:

- **Montos monetarios e impuestos:** `DECIMAL(14,2)`
- **Tasas de cambio:** `DECIMAL(12,4)`
- **Cantidades fraccionadas:** `DECIMAL(12,3)`

### Columnas requeridas en la tabla `ventas`

Al crear migraciones para soporte fiscal, añadir:

- `tipo_emision`: `ENUM('NO_FISCAL', 'IMPRENTA_DIGITAL', 'IMPRESORA_FISCAL')`
- `estado_fiscal`: `ENUM('PENDIENTE', 'EMITIDA', 'ERROR', 'ANULADA')`
- `numero_factura_fiscal`: `VARCHAR(50) NULL`
- `numero_control_fiscal`: `VARCHAR(50) NULL` (devuelto por la Imprenta Digital)
- `serial_maquina_fiscal`: `VARCHAR(50) NULL` (devuelto por Impresora Fiscal)
- `base_imponible_ves`: `DECIMAL(14,2)`
- `monto_exento_ves`: `DECIMAL(14,2)`
- `monto_iva_ves`: `DECIMAL(14,2)`
- `base_igtf_ves`: `DECIMAL(14,2)` (suma de pagos en divisas/cripto)
- `monto_igtf_ves`: `DECIMAL(14,2)` (3% sobre `base_igtf_ves`)
- `url_pdf_fiscal`: `VARCHAR(255) NULL`
- `respuesta_fiscal_raw`: `JSON NULL` (auditoría de respuesta del proveedor/SDK)

## 3. Cálculo Tributario (IVA e IGTF 3%)

1. **IVA:** Calcular por línea de `venta_detalles` según la alícuota del producto (`16.00%`, `8.00%` o `0.00%` Exento).
2. **IGTF (3%):**
   - Aplica exclusivamente sobre la fracción pagada mediante métodos en moneda extranjera o criptoactivos (`EFECTIVO_USD`, `ZELLE`, `BINANCE`).
   - No aplica sobre pagos en Bolívares (`EFECTIVO_VES`, `PUNTO_DEBITO`, `PUNTO_CREDITO`, `PAGO_MOVIL`).
   - Fórmula: `monto_igtf = ROUND(total_pagado_en_divisas * 0.03, 2)`.
   - El IGTF se calcula en el modal de cobro multipago de Vue 3 (`ferre_pos_web`) antes de confirmar la transacción y se valida nuevamente en el Backend (`VentaController`).

## 4. Arquitectura Backend (`ferre_pos_api` - CodeIgniter 4)

Implementar el **Patrón Strategy** bajo `app/Libraries/Fiscal/`:

- `FiscalProviderInterface.php`: Define los métodos `emitirFactura(array $ventaData): array` y `emitirNotaCredito(array $anulacionData): array`.
- `ImprentaDigitalProvider.php`: Implementa la comunicación HTTP REST (usando `Services::curlrequest()`) hacia el proveedor homologado, enviando el payload JSON y capturando el `numero_control_fiscal` y el enlace/base64 del PDF/XML.
- `ImpresoraFiscalProvider.php`: Envía el JSON estructurado hacia un servicio puente local (Spooler HTTP en `localhost` o cola Redis) que interactúa con el SDK del fabricante (ej. TFHKA / Bixolon / Hasar) por puerto Serial/USB.
- `FiscalFactory.php`: Instancia el proveedor configurado en `.env` (`FISCAL_DRIVER=imprenta_digital|impresora_fiscal|none`).

### Flujo Transaccional en `VentaController`

1. Ejecutar dentro de `$db->transStart()` con bloqueo `FOR UPDATE` en inventario.
2. Guardar la venta, detalles, pagos y movimientos de Kardex en MySQL.
3. Invocar `FiscalFactory::make()->emitirFactura($venta)`.
4. Si la emisión fiscal falla, marcar `estado_fiscal = 'ERROR'` (o revertir según configuración de contingencia) y registrar el error en `respuesta_fiscal_raw` sin perder la trazabilidad del inventario.
5. Para anulaciones (`POST /api/ventas/{id}/anular`), emitir obligatoriamente una **Nota de Crédito Fiscal** referenciando el número de factura original antes de reversar el stock en el Kardex.

## 5. Reglas Frontend (`ferre_pos_web` - Vue 3)

- **Datos del cliente obligatorios:** Exigir RIF/Cédula validado con formato venezolano (`^[VEJPG]-[0-9]{7,9}$`), Razón Social, Dirección Fiscal y Teléfono antes de emitir factura fiscal (o permitir "Consumidor Final" solo si el monto total no supera el límite legal vigente en unidades tributarias/bolívares).
- **Desglose en tiempo real:** En el modal de cobro multipago, recalcular dinámicamente el **IGTF (3%)** cada vez que el cajero agregue o elimine un abono en USD (`EFECTIVO_USD`, `ZELLE`, `BINANCE`).
- **Impresión / Descarga:** Mostrar botón para descargar/visualizar el PDF fiscal (Imprenta Digital) o confirmar impresión de ticket fiscal (Impresora Fiscal) tras recibir HTTP 200.
