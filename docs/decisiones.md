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

## ADR-E3-001 — Catálogo local persistente para el MVP de Entrega 3

- **Contexto.** E2 trató Product como frontera externa (ADR-E2-007): Project
  Rotor referenciaba `product_id` sin FK local porque el inventario vive en
  otro dominio. E3 exige persistencia PostgreSQL real y CRUD sobre catálogo
  en un entorno académico que no debe depender del sistema empresarial.
- **Decisión.** Para el MVP de Entrega 3, Project Rotor tendrá tablas locales
  `categories` y `products` que implementan el contrato del catálogo durante
  el MVP. Es un **adaptador sustituible**: conserva el contrato público del
  dominio (`ProductSummary` / `product_id` UUID) mientras la fuente de verdad
  de lectura/escritura del catálogo es PostgreSQL local.
- **Consecuencia.** Las FK locales (`products.category_id`,
  `cart_items.product_id`, `order_items.product_id`,
  `stock_reservations.product_id`) aplican en E3. ADR-E2-007 permanece como
  modelo conceptual futuro (inventario como frontera de integración); E3 no
  la borra, la implementa vía adaptador local reemplazable sin cambiar el
  contrato público.


# Decisiones de despliegue — Entrega 4

## ADR-E4-001 — Una sola imagen con dos objetivos (Dockerfile multietapa)

- **Contexto.** El repositorio tiene API (Laravel) e interfaz (Next.js) y la rúbrica pide un Dockerfile multietapa.
- **Decisión.** Un `Dockerfile` en la raíz con objetivos `app` (PHP-FPM) y `web` (Next.js standalone). Las dependencias se
  instalan en etapas aparte (`composer install --no-dev`, `npm ci`) para cachear capas y dejar fuera herramientas de
  desarrollo.
- **Consecuencia.** Las imágenes finales no contienen compiladores, `node_modules` completos ni dependencias de desarrollo.

## ADR-E4-002 — Proxy Caddy como único servicio expuesto, mismo origen

- **Decisión.** Caddy publica 80/443 y enruta por ruta: `/api`, `/sanctum`, `/storage`, `/up` → `app` (FastCGI), el resto →
  `web`. Obtiene y renueva el certificado HTTPS solo.
- **Por qué mismo origen.** Las cookies de sesión de Sanctum necesitan que SPA y API compartan dominio; con un único
  origen no hay CORS ni cookies de terceros, y la imagen `web` se compila con `NEXT_PUBLIC_API_BASE_URL=""` (rutas
  relativas), de modo que **la misma imagen sirve para cualquier dominio**.
- **Detalle verificado.** No se usa `php_fastcgi`: comprueba la existencia de archivos en el contenedor del proxy, que no
  contiene el código de Laravel, y devolvía respuestas vacías. Se usa `rewrite * /index.php` + `reverse_proxy` con
  transporte `fastcgi` (comprobado con Caddy y php-fpm reales).

## ADR-E4-003 — Redis para caché y sesiones; PostgreSQL con volumen

- **Decisión.** `cache` (Redis 7, appendonly) guarda caché y sesiones; `db` (PostgreSQL 16) y `cache` usan volúmenes
  nombrados (`pgdata`, `redisdata`). `app_storage` conserva imágenes subidas, logs y la `APP_KEY` generada.
- **Consecuencia.** Reiniciar o recrear contenedores no pierde datos. Los servicios se llaman entre sí por nombre
  (`db`, `cache`), nunca `127.0.0.1`.

## ADR-E4-004 — Secretos fuera del repositorio

- **Decisión.** `.env.example` solo trae nombres. `compose.yaml` funciona sin `.env` con valores de **desarrollo** (la
  base no se publica al host). `compose.prod.yaml` **exige** las variables sensibles (`${VAR:?}`) y falla si faltan.
  En desarrollo la `APP_KEY` se genera en el primer arranque y vive en el volumen, y la contraseña del primer
  administrador, si no se define, se genera y se imprime una sola vez en el log; en producción ambas son obligatorias.
- **Consecuencia.** El valor `rotor_dev_only` del compose es la única credencial literal y es solo de desarrollo; en
  producción no se usa porque el override obliga a definir `POSTGRES_PASSWORD`.

## ADR-E4-005 — CI/CD: pruebas como compuerta, imágenes versionadas, despliegue por SSH

- **Decisión.** `ci-cd.yaml`: PHPUnit, tipos/lint/compilación del frontend y validación de compose/Caddyfile/secretos
  deben pasar antes de construir; luego se publican `app` y `web` en GHCR con etiquetas `sha-xxxxxxx`, `latest` y la versión
  (`v0.4.0`), y se despliega por SSH con `docker compose pull && up -d`, esperando a que todo quede `healthy`.
  Las pruebas E2E con navegador corren pero no bloquean (evidencia informativa).
- **Rollback.** Ejecución manual del flujo con `image_tag` de una versión anterior.

## ADR-E4-006 — Pasarela de pago simulada incluida

- **Contexto.** Wompi sandbox necesita llaves y red externa; la revisión debe funcionar sin ellas.
- **Decisión.** `PAYMENT_PROVIDER=simulated` (por defecto) activa un proceso de pago completo con tarjetas de prueba
  (aprobada, rechazada, fondos insuficientes, error y pendiente) que **reutiliza el mismo servicio de asiento** que el
  webhook de Wompi (`PaymentSettlementService`): mismo cambio de estado del pedido y misma liberación o consumo de
  inventario. `PAYMENT_PROVIDER=wompi` restablece el flujo real.
- **Seguridad.** Nunca se guardan datos de tarjeta (solo los últimos 4 dígitos); el simulador responde 404 si el
  proveedor activo es Wompi.

## ADR-E4-007 — Validación de formularios estricta y compartida

- **Decisión.** Reglas idénticas en backend (`app/Rules`) y frontend (`src/lib/validation.ts`): teléfono
  `^3[0-9]{9}$`; tipo de documento como **lista cerrada** (CC, CE, NIT, PP) con formato del número según el tipo; nombre
  solo letras; contraseña 8–72 con letra y número; cantidades enteras de 1 al stock disponible. Los campos numéricos
  filtran caracteres inválidos al escribir o pegar. El backend es la autoridad.
