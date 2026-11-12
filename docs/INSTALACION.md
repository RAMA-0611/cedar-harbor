# Instalación completa del proyecto (versión de la Entrega 4)

Esta guía instala **Proyecto Rotor** desde cero en un computador nuevo, sin ayudas externas. Todo corre dentro de Docker:
no hace falta instalar PHP, Node ni PostgreSQL. Cada comando se copia y pega tal cual.

**Contenido**

1. [Instalar en Windows](#1-instalar-en-windows)
2. [Instalar en Linux o macOS](#2-instalar-en-linux-o-macos)
3. [Entrar y probar la aplicación](#3-entrar-y-probar-la-aplicación)
4. [Apagar, encender y borrar](#4-apagar-encender-y-borrar)
5. [Pruebas automáticas](#5-pruebas-automáticas)
6. [Referencia: servicios, variables y pagos de prueba](#6-referencia-servicios-variables-y-pagos-de-prueba)
7. [Problemas frecuentes](#7-problemas-frecuentes)

Para publicar la aplicación en internet con HTTPS, siga [`DESPLIEGUE-HOSTINGER.md`](DESPLIEGUE-HOSTINGER.md).

---

## 1. Instalar en Windows

Requisitos: Windows 10 (22H2) o Windows 11, 8 GB de RAM como mínimo y unos 10 GB libres en disco.

### 1.1 Activar la virtualización

Abra el **Administrador de tareas** (Ctrl + Mayús + Esc) → pestaña **Rendimiento** → **CPU**. Debe decir
**Virtualización: Habilitado**. Si dice *Deshabilitado*, reinicie el equipo, entre a la BIOS/UEFI (suele ser F2, F10, F12 o
Supr) y active *Intel VT-x*, *AMD-V* o *SVM Mode*.

### 1.2 Instalar WSL 2, Git y Docker Desktop

Abra **PowerShell como administrador** (clic derecho sobre el menú Inicio → *Terminal (Administrador)*) y ejecute, una línea a la vez:

```powershell
wsl --install
winget install -e --id Git.Git
winget install -e --id Docker.DockerDesktop
```

Reinicie el equipo cuando lo pida. Después abra **Docker Desktop** desde el menú Inicio, acepte los términos y espere a que el
ícono de la ballena (abajo a la derecha) quede estable. La primera vez puede tardar unos minutos.

### 1.3 Comprobar la instalación

Abra una PowerShell **normal** y ejecute:

```powershell
wsl --status
docker --version
docker compose version
git --version
docker run --rm hello-world
```

Debe ver las versiones (Compose **2.24 o superior**) y el mensaje *Hello from Docker!*.

### 1.4 Descargar el proyecto

```powershell
cd C:\
git clone https://github.com/RAMA-0611/cedar-harbor.git Rotor
cd C:\Rotor
dir compose.yaml
```

Debe aparecer el archivo `compose.yaml`. **Todos los comandos `docker compose` se ejecutan dentro de `C:\Rotor`.**

Si recibió el proyecto como `.zip`, descomprímalo de modo que `compose.yaml` quede directamente en `C:\Rotor`.

### 1.5 Construir y arrancar con un solo comando

```powershell
cd C:\Rotor
docker compose up -d --build
```

La primera vez tarda entre 3 y 10 minutos (descarga imágenes y compila). **No hace falta crear ningún `.env`**: en local se
usan valores de desarrollo. Cuando termine:

```powershell
docker compose ps
```

Los cinco servicios (`proxy`, `app`, `web`, `db`, `cache`) deben quedar en `healthy` o `running`. Si alguno dice `starting`,
espere un minuto y repita. Al primer arranque el sistema crea las tablas, el catálogo de demostración y el primer administrador.

---

## 2. Instalar en Linux o macOS

```bash
# Ubuntu / Debian
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"        # cierre sesión y vuelva a entrar
sudo apt-get install -y git

# macOS: instale Docker Desktop desde https://www.docker.com/products/docker-desktop/
# y Git con: xcode-select --install

docker compose version                 # debe ser 2.24 o superior
git clone https://github.com/RAMA-0611/cedar-harbor.git Rotor
cd Rotor
docker compose up -d --build
docker compose ps
```

---

## 3. Entrar y probar la aplicación

| Qué | Dirección |
|---|---|
| Tienda (catálogo) | http://localhost:8080 |
| Panel de administración | http://localhost:8080/login |
| Ingreso y registro de clientes | http://localhost:8080/cliente/login · http://localhost:8080/cliente/registro |

### 3.1 Contraseña del administrador

El primer administrador es `admin@rotor.test`. Si no definió contraseña, el sistema genera una y la muestra **una sola vez**
en el registro del contenedor `app`:

```powershell
docker compose logs app | Select-String "solo esta vez"       # Windows (PowerShell)
```

```bash
docker compose logs app | grep "solo esta vez"                # Linux / macOS
```

Busque «solo esta vez» y no «Contraseña generada»: PowerShell puede mostrar mal la «ñ». La contraseña es el texto que aparece
al final de esa línea. Para cambiarla: entre al panel → *Usuarios* → editar al administrador → *Nueva contraseña*.

Para **fijarla antes de arrancar**, cree el archivo `C:\Rotor\.env` con una sola línea `ADMIN_PASSWORD=SuClave2026+` y
ejecute `docker compose up -d`.

### 3.2 Recorrido de prueba completo

1. En http://localhost:8080 abra un producto y vaya a **Crear cuenta**. Reglas de los formularios: nombre solo con letras;
   teléfono de 10 dígitos que empiece por 3; tipo de documento de la lista (CC, CE, NIT, PP) con número válido; contraseña de
   8 a 72 caracteres con letras y números. El **ojo** junto a la contraseña la muestra u oculta.
2. **Agregar al carrito → Ir a checkout → Crear pedido → Pagar**.
3. En la pantalla de pago elija una tarjeta de prueba (tabla de la [sección 6](#6-referencia-servicios-variables-y-pagos-de-prueba)).
   Con `4242 4242 4242 4242` el pedido queda `PAID`.
4. Entre al panel con el administrador y revise **Pedidos** y **Productos** (el stock baja con el pago aprobado).
5. Abra la tienda desde el celular (misma red Wi-Fi: `http://IP-DEL-PC:8080`) y compruebe que se adapta a la pantalla.

---

## 4. Apagar, encender y borrar

| Acción | Comando (dentro de la carpeta del proyecto) |
|---|---|
| Apagar conservando los datos | `docker compose down` |
| Encender de nuevo | `docker compose up -d` |
| Ver el estado | `docker compose ps` |
| Ver los registros de un servicio | `docker compose logs -f app` |
| Reconstruir tras cambiar código | `docker compose up -d --build` |
| Borrar TODO, incluidos los datos | `docker compose down -v` |

Los datos viven en volúmenes de Docker (`pgdata`, `redisdata`, `app_storage`, `caddy_data`) y sobreviven a `down` y a los
reinicios. Para comprobarlo: cree un usuario, ejecute `docker compose restart` y vuelva a entrar con él.

---

## 5. Pruebas automáticas

El CI las ejecuta en cada *push* (ver `.github/workflows/ci-cd.yaml`). Para correrlas en su computador hace falta el modo sin
Docker (PHP 8.2+ con bcmath y pgsql, Composer, PostgreSQL 16 y Node 22):

```powershell
node scripts/setup.mjs                  # instala dependencias, migra y crea el administrador
cd backend
copy .env.testing.example .env.testing
php artisan test                        # PHPUnit sobre la base rotor_testing

cd ..\frontend
npm run test:e2e                        # Playwright sobre la base rotor_e2e
```

Incluye pruebas con pantalla de teléfono Android (`frontend/e2e/mobile-android.spec.ts`). Ambas suites se niegan a ejecutarse
contra la base de datos de desarrollo. Detalle en `docs/operations/TEST_DATABASE.md`.

---

## 6. Referencia: servicios, variables y pagos de prueba

### Servicios

| Servicio | Imagen / rol | Healthcheck |
|---|---|---|
| `proxy` | Caddy 2. Único puerto expuesto (80/443; en local 8080/8443). HTTPS automático. Enruta `/api`, `/sanctum`, `/storage` y `/up` a la API y el resto a la web | `wget` a `/up` |
| `app` | Laravel 12 + PHP-FPM 8.3 | `php-fpm` responde en `:9000` y `/up` |
| `web` | Next.js 16 (modo standalone) | HTTP 200 en `:3000` |
| `db` | PostgreSQL 16, volumen `pgdata` | `pg_isready` |
| `cache` | Redis 7, volumen `redisdata` | `redis-cli ping` |

La base de datos y Redis **no** se publican al host.

### Variables de entorno

Plantilla completa en [`.env.example`](../.env.example). **Nunca** versione `.env`.

| Variable | Obligatoria en producción | Descripción |
|---|---|---|
| `SITE_ADDRESS` | Sí | Dominio público sin `https://`. Con dominio, Caddy obtiene el certificado |
| `APP_URL` | Sí | `https://` + dominio |
| `SANCTUM_STATEFUL_DOMAINS` | Sí | El dominio sin `https://` (necesario para la sesión por cookie) |
| `SESSION_SECURE_COOKIE` | Sí | `true` con HTTPS |
| `APP_KEY` | Sí | `base64:` + `openssl rand -base64 32` |
| `POSTGRES_PASSWORD` | Sí | Contraseña de la base de datos |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Sí | Primer administrador |
| `HTTP_PORT` / `HTTPS_PORT` | No | Puertos del host (por defecto 8080 / 8443; en un servidor público, 80 / 443) |
| `PAYMENT_PROVIDER` | No | `simulated` (por defecto) o `wompi` |
| `WOMPI_*` | Solo con `wompi` | Llaves sandbox de Wompi |

### Pasarela de pago simulada

Con `PAYMENT_PROVIDER=simulated` el checkout lleva a una pantalla de pago con el flujo completo; no se mueve dinero real.
Use cualquier fecha futura y un CVC de 3 dígitos:

| Tarjeta | Resultado |
|---|---|
| 4242 4242 4242 4242 | Aprobada → pedido `PAID`, stock consumido |
| 4000 0000 0000 0002 | Rechazada → pedido `FAILED`, reserva liberada |
| 4000 0000 0000 9995 | Fondos insuficientes → `FAILED` |
| 4000 0000 0000 0119 | Error de la pasarela → `FAILED` |
| 4000 0000 0000 3220 | Pendiente → se resuelve con los botones «Aprobar / Rechazar» |

No se almacenan números de tarjeta, solo los últimos 4 dígitos.

---

## 7. Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `no configuration file provided: not found` | Está fuera de la carpeta del proyecto. Ejecute `cd C:\Rotor` |
| Docker Desktop dice *unable to start* | Active la virtualización (1.1), ejecute `wsl --update` como administrador y reinicie |
| `docker` no se reconoce como comando | Cierre y abra PowerShell; si persiste, reinicie el equipo con Docker Desktop instalado |
| El puerto 8080 está ocupado | Cree `.env` con `HTTP_PORT=8081` y abra http://localhost:8081 |
| Error de versión de Compose | Actualice Docker Desktop (se requiere Compose 2.24 o superior) |
| No aparece la contraseña del administrador | Búsquela con «solo esta vez» (3.1). Si ya no aparece, defina `ADMIN_PASSWORD` en `.env`, ejecute `docker compose down -v` (borra los datos) y luego `docker compose up -d` |
| Un servicio no queda `healthy` | `docker compose logs NOMBRE --tail 50` (`app`, `web`, `db` o `proxy`) |
| Se agotó el disco | `docker system prune -af` (no borra volúmenes con datos) |
