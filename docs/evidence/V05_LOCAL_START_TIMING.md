# Tiempo de puesta en marcha local (definición)

**Definición usada:**

> Desde arrancar Laravel (`php artisan serve :8000`) + Next (`npm run dev :3000`)
> en un equipo ya preparado (PHP, Composer, Node, PostgreSQL instalados y
> `vendor`/`node_modules` presentes), con servicios detenidos al inicio de cada corrida,
> hasta que `/up` y `/` responden correctamente.

**No** es “instalación desde computador vacío”.

Medición real (2026-11-04, máquina local de desarrollo):

| Run | TOTAL_READY_SECONDS |
|-----|---------------------|
| RUN1 | 100.93 |
| RUN2 | 98.17 |
| RUN3 | 98.15 |

- **mínimo:** 98.15 s  
- **máximo:** 100.93 s  
- **promedio:** 99.08 s  

Script de apoyo: `scripts/demo-start.ps1` (también verifica PostgreSQL y DB=`rotor`).

Opcional separado (no mezclado):

- `DEPENDENCY_INSTALL_TIME` = `composer install` + `npm ci` medidos aparte (no ejecutado en esta corrida).
