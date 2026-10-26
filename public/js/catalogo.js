// public/js/catalogo.js — Laboratorio 14: la vitrina se actualiza con fetch + API REST sin recargar la página.
//   · Grilla con filtros, orden y paginación desde GET /api/v1/productos
//   · AbortController: una respuesta VIEJA nunca pisa a la búsqueda más reciente; pausa (debounce) de 250 ms
//   · Autocompletar accesible (patrón combobox de WAI-ARIA) con GET /api/v1/productos/sugerencias
//   · Novedades: el RSS del Laboratorio 12 leído con DOMParser
// Regla de oro: los datos de la API se insertan SIEMPRE con textContent (nunca innerHTML): «<b>x</b>» se ve como texto.
import { bolivianos, conPausa, ErrorApi, obtener } from "./api.js";

const $ = (selector) => document.querySelector(selector);
const form = $("#filtros-catalogo");
const buscador = $("#q");
const resumen = $("#catalogo");
const contenido = $("#grilla-contenido");
const aviso = $("#catalogo-error");
const lista = $("#sugerencias");
const POR_PAGINA = 8;
const metricas = { peticionesGrilla: 0, canceladas: 0 };      // las leen las pruebas con navegador

let pagina = Number(new URLSearchParams(location.search).get("pagina")) || 1;
let controladorGrilla = null;
let controladorSugerencias = null;
let activa = -1;

const crear = (etiqueta, clase = "", texto = "") => {
  const el = document.createElement(etiqueta);
  if (clase) el.className = clase;
  if (texto) el.textContent = texto;
  return el;
};

/* ---------- 1) Grilla asíncrona ---------- */
function parametros() {
  const datos = new URLSearchParams();
  for (const [clave, valor] of new FormData(form)) {
    if (String(valor).trim() !== "") datos.set(clave, String(valor).trim());
  }
  if (datos.get("orden") === "nombre") datos.delete("orden");
  return datos;
}

async function cargarGrilla() {
  controladorGrilla?.abort();                     // cancela la petición anterior: en DevTools aparece «(canceled)»
  controladorGrilla = new AbortController();
  const filtros = parametros();
  const api = new URLSearchParams(filtros);
  api.set("pagina", String(pagina));
  api.set("por_pagina", String(POR_PAGINA));
  contenido.setAttribute("aria-busy", "true");
  metricas.peticionesGrilla++;
  try {
    const resultado = await obtener(`/api/v1/productos?${api}`, controladorGrilla.signal);
    dibujar(resultado);
    aviso.classList.add("d-none");
    if (pagina > 1) filtros.set("pagina", String(pagina));
    history.replaceState(null, "", filtros.toString() ? `/?${filtros}` : "/");   // la URL se puede compartir
  } catch (e) {
    if (e.name === "AbortError") { metricas.canceladas++; return; }
    if (e instanceof ErrorApi && e.status === 422 && pagina !== 1) { pagina = 1; cargarGrilla(); return; }
    aviso.textContent = e instanceof ErrorApi ? `${e.title}: ${e.message}` : "No se pudo cargar el catálogo.";
    aviso.classList.remove("d-none");
  } finally {
    contenido.setAttribute("aria-busy", "false");
  }
}

function dibujar({ datos, meta }) {
  const desde = meta.total === 0 ? 0 : (meta.pagina - 1) * meta.por_pagina + 1;
  resumen.textContent = meta.total === 0
    ? "No encontramos prendas con esos filtros."
    : `${meta.total} ${meta.total === 1 ? "prenda" : "prendas"}` + (meta.paginas > 1 ? ` · mostrando ${desde}–${desde + datos.length - 1}` : "");
  if (datos.length === 0) {
    const vacio = crear("div", "vacio text-center p-5 bg-white rounded-4 shadow-sm");
    vacio.append(crear("i", "bi bi-bag-x fs-1 text-secondary"), crear("p", "mt-2 mb-0", "No encontramos prendas con esos filtros."));
    contenido.replaceChildren(vacio);
    return;
  }
  const grilla = crear("ul", "grilla row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 list-unstyled");
  grilla.append(...datos.map(tarjeta));
  contenido.replaceChildren(grilla, navegacion(meta));
}

function tarjeta(p) {
  const item = crear("li", `tarjeta col${p.disponible > 0 ? "" : " agotado"}`);
  const card = crear("article", "card h-100 border-0 shadow-sm");
  const enlace = crear("a", "stretched-link text-decoration-none");
  enlace.href = `/producto.php?codigo=${encodeURIComponent(p.codigo)}`;
  Object.assign(enlace.dataset, { modal: "", ancho: "", titulo: "Detalle de la prenda" });
  const marco = crear("div", "ratio ratio-1x1 card-img-top overflow-hidden rounded-top");
  const inicial = () => {
    const sin = crear("span", "sin-foto", p.nombre.slice(0, 1));
    sin.setAttribute("aria-hidden", "true");
    return sin;
  };
  // La URL de la foto la arma el servidor; solo se aceptan rutas internas (nunca «javascript:» ni otro dominio)
  if (typeof p.foto === "string" && p.foto.startsWith("/") && !p.foto.startsWith("//")) {
    const img = Object.assign(crear("img", "object-fit-cover"), { src: p.foto, alt: p.nombre, width: 320, height: 320, loading: "lazy" });
    img.addEventListener("error", () => img.replaceWith(inicial()), { once: true });
    marco.append(img);
  } else {
    marco.append(inicial());
  }
  enlace.append(marco, crear("span", "visually-hidden", `Ver ${p.nombre}`));
  card.append(enlace);
  if (p.disponible <= 0) card.append(crear("span", "badge text-bg-danger position-absolute top-0 start-0 m-2", "Agotado"));
  const cuerpo = crear("div", "card-body");
  cuerpo.append(
    crear("span", "categoria small text-secondary d-block", p.categoria.nombre),
    crear("h2", "nombre h6 fw-semibold mb-1", p.nombre),
    crear("span", "precio fw-bold d-block", bolivianos(p.precio)),
    crear("span", "tallas small text-secondary", `Tallas: ${p.tallas.map((t) => (t === "U" ? "Talla única" : t)).join(" · ")}`),
  );
  card.append(cuerpo);
  item.append(card);
  return item;
}

function navegacion(meta) {
  const nav = crear("nav", "paginacion mt-4");
  nav.setAttribute("aria-label", "Paginación");
  if (meta.paginas <= 1) return nav;
  const ul = crear("ul", "pagination justify-content-center");
  const boton = (texto, destino, actual = false, deshabilitado = false) => {
    const li = crear("li", `page-item${actual ? " active" : ""}${deshabilitado ? " disabled" : ""}`);
    const b = crear("button", "page-link", texto);
    b.type = "button";
    b.disabled = deshabilitado;
    if (actual) b.setAttribute("aria-current", "page");
    b.addEventListener("click", () => { pagina = destino; cargarGrilla(); resumen.scrollIntoView({ behavior: "smooth" }); });
    li.append(b);
    return li;
  };
  ul.append(boton("← Anterior", meta.pagina - 1, false, meta.pagina === 1));
  for (let n = 1; n <= meta.paginas; n++) ul.append(boton(String(n), n, n === meta.pagina));
  ul.append(boton("Siguiente →", meta.pagina + 1, false, meta.pagina === meta.paginas));
  nav.append(ul);
  return nav;
}

/* ---------- 2) Autocompletar accesible (combobox WAI-ARIA) ---------- */
const pedirSugerencias = conPausa(async (texto) => {
  controladorSugerencias?.abort();
  if (texto.length < 2) { cerrarLista(); return; }
  controladorSugerencias = new AbortController();
  try {
    const { datos } = await obtener(`/api/v1/productos/sugerencias?q=${encodeURIComponent(texto)}`, controladorSugerencias.signal);
    mostrarSugerencias(datos);
  } catch (e) {
    if (e.name !== "AbortError") cerrarLista();
  }
}, 250);

function mostrarSugerencias(items) {
  activa = -1;
  lista.replaceChildren(...items.map((s, i) => {
    const li = crear("li", "list-group-item list-group-item-action", `${s.nombre} — ${bolivianos(s.precio)}`);
    li.id = `sugerencia-${i}`;
    li.setAttribute("role", "option");
    li.setAttribute("aria-selected", "false");
    li.dataset.nombre = s.nombre;
    li.addEventListener("mousedown", (ev) => { ev.preventDefault(); elegir(i); });
    return li;
  }));
  lista.classList.toggle("d-none", items.length === 0);
  buscador.setAttribute("aria-expanded", String(items.length > 0));
  $("#estado-sugerencias").textContent = items.length ? `${items.length} sugerencias. Usa las flechas para elegir.` : "Sin sugerencias.";
}

function cerrarLista() {
  activa = -1;                                    // sin esto, el siguiente Enter «elige» una opción que ya no existe
  lista.classList.add("d-none");
  lista.replaceChildren();
  buscador.setAttribute("aria-expanded", "false");
  buscador.removeAttribute("aria-activedescendant");
}

function marcar(i) {
  const opciones = [...lista.children];
  if (opciones.length === 0) return;
  activa = (i + opciones.length) % opciones.length;          // circular: tras la última vuelve a la primera
  opciones.forEach((li, n) => {
    li.setAttribute("aria-selected", String(n === activa));
    li.classList.toggle("active", n === activa);
  });
  buscador.setAttribute("aria-activedescendant", opciones[activa].id);   // el lector de pantalla anuncia la opción
}

function elegir(i) {
  const opcion = lista.children[i];
  if (!opcion) return;
  buscador.value = opcion.dataset.nombre;
  cerrarLista();
  pagina = 1;
  cargarGrilla();
}

/* ---------- 3) Novedades: RSS leído con fetch + DOMParser ---------- */
async function cargarNovedades() {
  const novedades = $("#novedades");
  if (!novedades) return;
  const mensaje = (texto) => novedades.replaceChildren(crear("li", "text-secondary", texto));
  try {
    const respuesta = await fetch("/noticias.rss", { headers: { Accept: "application/rss+xml" } });
    if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
    const xml = new DOMParser().parseFromString(await respuesta.text(), "application/xml");
    if (xml.querySelector("parsererror")) throw new Error("RSS inválido");
    const items = [...xml.querySelectorAll("item")].slice(0, 5).map((item) => {
      const li = crear("li", "mb-1");
      const enlace = crear("a", "text-decoration-none", item.querySelector("title")?.textContent ?? "(sin título)");
      const destino = item.querySelector("link")?.textContent ?? "";
      enlace.href = destino.startsWith(location.origin) ? destino : "/";
      const fecha = new Date(item.querySelector("pubDate")?.textContent ?? "");
      li.append(enlace);
      if (!Number.isNaN(fecha.getTime())) li.append(crear("span", "small text-secondary", ` · ${fecha.toLocaleDateString("es-BO")}`));
      return li;
    });
    if (items.length === 0) mensaje("Sin novedades por ahora.");
    else novedades.replaceChildren(...items);
  } catch {
    mensaje("No se pudieron cargar las novedades.");
  }
}

/* ---------- Eventos ---------- */
if (form && buscador && contenido) {
  const buscar = conPausa(() => { pagina = 1; cargarGrilla(); }, 250);
  buscador.addEventListener("input", () => { pedirSugerencias(buscador.value.trim()); buscar(); });
  buscador.addEventListener("keydown", (ev) => {
    if (ev.key === "ArrowDown") { ev.preventDefault(); marcar(activa + 1); }
    else if (ev.key === "ArrowUp") { ev.preventDefault(); marcar(activa - 1); }
    else if (ev.key === "Escape") { cerrarLista(); }
    else if (ev.key === "Enter") {
      ev.preventDefault();
      if (activa >= 0) elegir(activa);
      else { cerrarLista(); pagina = 1; cargarGrilla(); }
    }
  });
  buscador.addEventListener("blur", () => setTimeout(cerrarLista, 150));
  form.addEventListener("submit", (ev) => { ev.preventDefault(); pagina = 1; cargarGrilla(); });
  form.querySelectorAll("select, input[type=checkbox]").forEach((control) =>
    control.addEventListener("change", () => { pagina = 1; cargarGrilla(); }));
  // Interceptar la paginación que dibujó el servidor (primera carga sin esperar a la API)
  contenido.addEventListener("click", (ev) => {
    const enlace = ev.target.closest(".paginacion a.page-link");
    if (!enlace) return;
    ev.preventDefault();
    pagina = Number(new URL(enlace.href).searchParams.get("pagina")) || 1;
    cargarGrilla();
  });
  cargarNovedades();
}
window.catalogoMetricas = metricas;
