# Dificultades y errores durante el desarrollo (Entrega 4)

Aquí dejamos anotados los problemas que se presentaron al preparar la Entrega 4, cómo se detectaron y cómo se resolvieron.
Sirve como memoria del equipo y como guía de diagnóstico para quien repita el montaje.

**Contenido**

1. [Código, validaciones y diseño](#1-código-validaciones-y-diseño)
2. [Entorno de desarrollo en Windows](#2-entorno-de-desarrollo-en-windows)
3. [Servidor, dominio y despliegue](#3-servidor-dominio-y-despliegue)
4. [Rendimiento](#4-rendimiento)
5. [Lecciones aprendidas](#5-lecciones-aprendidas)

---

## 1. Código, validaciones y diseño

| # | Problema | Cómo se detectó | Solución |
|---|---|---|---|
| 1.1 | Los formularios aceptaban datos inválidos: teléfonos de cualquier longitud, tipo de documento libre, nombres con números, contraseñas débiles, cantidades absurdas en el carrito | Revisión manual de cada pantalla y casos límite | Reglas en el navegador **y** en el servidor (Form Requests de Laravel): teléfono de 10 dígitos que empieza por 3; tipo de documento de una lista fija (CC, CE, NIT, PP) con reglas de número por tipo; nombre solo con letras (2 a 100); contraseña de 8 a 72 con letra y número; cantidad de 1 a 999 limitada al stock; tarjeta con algoritmo de Luhn, vencimiento y CVC; productos y categorías con límites y sin `<` ni `>`. Cubiertas con pruebas de backend y de navegador |
| 1.2 | El pago real con Wompi dependía de llaves externas y no se podía demostrar siempre | Ensayo de la demo | Pasarela **simulada** con el mismo flujo de liquidación que Wompi y cinco tarjetas de prueba (aprobada, rechazada, fondos insuficientes, error y pendiente) |
| 1.3 | En un celular de 360 px el carrito se desbordaba (la página medía 438 px sobre 361 px disponibles) | Prueba automática con pantalla de teléfono Android | La tabla del carrito se apila en tarjetas por debajo de 640 px (`data-label` en cada celda) y el panel de administración usa un botón «Menú» |
| 1.4 | Una prueba de navegador buscaba el botón «Agregar al carrito» en la página pública sin sesión, que no existe | Fallo de la prueba | La prueba comprueba el SKU del producto, que sí es público |
| 1.5 | En la pasarela simulada **no se veían los números de las tarjetas de prueba**: el texto era blanco sobre fondo blanco | Captura de pantalla del equipo | El botón heredaba el color blanco del enlace del encabezado. Se creó un estilo propio para las tarjetas de prueba (número en letra oscura monoespaciada y descripción). El mismo defecto afectaba al botón «Quitar» del carrito y quedó corregido |
| 1.6 | Faltaba mostrar/ocultar la contraseña | Solicitud del equipo | Componente reutilizable `PasswordInput` con el ícono del ojo, usado en el ingreso de clientes, el registro (contraseña y confirmación), el ingreso de administración y el formulario de usuarios; con prueba automática |
| 1.7 | El diseño era sobrio pero poco identificable | Revisión del equipo | Identidad ByteBlade: logo en encabezado, pie, ingreso y menú de administración; encabezado claro, portada con degradado, tarjetas con elevación al pasar el mouse, botones y campos más cómodos de tocar y foco visible |
| 1.8 | Una prueba de interfaz podía romperse si el ojo de la contraseña repetía la etiqueta «Contraseña» | Revisión antes de ejecutar la suite | El botón se llama «Mostrar contraseña» / «Ocultar contraseña» y los campos se buscan por `id`; las 23 pruebas pasan |

## 2. Entorno de desarrollo en Windows

| # | Problema | Causa | Solución |
|---|---|---|---|
| 2.1 | Docker Desktop mostraba *unable to start* | WSL 2 desactualizado y falta de reinicio | Reiniciar el equipo y ejecutar `wsl --update` como administrador |
| 2.2 | `docker compose up` respondía *no configuration file provided: not found* | El comando se ejecutó fuera de la carpeta del proyecto | `cd C:\Rotor` antes de cualquier `docker compose` |
| 2.3 | `git clone <URL> Rotor` fallaba con errores de sintaxis | Se escribieron los signos `<` y `>` de la plantilla, que son solo marcadores | Escribir la URL sin los signos. Además el proyecto ya estaba en `C:\Rotor` |
| 2.4 | `php --version` mostraba 8.0.30 aunque se había instalado PHP 8.3 | XAMPP aparecía primero en el `PATH` | Anteponer la carpeta de PHP 8.3 al `PATH` del sistema (como administrador) y abrir una terminal nueva |
| 2.5 | `winget search composer` no encontraba Composer | No existe en el catálogo de winget | Instalar con el instalador oficial `Composer-Setup.exe` |
| 2.6 | PostgreSQL local rechazaba la contraseña de `postgres` | Contraseña desconocida | Procedimiento de restablecimiento: modo `trust` temporal en `pg_hba.conf`, cambio de contraseña y restauración desde la copia `.bak` |
| 2.7 | La contraseña generada del administrador «no aparecía» | PowerShell mostraba mal la «ñ» de «Contraseña generada» y la búsqueda no coincidía | Buscar «solo esta vez» |
| 2.8 | Riesgo de scripts con saltos de línea de Windows dentro de los contenedores | CRLF en archivos `.sh` | `.gitattributes` fuerza LF en scripts y archivos de Docker |

## 3. Servidor, dominio y despliegue

| # | Problema | Causa | Solución |
|---|---|---|---|
| 3.1 | Azure for Students respondió «No es apto» | La cuenta institucional no cumplía la verificación | Se descartó Azure y se contrató un VPS de pago de un mes (Hostinger KVM 1) |
| 3.2 | Duda sobre el tipo de producto de Hostinger | El hosting compartido no permite Docker ni Caddy | Se necesita el producto **VPS** con acceso root |
| 3.3 | `ssh root@IP` respondió *Permission denied* | La contraseña usada no coincidía con la guardada por el panel | *Restablecer contraseña* en el panel del VPS y volver a intentar |
| 3.4 | `nslookup byteblade.tech` respondía *Non-existent domain* | El dominio recién registrado aún no se había propagado y faltaba verificar el correo del registrador | Mientras tanto se usó el nombre de host del VPS (`srv2153504.hstgr.cloud`), que ya resolvía a la IP |
| 3.5 | Al pegar el bloque que crea el `.env`, la terminal mostró el texto mezclado y parecía que había fallado | La terminal intercala lo escrito con lo mostrado | Verificar el archivo con `grep -v -E "PASSWORD\|APP_KEY" .env` en lugar de fiarse de la pantalla |
| 3.6 | El flujo de CI/CD exige `APP_KEY` en el `.env` del servidor, y la primera instalación la generaba dentro del volumen | `compose.prod.yaml` declara `APP_KEY` como obligatoria | La guía de despliegue crea `APP_KEY` desde el primer `.env` |
| 3.7 | Los *secrets* de GitHub solo pueden crearlos los administradores del repositorio | Permisos de GitHub | El integrante de DevOps y calidad (administrador del repositorio) los configura; el resto verifica en *Actions* |

## 4. Rendimiento

| # | Problema | Estado |
|---|---|---|
| 4.1 | En Lighthouse local (móvil) el catálogo pasó de 93 a 95 en rendimiento, de 96 a 100 en buenas prácticas y su desplazamiento de diseño (CLS) de 0.091 a 0 | Resuelto (reserva de espacio del catálogo) |
| 4.2 | La página de registro bajó de 98 a 94 en rendimiento entre mediciones sin una causa identificada | Abierto; se repite la medición sobre la URL pública y se documenta en `informe-rendimiento.md` |
| 4.3 | Las mediciones locales no sirven como evidencia final: la entrega exige medir la URL pública | Pendiente de repetir sobre `https://srv2153504.hstgr.cloud` |

## 5. Lecciones aprendidas

1. Ejecutar siempre los comandos de Docker dentro de la carpeta del proyecto y comprobar el archivo con `dir` o `ls`.
2. Los textos entre `<` y `>` de una guía son marcadores: se reemplazan completos, incluidos los signos.
3. Nunca pegar, fotografiar ni publicar contraseñas, claves SSH ni tokens; si alguno se expone, se regenera de inmediato.
4. Verificar cada archivo de configuración con un comando que no muestre secretos antes de arrancar.
5. Un dominio nuevo tarda en propagarse: tener lista una alternativa inmediata (el nombre de host del proveedor).
6. Probar en pantalla de teléfono desde el inicio: es donde aparecen los desbordes de diseño.
7. Mantener pruebas automáticas por cada regla de validación evita regresiones al rediseñar.
8. Medir rendimiento sobre la URL pública y repetir la medición después de cada cambio visual.
