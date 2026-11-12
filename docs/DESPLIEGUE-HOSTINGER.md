# Despliegue en el servidor de Hostinger (VPS) con HTTPS y CI/CD

Guía paso a paso, con todos los comandos, para publicar **Proyecto Rotor** en internet con candado HTTPS y dejar el
despliegue automático. Aplica a un VPS **Ubuntu 24.04** de Hostinger (plan KVM 1) y sirve igual en otro proveedor con Ubuntu.

Aplicación publicada: **https://srv2153504.hstgr.cloud**

**Contenido**

1. [Contratar y preparar el VPS](#1-contratar-y-preparar-el-vps)
2. [Dominio](#2-dominio)
3. [Conectarse al servidor](#3-conectarse-al-servidor)
4. [Instalar Docker y abrir los puertos](#4-instalar-docker-y-abrir-los-puertos)
5. [Descargar el proyecto](#5-descargar-el-proyecto)
6. [Crear el archivo `.env`](#6-crear-el-archivo-env)
7. [Arrancar y verificar](#7-arrancar-y-verificar)
8. [Seguridad básica](#8-seguridad-básica)
9. [Despliegue automático (CI/CD)](#9-despliegue-automático-cicd)
10. [Versiones y reversión](#10-versiones-y-reversión)
11. [Operación diaria](#11-operación-diaria)
12. [Comprobar los requisitos de la entrega](#12-comprobar-los-requisitos-de-la-entrega)

---

## 1. Contratar y preparar el VPS

1. Entre a https://www.hostinger.com/vps-hosting y elija el plan **KVM 1** (1 vCPU, 4 GB de RAM, 50 GB). Elija el periodo
   más corto disponible. **No** contrate hosting compartido: no permite Docker.
2. Ubicación del servidor: cualquiera (el proyecto funciona igual).
3. Sistema operativo: **Ubuntu 24.04 LTS**, sin panel de control ni aplicaciones.
4. Contraseña de **root**: pulse *Generar*, copie la contraseña y guárdela en un lugar seguro. No la comparta ni la
   fotografíe.
5. En *Funciones adicionales* deje todo sin marcar y pulse *Finalizar configuración*.
6. Espere a que el estado sea **Funcionando**. Anote la **dirección IP** (por ejemplo `2.25.79.200`) y el nombre de host que
   Hostinger asigna (por ejemplo `srv2153504.hstgr.cloud`).

---

## 2. Dominio

Hay dos opciones:

- **Nombre de host del proveedor (inmediato):** en Hostinger es `srvNNNNNNN.hstgr.cloud` y ya apunta a la IP del VPS.
- **Dominio propio:** el plan incluye un dominio gratis el primer año (*Dominios → Reclamar dominio*). Después, en
  *Administrador de DNS*, cree un registro **A** con nombre `@` y valor igual a la IP del VPS. Verifique el correo de
  confirmación del registrador antes de la fecha límite o el dominio se suspende.

Compruebe desde su computador que el nombre resuelve a la IP (reemplace por el suyo):

```powershell
nslookup srv2153504.hstgr.cloud 8.8.8.8
```

Debe aparecer la IP del VPS. Un dominio recién registrado puede tardar entre 5 y 30 minutos en aparecer.

---

## 3. Conectarse al servidor

En PowerShell o en cualquier terminal:

```powershell
ssh root@IP_DEL_VPS
```

Responda `yes` a la primera pregunta y escriba la contraseña de root (no se ve nada al escribir; es normal). Debe aparecer
`root@srv…:~#`. Desde aquí todos los comandos se ejecutan **en el servidor**.

---

## 4. Instalar Docker y abrir los puertos

```bash
apt-get update && apt-get install -y git ufw
curl -fsSL https://get.docker.com | sh
docker --version && docker compose version

ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

Los puertos 80 y 443 deben estar abiertos: Caddy usa el 80 para obtener el certificado y el 443 para servir la web.

---

## 5. Descargar el proyecto

```bash
git clone https://github.com/RAMA-0611/cedar-harbor.git /opt/rotor
cd /opt/rotor
```

Alternativa sin GitHub: desde su computador copie el `.zip` con
`scp "C:\ruta\Proyecto Rotor completo.zip" root@IP_DEL_VPS:/root/rotor.zip` y, en el servidor,
`apt-get install -y unzip && unzip -q rotor.zip -d /opt && mv /opt/Rotor /opt/rotor && cd /opt/rotor`.

---

## 6. Crear el archivo `.env`

Este archivo guarda las contraseñas del servidor y **nunca se sube a GitHub**. Reemplace `DOMINIO` por su dominio sin
`https://` (por ejemplo `srv2153504.hstgr.cloud`) y pegue el bloque completo:

```bash
cd /opt/rotor
DOM=DOMINIO
ADMINPW="Rt$(openssl rand -hex 8)9"
cat > .env <<EOF
SITE_ADDRESS=$DOM
HTTP_PORT=80
HTTPS_PORT=443
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$DOM
SANCTUM_STATEFUL_DOMAINS=$DOM
SESSION_SECURE_COOKIE=true
APP_KEY=base64:$(openssl rand -base64 32)
POSTGRES_PASSWORD=$(openssl rand -hex 16)
ADMIN_EMAIL=admin@rotor.test
ADMIN_PASSWORD=$ADMINPW
SEED_DEMO=true
PAYMENT_PROVIDER=simulated
EOF
chmod 600 .env
echo "Contraseña del administrador: $ADMINPW"
```

**Copie y guarde la contraseña del administrador que aparece al final.** Compruebe el archivo sin mostrar los secretos:

```bash
grep -v -E "PASSWORD|APP_KEY" .env
```

Debe listar 10 líneas (dominio, puertos, entorno, URL, cookies, correo del administrador, demo y pasarela).

---

## 7. Arrancar y verificar

```bash
cd /opt/rotor
docker compose up -d --build
docker compose ps
```

La primera construcción tarda entre 3 y 10 minutos. Cuando los cinco servicios (`proxy`, `app`, `web`, `db`, `cache`) estén
`healthy`, abra **https://SU-DOMINIO** en el navegador: debe cargar el catálogo con el **candado** de HTTPS. Si la primera
vez falla, espere 30 segundos y recargue (Caddy está pidiendo el certificado).

Entre a **https://SU-DOMINIO/login** con `admin@rotor.test` y la contraseña del paso 6. Verifique desde el servidor:

```bash
curl -I http://SU-DOMINIO          # responde 308 hacia https://
curl -s https://SU-DOMINIO/up      # estado de la aplicación
```

Haga una compra de prueba con la tarjeta `4242 4242 4242 4242` y confirme que el pedido queda `PAID`.

---

## 8. Seguridad básica

- Cambie la contraseña del administrador desde el panel (*Usuarios → editar*).
- Cuando termine el paso 9 y compruebe que entra con la clave SSH, puede desactivar el ingreso por contraseña: en
  `/etc/ssh/sshd_config` ponga `PasswordAuthentication no` y ejecute `systemctl restart ssh`. **Hágalo solo si ya comprobó que
  entra con la clave**; de lo contrario se quedará sin acceso.
- La base de datos y Redis no se exponen a internet: solo salen los puertos 22, 80 y 443.
- Al terminar el curso, desactive la renovación automática del VPS y del dominio en el panel del proveedor para evitar cobros.

---

## 9. Despliegue automático (CI/CD)

El flujo `.github/workflows/ci-cd.yaml` hace, en cada *push* a `main`: **pruebas → construcción y publicación de imágenes en
GHCR → despliegue por SSH → espera a `healthy` → verificación de la URL pública**. Si una prueba falla, no se construye ni se
despliega nada. Una *pull request* solo ejecuta las pruebas.

> Los *secrets* y las *variables* solo los puede crear quien tenga permiso de **administrador del repositorio** en GitHub
> (en este equipo, el integrante de DevOps y calidad). Los demás pueden ver los resultados en la pestaña *Actions*.

### 9.1 Clave SSH exclusiva para el despliegue

En el **servidor**:

```bash
ssh-keygen -t ed25519 -N "" -f /root/.ssh/rotor_deploy -C "github-actions-rotor"
cat /root/.ssh/rotor_deploy.pub >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
cat /root/.ssh/rotor_deploy
```

El último comando imprime la **clave privada** (empieza con `-----BEGIN OPENSSH PRIVATE KEY-----`). Cópiela completa, con la
primera y la última línea: es el valor del secreto `DEPLOY_SSH_KEY`. No la pegue en chats ni en el repositorio.

### 9.2 Token para descargar las imágenes (solo si los paquetes son privados)

En GitHub: foto de perfil → *Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token
(classic)*. Marque solo **`read:packages`**, ponga una caducidad corta y copie el token (solo se muestra una vez). Será el
secreto `GHCR_PULL_TOKEN`. Si hace públicos los paquetes (*Packages → Package settings → Change visibility*), puede dejarlo vacío.

### 9.3 Secretos, variable y entorno

En el repositorio: *Settings → Secrets and variables → Actions*.

| Tipo | Nombre | Valor |
|---|---|---|
| Secret | `DEPLOY_HOST` | IP del servidor (por ejemplo `2.25.79.200`) |
| Secret | `DEPLOY_USER` | `root` |
| Secret | `DEPLOY_SSH_KEY` | la clave privada del paso 9.1 |
| Secret | `DEPLOY_PATH` | `/opt/rotor` |
| Secret | `GHCR_PULL_TOKEN` | el token del paso 9.2 (o vacío si los paquetes son públicos) |
| Variable | `PUBLIC_URL` | `https://SU-DOMINIO` |

Luego *Settings → Environments → New environment* con el nombre exacto **`production`**.

### 9.4 Primer despliegue automático

```bash
git add .
git commit -m "Entrega 4"
git push origin main
```

Abra la pestaña **Actions** y entre a la ejecución *ci-cd*. Deben ponerse en verde, en orden: pruebas de backend, frontend,
validación de Docker, construcción de `app` y `web` y *Desplegar en el servidor*. En el servidor, `docker compose ps` seguirá
mostrando los cinco servicios `healthy`, ahora con imágenes `ghcr.io/…`. Los datos se conservan porque viven en volúmenes.

### 9.5 Demostrar que el flujo se detiene si algo falla

1. Cree una rama: `git checkout -b prueba-fallo`.
2. Rompa a propósito una prueba (por ejemplo, cambie un número esperado en un archivo de `backend/tests/Feature/`).
3. Haga *commit*, *push* y abra una *pull request*: el trabajo de pruebas queda en **rojo** y no se construye ni se despliega.
4. Cierre la *pull request* sin fusionar y borre la rama. Esa ejecución roja queda en el historial como evidencia.

---

## 10. Versiones y reversión

```bash
git tag v0.4.0
git push origin v0.4.0
```

El *tag* publica imágenes con la etiqueta `v0.4.0`. Para **volver a una versión anterior**: *Actions → ci-cd → Run workflow*
y escriba en `image_tag` la etiqueta (por ejemplo `v0.4.0` o `sha-1a2b3c4`, visible en los paquetes de GHCR).

---

## 11. Operación diaria

Antes del primer despliegue automático basta `docker compose …` dentro de `/opt/rotor`. Una vez el CI/CD gestiona el
servidor, anteponga las opciones de producción:

```bash
cd /opt/rotor
DC="docker compose --env-file .env --env-file .deploy.env -f compose.yaml -f compose.prod.yaml"
$DC ps                                                              # estado
$DC logs -f app                                                     # registros
$DC exec -T db pg_dump -U rotor rotor > respaldo-$(date +%F).sql    # copia de seguridad
$DC exec -T db psql -U rotor rotor < respaldo-2026-11-12.sql        # restaurar (cambie la fecha)
```

Actualizar sin CI/CD: `cd /opt/rotor && git pull && docker compose up -d --build`.

---

## 12. Comprobar los requisitos de la entrega

| # | Capacidad | Cómo comprobarla |
|---|---|---|
| 1 | Arranque con un solo comando | `docker compose up -d --build` en una carpeta limpia |
| 2 | URL pública | Abrir la URL desde el celular con datos móviles (sin el Wi-Fi de casa) |
| 3 | HTTPS válido | Candado en el navegador; `curl -I https://SU-DOMINIO` responde `200` |
| 4 | Datos persistentes | Crear un producto, ejecutar `docker compose restart`, el producto sigue ahí |
| 5 | Secretos externalizados | `.env` solo en el servidor; `git log --all -- .env` no devuelve nada |
| 6 | Despliegue automático | Un *push* a `main` termina en verde y la web muestra el cambio |
| 7 | El flujo se detiene si falla | Ejecución en rojo del paso 9.5 |
| 8 | Healthchecks | `docker compose ps` muestra `healthy` en los cinco servicios |
| 9 | Rendimiento medido | Informes de Lighthouse en `docs/lighthouse/` y tabla en `docs/informe-rendimiento.md` |
| 10 | Accesibilidad ≥ 90 | Puntaje *Accessibility* en Lighthouse |

**Medir Lighthouse sobre la URL pública:** abra la URL en Google Chrome → F12 → pestaña **Lighthouse** → marque
*Performance*, *Accessibility* y *Best practices*, modo **Mobile** → *Analyze page load*. Repita en `/` y en
`/cliente/registro`, guarde cada resultado (⋮ → *Save as JSON*) en `docs/lighthouse/` y anote los puntajes antes y después en
[`informe-rendimiento.md`](informe-rendimiento.md).

Antes de entregar, busque credenciales en el historial (no debe imprimir nada):

```bash
git log --all --diff-filter=A --name-only --format= | grep -E "(^|/)\.env(\.[A-Za-z0-9_.-]+)?$|\.pem$|\.key$|id_(rsa|ed25519)$" | grep -v "\.example$"
```

---

## Si algo falla

| Síntoma | Causa y solución |
|---|---|
| `Permission denied` al entrar por SSH | Contraseña de root incorrecta: use *Restablecer contraseña* en el panel del VPS |
| La URL no carga o no hay candado | Espere 1 minuto; verifique con `nslookup` que el dominio apunta a la IP, que `ufw status` permite 80 y 443 y revise `docker compose logs proxy --tail 30` |
| La sesión no se mantiene (vuelve al login) | `APP_URL`, `SANCTUM_STATEFUL_DOMAINS` o `SESSION_SECURE_COOKIE` no coinciden con el dominio y HTTPS. Corrija `.env` y ejecute `docker compose up -d` |
| El despliegue dice *Falta .env en el servidor* | Cree `/opt/rotor/.env` (paso 6) |
| El despliegue dice `pull access denied` | Los paquetes de GHCR son privados y falta `GHCR_PULL_TOKEN`, o el token caducó |
| El despliegue falla por `APP_KEY` o `POSTGRES_PASSWORD` | Falta esa variable en el `.env` del servidor (paso 6) |
| Un servicio no queda `healthy` | `docker compose logs NOMBRE --tail 50` (`app`, `web`, `db` o `proxy`) |
