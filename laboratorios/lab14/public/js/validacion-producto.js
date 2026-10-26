// public/js/validacion-producto.js — Validación EN VIVO del formulario de producto (ayuda al usuario).
// La validación del servidor (lab 8) sigue siendo la que manda: esto solo avisa antes de enviar.
import { obtener, conPausa, ErrorApi } from "./api.js";

const FORMATO = /^[A-Z]{3}-[A-Z0-9]{2,4}$/;
const campo = document.querySelector("#f-codigo");          // solo existe al REGISTRAR (no al editar)
const precio = document.querySelector("#f-precio");

function mensaje(input, texto, ok) {
  let span = document.getElementById(`${input.id}-vivo`);
  if (!span) {
    span = Object.assign(document.createElement("span"), { id: `${input.id}-vivo`, className: "validacion" });
    span.setAttribute("aria-live", "polite");
    input.insertAdjacentElement("afterend", span);
    input.setAttribute("aria-describedby", span.id);
  }
  span.textContent = texto;
  span.classList.toggle("ok", ok === true);
  span.classList.toggle("mal", ok === false);
}

let controlador = null;
const revisarCodigo = conPausa(async () => {
  const codigo = campo.value.trim().toUpperCase();
  controlador?.abort();
  if (codigo === "") { mensaje(campo, "", null); return; }
  if (!FORMATO.test(codigo)) { mensaje(campo, "Formato AAA-999 (ej. MIE-500).", false); return; }   // sin ir al servidor
  controlador = new AbortController();
  mensaje(campo, "Comprobando…", null);
  try {
    await obtener(`/api/v1/productos/${encodeURIComponent(codigo)}`, controlador.signal);
    mensaje(campo, `Ya existe un producto con el código ${codigo}.`, false);       // 200 → ocupado
  } catch (e) {
    if (e.name === "AbortError") return;
    if (e instanceof ErrorApi && e.status === 404) {
      mensaje(campo, `El código ${codigo} está disponible.`, true);       // 404 → libre
    } else {
      mensaje(campo, "No se pudo comprobar ahora; se revisará al guardar.", null);
    }
  }
}, 400);

campo?.addEventListener("input", revisarCodigo);
precio?.addEventListener("input", () => {
  const ok = /^\d{1,5}([.,]\d{1,2})?$/.test(precio.value.trim()) && Number(precio.value.replace(",", ".")) > 0;
  mensaje(precio, precio.value.trim() === "" ? "" : ok ? "Precio válido." : "Escribe un número como 45 o 45,50.", precio.value.trim() === "" ? null : ok);
});
