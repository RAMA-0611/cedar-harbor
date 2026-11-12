# Guía de despliegue en un servidor (VPS) con dominio y HTTPS

## 1. Requisitos

- Un VPS Linux (Ubuntu 22.04/24.04, 2 GB de RAM mínimo) con IP pública.
- Un dominio (o subdominio) con un registro **A** hacia esa IP. Sirve un subdominio gratuito de [DuckDNS](https://www.duckdns.org/) (por ejemplo `rotor-byteblade.duckdns.org`): se inicia sesión, se crea el subdominio y se escribe la IP del servidor.
- Puertos **80 y 443** abiertos (el 80 lo necesita Caddy para obtener el certificado).

## 2. Preparar el servidor (una sola vez)

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER      # cierre sesión y vuelva a entrar
docker compose version             # debe ser >= 2.24
mkdir -p ~/rotor && cd ~/rotor
```

Cree `~/rotor/.env` (solo en el servidor):

```env
SITE_ADDRESS=rotor.midominio.com
APP_URL=https://rotor.midominio.com
SANCTUM_STATEFUL_DOMAINS=rotor.midominio.com
APP_KEY=base64:<salida de: openssl rand -base64 32>
POSTGRES_PASSWORD=<contraseña larga y aleatoria>
ADMIN_EMAIL=admin@midominio.com
ADMIN_PASSWORD=<contraseña de 12+ caracteres>
PAYMENT_PROVIDER=simulated
```

Genere contraseñas con `openssl rand -base64 24`. En producción `APP_KEY`, `ADMIN_PASSWORD`, `POSTGRES_PASSWORD`, `SITE_ADDRESS`, `APP_URL` y `SANCTUM_STATEFUL_DOMAINS` son obligatorias: si falta alguna, `compose.prod.yaml` se detiene con un mensaje que nombra la variable.

## 3. Configurar GitHub

*Settings → Secrets and variables → Actions*:

| Tipo | Nombre | Valor |
|---|---|---|
| Secret | `DEPLOY_HOST` | IP o dominio del servidor |
| Secret | `DEPLOY_USER` | usuario SSH |
| Secret | `DEPLOY_SSH_KEY` | llave privada (cree un par solo para esto: `ssh-keygen -t ed25519`; la pública va en `~/.ssh/authorized_keys` del servidor) |
| Secret | `DEPLOY_PATH` | `/home/<usuario>/rotor` |
| Secret | `GHCR_PULL_TOKEN` | PAT con `read:packages` (necesario si los paquetes son privados; vacío si los hace públicos) |
| Variable | `PUBLIC_URL` | `https://rotor.midominio.com` |

Cree además el *environment* `production` (Settings → Environments).

## 4. Publicar

```bash
git push origin main                 # dispara pruebas -> imágenes -> despliegue
git tag v0.4.0 && git push origin v0.4.0
```

Siga la ejecución en la pestaña *Actions*. Al terminar, abra la URL pública y compruebe el candado HTTPS.

## 5. Reversión

*Actions → ci-cd → Run workflow →* `image_tag` = `v0.4.0` (o `sha-xxxxxxx` de una ejecución anterior).

## 6. Operación

| Tarea | Comando (en `~/rotor`) |
|---|---|
| Estado | `docker compose --env-file .env --env-file .deploy.env -f compose.yaml -f compose.prod.yaml ps` |
| Logs | igual con `logs -f app` |
| Copia de la base | `docker compose exec db pg_dump -U rotor rotor > respaldo.sql` |

## 7. Si algo falla

| Síntoma | Causa probable |
|---|---|
| Sin certificado / error TLS | DNS aún no apunta al servidor, o puerto 80/443 cerrado |
| El job de despliegue dice «Falta .env» | No creó `~/rotor/.env` |
| `pull access denied` | Paquetes GHCR privados y falta `GHCR_PULL_TOKEN`, o el nombre del repositorio tiene mayúsculas (el flujo ya lo pasa a minúsculas) |
| Un servicio no queda `healthy` | `docker compose logs <servicio>` |
