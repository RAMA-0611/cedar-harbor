# Evidencia E2 — Accesibilidad y buenas prácticas del prototipo

Resultados ejecutados sobre el prototipo estático de `web/`, servido localmente con:

```bash
cd web
python -m http.server 8091 --bind 127.0.0.1
```

Herramientas (instaladas de forma temporal fuera del repositorio; no se agregaron dependencias al proyecto):

| Herramienta | Versión |
|---|---|
| Lighthouse | 13.5.0 |
| Google Chrome (headless) | 153 |
| axe-core | 4.13.0 |
| playwright-core | 1.63.0 |

Los reportes HTML/JSON completos de Lighthouse se generaron en una carpeta temporal y no se versionan. Solo se incluyen capturas recortadas de la cabecera de puntuaciones.

## 1. Lighthouse

Categorías evaluadas: Accessibility y Best Practices (configuración por defecto de Lighthouse, emulación móvil).

| Vista | URL local | Accessibility | Best Practices | Resultado |
|---|---|---|---|---|
| Catálogo | http://127.0.0.1:8091/ | 100 | 100 | Aprobado |
| Detalle | http://127.0.0.1:8091/detalle.html?id=7c1e4b2a-5d3f-4a8e-9b61-2f0d3c4e5a01 | 100 | 100 | Aprobado |
| Checkout | http://127.0.0.1:8091/checkout.html | 100 | 100 | Aprobado |

Capturas:

- [`lighthouse-catalogo.png`](./lighthouse-catalogo.png)
- [`lighthouse-detalle.png`](./lighthouse-detalle.png)
- [`lighthouse-checkout.png`](./lighthouse-checkout.png)

Comando usado por vista:

```bash
lighthouse <URL> --only-categories=accessibility,best-practices --chrome-flags="--headless=new"
```

## 2. Ancho mínimo de 360 px

Script Playwright con viewport de 360 px que compara `scrollWidth` con `clientWidth` del documento.

| Vista / estado | scrollWidth | clientWidth |
|---|---|---|
| Catálogo | 360 | 360 |
| Catálogo `?state=loading` | 360 | 360 |
| Catálogo `?state=empty` | 360 | 360 |
| Catálogo `?state=error` | 360 | 360 |
| Detalle de producto con pocas unidades | 360 | 360 |
| Detalle con id inexistente | 360 | 360 |
| Detalle `&state=error` | 360 | 360 |
| Checkout con carrito vacío | 360 | 360 |
| Checkout `?state=error` | 360 | 360 |
| Checkout con carrito de 2 líneas | 360 | 360 |
| Checkout con errores de validación | 360 | 360 |

Resultado: **sin desbordamiento horizontal** en las 11 combinaciones.

## 3. Navegación por teclado

Script Playwright que recorre cada vista con `Tab` (a 1280 px y 360 px) y verifica que cada parada tenga foco visible, no quede tapada y esté dentro de la pantalla.

| Vista | Paradas de foco | Sin foco visible | Tapadas | Fuera de pantalla |
|---|---|---|---|---|
| Catálogo (1280 / 360) | 26 / 26 | 0 | 0 | 0 |
| Detalle (1280 / 360) | 15 / 15 | 0 | 0 | 0 |
| Checkout vacío (1280 / 360) | 10 / 10 | 0 | 0 | 0 |

Flujo completo realizado solo con teclado:

1. «Saltar al contenido» recibe foco visible y, con Enter, lo mueve a `#contenido`.
2. Búsqueda «contactor»: «1 de 12 productos».
3. Enter en «Ver detalle» abre el detalle del producto.
4. Cantidad 0 + Enter: «La cantidad debe ser mayor que cero.» y el foco vuelve al campo.
5. Cantidad 2 + Enter: producto agregado; el contador del carrito pasa a 2.
6. «Continuar con mis datos» mueve el foco al título del paso de datos.
7. Formulario completo con retiro en sede + Enter en «Continuar al pago»: confirmación del pedido de demostración, con el foco en el título de confirmación.
8. Estado de error + Enter en «Reintentar»: vuelven los 12 productos.
9. Estado vacío + Enter en «Limpiar filtros»: vuelven los 12 productos y el foco queda en la búsqueda.

## 4. axe-core

Reglas: `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` y `best-practice`, a 1280 px y 360 px.

| Estado analizado | Violaciones 1280 px | Violaciones 360 px |
|---|---|---|
| Catálogo en carga | 0 | 0 |
| Detalle de producto agotado | 0 | 0 |
| Detalle con confirmación de agregado | 0 | 0 |
| Checkout con carrito | 0 | 0 |
| Checkout con errores de validación | 0 | 0 |
| Checkout con confirmación | 0 | 0 |
| Checkout en error | 0 | 0 |

Resultado: **0 violaciones** en los 14 análisis. Las vistas iniciales de catálogo, detalle y checkout también quedan cubiertas por la auditoría de accesibilidad de Lighthouse, que usa axe-core.

## Alcance

Las herramientas automáticas detectan solo una parte de los problemas de accesibilidad. Estas pruebas no reemplazan una revisión manual con lectores de pantalla.
