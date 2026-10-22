# Base de datos de pruebas

| Base | Uso |
|---|---|
| `rotor` | desarrollo / demo local. Sin tests destructivos. |
| `rotor_testing` | **solo** PHPUnit |
| `rotor_e2e` | **solo** Playwright (`php artisan e2e:reset` + `npm run test:e2e`) |

`php artisan test` **nunca** debe usar `rotor` ni `rotor_e2e`. Playwright **nunca** debe usar `rotor` ni `rotor_testing`.

Durante MVP-0, `RefreshDatabase` heredó `backend/.env` y vació datos de dominio en `rotor`. El dump custom los recuperó. MVP-1 aísla la suite.

## Setup local

1. Crear PostgreSQL `rotor_testing` en `127.0.0.1` (el rol de aplicación `rotor` **no** tiene `CREATEDB`). Usar un rol administrativo local:

```powershell
$env:PG_ADMIN_USERNAME = 'postgres'
# $env:PG_ADMIN_PASSWORD = '<no versionar>'
powershell -ExecutionPolicy Bypass -File .\scripts\db-create-test.ps1
```

Owner preferido: `rotor` (CONNECT / DDL sobre `rotor_testing` únicamente). No otorgar `CREATEDB` al rol de aplicación.

Si no hay credenciales de admin en el entorno, crear la base en pgAdmin Query Tool como `postgres` sobre la base `postgres` (`127.0.0.1:5432`):

```sql
CREATE DATABASE rotor_testing OWNER rotor;
```

2. Copiar variables de testing (sin password real en git):

```powershell
cd backend
copy .env.testing.example .env.testing
```

Completar `APP_KEY` y `DB_PASSWORD` en `.env.testing` (archivo ignorado). `phpunit.xml` fuerza `DB_DATABASE=rotor_testing`.

3. Ejecutar:

```powershell
cd backend
php artisan test
```

## Guard

`Tests\Support\TestingDatabaseGuard` corre en `Tests\TestCase::refreshApplication()` **y** en `beforeRefreshingDatabase()`, antes de `migrate` / drop.

Aborta con:

```text
Refusing to run tests against non-testing database.
```

si `APP_ENV` no es `testing`, la conexión no es `pgsql`, o la base no es exactamente `rotor_testing`.

El reset E2E (`php artisan e2e:reset`) carga `backend/.env.e2e` y aborta con:

```text
Refusing E2E reset outside rotor_e2e.
```

salvo que `APP_ENV=e2e` y `current_database()` sea exactamente `rotor_e2e`. El rol `rotor` no recibe `CREATEDB`.

## Safety

- Host de tests: `127.0.0.1`.
- No SQLite: las migraciones usan CHECK, UUID, `timestampTz` y locking de PostgreSQL.
- No ejecutar `php artisan migrate:fresh` contra `rotor` para “probar tests”.
- Backup de `rotor` ya no es necesario para correr la suite normal, una vez `rotor_testing` existe.
