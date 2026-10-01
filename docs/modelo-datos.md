# Modelo de datos — Project Rotor

Documento de evaluación de la Entrega 2. Resume el modelo técnico completo
de `docs/architecture/DATA_MODEL.md` (v0.1) y lo contrasta con el contrato
`api/openapi.yaml` y con el prototipo navegable `web/`.

Todos los nombres, valores y ejemplos son sintéticos.

---

## Motor elegido

**PostgreSQL.**

El núcleo de Project Rotor es transaccional: un carrito se convierte en una
orden con varias líneas, la orden reserva existencias, recibe uno o varios
intentos de pago y se confirma mediante eventos externos. Esas entidades
están fuertemente relacionadas y sus reglas (una orden por clave de
idempotencia, un evento de webhook procesado una sola vez, una línea por
producto dentro de un carrito) se expresan de forma natural como claves
foráneas y restricciones `UNIQUE`. Un modelo relacional con integridad
referencial declarada en la base de datos encaja mejor que un modelo de
documentos, donde esas garantías quedarían en el código de la aplicación.

PostgreSQL ofrece transacciones ACID y control de concurrencia multiversión
con bloqueo de filas (`SELECT … FOR UPDATE`), necesarios para crear la orden,
consumir reservas y aplicar un webhook sin duplicar efectos aunque lleguen
peticiones simultáneas. Su tipo `NUMERIC`/`DECIMAL` es exacto, por lo que los
importes no sufren errores de redondeo de coma flotante, y `TIMESTAMPTZ`
guarda instantes con zona horaria, coherente con las fechas ISO-8601 del
contrato.

Además, `JSONB` permite conservar el payload sanitizado de los webhooks para
auditoría sin renunciar al modelo relacional en el resto del dominio.

> **Estado de implementación.** PostgreSQL es el motor elegido para la
> arquitectura. El backend Laravel incorporado al repositorio es todavía un
> skeleton: no tiene migraciones del dominio y su configuración de ejemplo
> no está alineada con PostgreSQL. Esa alineación se hará en la rama de
> integración. Este documento no afirma que exista una base PostgreSQL
> desplegada ni en funcionamiento.

---

## Entidades

| Entidad | Tabla | Rol |
|---|---|---|
| Customer | `customers` | Comprador durable opcional (el checkout como invitado también es válido) |
| Cart | `carts` | Intención de compra; un carrito activo y sus históricos |
| CartItem | `cart_items` | Línea del carrito: producto y cantidad |
| StockReservation | `stock_reservations` | Apartado temporal de existencias; orquesta, no es el stock físico |
| Order | `orders` | Orden comercial con snapshot del comprador y totales |
| OrderItem | `order_items` | Snapshot histórico e inmutable de cada línea comprada |
| Payment | `payments` | Intento de pago sandbox; una orden puede tener varios |
| WebhookEvent | `webhook_events` | Evento recibido del proveedor de pagos, procesado de forma idempotente |
| Product (externo) | — | Frontera de integración con el dominio de inventario; no es tabla de Project Rotor |

---

## Convenciones de atributos

- **Tipos.** Tipos PostgreSQL propuestos para la implementación. Las
  longitudes de `VARCHAR` son una propuesta inicial ajustable en las
  migraciones sin cambiar el modelo.
- **Enumeraciones.** Se guardan como `VARCHAR` con restricción
  `CHECK (… IN (…))`, usando exactamente los valores del contrato OpenAPI.
- **Identificadores.** Toda PK es `UUID`. La aplicación genera el valor; la
  base de datos no define default para `id`.
- **Dinero.** `DECIMAL(18,2)` para importes y `DECIMAL(8,4)` para tasas.
  Nunca `FLOAT` ni `DOUBLE`.
- **Fechas.** `TIMESTAMPTZ` (instante con zona horaria).
- **Defaults de estado.** Coinciden con el estado inicial de cada máquina
  de estados; la aplicación puede fijarlo explícitamente.
- **Nullable `Sí`.** El default es `NULL`.

### Campos de control

La guía de la entrega pide campos equivalentes a `creado_en` y
`actualizado_en`. En este modelo se llaman **`created_at`** y
**`updated_at`**: son los campos de control de todas las tablas del modelo
(`TIMESTAMPTZ`, no nulos, default `CURRENT_TIMESTAMP`). Se conservan esos
nombres porque son los del modelo técnico y la convención de Laravel; no se
renombran solo para la entrega. Para no repetirlos, las tablas siguientes los
resumen en una fila.

Los timestamps de estado (`paid_at`, `cancelled_at`, `released_at`, …) son
auditoría; el estado vigente siempre lo indica la columna `status`.

---

## Atributos por entidad

### Customer (`customers`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador |
| name | VARCHAR(160) | No | Sin valor por defecto | — | Nombre del comprador |
| email | VARCHAR(254) | No | Sin valor por defecto | Índice no único | Correo |
| phone | VARCHAR(30) | No | Sin valor por defecto | — | Teléfono |
| document_type | VARCHAR(10) | No | Sin valor por defecto | Índice no único con `document_number` | Código del tipo de documento (ej. `CC`) |
| document_number | VARCHAR(30) | No | Sin valor por defecto | Índice no único con `document_type` | Número de documento |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

No se asume unicidad de correo ni de documento en v0.1.

### Cart (`carts`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador |
| customer_id | UUID | Sí | NULL | FK → `customers.id` | Dueño cuando existe Customer durable; NULL para invitado |
| status | VARCHAR(20) | No | `'ACTIVE'` | CHECK IN (`ACTIVE`, `CONVERTED`, `EXPIRED`, `ABANDONED`) | Estado del carrito |
| currency | CHAR(3) | No | Sin valor por defecto | Código ISO-4217 | Moneda del carrito (ej. `COP`) |
| expires_at | TIMESTAMPTZ | Sí | NULL | — | Ventana de carrito/reserva |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

Regla: como máximo un carrito `ACTIVE` por cliente. Se garantiza con
transacción + bloqueo + validación de dominio, **no** con
`UNIQUE(customer_id, status)`, porque eso impediría guardar varios carritos
históricos. PostgreSQL permitiría un índice único parcial
(`WHERE status = 'ACTIVE'`); queda solo como posible implementación futura.

### CartItem (`cart_items`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador de la línea |
| cart_id | UUID | No | Sin valor por defecto | FK → `carts.id`; UNIQUE con `product_id` | Carrito dueño |
| product_id | UUID | No | Sin valor por defecto | Referencia externa a Product; UNIQUE con `cart_id` | Producto del dominio de inventario |
| quantity | INTEGER | No | Sin valor por defecto | CHECK (`quantity > 0`) | Cantidad solicitada |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

El carrito no guarda precio: muestra el precio vigente del catálogo y el
cambio de precio (`PRICE_CHANGED`) se detecta al validar el checkout.

### StockReservation (`stock_reservations`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador |
| cart_id | UUID | No | Sin valor por defecto | FK → `carts.id` | Carrito de origen |
| cart_item_id | UUID | Sí | NULL | FK → `cart_items.id` | Línea asociada |
| order_id | UUID | Sí | NULL | FK → `orders.id` | Se llena al convertir el carrito en orden |
| product_id | UUID | No | Sin valor por defecto | Referencia externa a Product | Producto reservado |
| quantity | INTEGER | No | Sin valor por defecto | CHECK (`quantity > 0`) | Unidades apartadas |
| status | VARCHAR(20) | No | `'ACTIVE'` | CHECK IN (`ACTIVE`, `CONSUMED`, `RELEASED`, `EXPIRED`) | Estado de la reserva |
| expires_at | TIMESTAMPTZ | No | Sin valor por defecto | — | Caducidad de la reserva |
| released_at | TIMESTAMPTZ | Sí | NULL | — | Momento de liberación |
| consumed_at | TIMESTAMPTZ | Sí | NULL | — | Momento del consumo definitivo |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

El paso `ACTIVE → CONSUMED` ocurre una sola vez mediante una actualización
condicional (`WHERE status = 'ACTIVE'`), aunque lleguen webhooks duplicados o
una conciliación simultánea.

### Order (`orders`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador |
| order_number | VARCHAR(30) | No | Sin valor por defecto | UNIQUE | Número público (ej. `RTR-20260918-0001`) |
| customer_id | UUID | Sí | NULL | FK → `customers.id` | Referencia opcional; NULL en checkout como invitado |
| status | VARCHAR(20) | No | `'DRAFT'` | CHECK IN (`DRAFT`, `PENDING_PAYMENT`, `PAID`, `FULFILLED`, `FAILED`, `EXPIRED`, `CANCELLED`) | Estado actual |
| currency | CHAR(3) | No | Sin valor por defecto | Código ISO-4217 | Moneda única de la orden |
| customer_name | VARCHAR(160) | No | Sin valor por defecto | — | Snapshot del comprador |
| customer_email | VARCHAR(254) | No | Sin valor por defecto | — | Snapshot del comprador |
| customer_phone | VARCHAR(30) | No | Sin valor por defecto | — | Snapshot del comprador |
| customer_document_type | VARCHAR(10) | No | Sin valor por defecto | — | Snapshot del comprador |
| customer_document_number | VARCHAR(30) | No | Sin valor por defecto | — | Snapshot del comprador |
| delivery_mode | VARCHAR(30) | No | Sin valor por defecto | CHECK IN (`PICKUP`, `COORDINATED_SHIPPING`) | Modalidad de entrega |
| delivery_address | VARCHAR(255) | Sí | NULL | CHECK de modalidad (ver abajo) | Snapshot de la dirección de envío; NULL con `PICKUP` |
| subtotal | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Suma de subtotales de línea |
| tax_total | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Suma de impuestos de línea |
| total | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Total a pagar |
| idempotency_key | VARCHAR(255) | No | Sin valor por defecto | UNIQUE | Valor del header `Idempotency-Key` |
| idempotency_request_hash | VARCHAR(128) | No | Sin valor por defecto | — | Huella canónica de la solicitud |
| paid_at / fulfilled_at / cancelled_at / failed_at / expired_at | TIMESTAMPTZ | Sí | NULL | — | Auditoría de cada transición |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

**Dirección de entrega.** `delivery_address` es el snapshot de la dirección
usada para esa orden (`DeliveryData.address` del contrato): no depende de
que el cliente cambie después sus datos, no es FK y no existe tabla de
direcciones. Es nullable porque el retiro en sede no la necesita. Regla:
con `PICKUP` debe ser NULL; con `COORDINATED_SHIPPING` es obligatoria y no
vacía. Restricción conceptual (sin migración todavía):

```text
CHECK (
  (delivery_mode = 'PICKUP' AND delivery_address IS NULL)
  OR
  (delivery_mode = 'COORDINATED_SHIPPING'
   AND delivery_address IS NOT NULL
   AND btrim(delivery_address) <> '')
)
```

Transiciones permitidas: `DRAFT → PENDING_PAYMENT | CANCELLED`;
`PENDING_PAYMENT → PAID | FAILED | EXPIRED | CANCELLED`; `PAID → FULFILLED`.
Son reglas de dominio, no triggers.

### OrderItem (`order_items`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador |
| order_id | UUID | No | Sin valor por defecto | FK → `orders.id` | Orden dueña |
| product_id | UUID | No | Sin valor por defecto | Referencia externa a Product | Correlación con el catálogo |
| sku | VARCHAR(64) | No | Sin valor por defecto | — | Snapshot del SKU |
| product_name | VARCHAR(255) | No | Sin valor por defecto | — | Snapshot del nombre |
| quantity | INTEGER | No | Sin valor por defecto | CHECK (`quantity > 0`) | Cantidad comprada |
| unit_price | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Precio unitario fijado al comprar |
| tax_rate | DECIMAL(8,4) | Sí | NULL | CHECK (`>= 0`) | Tasa aplicada (ej. `0.1900`) |
| tax_amount | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Impuesto de la línea |
| line_subtotal | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Subtotal de la línea |
| line_total | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Total de la línea |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control; montos, SKU y nombre no se modifican |

La moneda de la línea es la de la orden (`orders.currency`).

### Payment (`payments`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador (= `payment_id` de la API) |
| order_id | UUID | No | Sin valor por defecto | FK → `orders.id` | Orden asociada |
| provider | VARCHAR(20) | No | Sin valor por defecto | CHECK IN (`WOMPI`, `MERCADOPAGO`); UNIQUE con `provider_reference` | Proveedor sandbox |
| provider_reference | VARCHAR(255) | Sí | NULL | UNIQUE con `provider` cuando no es NULL | Referencia del proveedor; NULL antes de contactarlo |
| status | VARCHAR(20) | No | `'PENDING'` | CHECK IN (`PENDING`, `APPROVED`, `DECLINED`, `EXPIRED`, `CANCELLED`, `ERROR`) | Estado normalizado |
| amount | DECIMAL(18,2) | No | Sin valor por defecto | CHECK (`>= 0`) | Monto del intento |
| currency | CHAR(3) | No | Sin valor por defecto | Igual a `orders.currency` | Moneda |
| redirect_url | TEXT | Sí | NULL | — | URL de experiencia del comprador; no confirma el pago |
| expires_at | TIMESTAMPTZ | Sí | NULL | — | Expiración del intento |
| paid_at | TIMESTAMPTZ | Sí | NULL | — | Momento de aprobación |
| failed_at | TIMESTAMPTZ | Sí | NULL | — | Momento de fallo o rechazo |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

Nunca se almacenan número completo de tarjeta, CVV, claves, secretos ni
tokens del instrumento de pago.

### WebhookEvent (`webhook_events`)

| Campo | Tipo | Nullable | Default | Restricción | Descripción |
|---|---|---|---|---|---|
| id | UUID | No | Sin valor por defecto (lo genera la aplicación) | PK | Identificador interno |
| provider | VARCHAR(20) | No | Sin valor por defecto | CHECK IN (`WOMPI`, `MERCADOPAGO`); UNIQUE con `external_event_id` | Proveedor de origen |
| external_event_id | VARCHAR(255) | No | Sin valor por defecto | UNIQUE con `provider` | `event_id` / header `X-Webhook-Id` |
| event_type | VARCHAR(100) | Sí | NULL | — | Tipo de evento |
| signature_verified | BOOLEAN | No | `FALSE` | — | Firma validada antes de aplicar efectos |
| payload | JSONB | Sí | NULL | — | Payload sanitizado (incluye `occurred_at` del proveedor) |
| received_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Momento de recepción |
| processed_at | TIMESTAMPTZ | Sí | NULL | — | Fin del procesamiento |
| processing_status | VARCHAR(30) | No | `'RECEIVED'` | CHECK IN (`RECEIVED`, `PROCESSED`, `IGNORED_DUPLICATE`, `FAILED`) | Estado del procesamiento |
| processing_result | VARCHAR(255) | Sí | NULL | — | Resumen interno (ej. `DUPLICATE_WEBHOOK_EVENT`) |
| order_id | UUID | Sí | NULL | FK → `orders.id` | Correlación con la orden, cuando se resuelve |
| payment_id | UUID | Sí | NULL | FK → `payments.id` | Correlación con el pago, cuando se resuelve |
| created_at / updated_at | TIMESTAMPTZ | No | CURRENT_TIMESTAMP | — | Campos de control |

### Product (frontera externa)

Project Rotor **no** crea tablas de productos, existencias ni bodegas. El
producto pertenece al dominio de inventario existente y se referencia por
UUID. Estos son los datos que Project Rotor consume a través de la API de
catálogo (`ProductSummary`); no se persisten localmente:

| Dato consumido | Tipo en la API | Uso en Project Rotor |
|---|---|---|
| id | UUID | Valor de `product_id` en CartItem, StockReservation y OrderItem |
| sku | string | Se copia como snapshot en OrderItem |
| name | string | Se copia como snapshot (`product_name`) en OrderItem |
| unit_price | MoneyAmount (decimal como texto) | Se fija como snapshot en OrderItem |
| tax_rate | decimal como texto, opcional | Se fija como snapshot en OrderItem |
| available_quantity | integer | Validación de cantidad; `available_quantity = quantity - reserved_quantity` |

---

## Claves

| Tipo | Tabla | Columnas | Propósito |
|---|---|---|---|
| PK | todas | `id` (UUID) | Identificador global, igual al `format: uuid` del contrato |
| FK | `carts` | `customer_id → customers.id` | Dueño opcional |
| FK | `cart_items` | `cart_id → carts.id` | Línea de un carrito |
| FK | `stock_reservations` | `cart_id → carts.id` | Reserva de un carrito |
| FK | `stock_reservations` | `cart_item_id → cart_items.id` | Línea reservada (opcional) |
| FK | `stock_reservations` | `order_id → orders.id` | Reserva retenida por la orden (opcional) |
| FK | `orders` | `customer_id → customers.id` | Comprador durable (opcional) |
| FK | `order_items` | `order_id → orders.id` | Línea de una orden |
| FK | `payments` | `order_id → orders.id` | Intento de pago de una orden |
| FK | `webhook_events` | `order_id → orders.id` | Correlación (opcional) |
| FK | `webhook_events` | `payment_id → payments.id` | Correlación (opcional) |
| UNIQUE | `orders` | `order_number` | Número público irrepetible |
| UNIQUE | `orders` | `idempotency_key` | Una sola orden por clave de idempotencia |
| UNIQUE | `cart_items` | `(cart_id, product_id)` | Un producto aparece una sola vez por carrito |
| UNIQUE | `payments` | `(provider, provider_reference)` | Una referencia del proveedor no se repite; varios NULL permitidos |
| UNIQUE | `webhook_events` | `(provider, external_event_id)` | Un evento del proveedor se registra una sola vez |
| Referencia externa | `cart_items`, `stock_reservations`, `order_items` | `product_id` | Referencia conceptual al dominio de inventario; **no** es FK local |

Restricción prohibida: `UNIQUE(customer_id, status)` en `carts`.

---

## Relaciones y cardinalidades

Notación: **1** = exactamente uno; **0..1** = ninguno o uno; **0..N** =
ninguno o muchos; **1..N** = uno o muchos.

| Relación | Desde A | Desde B | Lectura |
|---|---|---|---|
| Customer — Cart | Un Customer tiene 0..N Carts | Un Cart tiene 0..1 Customer | El carrito de un invitado no tiene Customer |
| Customer — Order | Un Customer tiene 0..N Orders | Una Order tiene 0..1 Customer | El comprador siempre queda como snapshot en la orden |
| Cart — CartItem | Un Cart tiene 0..N CartItems | Un CartItem pertenece a 1 Cart | Un carrito puede estar vacío |
| Cart — StockReservation | Un Cart tiene 0..N StockReservations | Una StockReservation pertenece a 1 Cart | El carrito orquesta sus reservas |
| CartItem — StockReservation | Un CartItem tiene 0..N StockReservations | Una StockReservation tiene 0..1 CartItem | Historial de reservas de una línea (ej. una expiró y se creó otra) |
| Order — OrderItem | Una Order tiene 1..N OrderItems | Un OrderItem pertenece a 1 Order | Ver nota |
| Order — Payment | Una Order tiene 0..N Payments | Un Payment pertenece a 1 Order | Varios intentos sandbox por orden |
| Order — StockReservation | Una Order tiene 0..N StockReservations | Una StockReservation tiene 0..1 Order | `order_id` se llena al crear la orden |
| Order — WebhookEvent | Una Order tiene 0..N WebhookEvents | Un WebhookEvent tiene 0..1 Order | La correlación puede resolverse después de recibir el evento |
| Payment — WebhookEvent | Un Payment tiene 0..N WebhookEvents | Un WebhookEvent tiene 0..1 Payment | Ídem |
| Product — CartItem / StockReservation / OrderItem | Un Product aparece en 0..N filas | Cada fila referencia 1 Product | Referencia externa por UUID |

**Nota Order — OrderItem.** Por regla de negocio una orden tiene al menos
una línea (el contrato exige `items` con `minItems: 1`). En el diagrama
entidad-relación se dibuja como 0..N porque una foreign key no puede imponer
un número mínimo de filas hijas; el mínimo lo garantiza la creación
transaccional de la orden.

El diagrama está en `docs/modelo-datos.png` (fuente: `docs/modelo-datos.mmd`).

---

## Índices

Estrategia definitiva de `DATA_MODEL.md` §15. Cada FK queda cubierta por un
índice que empieza por esa columna; no se crean índices duplicados.

### Índices implícitos por PK y UNIQUE

PostgreSQL crea un índice por cada PK y cada restricción UNIQUE de la sección
Claves. Además de garantizar unicidad, sirven a estos patrones de acceso:

- `UNIQUE(cart_id, product_id)` también resuelve “líneas de un carrito”
  (prefijo `cart_id`); por eso **no** existe un índice simple sobre
  `cart_items.cart_id`.
- `UNIQUE(order_number)` resuelve la búsqueda por número público.
- `UNIQUE(idempotency_key)` resuelve la búsqueda de la orden previa ante un
  reintento.
- `UNIQUE(provider, provider_reference)` resuelve la conciliación por
  referencia del proveedor.
- `UNIQUE(provider, external_event_id)` resuelve la detección de webhooks
  duplicados.

### Índices no únicos

| Índice | Tabla | Por qué |
|---|---|---|
| `(email)` | `customers` | Buscar o correlacionar un comprador por correo (no único) |
| `(document_type, document_number)` | `customers` | Buscar por documento (no único) |
| `(customer_id, status)` | `carts` | Localizar el carrito ACTIVE y el historial; cubre la FK `customer_id` |
| `(product_id, status, expires_at)` | `stock_reservations` | Reservas activas de un producto y barrido de expiración |
| `(order_id)` | `stock_reservations` | Reservas retenidas por una orden; cubre la FK |
| `(cart_id)` | `stock_reservations` | Reservas de un carrito al convertirlo; cubre la FK |
| `(cart_item_id)` | `stock_reservations` | Reservas de una línea al modificarla o quitarla; cubre la FK |
| `(customer_id, created_at)` | `orders` | Historial de órdenes por cliente; cubre la FK |
| `(status, created_at)` | `orders` | Operación y conciliación por estado |
| `(order_id)` | `order_items` | Líneas de una orden; cubre la FK |
| `(order_id)` | `payments` | Intentos de pago de una orden; cubre la FK |
| `(order_id)` | `webhook_events` | Auditoría por orden; cubre la FK |
| `(payment_id)` | `webhook_events` | Auditoría por pago; cubre la FK |
| `(processing_status, received_at)` | `webhook_events` | Reproceso de eventos pendientes o fallidos |

A diferencia de otros motores, PostgreSQL no indexa automáticamente las
columnas FK. Por eso `order_items(order_id)`, `stock_reservations(cart_id)` y
`stock_reservations(cart_item_id)` se declaran explícitamente. No son
optimizaciones nuevas: cubren los mismos patrones de acceso ya aprobados.

---

## Coherencia con OpenAPI

Comparación contra `api/openapi.yaml` de la rama `docs/e2-api-contract`.

| Concepto | API | Modelo | Resultado |
|---|---|---|---|
| UUID | Todos los `id` con `format: uuid` | PK `UUID` en todas las tablas; `product_id` UUID | COHERENTE |
| OrderStatus | `DRAFT`, `PENDING_PAYMENT`, `PAID`, `FULFILLED`, `FAILED`, `EXPIRED`, `CANCELLED` y sus transiciones | `orders.status` con los mismos 7 valores y transiciones | COHERENTE |
| PaymentStatus | `PENDING`, `APPROVED`, `DECLINED`, `EXPIRED`, `CANCELLED`, `ERROR` | `payments.status` con los mismos 6 valores | COHERENTE |
| PaymentProvider | `WOMPI`, `MERCADOPAGO` | `payments.provider`, `webhook_events.provider` | COHERENTE |
| CustomerData | `name`, `email`, `phone`, `document_type`, `document_number` | Columnas `customer_*` de `orders` (snapshot) y tabla `customers` | COHERENTE |
| DeliveryData | `mode`: `PICKUP` o `COORDINATED_SHIPPING` | `orders.delivery_mode` con los mismos valores | COHERENTE |
| Dirección de entrega | `DeliveryData.address`: obligatoria y no vacía con `COORDINATED_SHIPPING`; omitida o null con `PICKUP` (`if`/`then`/`else`); máximo 255 | `orders.delivery_address` `VARCHAR(255)`, snapshot, con la CHECK de modalidad | COHERENTE |
| Cart | `id`, `items`, `subtotal`, `tax_total`, `total`, `currency`, `expires_at` | `carts` (`id`, `currency`, `expires_at`); totales calculados con el precio vigente | COHERENTE (totales calculados, no guardados) |
| CartItem | `id`, `product_id`, `sku`, `name`, `quantity`, `unit_price`, `tax_amount`, `line_total`, `available_quantity` | `cart_items` (`id`, `product_id`, `quantity`); el resto se obtiene del catálogo | COHERENTE (precio vigente, no snapshot) |
| Order | `id`, `order_number`, `status`, `currency`, totales, `customer`, `delivery`, `items`, `created_at`, `updated_at` | `orders` con los mismos datos; `customer` y `delivery` aplanados | COHERENTE |
| OrderItem | `id`, `product_id`, `sku`, `product_name`, `quantity`, `unit_price`, `tax_amount`, `line_total` | `order_items` con esos campos más `tax_rate` y `line_subtotal` | COHERENTE |
| PaymentIntent | `payment_id`, `provider`, `provider_reference`, `status`, `redirect_url`, `expires_at` | `payments` (`id` = `payment_id`) | COHERENTE |
| Expiración de reserva | `CartItem.reservation_expires_at`, `Cart.expires_at` | `stock_reservations.expires_at`, `carts.expires_at` | COHERENTE |
| Moneda | `CurrencyCode` ISO-4217 de 3 letras | `CHAR(3)` única por orden | COHERENTE |
| Dinero | `MoneyAmount` texto decimal con 2 decimales; `tax_rate` hasta 4 decimales | `DECIMAL(18,2)` y `DECIMAL(8,4)` | COHERENTE |
| Idempotency-Key | Header obligatorio en `POST /api/orders`; reutilización con otro payload → 409 `IDEMPOTENCY_CONFLICT` | `orders.idempotency_key` UNIQUE + `idempotency_request_hash` | COHERENTE |
| Id del evento de webhook | `event_id` / header `X-Webhook-Id`; duplicado → 200 sin efectos | `webhook_events.external_event_id` UNIQUE con `provider` | COHERENTE |

Observaciones (no bloqueantes, sin cambios al contrato):

- `WebhookEvent.occurred_at` no tiene columna propia: se conserva dentro de
  `payload`, y el modelo registra además `received_at`.
- `PaymentIntent.provider_reference` es obligatorio en la respuesta, mientras
  que `payments.provider_reference` admite NULL. Es compatible: el intento
  solo se devuelve después de obtener la referencia sandbox.
- `idempotency_request_hash`, `order_id`/`payment_id` en webhooks y los
  timestamps de estado son detalles internos que la API no expone.
- La API no tiene recurso `Customer`; el checkout como invitado se apoya en
  la sesión (`cookieAuth`) y en el snapshot `CustomerData`.

Resultado global: **COHERENTE**.

---

## Coherencia con el prototipo

Revisión de solo lectura de `web/` en la rama `feat/e2-prototype-ui`.

| UI | API / modelo | Coherente |
|---|---|---|
| Nombre y SKU del producto | `ProductSummary.name` / `sku` → snapshot `order_items.product_name` / `sku` | Sí |
| Cantidad (mínimo 1, máximo disponible) | `quantity` con `minimum: 1` y CHECK `> 0`; `available_quantity` limita | Sí |
| Precio unitario “+ IVA” | `unit_price` sin impuesto; impuesto aparte en `tax_amount` | Sí |
| Stock: disponible / pocas unidades / agotado | Derivado de `available_quantity` | Sí (la etiqueta es solo de presentación) |
| Comprador: nombre, tipo y número de documento, correo, teléfono | `CustomerData` → columnas `customer_*` | Sí |
| Tipos de documento CC, CE, NIT, PAS | `document_type` como código | Sí |
| Modalidad: retiro en sede / envío coordinado | `DeliveryData.mode`: `PICKUP` / `COORDINATED_SHIPPING` → `delivery_mode` | Sí |
| Dirección de entrega (obligatoria con envío, mínimo 8 caracteres) | `DeliveryData.address` → `orders.delivery_address` (snapshot); obligatoria con `COORDINATED_SHIPPING`, NULL con `PICKUP` | Sí — COHERENTE |
| Subtotal | `subtotal` (suma de líneas) | Sí |
| IVA 19 % | `tax_total` = suma de `tax_amount` por línea | Parcial — ver observación |
| Total | `total` = `subtotal` + `tax_total` | Sí |
| Categoría, marca, condición, unidad de venta, descripción | Datos del dominio externo de producto; no están en `ProductSummary` | Parcial — ver observación |
| Pago sandbox y confirmación simulada | `PaymentIntent` y `redirect_url`; el redirect no confirma el pago | Sí (la UI aclara que es demostración) |

**Dirección de entrega: COHERENTE.** Decisión del equipo: la UI conserva el
campo, obligatorio solo con envío coordinado. El contrato lo expresa en
`DeliveryData.address` y el modelo lo guarda como snapshot en
`orders.delivery_address`. El mínimo de la UI (8 caracteres) es más estricto
que el del contrato (5), así que todo valor aceptado por la UI es válido
para la API.

**Observaciones (no bloqueantes):**

- Para el frontend definitivo: limitar el campo de dirección a 255
  caracteres (el prototipo no fija máximo) y no enviar `address` con
  `PICKUP`, aunque el usuario la haya escrito antes de cambiar de modalidad.

- La UI calcula el IVA sobre el subtotal con una tasa global; el modelo lo
  calcula por línea (`tax_amount`) y lo suma. Con una sola tasa el resultado
  coincide salvo el redondeo. El frontend definitivo debe mostrar los totales
  que devuelve la API.
- Los filtros por categoría y disponibilidad y el orden por precio o nombre
  se resuelven en el navegador con datos locales. La API ofrece hoy búsqueda
  libre (`q`) y paginación. Categoría, marca, condición y unidad pertenecen
  al dominio de producto externo; exponerlos requeriría ampliar
  `ProductSummary`.
- El prototipo guarda el carrito en el navegador; en la arquitectura el
  carrito se persiste en `carts`/`cart_items`.

---

## Fuera de alcance

- Migraciones Laravel y SQL ejecutable.
- Comportamiento `ON DELETE` de cada FK (se define en las migraciones; no
  hay borrado físico de órdenes, pagos ni eventos).
- Jobs de expiración de reservas.
- Modelo completo de inventario.
