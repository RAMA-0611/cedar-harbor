# Informe de rendimiento — Lighthouse antes y después

> **Alcance y honestidad de las mediciones.** Las dos corridas de este informe se hicieron el **11 de noviembre de 2026**
> sobre el **conjunto desplegado en una máquina local** (mismo Caddyfile, mismo php-fpm, Redis, PostgreSQL y la
> compilación `standalone` de Next.js que produce el Dockerfile), accediendo por `http://localhost`. **No son mediciones
> sobre la URL pública**: el hosting no estaba disponible al escribir este informe. La entrega exige repetir las dos corridas
> sobre la URL pública (sección 6 explica cómo y dónde anotar los resultados). Las cifras locales sirven para identificar
> causas y comprobar que cada corrección sirvió; la latencia de red real las cambiará.
>
> Herramienta: Lighthouse 12, modo móvil por defecto (emulación de Moto G Power y red 4G lenta simulada), Chromium sin interfaz.
> Los JSON completos están en `docs/lighthouse/`.

## 1. Línea base (antes de optimizar)

| Página | Rendimiento | Accesibilidad | Buenas prácticas | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| Catálogo `/` | **93** | **100** | **96** | **100** | 0.9 s | 2.7 s | 120 ms | **0.091** |
| Registro `/cliente/registro` | **98** | **100** | **96** | **100** | 0.9 s | 2.3 s | 50 ms | 0.028 |

Archivos: `docs/lighthouse/antes-catalogo.json`, `docs/lighthouse/antes-registro.json` (fecha: 11 de noviembre de 2026).

> Nota: antes de esta línea base ya se habían aplicado, como parte de la contenerización, tres decisiones que también
> ayudan al rendimiento y que **no** se midieron por separado: fuentes Geist locales (en vez de pedirlas a Google Fonts en
> cada carga), compilación `standalone` de Next.js y compresión zstd/gzip en el proxy Caddy.

## 2. Diagnóstico

Qué reportó la herramienta y a qué archivo o recurso corresponde cada hallazgo:

| Hallazgo | Recurso responsable | Efecto |
|---|---|---|
| **Error en consola** (Buenas prácticas 96) | `GET /api/auth/me` devolvía **401** a todo visitante anónimo en cada carga: lo dispara `AuthProvider` (`frontend/src/contexts/AuthContext.tsx`) al montar | Una petición de red inútil y un error en consola en cada visita |
| **CLS 0.091** | La rejilla del catálogo (`.store-grid`, `frontend/src/components/store/storefront.css`) estaba vacía mientras cargaba y el pie de página saltaba al llegar los productos | Salto de diseño perceptible |
| **Recursos que bloquean el renderizado** (ahorro estimado 150–300 ms) | Las hojas de estilo `/_next/static/chunks/0d3--tb4_y5tp.css` (1.9 KB) y `…/2ze_2-fo97jnp.css` (5.9 KB) | El navegador no pinta hasta descargarlas |
| **LCP 2.7 s** | Elemento: el párrafo del encabezado de la página (`section.store-hero > p`). TTFB ≈ 455 ms (17 % del LCP); el resto es retardo de renderizado por el CSS bloqueante y el JS de hidratación | Es texto, no una imagen: no hay imagen que optimizar en la zona visible |
| JS sin usar (~26 KiB) y JS heredado (~13 KiB) | `chunks/2khxl0v1q4i6n.js` (72 KB, 36 % sin usar) | Pendiente (ver sección 5) |
| `label-content-name-mismatch` | Enlace del logo en `StorefrontShell.tsx`: `aria-label="Proyecto Rotor tienda"` no coincidía con el texto visible «ByteBlade Proyecto Rotor» | Defecto de accesibilidad para quien usa control por voz |

## 3. Intervenciones (en el orden en que se aplicaron)

1. **No consultar `/api/auth/me` si nunca hubo sesión** (`AuthContext.tsx`). La cookie `XSRF-TOKEN` solo existe después de
   que alguien inicia sesión o se registra; sin ella se omite la petición. *Por qué:* elimina una petición por visita y el
   error de consola, sin cambiar el comportamiento (un visitante sin cookie siempre recibía 401).
2. **Reservar el espacio del catálogo** (`min-height: 70vh` en `.store-grid`). *Por qué:* el CLS lo provocaba el contenido
   que llegaba después; reservar su altura evita el salto sin ocultar nada.
3. **Insertar el CSS crítico en el HTML** (`experimental.inlineCss` en `next.config.ts`). *Por qué:* las dos hojas
   bloqueantes pesan 7.8 KB; incrustarlas elimina dos viajes de red antes del primer pintado.
4. **Nombre accesible del logo**: se quitó el `aria-label` que contradecía el texto visible; el nombre accesible ahora es
   el texto del enlace (corrige `label-content-name-mismatch`).

Después de cada cambio se verificó que `npm run lint`, `tsc`, las pruebas de Playwright y las de PHPUnit siguen pasando.

## 4. Medición posterior

| Página | Rendimiento | Accesibilidad | Buenas prácticas | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| Catálogo `/` | **95** (+2) | **100** (=) | **100** (+4) | **100** (=) | 0.7 s (−0.2) | 2.8 s (+0.1) | 110 ms (−10) | **0** (−0.091) |
| Registro `/cliente/registro` | **94** (−4) | **100** (=) | **100** (+4) | **100** (=) | 0.6 s (−0.3) | 2.8 s (+0.5) | 150 ms (+100) | 0.028 (=) |

Archivos: `docs/lighthouse/despues-catalogo.json`, `docs/lighthouse/despues-registro.json` (fecha: 11 de noviembre de 2026).

**Lectura de los números, sin adornos:**

- **Mejoraron de forma clara y atribuible a una intervención:** CLS del catálogo (0.091 → 0), Buenas prácticas en ambas
  páginas (96 → 100, por el 401 eliminado), FCP (≈ −0.2/−0.3 s, por el CSS en línea) y desaparición del hallazgo de
  recursos que bloquean el renderizado.
- **No mejoró:** el LCP no bajó (2.7 → 2.8 s en catálogo) y en el registro **empeoró** (2.3 → 2.8 s, rendimiento 98 → 94).
  Cada medición es una sola corrida con red simulada y varía por corrida; no se puede afirmar que el CSS en línea
  empeore el LCP del registro ni que no lo haga sin repetir varias veces. Queda como **pendiente de verificar** (ver 5).
- **Accesibilidad:** se mantiene en 100 (requisito: ≥ 90).

## 5. Pendiente y por qué

| Pendiente | Razón |
|---|---|
| Repetir cada medición 3–5 veces y reportar la mediana | Con una sola corrida no se puede separar el efecto real del ruido (en especial el LCP del registro) |
| Reducir JS sin usar (~26 KiB) y heredado (~13 KiB) | Requiere dividir paquetes por ruta y revisar el objetivo de compilación; el beneficio esperado es pequeño frente al riesgo a un día de la entrega |
| LCP ≈ 2.7 s (límite «bueno»: 2.5 s) | Dominado por el TTFB y la hidratación de React; mejorar de verdad requiere renderizar el catálogo en el servidor, un cambio de arquitectura fuera del alcance de esta entrega |
| Medir con la red real del hosting | Ver sección 6 |

## 6. Cómo repetir las mediciones sobre la URL pública (obligatorio para la entrega)

```bash
npx lighthouse https://srv2153504.hstgr.cloud/ --output=json --output-path=docs/lighthouse/publica-despues-catalogo.json
npx lighthouse https://srv2153504.hstgr.cloud/cliente/registro --output=json --output-path=docs/lighthouse/publica-despues-registro.json
```

1. Haga la **línea base pública** con el despliegue *anterior* a estas correcciones (o, si no existe, desactívelas
   temporalmente en una rama) y guarde captura y JSON con fecha.
2. Despliegue y repita. Anote ambas tablas aquí, reemplazando o ampliando las secciones 1 y 4 con la etiqueta
   «URL pública».
3. Verifique que la **accesibilidad siga en 90 o más** (hoy: 100).

## 7. Mediciones sobre la URL pública

Aplicación publicada en **https://srv2153504.hstgr.cloud** (VPS Ubuntu 24.04, Caddy con HTTPS). Mediciones del
**12 de noviembre de 2026** con Lighthouse 13.5.0 desde las herramientas de Chrome 157, en ventana de incógnito, modo móvil
con red simulada.

### 7.1 Línea base pública

| Página | Rendimiento | Accesibilidad | Buenas prácticas | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| Catálogo `/` | **100** | **100** | **96** | **100** | 1.1 s | 1.1 s | 40 ms | **0** |

Archivo: `docs/lighthouse/publica-antes-catalogo.json`. Se hicieron dos corridas (ventana normal e incógnito) con el mismo resultado.

### 7.2 Diagnóstico en la URL pública

| Hallazgo | Recurso responsable | Efecto |
|---|---|---|
| **Error en consola** (Buenas prácticas 96) | `GET /api/auth/me` respondía **401** a un visitante anónimo. La intervención 1 usaba la cookie `XSRF-TOKEN` como señal de «hubo sesión», pero la API entrega esa cookie a cualquier visitante desde la primera respuesta; a partir de la segunda carga la petición se hacía igual. En local no apareció porque cada corrida usaba un perfil de navegador vacío | Una petición inútil y un error en consola en cada visita repetida |
| **Imagen más pesada de lo necesario** (ahorro estimado ≈ 30 KiB) | `frontend/public/logo-byteblade.png`: 615 × 169 px para mostrarse a 105 × 29 px | Descarga innecesaria en la zona visible |

### 7.3 Intervenciones

5. **Señal de sesión propia** (`frontend/src/contexts/AuthContext.tsx`): al iniciar sesión o registrarse se guarda una marca
   local (`rotor.session`) y se borra al cerrar sesión o al recibir un 401. Solo se consulta `/api/auth/me` cuando esa marca
   existe. *Por qué:* es la única señal que distingue a quien inició sesión de un visitante anónimo; la cookie de sesión
   real es `HttpOnly` y no se puede leer desde el navegador. La autoridad sigue siendo el servidor.
6. **Logo redimensionado y con paleta reducida**: de 615 × 169 px (30 KB) a 300 × 82 px (menos de 4 KB) en sus dos versiones.
   *Por qué:* 300 px cubre con margen el tamaño al que se muestra, incluso en pantallas de alta densidad.

Tras los cambios se repitieron tipos, lint y las 23 pruebas de navegador: todas pasan.

### 7.4 Medición posterior en la URL pública

| Página | Rendimiento | Accesibilidad | Buenas prácticas | SEO | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|---|
| Catálogo `/` | **99** (−1) | **100** (=) | **100** (+4) | **100** (=) | 1.1 s (=) | 1.1 s (=) | 90 ms (+50) | **0** (=) |
| Registro `/cliente/registro` | **100** | **100** | **100** | **100** | 1.1 s | 1.1 s | 90 ms | **0** |

Archivos: `docs/lighthouse/publica-despues-catalogo.json` y `docs/lighthouse/publica-despues-registro.json`
(12 de noviembre de 2026, ventana de incógnito).

- **Mejoró y se atribuye a una intervención:** Buenas prácticas del catálogo pasó de 96 a 100; el error 401 de la consola
  desapareció (intervención 5) y el hallazgo del logo pesado ya no aparece (intervención 6).
- **No mejoró:** el rendimiento del catálogo quedó en 99 frente a 100, con un tiempo de bloqueo de 90 ms frente a 40 ms. Es
  una sola corrida por medición y esa diferencia cabe en la variación normal entre corridas; no hay evidencia de que los
  cambios la causen ni de que no.
- **Registro:** no tiene línea base pública (solo se midió después). Su «antes» y «después» son los locales de las
  secciones 1 y 4.
- **Sigue pendiente:** el aviso de JavaScript heredado (ahorro estimado de 13 KiB), ya anotado en la sección 5.

### 7.5 Cómo leer estos números

- El LCP local (2.8 s) y el público (1.1 s) **no son comparables**: las corridas locales usaron la línea de comandos con red
  4G lenta simulada y las públicas se hicieron desde las herramientas del navegador contra el servidor real. Por eso cada
  entorno tiene su propio «antes» y «después».
- La accesibilidad se mantiene en **100** (requisito: 90 o más).
