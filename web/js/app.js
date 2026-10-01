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

const cargarCatalogo = async () => {
  const respuesta = await fetch(RUTA_DATOS, { cache: 'no-store' });
  if (!respuesta.ok) throw new Error(`No se pudo cargar el catálogo (HTTP ${respuesta.status})`);
  return respuesta.json();
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

const iniciarCatalogo = async () => {
  const formulario = document.getElementById('form-filtros');
  const lista = document.getElementById('lista-productos');
  const conteo = document.getElementById('conteo');
  const estado = document.getElementById('estado');
  const selectCategoria = document.getElementById('categoria');

  estado.innerHTML = '<p class="mensaje">Cargando catálogo…</p>';

  let productos = [];
  try {
    ({ productos } = await cargarCatalogo());
  } catch (error) {
    estado.innerHTML = `<p class="mensaje">${escaparHtml(error.message)}</p>`;
    return;
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
    estado.innerHTML = visibles.length ? '' : '<p class="mensaje">No encontramos productos para los filtros seleccionados.</p>';
  };

  formulario.addEventListener('input', renderizar);
  formulario.addEventListener('submit', (evento) => {
    evento.preventDefault();
    renderizar();
  });
  formulario.addEventListener('reset', () => requestAnimationFrame(renderizar));

  renderizar();
};

const PAGINAS = {
  catalogo: iniciarCatalogo,
};

document.addEventListener('DOMContentLoaded', () => {
  PAGINAS[document.body.dataset.page]?.();
});
