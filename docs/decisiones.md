# Decisiones de arquitectura — Project Rotor (Entrega 2)

Síntesis de las decisiones principales del modelo de datos y del flujo
transaccional. El detalle técnico está en `docs/architecture/DATA_MODEL.md`
(ADR-DATA-*) y el contrato en `api/openapi.yaml`.

---

## ADR-E2-001 — PostgreSQL como motor de persistencia

- **Contexto.** La especificación de la entrega limita el motor documentado a
  PostgreSQL o MongoDB. El dominio maneja órdenes, líneas de orden, pagos,
  carritos, reservas e idempotencia, con muchas relaciones, restricciones
  UNIQUE, integridad referencial y operaciones que deben ser atómicas.
- **Decisión.** PostgreSQL. Aporta modelo relacional, transacciones ACID,
  foreign keys, restricciones CHECK/UNIQUE, índices, `DECIMAL` exacto para
  dinero, `JSONB` para payloads de webhooks y control de concurrencia con
  bloqueo de filas. Se descarta MongoDB porque esas garantías quedarían en el
  código de la aplicación en lugar de en la base de datos.
- **Consecuencia.** El modelo se documenta con tipos PostgreSQL y las FK sin
  índice que las cubra llevan uno explícito, porque PostgreSQL no los crea
  solo. El skeleton Laravel del repositorio todavía no está configurado para
  PostgreSQL ni tiene migraciones del dominio. `ARCHITECTURE.md`, `README.md`
  y `backend/.env.example` se alinearán en la rama de integración. No hay una
  base PostgreSQL desplegada.

## ADR-E2-002 — Identificadores UUID

- **Contexto.** El contrato identifica productos, carritos, órdenes y pagos
  con `format: uuid`, y el producto proviene de un dominio externo.
- **Decisión.** Toda PK del modelo es `UUID`, generada por la aplicación;
  `product_id` también es UUID.
- **Consecuencia.** Los identificadores no revelan volumen ni secuencia, se
  pueden generar antes de insertar y coinciden con la API sin traducciones.
  Los índices sobre UUID son algo más grandes que sobre enteros.

## ADR-E2-003 — Dinero con decimal, no float

- **Contexto.** Precios, impuestos y totales se suman y comparan; la coma
  flotante introduce errores de redondeo.
- **Decisión.** Importes en `DECIMAL(18,2)` y tasas en `DECIMAL(8,4)`. La API
  serializa importes como texto decimal (`MoneyAmount`). Una sola moneda
  ISO-4217 por orden.
- **Consecuencia.** Los totales son exactos y reproducibles. No hay órdenes
  con varias monedas en v0.1.

## ADR-E2-004 — Snapshot histórico de OrderItem

- **Contexto.** El catálogo puede cambiar precio, nombre o SKU después de una
  compra.
- **Decisión.** `order_items` guarda una copia de SKU, nombre, precio
  unitario, tasa e importes en el momento de crear la orden. La orden copia
  también los datos del comprador y la dirección de entrega
  (`delivery_address`), que solo aplica a `COORDINATED_SHIPPING`: es
  obligatoria con envío coordinado y NULL con retiro en sede (CHECK de
  modalidad). No hay tabla de direcciones ni FK.
- **Consecuencia.** La orden es auditable aunque el catálogo cambie. El
  carrito, en cambio, usa el precio vigente y el cambio se detecta en el
  checkout (`PRICE_CHANGED`).

## ADR-E2-005 — Idempotencia en la creación de Order

- **Contexto.** Un reintento de red o un doble clic pueden enviar dos veces
  `POST /api/orders`.
- **Decisión.** Header obligatorio `Idempotency-Key`, guardado en
  `orders.idempotency_key` con restricción UNIQUE, junto con una huella de la
  solicitud (`idempotency_request_hash`).
- **Consecuencia.** Misma clave y misma solicitud devuelven la misma orden.
  Misma clave con otra solicitud responde 409 `IDEMPOTENCY_CONFLICT`. No hace
  falta una tabla genérica de claves en v0.1.

## ADR-E2-006 — Webhook idempotente

- **Contexto.** Los proveedores de pago entregan eventos al menos una vez y
  pueden repetirlos o enviarlos en paralelo con una conciliación.
- **Decisión.** `webhook_events` con `UNIQUE(provider, external_event_id)`.
  Un duplicado se responde con HTTP 200 sin repetir efectos. Las transiciones
  de la orden (`PENDING_PAYMENT → PAID`) y de la reserva
  (`ACTIVE → CONSUMED`) son actualizaciones condicionales.
- **Consecuencia.** El efecto de negocio ocurre una sola vez aunque el
  mensaje llegue varias veces. El proveedor no entra en bucles de reintento.

## ADR-E2-007 — Inventario como frontera de integración

- **Contexto.** El inventario (productos, existencias, movimientos) ya existe
  en otro dominio y esta fase no lo reimplementa.
- **Decisión.** Project Rotor referencia el producto por `product_id` (UUID)
  sin FK local. `stock_reservations` orquesta los apartados temporales; el
  dominio de inventario valida y aplica la disponibilidad real.
- **Consecuencia.** No hay tablas de productos, existencias ni bodegas en
  este modelo. La integridad del `product_id` se valida contra el dominio
  externo y no mediante una foreign key.

## ADR-E2-008 — Checkout como invitado

- **Contexto.** El contrato pide `CustomerData` en el checkout y no define un
  recurso de cliente; no todos los compradores tendrán registro durable.
- **Decisión.** Se permite el checkout como invitado: `orders.customer_id` y
  `carts.customer_id` admiten NULL. El comprador siempre queda como snapshot
  en la orden.
- **Consecuencia.** Se puede comprar sin crear un cliente. `customers` es
  opcional y nunca es la única fuente del comprador de una orden.

## ADR-E2-009 — El redirect del navegador no confirma el pago

- **Contexto.** Tras pagar, el proveedor redirige al comprador con parámetros
  en la URL; esa URL puede alterarse, repetirse o no completarse nunca.
- **Decisión.** `redirect_url` sirve solo para la experiencia del comprador.
  La orden pasa a `PAID` únicamente con un webhook de firma validada o con
  una conciliación explícita contra el proveedor.
- **Consecuencia.** Un retorno manipulado no confirma pagos ni descuenta
  inventario. La pantalla de retorno debe mostrar “pago en verificación”
  hasta que la API informe el estado definitivo.
