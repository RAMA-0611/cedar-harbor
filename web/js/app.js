'use strict';

const RUTA_DATOS = 'datos/ejemplo.json';

const DISPONIBILIDAD = {
  disponible: { etiqueta: 'Disponible', icono: '✓' },
  pocas_unidades: { etiqueta: 'Pocas unidades', icono: '!' },
  agotado: { etiqueta: 'Agotado', icono: '✕' },
};

const ICONOS_CATEGORIA = {
  Rodamientos: '<circle cx="24" cy="24" r="18" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="24" cy="24" r="8" fill="none" stroke="currentColor" stroke-width="3"/><g fill="currentColor"><circle cx="24" cy="11" r="2.5"/><circle cx="37" cy="24" r="2.5"/><circle cx="24" cy="37" r="2.5"/><circle cx="11" cy="24" r="2.5"/></g>',
  'Cables y conductores': '<path d="M6 30c6 0 6-12 12-12s6 12 12 12 6-12 12-12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="6" cy="30" r="3" fill="currentColor"/><circle cx="42" cy="18" r="3" fill="currentColor"/>',
  Motores: '<rect x="8" y="14" width="26" height="20" rx="3" fill="none" stroke="currentColor" stroke-width="3"/><path d="M34 24h8M14 14v-4h14v4M12 34v4M30 34v4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
  Transformadores: '<rect x="8" y="12" width="32" height="24" rx="3" fill="none" stroke="currentColor" stroke-width="3"/><path d="M18 12v24M30 12v24" stroke="currentColor" stroke-width="3"/>',
  defecto: '<path d="M27 4 12 27h11l-3 17 16-24H25z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>',
};

const formatoMoneda = new Intl.NumberFormat('es-CO', {
  style: 'currency',
  currency: 'COP',
  maximumFractionDigits: 0,
});

const formatearPrecio = (valor) => formatoMoneda.format(valor);

const escaparHtml = (valor) =>
  String(valor).replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[caracter]);

const normalizarTexto = (texto) =>
  String(texto).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const obtenerClaveDisponibilidad = (producto) => {
  if (producto.cantidad_disponible <= 0) return 'agotado';
  if (producto.cantidad_disponible <= 5) return 'pocas_unidades';
  return 'disponible';
};

const pluralizarUnidad = (unidad, cantidad) => {
  if (cantidad === 1) return unidad;
  const [primera, ...resto] = unidad.split(' ');
  const plural = /[aeiou]$/i.test(primera) ? `${primera}s` : `${primera}es`;
  return [plural, ...resto].join(' ');
};

const describirStock = (producto) => {
  const clave = obtenerClaveDisponibilidad(producto);
  const { etiqueta, icono } = DISPONIBILIDAD[clave];
  const detalle = clave === 'agotado'
    ? ''
    : ` · ${producto.cantidad_disponible} ${pluralizarUnidad(producto.unidad, producto.cantidad_disponible)}`;
  return `<p class="stock stock--${clave}"><span aria-hidden="true">${icono}</span> ${etiqueta}${escaparHtml(detalle)}</p>`;
};

const iconoCategoria = (categoria) =>
  `<svg viewBox="0 0 48 48" focusable="false" aria-hidden="true">${ICONOS_CATEGORIA[categoria] ?? ICONOS_CATEGORIA.defecto}</svg>`;

/* Estados de demostración: ?state=loading|empty|error */

const ESTADOS_DEMO = {
  loading: 'carga en curso',
  empty: 'vacío',
  error: 'error de carga',
};

const leerEstadoDemo = () => {
  const estado = new URLSearchParams(window.location.search).get('state');
  return Object.hasOwn(ESTADOS_DEMO, estado ?? '') ? estado : null;
};

const urlSinEstadoDemo = () => {
  const url = new URL(window.location.href);
  url.searchParams.delete('state');
  return `${url.pathname}${url.search}${url.hash}`;
};

const quitarEstadoDemo = () => {
  if (!leerEstadoDemo()) return;
  window.history.replaceState(null, '', urlSinEstadoDemo());
  document.getElementById('aviso-demo')?.remove();
};

const mostrarAvisoDemo = () => {
  const estado = leerEstadoDemo();
  const contenedorEstado = document.getElementById('estado');
  if (!estado || !contenedorEstado) return;
  const aviso = document.createElement('p');
  aviso.id = 'aviso-demo';
  aviso.className = 'aviso aviso--info aviso-demo';
  aviso.innerHTML = `Vista de demostración: estado <strong>${ESTADOS_DEMO[estado]}</strong>.
    <a href="${escaparHtml(urlSinEstadoDemo())}">Ver la página sin simulación</a>`;
  contenedorEstado.before(aviso);
};

const plantillaCargando = (texto) => `
  <div class="estado estado--cargando" role="status">
    <span class="cargador" aria-hidden="true"></span>
    <p>${escaparHtml(texto)}</p>
  </div>`;

const plantillaError = (nivelTitulo, titulo, detalle) => `
  <div class="estado estado--error" role="alert">
    <h${nivelTitulo} class="estado__titulo">${escaparHtml(titulo)}</h${nivelTitulo}>
    <p>${escaparHtml(detalle)}</p>
    <button type="button" class="boton boton--primario" data-reintentar>Reintentar</button>
  </div>`;

const enfocarPrimerEncabezadoVisible = (contenedor) => {
  const encabezado = [...contenedor.querySelectorAll('h1, h2, h3')].find((nodo) => nodo.offsetParent !== null);
  if (!encabezado) return;
  encabezado.tabIndex = -1;
  encabezado.focus();
};

const conectarReintento = (iniciar) => {
  const estado = document.getElementById('estado');
  estado?.addEventListener('click', async (evento) => {
    if (!evento.target.closest('[data-reintentar]')) return;
    quitarEstadoDemo();
    await iniciar();
    enfocarPrimerEncabezadoVisible(estado.closest('section') ?? document.getElementById('contenido'));
  });
};

const cargarCatalogo = async () => {
  const estadoDemo = leerEstadoDemo();
  if (estadoDemo === 'loading') return new Promise(() => {});
  if (estadoDemo === 'error') throw new Error('Error simulado: el servicio de catálogo no respondió. Revisa tu conexión e inténtalo de nuevo.');

  const respuesta = await fetch(RUTA_DATOS, { cache: 'no-store' });
  if (!respuesta.ok) throw new Error(`No se pudo cargar el catálogo (HTTP ${respuesta.status})`);
  return respuesta.json();
};

/* Carrito (localStorage) */

const CLAVE_CARRITO = 'proyecto-rotor:carrito';

const leerCarrito = () => {
  try {
    const lineas = JSON.parse(localStorage.getItem(CLAVE_CARRITO) ?? '[]');
    return Array.isArray(lineas)
      ? lineas.filter((linea) => typeof linea.id === 'string' && Number.isInteger(linea.cantidad) && linea.cantidad > 0)
      : [];
  } catch {
    return [];
  }
};

const guardarCarrito = (lineas) => {
  localStorage.setItem(CLAVE_CARRITO, JSON.stringify(lineas));
  actualizarContadorCarrito();
};

const cantidadEnCarrito = (id) => leerCarrito().find((linea) => linea.id === id)?.cantidad ?? 0;

const agregarAlCarrito = (id, cantidad) => {
  const lineas = leerCarrito();
  const existente = lineas.find((linea) => linea.id === id);
  if (existente) {
    existente.cantidad += cantidad;
  } else {
    lineas.push({ id, cantidad });
  }
  guardarCarrito(lineas);
};

const actualizarCantidadCarrito = (id, cantidad) => {
  guardarCarrito(leerCarrito().map((linea) => (linea.id === id ? { ...linea, cantidad } : linea)));
};

const quitarDelCarrito = (id) => {
  guardarCarrito(leerCarrito().filter((linea) => linea.id !== id));
};

const vaciarCarrito = () => guardarCarrito([]);

const actualizarContadorCarrito = () => {
  const total = leerCarrito().reduce((suma, linea) => suma + linea.cantidad, 0);
  document.querySelectorAll('[data-cart-count]').forEach((nodo) => {
    nodo.textContent = String(total);
  });
};

const calcularTotales = (lineas, tasaIva) => {
  const subtotal = lineas.reduce((suma, linea) => suma + linea.producto.precio * linea.cantidad, 0);
  const iva = Math.round(subtotal * tasaIva);
  return { subtotal, iva, total: subtotal + iva };
};

/* Catálogo */

const crearTarjeta = (producto) => `
  <li>
    <article class="tarjeta">
      <div class="tarjeta__imagen">${iconoCategoria(producto.categoria)}</div>
      <div class="tarjeta__cuerpo">
        <p class="tarjeta__categoria">${escaparHtml(producto.categoria)}</p>
        <h3 class="tarjeta__titulo">${escaparHtml(producto.nombre)}</h3>
        <p class="tarjeta__sku">SKU: ${escaparHtml(producto.sku)} · ${escaparHtml(producto.condicion)}</p>
        ${describirStock(producto)}
        <p class="tarjeta__precio">${formatearPrecio(producto.precio)} <small>+ IVA / ${escaparHtml(producto.unidad)}</small></p>
        <a class="boton boton--primario" href="detalle.html?id=${encodeURIComponent(producto.id)}">
          Ver detalle<span class="sr-only"> de ${escaparHtml(producto.nombre)}</span>
        </a>
      </div>
    </article>
  </li>`;

const filtrarProductos = (productos, filtros) => {
  const consulta = normalizarTexto(filtros.q.trim());
  const filtrados = productos.filter((producto) => {
    const coincideTexto = !consulta
      || normalizarTexto(`${producto.nombre} ${producto.sku} ${producto.categoria}`).includes(consulta);
    const coincideCategoria = !filtros.categoria || producto.categoria === filtros.categoria;
    const coincideDisponibilidad = !filtros.disponibilidad
      || obtenerClaveDisponibilidad(producto) === filtros.disponibilidad;
    return coincideTexto && coincideCategoria && coincideDisponibilidad;
  });

  const ordenadores = {
    'precio-asc': (a, b) => a.precio - b.precio,
    'precio-desc': (a, b) => b.precio - a.precio,
    nombre: (a, b) => a.nombre.localeCompare(b.nombre, 'es'),
  };
  const ordenar = ordenadores[filtros.orden];
  return ordenar ? [...filtrados].sort(ordenar) : filtrados;
};

const leerFiltros = (formulario) => {
  const datos = new FormData(formulario);
  return {
    q: datos.get('q') ?? '',
    categoria: datos.get('categoria') ?? '',
    disponibilidad: datos.get('disponibilidad') ?? '',
    orden: datos.get('orden') ?? 'relevancia',
  };
};

const PLANTILLA_SIN_RESULTADOS = `
  <div class="estado estado--vacio">
    <h3 class="estado__titulo">No encontramos productos para los filtros seleccionados</h3>
    <p>Prueba con otra búsqueda o limpia los filtros para ver todo el catálogo.</p>
    <button type="button" class="boton boton--secundario" data-limpiar-filtros>Limpiar filtros</button>
  </div>`;

const aplicarFiltrosSinResultados = (formulario, productos, categorias) => {
  const categoriaSinAgotados = categorias.find((categoria) => !productos
    .some((producto) => producto.categoria === categoria && obtenerClaveDisponibilidad(producto) === 'agotado'));
  if (categoriaSinAgotados) {
    formulario.elements.categoria.value = categoriaSinAgotados;
    formulario.elements.disponibilidad.value = 'agotado';
    return;
  }
  formulario.elements.q.value = 'sin coincidencias';
};

const iniciarCatalogo = async () => {
  const formulario = document.getElementById('form-filtros');
  const resultados = document.querySelector('.resultados');
  const lista = document.getElementById('lista-productos');
  const conteo = document.getElementById('conteo');
  const estado = document.getElementById('estado');
  const selectCategoria = document.getElementById('categoria');

  lista.innerHTML = '';
  conteo.textContent = '';
  resultados.setAttribute('aria-busy', 'true');
  estado.innerHTML = plantillaCargando('Cargando catálogo…');

  let productos = [];
  try {
    ({ productos } = await cargarCatalogo());
  } catch (error) {
    estado.innerHTML = plantillaError(3, 'No pudimos cargar el catálogo', error.message);
    return;
  } finally {
    resultados.removeAttribute('aria-busy');
  }
  estado.innerHTML = '';

  const categorias = [...new Set(productos.map((producto) => producto.categoria))].sort((a, b) => a.localeCompare(b, 'es'));
  selectCategoria.insertAdjacentHTML('beforeend', categorias
    .map((categoria) => `<option value="${escaparHtml(categoria)}">${escaparHtml(categoria)}</option>`)
    .join(''));

  const renderizar = () => {
    const visibles = filtrarProductos(productos, leerFiltros(formulario));
    lista.innerHTML = visibles.map(crearTarjeta).join('');
    conteo.textContent = `${visibles.length} de ${productos.length} productos`;
    estado.innerHTML = visibles.length ? '' : PLANTILLA_SIN_RESULTADOS;
  };

  formulario.addEventListener('input', () => {
    quitarEstadoDemo();
    renderizar();
  });
  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    renderizar();
  });
  formulario.addEventListener('reset', () => {
    quitarEstadoDemo();
    requestAnimationFrame(renderizar);
  });

  estado.addEventListener('click', (evento) => {
    if (!evento.target.closest('[data-limpiar-filtros]')) return;
    formulario.reset();
    document.getElementById('busqueda').focus();
  });

  if (leerEstadoDemo() === 'empty') aplicarFiltrosSinResultados(formulario, productos, categorias);
  renderizar();
};

/* Detalle */

const validarCantidad = (valorTexto, maximo, contexto = 'agregar') => {
  if (valorTexto.trim() === '') return 'Ingresa una cantidad.';
  const valor = Number(valorTexto);
  if (!Number.isInteger(valor)) return 'Ingresa un número entero.';
  if (valor <= 0) return 'La cantidad debe ser mayor que cero.';
  if (valor <= maximo) return '';
  const unidades = `${maximo} ${maximo === 1 ? 'unidad' : 'unidades'}`;
  return contexto === 'agregar'
    ? `Solo puedes agregar ${unidades} más.`
    : `Solo hay ${unidades} disponibles.`;
};

const mostrarErrorCampo = (input, nodoError, mensaje) => {
  nodoError.textContent = mensaje;
  if (mensaje) {
    input.setAttribute('aria-invalid', 'true');
  } else {
    input.removeAttribute('aria-invalid');
  }
};

const iniciarDetalle = async () => {
  const estado = document.getElementById('estado');
  const ficha = document.getElementById('ficha');
  const id = new URLSearchParams(window.location.search).get('id');

  estado.innerHTML = plantillaCargando('Cargando producto…');

  let producto;
  try {
    const { productos } = await cargarCatalogo();
    producto = productos.find((item) => item.id === id);
  } catch (error) {
    estado.innerHTML = plantillaError(1, 'No pudimos cargar el producto', error.message);
    return;
  }

  if (!producto) {
    estado.innerHTML = `
      <h1>Producto no encontrado</h1>
      <p class="mensaje">El enlace no corresponde a ningún producto del catálogo.</p>
      <a class="boton boton--primario" href="index.html">Ir al catálogo</a>`;
    return;
  }

  estado.innerHTML = '';
  document.title = `${producto.nombre} · Proyecto Rotor`;
  document.getElementById('miga-categoria').textContent = producto.categoria;
  document.getElementById('miga-producto').textContent = producto.nombre;
  document.getElementById('ficha-imagen').innerHTML = iconoCategoria(producto.categoria);
  document.getElementById('ficha-pildoras').innerHTML = [producto.categoria, producto.condicion, producto.marca]
    .map((texto) => `<li class="pildora">${escaparHtml(texto)}</li>`)
    .join('');
  document.getElementById('ficha-nombre').textContent = producto.nombre;
  document.getElementById('ficha-descripcion').textContent = producto.descripcion;
  document.getElementById('ficha-sku').textContent = producto.sku;
  document.getElementById('ficha-categoria').textContent = producto.categoria;
  document.getElementById('ficha-condicion').textContent = producto.condicion;
  document.getElementById('ficha-marca').textContent = producto.marca;
  document.getElementById('ficha-unidad').textContent = producto.unidad;
  document.getElementById('ficha-precio').textContent = formatearPrecio(producto.precio);
  document.getElementById('ficha-precio-unidad').textContent = producto.unidad;
  document.getElementById('ficha-stock').innerHTML = describirStock(producto);
  ficha.hidden = false;

  const formulario = document.getElementById('form-agregar');
  const input = document.getElementById('cantidad');
  const ayuda = document.getElementById('cantidad-ayuda');
  const error = document.getElementById('cantidad-error');
  const botonAgregar = document.getElementById('boton-agregar');
  const botonesPaso = formulario.querySelectorAll('[data-paso]');
  const confirmacion = document.getElementById('confirmacion-agregar');

  const maximoDisponible = () => Math.max(producto.cantidad_disponible - cantidadEnCarrito(producto.id), 0);

  const actualizarLimites = () => {
    const maximo = maximoDisponible();
    const enCarrito = cantidadEnCarrito(producto.id);
    input.max = String(Math.max(maximo, 1));

    if (producto.cantidad_disponible === 0) {
      ayuda.textContent = 'Este producto no tiene unidades disponibles en este momento.';
    } else if (maximo === 0) {
      ayuda.textContent = `Ya tienes en el carrito todas las unidades disponibles (${enCarrito}).`;
    } else {
      const textoCarrito = enCarrito ? ` Ya tienes ${enCarrito} en el carrito.` : '';
      ayuda.textContent = `Puedes agregar hasta ${maximo} ${pluralizarUnidad(producto.unidad, maximo)}.${textoCarrito}`;
    }

    const sinCupo = maximo === 0;
    input.disabled = sinCupo;
    botonesPaso.forEach((boton) => { boton.disabled = sinCupo; });
    botonAgregar.disabled = sinCupo;
    botonAgregar.textContent = producto.cantidad_disponible === 0 ? 'Producto agotado' : 'Agregar al carrito';
  };

  botonesPaso.forEach((boton) => {
    boton.addEventListener('click', () => {
      const actual = Number.parseInt(input.value, 10) || 0;
      const siguiente = Math.min(Math.max(actual + Number(boton.dataset.paso), 1), Math.max(maximoDisponible(), 1));
      input.value = String(siguiente);
      mostrarErrorCampo(input, error, validarCantidad(input.value, maximoDisponible()));
    });
  });

  input.addEventListener('input', () => {
    mostrarErrorCampo(input, error, validarCantidad(input.value, maximoDisponible()));
  });

  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    const mensaje = validarCantidad(input.value, maximoDisponible());
    mostrarErrorCampo(input, error, mensaje);
    if (mensaje) {
      input.focus();
      return;
    }

    const cantidad = Number(input.value);
    agregarAlCarrito(producto.id, cantidad);
    confirmacion.innerHTML = `
      <p>Agregaste <strong>${cantidad} × ${escaparHtml(producto.nombre)}</strong> al carrito.</p>
      <div class="acciones">
        <a class="boton boton--primario" href="checkout.html">Ir al carrito y checkout</a>
        <a class="boton boton--secundario" href="index.html">Seguir comprando</a>
      </div>`;
    confirmacion.hidden = false;
    input.value = '1';
    actualizarLimites();
  });

  actualizarLimites();
};

/* Checkout */

const REGLAS_CHECKOUT = {
  nombre: (valor) => (valor.trim().split(/\s+/).filter(Boolean).length >= 2 ? '' : 'Escribe tu nombre y al menos un apellido.'),
  tipo_documento: (valor) => (valor ? '' : 'Selecciona el tipo de documento.'),
  numero_documento: (valor) => (/^\d{5,12}$/.test(valor.trim()) ? '' : 'El número de documento debe tener entre 5 y 12 dígitos, sin puntos ni espacios.'),
  correo: (valor) => (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor.trim()) ? '' : 'Escribe un correo válido, por ejemplo nombre@example.com.'),
  telefono: (valor) => (/^\d{10}$/.test(valor.replace(/\s+/g, '')) ? '' : 'El teléfono debe tener 10 dígitos.'),
  entrega: (valor) => (valor ? '' : 'Elige una modalidad de entrega.'),
  direccion: (valor, datos) => (datos.get('entrega') !== 'envio' || valor.trim().length >= 8 ? '' : 'Escribe la dirección de entrega (mínimo 8 caracteres).'),
};

const CAMPOS_CHECKOUT = {
  nombre: { id: 'nombre', etiqueta: 'Nombre completo' },
  tipo_documento: { id: 'tipo-documento', etiqueta: 'Tipo de documento' },
  numero_documento: { id: 'numero-documento', etiqueta: 'Número de documento' },
  correo: { id: 'correo', etiqueta: 'Correo electrónico' },
  telefono: { id: 'telefono', etiqueta: 'Teléfono' },
  entrega: { id: 'entrega', etiqueta: 'Modalidad de entrega' },
  direccion: { id: 'direccion', etiqueta: 'Dirección de entrega' },
};

const iniciarCheckout = async () => {
  const estado = document.getElementById('estado');
  const resumen = document.getElementById('resumen');
  const secciones = {
    carrito: document.getElementById('paso-carrito'),
    datos: document.getElementById('paso-datos'),
    confirmacion: document.getElementById('paso-confirmacion'),
  };

  estado.innerHTML = plantillaCargando('Cargando carrito…');

  let catalogo;
  try {
    catalogo = await cargarCatalogo();
  } catch (error) {
    estado.innerHTML = plantillaError(2, 'No pudimos cargar tu carrito', error.message);
    return;
  }
  estado.innerHTML = '';

  const tasaIva = catalogo.tasa_iva;
  const productosPorId = new Map(catalogo.productos.map((producto) => [producto.id, producto]));

  const obtenerLineas = () => (leerEstadoDemo() === 'empty' ? [] : leerCarrito())
    .filter((linea) => productosPorId.has(linea.id))
    .map((linea) => ({ ...linea, producto: productosPorId.get(linea.id) }));

  const mostrarPaso = (paso, moverFoco = true) => {
    Object.entries(secciones).forEach(([nombre, seccion]) => { seccion.hidden = nombre !== paso; });
    document.querySelectorAll('[data-paso-indicador]').forEach((item) => {
      if (item.dataset.pasoIndicador === paso) {
        item.setAttribute('aria-current', 'step');
      } else {
        item.removeAttribute('aria-current');
      }
    });
    if (moverFoco) secciones[paso].querySelector('h2').focus();
  };

  const renderizarResumen = (lineas) => {
    const { subtotal, iva, total } = calcularTotales(lineas, tasaIva);
    document.getElementById('resumen-lineas').innerHTML = lineas.map((linea) => `
      <li>
        <span>${escaparHtml(linea.producto.nombre)} <span class="resumen__cantidad">× ${linea.cantidad}</span></span>
        <span>${formatearPrecio(linea.producto.precio * linea.cantidad)}</span>
      </li>`).join('');
    document.getElementById('resumen-subtotal').textContent = formatearPrecio(subtotal);
    document.getElementById('resumen-iva').textContent = formatearPrecio(iva);
    document.getElementById('resumen-total').textContent = formatearPrecio(total);
    resumen.hidden = lineas.length === 0;
    return total;
  };

  const mostrarCarritoVacio = () => {
    Object.values(secciones).forEach((seccion) => { seccion.hidden = true; });
    resumen.hidden = true;
    estado.innerHTML = `
      <div class="mensaje">
        <h2>Tu carrito está vacío</h2>
        <p>Agrega productos desde el catálogo para continuar con la compra.</p>
        <a class="boton boton--primario" href="index.html">Ir al catálogo</a>
      </div>`;
  };

  const renderizarCarrito = () => {
    const lineas = obtenerLineas();
    if (lineas.length === 0) {
      mostrarCarritoVacio();
      return;
    }

    document.getElementById('lineas-carrito').innerHTML = lineas.map((linea) => {
      const { producto } = linea;
      const idCampo = `cantidad-${producto.id}`;
      return `
        <li class="linea">
          <div class="linea__info">
            <h3 class="linea__nombre"><a href="detalle.html?id=${encodeURIComponent(producto.id)}">${escaparHtml(producto.nombre)}</a></h3>
            <p class="linea__meta">SKU: ${escaparHtml(producto.sku)} · ${formatearPrecio(producto.precio)} + IVA por ${escaparHtml(producto.unidad)}</p>
          </div>
          <div class="linea__cantidad campo">
            <label for="${idCampo}">Cantidad<span class="sr-only"> de ${escaparHtml(producto.nombre)}</span></label>
            <input id="${idCampo}" type="number" inputmode="numeric" min="1" max="${producto.cantidad_disponible}" step="1" value="${linea.cantidad}" data-id="${escaparHtml(producto.id)}" aria-describedby="${idCampo}-error">
            <p id="${idCampo}-error" class="error-campo" aria-live="polite"></p>
          </div>
          <p class="linea__subtotal">${formatearPrecio(producto.precio * linea.cantidad)}</p>
          <button type="button" class="boton boton--texto" data-quitar="${escaparHtml(producto.id)}">
            Quitar<span class="sr-only"> ${escaparHtml(producto.nombre)} del carrito</span>
          </button>
        </li>`;
    }).join('');

    renderizarResumen(lineas);
  };

  const listaCarrito = document.getElementById('lineas-carrito');

  listaCarrito.addEventListener('change', (evento) => {
    const input = evento.target.closest('input[data-id]');
    if (!input) return;
    const producto = productosPorId.get(input.dataset.id);
    const nodoError = document.getElementById(`${input.id}-error`);
    const mensaje = validarCantidad(input.value, producto.cantidad_disponible, 'total');
    mostrarErrorCampo(input, nodoError, mensaje);
    if (mensaje) return;
    actualizarCantidadCarrito(producto.id, Number(input.value));
    const posicion = input.id;
    renderizarCarrito();
    document.getElementById(posicion)?.focus();
  });

  listaCarrito.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-quitar]');
    if (!boton) return;
    quitarDelCarrito(boton.dataset.quitar);
    renderizarCarrito();
    if (obtenerLineas().length) secciones.carrito.querySelector('h2').focus();
  });

  document.getElementById('ir-a-datos').addEventListener('click', () => {
    const hayErrores = [...listaCarrito.querySelectorAll('input[aria-invalid="true"]')];
    if (hayErrores.length) {
      hayErrores[0].focus();
      return;
    }
    mostrarPaso('datos');
  });

  document.getElementById('volver-a-carrito').addEventListener('click', () => mostrarPaso('carrito'));

  const formulario = document.getElementById('form-checkout');
  const campoDireccion = document.getElementById('campo-direccion');
  const resumenErrores = document.getElementById('resumen-errores');

  formulario.addEventListener('change', (evento) => {
    if (evento.target.name !== 'entrega') return;
    const esEnvio = evento.target.value === 'envio';
    campoDireccion.hidden = !esEnvio;
    document.getElementById('direccion').required = esEnvio;
  });

  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    const datos = new FormData(formulario);
    const errores = [];

    Object.entries(REGLAS_CHECKOUT).forEach(([nombre, regla]) => {
      const { id, etiqueta } = CAMPOS_CHECKOUT[nombre];
      const mensaje = regla(String(datos.get(nombre) ?? ''), datos);
      const nodoError = document.getElementById(`${id}-error`);
      const input = nombre === 'entrega'
        ? formulario.querySelector('input[name="entrega"]')
        : document.getElementById(id);
      mostrarErrorCampo(input, nodoError, mensaje);
      if (mensaje) errores.push({ id: input.id || id, etiqueta, mensaje, input });
    });

    if (errores.length) {
      resumenErrores.innerHTML = `
        <h3>Revisa ${errores.length === 1 ? 'el siguiente campo' : `los siguientes ${errores.length} campos`}:</h3>
        <ul>${errores.map((error) => `<li><a href="#${error.input.id}">${escaparHtml(error.etiqueta)}: ${escaparHtml(error.mensaje)}</a></li>`).join('')}</ul>`;
      resumenErrores.hidden = false;
      resumenErrores.focus();
      return;
    }

    resumenErrores.hidden = true;
    const lineas = obtenerLineas();
    const total = renderizarResumen(lineas);
    const codigo = `PR-SBX-${String(Math.floor(Math.random() * 1e6)).padStart(6, '0')}`;
    const entrega = datos.get('entrega') === 'envio' ? 'Envío coordinado' : 'Retiro en sede';
    document.getElementById('confirmacion-detalle').innerHTML = `
      Pedido de demostración <strong>${codigo}</strong> por <strong>${formatearPrecio(total)}</strong> (IVA incluido).
      Modalidad: ${entrega}. En un flujo real enviaríamos el comprobante a <strong>${escaparHtml(String(datos.get('correo')).trim())}</strong>.`;
    vaciarCarrito();
    formulario.reset();
    campoDireccion.hidden = true;
    mostrarPaso('confirmacion');
  });

  resumenErrores.addEventListener('click', (evento) => {
    const enlace = evento.target.closest('a[href^="#"]');
    if (!enlace) return;
    evento.preventDefault();
    document.getElementById(enlace.getAttribute('href').slice(1))?.focus();
  });

  renderizarCarrito();
  if (obtenerLineas().length) mostrarPaso('carrito', false);
};

const PAGINAS = {
  catalogo: iniciarCatalogo,
  detalle: iniciarDetalle,
  checkout: iniciarCheckout,
};

document.addEventListener('DOMContentLoaded', () => {
  actualizarContadorCarrito();
  mostrarAvisoDemo();
  const iniciarPagina = PAGINAS[document.body.dataset.page];
  if (!iniciarPagina) return;
  conectarReintento(iniciarPagina);
  iniciarPagina();
});
