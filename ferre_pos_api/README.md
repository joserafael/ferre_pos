# FerrePOS API - Backend RESTful

API RESTful desarrollada con **CodeIgniter 4** y **MySQL 8+** para la gestión integral de punto de venta (POS), control de inventario fraccionado y facturación bimonetaria (**Dólares estadounidenses - USD** y **Bolívares - VES**) orientada al sector ferretero en Venezuela.

---

## Arquitectura y Características Principales

El sistema sigue una arquitectura estrictamente desacoplada donde el backend actúa exclusivamente como proveedor de servicios vía HTTP/JSON:

- **Gestión Bimonetaria Nativa (USD / VES):** El dólar estadounidense (**USD**) funciona como moneda ancla para costos y precios base. Los montos en bolívares (**VES**) se calculan en tiempo real a partir de la tasa de cambio activa y se congelan históricamente en cada factura, compra o movimiento de inventario.
- **Inventario Fraccionado y Conversión de Unidades:** Soporte para unidades enteras y decimales (`UND`, `M`, `KG`, `L`, `CAJA`, `ROLLO`) con factor de conversión automático entre la unidad de compra y la unidad de venta (por ejemplo, compra por rollo de 100 metros y venta detallada por metro).
- **Kárdex Inmutable:** Toda variación de existencias requiere y registra obligatoriamente un movimiento auditable en la tabla `movimientos_inventario` (`ENTRADA_COMPRA`, `SALIDA_VENTA`, `ANULACION_VENTA`, `AJUSTE_POSITIVO`, `AJUSTE_NEGATIVO`) dentro de transacciones de base de datos ACID.
- **Facturación y Multipago Mixto:** Permite cancelar una misma factura combinando múltiples métodos de pago en ambas monedas (`EFECTIVO_VES`, `EFECTIVO_USD`, `PUNTO_DEBITO`, `PUNTO_CREDITO`, `BINANCE`, `ZELLE`, `PAGO_MOVIL`), validando referencias de transacción y registrando el vuelto entregado en dólares y bolívares.
- **Seguridad y Control de Acceso Basado en Roles (RBAC):** Autenticación sin estado mediante **JSON Web Tokens (JWT)** firmados con `HS256` y segregación de permisos entre los roles `ADMIN` y `CAJERO`.

---

## Precisión Numérica en Base de Datos

Para garantizar la exactitud contable y evitar errores de redondeo en punto flotante, no se emplean tipos `FLOAT` ni `DOUBLE`. Toda la persistencia numérica utiliza tipos `DECIMAL` bajo motor **InnoDB**:

| Concepto                               | Tipo de Dato    | Descripción                                                    |
| :------------------------------------- | :-------------- | :------------------------------------------------------------- |
| Precios, costos y totales monetarios   | `DECIMAL(14,2)` | Importes exactos en USD y VES.                                 |
| Tasas de cambio                        | `DECIMAL(12,4)` | Tasa oficial o de referencia (`tasa_ves`).                     |
| Cantidades y existencias de inventario | `DECIMAL(12,3)` | Soporte para ventas fraccionadas (metros, kilogramos, litros). |

---

## Requisitos del Sistema

- **PHP:** Versión 8.2 o superior.
- **Extensiones PHP requeridas:** `intl`, `mbstring`, `json`, `mysqlnd`, `curl`.
- **Base de datos:** MySQL 8.0+ o MariaDB equivalente con soporte para motor InnoDB.
- **Gestor de dependencias:** Composer 2.x.

---

## Instalación y Configuración

### 1. Instalar dependencias

Desde el directorio `ferre_pos_api`, ejecute:

```bash
composer install
```

### 2. Configurar variables de entorno

Copie el archivo de plantilla `env_example` a `.env` (o edite el archivo `.env` existente) y ajuste los parámetros de entorno, conexión a base de datos y clave secreta JWT:

```ini
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = ferre_pos
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port     = 3306

JWT_SECRET = 'ferre_pos_super_secret_jwt_key_2026_bimonetario_usd_ves'
JWT_TTL    = 86400
```

### 3. Ejecutar migraciones y datos de prueba (Seeders)

Genere la estructura de tablas e inserte el catálogo base (usuarios iniciales, tasa de cambio, categorías, unidades de medida, métodos de pago y productos de demostración):

```bash
php spark migrate
php spark db:seed InitialFerrePosSeeder
```

### 4. Iniciar el servidor de desarrollo

```bash
php spark serve --port 8080
```

En entornos de producción, configure el servidor web (Nginx o Apache) para que el directorio raíz del documento (_DocumentRoot_) apunte a la carpeta `public/`.

---

## Credenciales Iniciales de Prueba

Al ejecutar el seeder `InitialFerrePosSeeder`, se crean dos cuentas predeterminadas:

| Rol      | Usuario  | Correo Electrónico     | Contraseña  | Alcance de Permisos                                                                  |
| :------- | :------- | :--------------------- | :---------- | :----------------------------------------------------------------------------------- |
| `ADMIN`  | `admin`  | `admin@ferrepos.local` | `admin123`  | Acceso total al sistema, configuración, inventario, reportes y anulaciones.          |
| `CAJERO` | `cajero` | `caja@ferrepos.local`  | `cajero123` | Acceso exclusivo a consultas de catálogo, gestión de clientes y facturación en caja. |

---

## Formato Estándar de Respuesta y Autenticación

### Estructura JSON

Todas las respuestas de la API utilizan la estructura unificada definida en `App\Controllers\Api\ApiController`:

```json
{
  "status": 200,
  "error": null,
  "messages": "Operación exitosa",
  "data": {}
}
```

En caso de error (validación, permisos o excepción de negocio), el código HTTP coincide con el campo `status` y se detalla la causa en `error` y `messages`:

```json
{
  "status": 401,
  "error": "UNAUTHORIZED",
  "messages": "Token de autenticación no proporcionado.",
  "data": null
}
```

### Uso del Token JWT

Los endpoints protegidos requieren el envío del encabezado `Authorization` con el esquema `Bearer`:

```http
Authorization: Bearer <token_jwt>
```

---

## Referencia de Endpoints

Prefijo base de todas las rutas: `/api`

### 1. Endpoints Públicos

No requieren autenticación previa.

| Método | Ruta                | Controlador                    | Descripción                                                                                                  |
| :----- | :------------------ | :----------------------------- | :----------------------------------------------------------------------------------------------------------- |
| `POST` | `/api/auth/login`   | `AuthController::login`        | Autentica al usuario por `username` o `email`, devuelve el token JWT, los datos del perfil y la tasa activa. |
| `GET`  | `/api/tasas/activa` | `TasaCambioController::activa` | Consulta la tasa de cambio vigente (`USD` a `VES`).                                                          |

### 2. Endpoints de Operación POS (Roles: `CAJERO` y `ADMIN`)

Requieren filtro `jwt`. Permiten la operativa diaria de facturación y atención al cliente.

| Método | Ruta                   | Controlador                       | Descripción                                                                                                     |
| :----- | :--------------------- | :-------------------------------- | :-------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/api/auth/me`         | `AuthController::me`              | Obtiene la sesión actual del usuario autenticado y la tasa activa.                                              |
| `GET`  | `/api/categorias`      | `CategoriaController::index`      | Lista las categorías de productos disponibles.                                                                  |
| `GET`  | `/api/unidades-medida` | `CatalogoController::unidades`    | Lista las unidades de medida y si permiten decimales.                                                           |
| `GET`  | `/api/metodos-pago`    | `CatalogoController::metodosPago` | Lista los métodos de pago activos, su moneda y si exigen referencia.                                            |
| `GET`  | `/api/productos`       | `ProductoController::index`       | Consulta el catálogo de productos con cálculo dinámico en bolívares.                                            |
| `GET`  | `/api/productos/{id}`  | `ProductoController::show`        | Obtiene el detalle de un producto específico por su identificador.                                              |
| `GET`  | `/api/clientes`        | `ClienteController::index`        | Lista el directorio de clientes registrados.                                                                    |
| `GET`  | `/api/clientes/buscar` | `ClienteController::buscar`       | Busca clientes por documento de identidad (Cédula/RIF) o nombre.                                                |
| `POST` | `/api/clientes`        | `ClienteController::create`       | Registra un nuevo cliente desde el punto de venta.                                                              |
| `PUT`  | `/api/clientes/{id}`   | `ClienteController::update`       | Actualiza los datos de contacto de un cliente existente.                                                        |
| `GET`  | `/api/ventas`          | `VentaController::index`          | Lista el historial de ventas (el cajero visualiza sus propias ventas).                                          |
| `GET`  | `/api/ventas/{id}`     | `VentaController::show`           | Consulta el detalle completo de una factura, sus líneas y multipagos.                                           |
| `POST` | `/api/ventas`          | `VentaController::create`         | Procesa una nueva venta, descuenta el stock, genera el movimiento en el kárdex y registra el desglose de pagos. |

### 3. Endpoints Administrativos (Exclusivo Rol: `ADMIN`)

Requieren filtro `jwt:ADMIN`. Si un usuario con rol `CAJERO` intenta consumir estas rutas, la API responde con estado `403 Forbidden`.

| Método   | Ruta                            | Controlador                          | Descripción                                                                                                                                   |
| :------- | :------------------------------ | :----------------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`    | `/api/dashboard`                | `DashboardController::index`         | Obtiene métricas financieras, resumen de ventas en USD/VES y alertas de stock mínimo.                                                         |
| `GET`    | `/api/tasas`                    | `TasaCambioController::index`        | Consulta el historial de tasas de cambio registradas.                                                                                         |
| `POST`   | `/api/tasas`                    | `TasaCambioController::create`       | Registra una nueva tasa de cambio y la establece como activa.                                                                                 |
| `POST`   | `/api/categorias`               | `CategoriaController::create`        | Crea una nueva categoría de productos.                                                                                                        |
| `PUT`    | `/api/categorias/{id}`          | `CategoriaController::update`        | Actualiza los datos de una categoría.                                                                                                         |
| `DELETE` | `/api/categorias/{id}`          | `CategoriaController::delete`        | Desactiva o elimina una categoría.                                                                                                            |
| `POST`   | `/api/unidades-medida`          | `CatalogoController::crearUnidad`    | Registra una nueva unidad de medida en el catálogo.                                                                                           |
| `POST`   | `/api/productos`                | `ProductoController::create`         | Crea un nuevo producto con costos, precios en USD y configuración de unidades.                                                                |
| `PUT`    | `/api/productos/{id}`           | `ProductoController::update`         | Actualiza la ficha técnica y precios de un producto.                                                                                          |
| `DELETE` | `/api/productos/{id}`           | `ProductoController::delete`         | Desactiva o elimina un producto del catálogo.                                                                                                 |
| `GET`    | `/api/inventario/entradas`      | `InventarioController::entradas`     | Lista las órdenes de entrada y compras de mercancía registradas.                                                                              |
| `GET`    | `/api/inventario/entradas/{id}` | `InventarioController::showEntrada`  | Muestra el detalle de una entrada de inventario específica.                                                                                   |
| `POST`   | `/api/inventario/entradas`      | `InventarioController::crearEntrada` | Registra una compra de mercancía (en USD o VES), aplica el factor de conversión, actualiza costos/precios e incrementa el stock en el kárdex. |
| `POST`   | `/api/inventario/ajustes`       | `InventarioController::crearAjuste`  | Registra un ajuste manual de inventario (positivo o negativo) con observación obligatoria.                                                    |
| `GET`    | `/api/inventario/kardex`        | `InventarioController::kardex`       | Consulta la bitácora inmutable de movimientos de inventario filtrable por producto, tipo o fecha.                                             |
| `POST`   | `/api/ventas/{id}/anular`       | `VentaController::anular`            | Anula una factura emitida y revierte automáticamente las existencias al inventario mediante un movimiento `ANULACION_VENTA`.                  |
| `GET`    | `/api/cajeros`                  | `CajeroController::index`            | Lista los usuarios con rol de cajero y sus indicadores de facturación.                                                                        |
| `POST`   | `/api/cajeros`                  | `CajeroController::create`           | Crea una nueva cuenta de usuario con rol `CAJERO`.                                                                                            |
| `PUT`    | `/api/cajeros/{id}`             | `CajeroController::update`           | Modifica los datos, contraseña o estado activo de un cajero.                                                                                  |
| `GET`    | `/api/cajeros/{id}/ventas`      | `CajeroController::ventas`           | Consulta el historial detallado de ventas realizadas por un cajero específico.                                                                |

---

## Estructura Principal del Directorio `app/`

- `app/Config/Routes.php`: Definición de rutas HTTP, políticas preflight CORS y agrupación por filtros de rol.
- `app/Config/Filters.php`: Registro de los filtros globales `CorsFilter` y `JwtAuthFilter`.
- `app/Controllers/Api/`: Controladores RESTful que extienden de `ApiController`.
- `app/Database/Migrations/`: Migraciones del esquema relacional de la ferretería.
- `app/Database/Seeds/`: Poblado inicial de datos (`InitialFerrePosSeeder`).
- `app/Filters/`: Middlewares de validación de cabeceras CORS y verificación criptográfica de tokens JWT y roles.
- `app/Libraries/JwtLib.php`: Librería nativa para codificación, firma y validación de tokens JWT (`HS256`).
- `app/Models/`: Modelos de acceso a datos y reglas de integridad de CodeIgniter 4.
