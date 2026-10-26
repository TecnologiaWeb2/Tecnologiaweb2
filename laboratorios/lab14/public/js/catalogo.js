// public/js/catalogo.js — Catálogo asíncrono: grilla con paginación y orden, autocompletar accesible y novedades (RSS).
// Regla de oro: los datos de la API se insertan SIEMPRE con textContent (nunca innerHTML): así no hay XSS.
import { obtener, conPausa, bolivianos, ErrorApi } from "./api.js";

const estado = { pagina: 1, orden: "nombre", q: "" };
let controladorGrilla = null;            // AbortController de la petición en curso
const metricas = { peticionesGrilla: 0, canceladas: 0 };   // para las pruebas con navegador

const $ = (selector) => document.querySelector(selector);
const tabla = $("#grilla tbody");
const resumen = $("#resumen");
const aviso = $("#aviso");

/* ---------- 1) Grilla asíncrona ---------- */
async function cargarGrilla() {
  // Cancela la petición anterior: si el usuario busca rápido, una respuesta VIEJA no debe pisar a la nueva.
  controladorGrilla?.abort();
  controladorGrilla = new AbortController();
  const params = new URLSearchParams({ pagina: estado.pagina, por_pagina: 10, orden: estado.orden });
  if (estado.q) params.set("q", estado.q);
  $("#grilla").setAttribute("aria-busy", "true");
  aviso.hidden = true;
  metricas.peticionesGrilla++;
  try {
    const { datos } = await obtener(`/api/v1/productos?${params}`, controladorGrilla?.signal);
    dibujarFilas(datos.datos);
    const m = datos.meta;
    const desde = m.total === 0 ? 0 : (m.pagina - 1) * m.por_pagina + 1;
    resumen.textContent = m.total === 0 ? "Sin resultados"
      : `Mostrando ${desde}–${desde + datos.datos.length - 1} de ${m.total} · página ${m.pagina} de ${m.paginas}`;
    $("#anterior").disabled = !datos.enlaces.anterior;
    $("#siguiente").disabled = !datos.enlaces.siguiente;
  } catch (e) {
    if (e.name === "AbortError") { metricas.canceladas++; return; }
    mostrarError(e);
  } finally {
    $("#grilla").setAttribute("aria-busy", "false");
  }
}

function celda(texto, clase = "") {
  const td = document.createElement("td");
  if (clase) td.className = clase;
  td.textContent = texto;                       // siempre textContent: nunca HTML
  return td;
}

/** Celda "Producto": miniatura + nombre. La URL de la imagen la fija el servidor (solo rutas internas). */
function celdaProducto(p) {
  const td = document.createElement("td");
  const caja = document.createElement("div");
  caja.className = "celda-producto";
  const img = document.createElement("img");
  img.className = "miniatura";
  img.width = 56; img.height = 56; img.loading = "lazy";
  img.alt = `Foto de ${p.nombre}`;
  img.src = typeof p.imagen === "string" && p.imagen.startsWith("/") && !p.imagen.startsWith("//") ? p.imagen : "/img/sin-imagen.svg";
  const nombre = document.createElement("strong");
  nombre.textContent = p.nombre;
  caja.append(img, nombre);
  td.append(caja);
  return td;
}

function dibujarFilas(productos) {
  // Los datos de la API se insertan SIEMPRE con textContent: "<b>Miel</b>" se muestra como texto, nunca como HTML.
  const filas = productos.map((p) => {
    const tr = document.createElement("tr");
    if (p.stock === 0) tr.className = "agotado";
    tr.append(celda(p.codigo), celdaProducto(p), celda(p.categoria), celda(bolivianos(p.precio), "num"),
      celda(String(p.stock), "num"), celda(p.socio.nombre));
    return tr;
  });
  tabla.replaceChildren(...filas);
}

function mostrarError(e) {
  aviso.textContent = e instanceof ErrorApi ? `${e.titulo}: ${e.message}` : "Ocurrió un error inesperado.";
  aviso.hidden = false;
}

$("#anterior").addEventListener("click", () => { estado.pagina--; cargarGrilla(); });
$("#siguiente").addEventListener("click", () => { estado.pagina++; cargarGrilla(); });
document.querySelectorAll("#grilla th button[data-orden]").forEach((boton) => {
  boton.addEventListener("click", () => {
    const campo = boton.dataset.orden;                     // "precio": alterna menor ↔ mayor
    estado.orden = campo === "precio" && estado.orden === "precio" ? "precio_desc" : campo;
    estado.pagina = 1;
    document.querySelectorAll("#grilla th").forEach((th) => th.removeAttribute("aria-sort"));
    boton.closest("th").setAttribute("aria-sort", estado.orden === "precio_desc" ? "descending" : "ascending");
    cargarGrilla();
  });
});

/* ---------- 2) Autocompletar (patrón combobox de WAI-ARIA) ---------- */
const buscador = $("#buscar");
const lista = $("#sugerencias");
let controladorSugerencias = null;
let activa = -1;

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
    const li = document.createElement("li");
    li.id = `sugerencia-${i}`;
    li.setAttribute("role", "option");
    li.dataset.nombre = s.nombre;
    li.textContent = `${s.nombre} — ${bolivianos(s.precio)}`;
    li.addEventListener("mousedown", (ev) => { ev.preventDefault(); elegir(i); });
    return li;
  }));
  lista.hidden = items.length === 0;
  buscador.setAttribute("aria-expanded", String(items.length > 0));
  $("#estado-sugerencias").textContent = items.length ? `${items.length} sugerencias. Usa las flechas para elegir.` : "Sin sugerencias.";
}

function cerrarLista() {
  activa = -1;                                   // sin esto, el siguiente Enter "elige" una opción que ya no existe
  lista.hidden = true;
  lista.replaceChildren();
  buscador.setAttribute("aria-expanded", "false");
  buscador.removeAttribute("aria-activedescendant");
}

function marcar(i) {
  const opciones = [...lista.children];
  if (opciones.length === 0) return;
  activa = (i + opciones.length) % opciones.length;          // circular: tras la última vuelve a la primera
  opciones.forEach((li, n) => li.setAttribute("aria-selected", String(n === activa)));
  buscador.setAttribute("aria-activedescendant", opciones[activa].id);   // el lector de pantalla anuncia la opción activa
}

function elegir(i) {
  const opcion = lista.children[i];
  if (!opcion) return;
  buscador.value = opcion.dataset.nombre;
  cerrarLista();
  aplicarBusqueda();
}

function aplicarBusqueda() {
  estado.q = buscador.value.trim();
  estado.pagina = 1;
  cargarGrilla();
}

buscador.addEventListener("input", () => pedirSugerencias(buscador.value.trim()));
buscador.addEventListener("keydown", (ev) => {
  if (ev.key === "ArrowDown") { ev.preventDefault(); marcar(activa + 1); }
  else if (ev.key === "ArrowUp") { ev.preventDefault(); marcar(activa - 1); }
  else if (ev.key === "Escape") { cerrarLista(); }
  else if (ev.key === "Enter") {
    ev.preventDefault();
    if (activa >= 0) elegir(activa); else { cerrarLista(); aplicarBusqueda(); }
  }
});
buscador.addEventListener("blur", () => setTimeout(cerrarLista, 100));

/* ---------- 3) Novedades: el RSS del lab 12 leído con fetch + DOMParser ---------- */
async function cargarNovedades() {
  const novedades = $("#novedades");
  try {
    const respuesta = await fetch("/noticias.rss");
    if (!respuesta.ok) throw new Error(String(respuesta.status));
    const texto = await respuesta.text();
    const xml = new DOMParser().parseFromString(texto, "application/xml");
    if (xml.querySelector("parsererror")) throw new Error("RSS no válido");
    const items = [...xml.querySelectorAll("item")].slice(0, 5).map((item) => {
      const titulo = item.querySelector("title")?.textContent ?? "(sin título)";
      const fecha = new Date(item.querySelector("pubDate")?.textContent ?? "");
      const li = document.createElement("li");
      li.textContent = Number.isNaN(fecha.getTime()) ? titulo : `${titulo} · ${fecha.toLocaleDateString("es-BO")}`;
      return li;
    });
    novedades.replaceChildren(...(items.length ? items : [Object.assign(document.createElement("li"), { textContent: "Sin novedades por ahora." })]));
  } catch {
    novedades.replaceChildren(Object.assign(document.createElement("li"), { textContent: "No se pudieron cargar las novedades." }));
  }
}

cargarGrilla();
cargarNovedades();
window.catalogoMetricas = metricas;      // solo para que las pruebas automáticas lean los contadores
