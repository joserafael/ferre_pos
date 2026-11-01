# FerrePOS - Manual de Usuario y Operaciones

Sistema Integral de Punto de Venta (POS), Inventario Fraccionado y Facturación Bimonetaria (USD / VES) diseñado para ferreterías y comercios afines en Venezuela.

---

## Tabla de Contenidos

1. [Introducción y Conceptos Fundamentales](#1-introducción-y-conceptos-fundamentales)
2. [Acceso al Sistema y Roles](#2-acceso-al-sistema-y-roles)
3. [Gestión de la Tasa de Cambio Oficial (USD / VES)](#3-gestión-de-la-tasa-de-cambio-oficial-usd--ves)
4. [Punto de Venta (POS) y Venta Fraccionada](#4-punto-de-venta-pos-y-venta-fraccionada)
5. [Cobro y Multipago Mixto (USD / VES)](#5-cobro-y-multipago-mixto-usd--ves)
6. [Gestión de Inventario, Compras y Kardex](#6-gestión-de-inventario-compras-y-kardex)
7. [Dashboard Operativo y Cuadre de Caja](#7-dashboard-operativo-y-cuadre-de-caja)
8. [Historial de Facturas y Anulaciones](#8-historial-de-facturas-y-anulaciones)
9. [Preguntas Frecuentes y Solución de Problemas](#9-preguntas-frecuentes-y-solución-de-problemas)

---

## 1. Introducción y Conceptos Fundamentales

FerrePOS está diseñado para responder a las dinámicas del comercio ferretero venezolano:

- **Moneda Ancla (USD)**: Todos los costos, márgenes de ganancia y precios base de venta se estructuran en Dólares Estadounidenses (USD) para proteger el valor de reposición del inventario ante fluctuaciones cambiarias.
- **Facturación Bimonetaria Transparente**: Los precios en Bolívares (VES) se calculan en vivo mediante la Tasa Oficial del Banco Central de Venezuela (BCV). Al momento de emitir una factura, la tasa queda **congelada e inmutable** en el registro fiscal.
- **Inventario Fraccionado de Alta Precisión (`DECIMAL 12,3`)**: Soporte nativo para vender y controlar existencias de productos por metros (cables, mangueras), kilogramos (clavos, alambres, tornillos a granel) o litros (thinner, pinturas, solventes) con 3 decimales de exactitud (ej. `0.250 KG`, `1.500 M`).
- **Kardex Inmutable**: Cada entrada, venta o ajuste genera un registro auditable e irreversible que garantiza la trazabilidad física y contable del almacén.

---

## 2. Acceso al Sistema y Roles

### 2.1. Inicio de Sesión
Ingrese a la dirección web local del sistema (por defecto `http://localhost:5173` o la dirección IP de su servidor de red local) e introduzca sus credenciales de usuario.

### 2.2. Perfiles y Permisos

| Rol | Módulos Accesibles | Responsabilidades Principales |
| :--- | :--- | :--- |
| **ADMIN** (Administrador) | Acceso total: Dashboard, POS, Facturación, Catálogo de Productos, Compras/Kardex, Tasas de Cambio, Categorías y Gestión de Cajeros. | Fijar la tasa oficial diaria, registrar compras a proveedores, dar de alta productos, realizar ajustes de inventario y anular facturas. |
| **CAJERO** (Operador de Caja) | Punto de Venta (POS), Búsqueda de Productos, Emisión de Tickets de Venta y Consulta de Facturas Realizadas en su turno. | Atender al cliente en mostrador, despachar artículos fraccionados, registrar pagos mixtos y emitir comprobantes. |

---

## 3. Gestión de la Tasa de Cambio Oficial (USD / VES)

La tasa de cambio oficial rige la conversión automática de todos los precios visibles en el mostrador y en los tickets.

![Gestión de Tasa de Cambio](../imagenes/1.png)

### 3.1. Consulta de la Tasa Activa
En la barra superior de la aplicación siempre se visualiza la tasa activa en formato:
`TASA ACTIVA (BCV Oficial): Bs. [TASA] / $1`.

### 3.2. Actualizar la Tasa de Cambio Diaria (Rol Administrador)
1. En la barra superior, haga clic en el botón amarillo **Cambiar** o navegue al módulo **Tasas de Cambio**.
2. En la ventana modal:
   - **Tasa Oficial (Bs. por 1 USD)**: Ingrese el valor publicado por el Banco Central de Venezuela (con hasta 4 decimales, ej. `889.5754`).
   - **Fuente / Referencia**: Por defecto "BCV Oficial" o la especificación correspondiente a la fecha.
3. Haga clic en **Guardar y Activar**.
4. Inmediatamente todos los terminales y pantallas del POS actualizarán los precios en Bolívares en tiempo real.
5. El sistema conserva un historial inmutable con fecha, hora exacta y el usuario que realizó la actualización.

> **Importante**: Actualizar la tasa **no altera** las facturas previamente emitidas. Cada factura conserva para siempre la tasa con la que fue procesada.

---

## 4. Punto de Venta (POS) y Venta Fraccionada

La interfaz del POS está optimizada para trabajo continuo y ágil en mostrador, permitiendo operaciones táctiles o con teclado y lector de código de barras.

![Punto de Venta Bimonetario](../imagenes/2.png)

### 4.1. Búsqueda y Selección de Productos
- **Buscador**: Ingrese el nombre del producto, SKU o escanee el código de barras físico.
- **Filtros por Categoría**: Utilice los botones superiores (*Construcción, Electricidad, Herramientas, Pinturas y Químicos, Plomería, Tornillería*) para filtrar rápidamente los artículos.

### 4.2. Venta de Productos Fraccionados
Los productos fraccionables se identifican con una etiqueta destacada (ej. `M (Fraccionable)` o `KG (Fraccionable)`):
1. **Atajos Rápidos**: Puede pulsar directamente sobre los botones rápidos:
   - En metros: `+0.25 M`, `+0.50 M`, `+2.50 M`.
   - En kilogramos: `+0.25 KG`, `+0.50 KG`, `+2.50 KG`.
2. **Cantidad Personalizada**: Haga clic en **+ Agregar** e introduzca la cantidad exacta requerida por el cliente (ejemplo: `1.350 M` de cable o `0.750 KG` de clavos).

### 4.3. Datos del Cliente en el Ticket
1. En el panel lateral derecho (**Ticket de Venta Bimonetario**), introduzca la **Cédula o RIF** del cliente en el campo obligatorio (ejemplo: `V-12345678` o `J-40123456-7`).
2. Haga clic en **Buscar** para cargar clientes frecuentes o complete sus datos de contacto.
3. El ticket desglosará automáticamente:
   - **Subtotal (USD y VES)**
   - **IVA 16% (USD y VES)**
   - **TOTAL EN DÓLARES (Moneda Ancla) y TOTAL EN BOLÍVARES (Tasa Activa)**

---

## 5. Cobro y Multipago Mixto (USD / VES)

Para adaptarse a la realidad financiera de Venezuela, FerrePOS permite cancelar una misma factura combinando diversos métodos de pago en divisas y moneda nacional.

![Cobro Multipago Mixto](../imagenes/3.png)

### 5.1. Flujo de Cobro Paso a Paso
1. En el Ticket de Venta, haga clic en el botón **Cobrar / Multipago Mixto (USD + Bs.)**.
2. Se desplegará la ventana de liquidación con la **tasa congelada** de la transacción.
3. Seleccione el **Método de Pago**:
   - `Efectivo USD`: Pagos en dólares en billete (no requiere referencia).
   - `Efectivo VES`: Pagos en bolívares en efectivo (no requiere referencia).
   - `Punto Débito (VES)`: Tarjetas de débito bancarias (requiere número de aprobación/lote).
   - `Punto Crédito (VES)`: Tarjetas de crédito bancarias (requiere número de aprobación/lote).
   - `Pago Móvil (VES)`: Transferencia interbancaria móvil (requiere últimos dígitos de referencia).
   - `Zelle (USD)`: Transferencia en divisas (requiere nombre o referencia).
   - `Binance Pay (USD)`: Criptoactivos USDT (requiere Order ID).
4. Ingrese el **Monto** en la moneda correspondiente y la **Referencia** (si aplica).
5. Haga clic en **+ Añadir**. El abono se sumará a la lista inferior calculando su equivalente exacto en dólares.
6. **Repita el paso anterior** para abonar otros métodos hasta que el **Total Factura** esté cubierto.

### 5.2. Control de Vueltos y Excedentes
- Si el cliente entrega un monto superior al total (por ejemplo, billete de `$50.00` en una factura de `$29.06`), el sistema calculará en tiempo real el **Excedente / Vuelto** tanto en USD (`$20.94`) como su equivalente en Bolívares.
- Una vez cubierto o superado el total, pulse el botón verde **Emitir Factura y Descontar Kardex**.

---

## 6. Gestión de Inventario, Compras y Kardex

El inventario de FerrePOS opera bajo el principio de **Kardex Inmutable**: ninguna existencia se modifica sin dejar rastro auditable.

![Kardex de Inventario](../imagenes/4.png)

### 6.1. Tipos de Movimiento en el Kardex
- `ENTRADA_COMPRA`: Incremento por recepción de mercancía de proveedores.
- `SALIDA_VENTA`: Descuento automático generado inmediatamente al facturar en el POS.
- `ANULACION_VENTA`: Devolución atómica de stock por anulación de factura autorizada.
- `AJUSTE_POSITIVO` / `AJUSTE_NEGATIVO`: Correcciones justificadas por inventario físico o mermas.

### 6.2. Factor de Conversión de Compras
Muchos productos ferreteros se compran en empaques mayoristas y se venden al detal:
- **Ejemplo**: Se compra un rollo de cable (`ROLLO`) que contiene `100.000` metros.
- Al registrar la compra de `2 ROLLOS`, FerrePOS utiliza el **factor de conversión** (`100.000`) para ingresar automáticamente `200.000 M` al inventario de venta.

### 6.3. Auditoría de Existencias
En la tabla del Kardex podrá auditar en todo momento:
- Fecha y hora exacta.
- Tipo de movimiento.
- Cantidad con 3 decimales (`1.000 M`, `40.000 L`).
- **Saldo Anterior -> Saldo Resultante** (ejemplo: `300.000 -> 299.000`).
- Costo unitario en USD y VES al momento del movimiento.
- Documento de respaldo (ej. `Venta POS Factura FAC-000001` o número de factura del proveedor).

---

## 7. Dashboard Operativo y Cuadre de Caja

El Dashboard permite a los administradores y encargados monitorear en tiempo real la salud operativa y financiera del negocio.

![Dashboard Operativo](../imagenes/5.png)

### 7.1. Indicadores Principales
1. **Ventas de Hoy**: Total facturado en la jornada expresado en USD y VES, y cantidad de comprobantes emitidos.
2. **Facturación Acumulada**: Histórico de facturación del período.
3. **Valorización del Inventario**: Costo total del inventario disponible y proyección de venta estimada.
4. **Tasa BCV Aplicada**: Tasa que está rigiendo las transacciones actuales.

### 7.2. Recaudación por Método de Pago (Cuadre de Turno)
El panel de recaudación muestra el monto original recaudado y su equivalente en dólares por cada canal:
- Total en Punto de Débito (VES).
- Total en Efectivo (Bs.).
- Total en Efectivo ($).
- Total en Pago Móvil y canales digitales.

Este reporte permite al cajero y al administrador contrastar los lotes del punto de venta y el dinero en gaveta al finalizar el turno.

### 7.3. Monitor de Stock Crítico
Muestra alertas automáticas de productos cuyo stock actual sea igual o menor a su `stock_minimo`, indicando la necesidad de emitir órdenes de compra a proveedores.

---

## 8. Historial de Facturas y Anulaciones

### 8.1. Consulta de Ventas
En el módulo **Facturas & Histórico** se pueden consultar todas las ventas emitidas, filtrando por rango de fechas, número de factura o cliente. Al abrir una factura, se observa el desglose de productos, la tasa congelada en ese instante y los métodos de pago empleados.

### 8.2. Procedimiento de Anulación (Solo Administrador)
1. Localice la factura en el historial.
2. Haga clic en **Anular Factura**.
3. Indique el motivo de la anulación (devolución del cliente, error de carga, etc.).
4. Al confirmar, el sistema ejecuta una transacción atómica:
   - Marca la factura como `ANULADA`.
   - Genera automáticamente un movimiento `ANULACION_VENTA` en el Kardex devolviendo cada artículo y fracción al inventario disponible.
   - Ajusta los reportes de recaudación y ventas netas.

---

## 9. Preguntas Frecuentes y Solución de Problemas

### ¿Qué sucede si la tasa del BCV cambia a mitad de la tarde?
El administrador debe actualizar la tasa en el módulo de Tasas. A partir de ese segundo, todas las nuevas cotizaciones y tickets del POS adoptarán la nueva tasa. Las facturas ya emitidas en la mañana no sufrirán ninguna modificación, protegiendo la integridad contable.

### ¿Cómo registrar la venta de 1/2 kilo de clavos o 3/4 de metro de manguera?
En el catálogo del POS, pulse el producto y en el campo de cantidad ingrese `0.500` (para 1/2 kilo) o `0.750` (para 3/4 de metro). El sistema calculará el precio exacto proporcional a la fracción.

### ¿Un cliente puede pagar una parte con tarjeta de débito y otra con un billete de $20?
Sí. En el modal de Cobro Multipago, ingrese primero el abono en Dólares (`$20.00` en `Efectivo USD`) y luego ingrese el monto restante por `Punto Débito` con su respectivo número de referencia.

### ¿Dónde verificar el inventario de un producto con dudas de existencia?
Consulte el módulo **Kardex** filtrando por el producto deseado. Podrá ver línea por línea cada factura donde salió mercancía y cada compra donde ingresó, con la fecha y el usuario responsable.

---

*FerrePOS — Sistema Ferretero Bimonetario de Venezuela.*

