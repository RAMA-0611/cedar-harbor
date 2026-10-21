# Backup y restore de PostgreSQL (local)

Scripts:

- `scripts/db-backup.ps1`
- `scripts/db-restore.ps1`

Destino por defecto del dump: `C:\Temp\rotor-backups` (fuera del repo).  
Formato: PostgreSQL **custom** (`pg_dump -F c`).  
Los scripts **nunca imprimen** el password. Lo leen de `backend/.env` (`DB_PASSWORD`) o de un `-EnvFile`.

No versionar `*.dump`, `*.backup` ni `backups/`.

## Requisitos

- PostgreSQL 16 local (`pg_dump`, `pg_restore`, `psql` en `C:\Program Files\PostgreSQL\16\bin`)
- `DB_HOST` local: `127.0.0.1`, `localhost` o `::1`
- Base de aplicación: `rotor`
- PowerShell 5.1+

## Backup

```powershell
cd C:\dev\cedar-harbor
powershell -ExecutionPolicy Bypass -File .\scripts\db-backup.ps1
```

Destino opcional:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\db-backup.ps1 -DestinationDirectory 'C:\Temp\rotor-backups'
```

El script verifica host local, ejecuta `pg_dump -F c`, falla si el archivo no existe o pesa 0 bytes, e imprime:

- `BACKUP_PATH`
- `BACKUP_SIZE_BYTES`
- `BACKUP_SHA256`
- `BACKUP_DATABASE`
- `BACKUP_HOST`

Verificar el dump sin restaurar:

```powershell
& 'C:\Program Files\PostgreSQL\16\bin\pg_restore.exe' --list PATH\al\archivo.dump
```

Debe listar objetos (tablas, constraints, indexes).

## Restore en base temporal (camino seguro)

1. Crear la base **como superuser** si el rol `rotor` no tiene `CREATEDB` (en el cluster local de esta máquina `rolcreatedb=false`):

```sql
CREATE DATABASE rotor_restore_mvp0 OWNER rotor;
```

2. Restaurar **sin** tocar `rotor`:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\db-restore.ps1 `
  -BackupPath 'C:\Temp\rotor-backups\rotor_mvp0_YYYYMMDD_HHMMSS.dump' `
  -TargetDatabase 'rotor_restore_mvp0'
```

Si el rol de conexión **sí** puede crear bases (p. ej. un PostgreSQL local de prueba donde `rotor` es owner superuser):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\db-restore.ps1 `
  -BackupPath 'C:\Temp\rotor-backups\rotor_mvp0_YYYYMMDD_HHMMSS.dump' `
  -TargetDatabase 'rotor_restore_script_test' `
  -CreateTargetIfMissing
```

Opcional: `PG_ADMIN_USERNAME` / `PG_ADMIN_PASSWORD` en el entorno para que solo el `CREATE DATABASE` use un rol administrador. No imprimir esas variables.

3. Comparar conteos con `rotor` (users, categories, products, customers, carts, orders, order_items, stock_reservations, migrations).

4. Eliminar **únicamente** la base temporal creada para la prueba. Nunca `DROP DATABASE rotor`.

## Restore destructivo sobre `rotor`

El script **rechaza** `TargetDatabase=rotor` por defecto.

Solo si hay dump verificado y se entiende la pérdida de datos actuales:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\db-restore.ps1 `
  -BackupPath 'C:\Temp\rotor-backups\rotor_mvp0_YYYYMMDD_HHMMSS.dump' `
  -TargetDatabase 'rotor' `
  -AllowDestructiveRestore `
  -ConfirmDestructive `
  -Clean
```

`-Clean` añade `pg_restore --clean --if-exists`. Hace falta cuando la base destino ya tiene esquema (p. ej. después de `php artisan test` / `RefreshDatabase`).

PHPUnit **no** debe correr contra `rotor`. Tras MVP-1 la suite usa `rotor_testing` (ver [`TEST_DATABASE.md`](./TEST_DATABASE.md)). Un `php artisan test` anterior a ese aislamiento, con `RefreshDatabase`, vaciaba `rotor`. Hacer backup **antes** de cualquier suite si aún no está aislada.

## Variables

Leídas de `.env` / `-EnvFile`:

- `DB_HOST`
- `DB_PORT` (default 5432)
- `DB_DATABASE` (nombre protegido; default `rotor`)
- `DB_USERNAME`
- `DB_PASSWORD` (no se imprime)

Opcionales de proceso:

- `PG_ADMIN_USERNAME`
- `PG_ADMIN_PASSWORD`

## Errores frecuentes

| Síntoma | Causa | Qué hacer |
|---|---|---|
| `Refusing backup: DB_HOST is not local` | Host remoto o vacío | Solo backup local |
| `Dump file is empty` | `pg_dump` falló | Revisar que la base exista; no se imprime password |
| `Refusing restore onto protected database` | Destino = `rotor` | Usar una DB temporal, o ambos flags destructivos |
| `CREATE DATABASE failed` / permiso denegado | `rotor` sin `CREATEDB` | Crear la DB como superuser y restaurar sin `-CreateTargetIfMissing` |
| `Target database does not exist` | Falta crear la DB | Superuser `CREATE DATABASE ... OWNER rotor` |
| `pg_restore` exit 1 | Warnings `--clean` de objetos ausentes | El script trata exit > 1 como fallo; 0 y 1 se aceptan |
| Conteos no MATCH | Dump de otro momento o restore incompleto | No usar el dump; repetir backup |

## Confidencialidad

No commitear dumps. No pegar `DB_PASSWORD`, hashes de usuarios, ni listados de `pg_restore` con datos de filas en issues/PRs.
