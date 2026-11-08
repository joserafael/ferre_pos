# FerrePOS Web - Frontend SPA

Aplicación web de página única (**Single Page Application - SPA**) desarrollada con **Vue 3**, **Pinia**, **Vue Router** y **Vite** para la operación del punto de venta (POS), control de inventario fraccionado y facturación bimonetaria (**Dólares estadounidenses - USD** y **Bolívares - VES**) de una ferretería.

---

## Arquitectura y Características Principales

El cliente web se comunica de forma desacoplada con el backend RESTful (`ferre_pos_api`) mediante peticiones HTTP en formato JSON:

- **Interfaz Bimonetaria en Tiempo Real (USD / VES):** Todos los precios unitarios, subtotales, impuestos (IVA), descuentos, pagos y vueltos se muestran simultáneamente en dólares (`$`) y bolívares (`Bs.`), calculados de manera reactiva a partir de la tasa de cambio activa administrada en `tasaStore`.
- **Punto de Venta (POS) con Soporte Fraccionado:** Permite buscar artículos por código de barras, código SKU, nombre o categoría, validando en tiempo real el stock disponible y respetando si la unidad de medida admite cantidades decimales (metros, kilogramos, litros con precisión de hasta tres decimales) o exclusivamente cantidades enteras.
- **Facturación Multipago y Gestión de Clientes:** Soporta la búsqueda y registro inmediato de clientes por documento de identidad (Cédula o RIF) durante la venta, así como el cobro combinado en múltiples métodos de pago (`EFECTIVO_VES`, `EFECTIVO_USD`, `PUNTO_DEBITO`, `PUNTO_CREDITO`, `BINANCE`, `ZELLE`, `PAGO_MOVIL`), controlando saldos pendientes, referencias obligatorias y cálculo de vuelto mixto.
- **Control de Acceso por Roles en Navegación:** Protección de rutas mediante guardias de navegación en Vue Router (`requiresAuth`, `guest`, `adminOnly`) que adaptan la interfaz según el perfil del usuario autenticado (`ADMIN` o `CAJERO`).
- **Módulos Administrativos Integrados:** Panel de métricas financieras y alertas de stock mínimo, gestión del catálogo de productos y categorías, compras bimonetarias con conversión de unidades, ajustes de existencias, consulta del kárdex inmutable, administración de cajeros y anulación de facturas.

---

## Stack Tecnológico

- **Framework Principal:** Vue 3.5+ con Composition API (`<script setup>`).
- **Gestión de Estado:** Pinia 2.x.
- **Enrutamiento:** Vue Router 4.x (`createWebHistory`).
- **Cliente HTTP:** Axios con interceptores para inyección automática del token JWT (`Authorization: Bearer <token>`) y manejo centralizado de sesiones expiradas (`401 Unauthorized`).
- **Empaquetador y Servidor de Desarrollo:** Vite 5.x.
- **Pruebas Unitarias y de Componentes:** Vitest 2.x, `@vue/test-utils` y `happy-dom`.

---

## Requisitos Previos

- **Node.js:** Versión 18.x o superior (se recomienda Node.js 20 LTS o superior).
- **Gestor de paquetes:** npm 9+ (incluido con Node.js).
- **Backend activo:** Instancia de `ferre_pos_api` en ejecución (por defecto en `http://127.0.0.1:8080`).

---

## Instalación y Puesta en Marcha

### 1. Instalar dependencias

Desde el directorio `ferre_pos_web`, ejecute:

```bash
npm install
```

### 2. Configuración de entorno y proxy

En modo de desarrollo, Vite redirige automáticamente todas las peticiones dirigidas a `/api` hacia el servidor backend a través del proxy configurado en `vite.config.js` (`http://127.0.0.1:8080` por defecto).

Si desea apuntar a una URL de backend distinta, puede definir las siguientes variables de entorno en un archivo `.env.local`:

```ini
# URL base consumida por Axios (por defecto: /api)
VITE_API_URL=/api

# Destino del proxy de Vite durante el desarrollo (por defecto: http://127.0.0.1:8080)
VITE_API_PROXY_TARGET=http://127.0.0.1:8080
```

### 3. Iniciar el servidor de desarrollo

```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:5173`.

### 4. Compilar para producción

```bash
npm run build
```

Los archivos estáticos optimizados se generarán en el directorio `dist/`. Para previsualizar localmente la compilación de producción:

```bash
npm run preview
```

### 5. Ejecutar pruebas automatizadas

Para ejecutar la suite de pruebas unitarias (stores, enrutador y componentes) con Vitest:

```bash
npm test
```

Para ejecutar las pruebas en modo interactivo de observación (*watch mode*):

```bash
npm run test:watch
```

---

## Mapa de Rutas y Permisos

El enrutador (`src/router/index.js`) aplica validaciones automáticas antes de cada transición de vista:

| Ruta | Nombre | Componente de Vista | Acceso | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `/login` | `login` | `LoginView.vue` | Público (`guest`) | Inicio de sesión mediante usuario o correo electrónico y contraseña. |
| `/pos` | `pos` | `PosView.vue` | `CAJERO`, `ADMIN` | Terminal de punto de venta bimonetario, carrito fraccionado, asignación de cliente y multipago. |
| `/ventas` | `ventas` | `VentasView.vue` | `CAJERO`, `ADMIN` | Historial de facturas emitidas, consulta de desglose de pagos y anulación (exclusiva para `ADMIN`). |
| `/dashboard` | `dashboard` | `DashboardView.vue` | Solo `ADMIN` | Resumen de indicadores financieros en USD/VES, actualización de tasa cambiaria y alertas de inventario crítico. |
| `/productos` | `productos` | `ProductosView.vue` | Solo `ADMIN` | Creación y edición de productos, configuración de unidades de compra/venta, factor de conversión, costos, precios e IVA. |
| `/categorias` | `categorias` | `CategoriasView.vue` | Solo `ADMIN` | Administración del catálogo de categorías de la ferretería. |
| `/inventario` | `inventario` | `InventarioView.vue` | Solo `ADMIN` | Registro de entradas de mercancía (en USD o VES), ajustes manuales de stock y auditoría del kárdex inmutable. |
| `/cajeros` | `cajeros` | `CajerosView.vue` | Solo `ADMIN` | Gestión de cuentas de cajeros, activación/desactivación de acceso y consulta de ventas por operador. |

---

## Gestión de Estado con Pinia (`src/stores/`)

La lógica reactiva de negocio se divide en tres almacenes centrales:

1. **`authStore.js` (`useAuthStore`):**
   - Administra el ciclo de vida de la sesión (`login`, `logout`) y sincroniza el token JWT y los datos del usuario en `localStorage` (`ferrepos_token` y `ferrepos_user`).
   - Expone propiedades computadas de autorización (`isAuthenticated`, `isAdmin`, `isCajero`).
   - Al iniciar sesión, sincroniza de inmediato la tasa de cambio vigente devuelta por el backend con `tasaStore`.

2. **`tasaStore.js` (`useTasaStore`):**
   - Mantiene la tasa de cambio activa (`tasa_ves`, fuente y fecha) y el historial de variaciones cambiarias.
   - Proporciona funciones utilitarias de conversión (`aBolivares`, `aDolares`) y formateadores monetarios localizados (`formatUsd`, `formatVes`).

3. **`posStore.js` (`usePosStore`):**
   - Gestiona el estado del carrito de compras, los datos del cliente asociado a la factura y los descuentos en dólares.
   - Valida las existencias máximas disponibles (`stock_actual`) y aplica las reglas de redondeo según la propiedad `permite_decimales` de cada producto.
   - Calcula de forma reactiva los subtotales, el impuesto al valor agregado (respetando productos exentos y alícuotas configuradas) y los totales generales tanto en USD como en VES.

---

## Estructura del Proyecto

```text
ferre_pos_web/
├── index.html                  # Documento HTML raíz de la SPA
├── package.json                # Dependencias y scripts de construcción y pruebas
├── vite.config.js              # Configuración de Vite, alias (@), proxy API y Vitest
├── src/
│   ├── App.vue                 # Componente raíz de Vue
│   ├── main.js                 # Punto de entrada e inicialización de Pinia y Vue Router
│   ├── style.css               # Sistema de estilos globales y variables de diseño
│   ├── layouts/
│   │   └── MainLayout.vue      # Estructura principal con barra lateral, cabecera bimonetaria y selector de tasa
│   ├── router/
│   │   └── index.js            # Definición de rutas y guardias de autenticación y rol
│   ├── services/
│   │   └── api.js              # Instancia de Axios e interceptores de solicitud y respuesta
│   ├── stores/
│   │   ├── authStore.js        # Estado de autenticación y permisos de usuario
│   │   ├── posStore.js         # Estado del carrito POS, validaciones de stock y cálculos bimonetarios
│   │   └── tasaStore.js        # Estado de la tasa de cambio USD/VES y formateadores
│   └── views/                  # Vistas principales de cada módulo del sistema
└── tests/                      # Pruebas unitarias y de integración con Vitest
    ├── components/             # Pruebas de vistas (LoginView, PosView)
    ├── router/                 # Pruebas de protección de rutas por rol
    └── stores/                 # Pruebas de lógica de negocio en Pinia
```
