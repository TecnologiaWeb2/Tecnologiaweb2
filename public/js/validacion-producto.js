// public/js/validacion-producto.js — Laboratorio 14: validación EN VIVO del formulario de prenda (también dentro del modal).
//   «abc»      → aviso local, sin petición (formato AAA-999)
//   «POL-001»  → GET /api/v1/productos/POL-001 → 200 = el código ya está ocupado
//   «POL-999»  → 404 = disponible
// Es solo una ayuda: la validación del servidor (src/validacion.php) sigue siendo obligatoria.
import { conPausa, ErrorApi, obtener } from "./api.js";

const FORMATO = /^[A-Z]{3}-[A-Z0-9]{2,5}$/;
let controlador = null;

function mensaje(input, texto, ok) {
  let span = document.getElementById(`${input.id}-vivo`);
  if (!span) {
    span = Object.assign(document.createElement("div"), { id: `${input.id}-vivo`, className: "form-text" });
    span.setAttribute("aria-live", "polite");
    input.insertAdjacentElement("afterend", span);
    input.setAttribute("aria-describedby", [input.getAttribute("aria-describedby"), span.id].filter(Boolean).join(" "));
  }
  span.textContent = texto;
  span.classList.toggle("text-success", ok === true);
  span.classList.toggle("text-danger", ok === false);
}

const revisarCodigo = conPausa(async (campo) => {
  const codigo = campo.value.trim().toUpperCase();
  controlador?.abort();
  if (codigo === "") { mensaje(campo, "", null); return; }
  if (!FORMATO.test(codigo)) { mensaje(campo, "Formato AAA-999 (ej. POL-001).", false); return; }   // sin ir al servidor
  controlador = new AbortController();
  mensaje(campo, "Comprobando…", null);
  try {
    await obtener(`/api/v1/productos/${encodeURIComponent(codigo)}`, controlador.signal);
    mensaje(campo, `Ya existe una prenda con el código ${codigo}.`, false);          // 200 → ocupado
  } catch (e) {
    if (e.name === "AbortError") return;
    if (e instanceof ErrorApi && e.status === 404) mensaje(campo, `El código ${codigo} está disponible.`, true);   // 404 → libre
    else mensaje(campo, "No se pudo comprobar ahora; se revisará al guardar.", null);
  }
}, 400);

function revisarPrecio(campo) {
  const valor = campo.value.trim();
  const ok = /^\d{1,6}([.,]\d{1,2})?$/.test(valor) && Number(valor.replace(",", ".")) > 0;
  mensaje(campo, valor === "" ? "" : ok ? "Precio válido." : "Escribe un número como 95 o 95,50.", valor === "" ? null : ok);
}

// Delegación de eventos: funciona en la página normal y en el formulario que llega dentro del modal
document.addEventListener("input", (ev) => {
  const campo = ev.target;
  if (!(campo instanceof HTMLInputElement) || !campo.closest("form.ficha")) return;
  if (campo.id === "f-codigo" && !campo.readOnly) revisarCodigo(campo);
  if (campo.id === "f-precio") revisarPrecio(campo);
});
