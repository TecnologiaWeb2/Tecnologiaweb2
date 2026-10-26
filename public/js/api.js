// public/js/api.js — Cliente fetch centralizado (Laboratorio 14).
// fetch NO rechaza la promesa con 404 o 500: hay que mirar response.ok. Los errores de la API llegan en
// application/problem+json y se convierten en ErrorApi; un fallo de red y una cancelación (AbortError) se distinguen.

export class ErrorApi extends Error {
  constructor(problema) {
    super(problema.detail || problema.title || "Error de la API");
    this.name = "ErrorApi";
    this.status = problema.status ?? 0;
    this.title = problema.title ?? "Error";
    this.errores = problema.errores ?? {};
  }
}

export async function obtener(url, signal) {
  let respuesta;
  try {
    respuesta = await fetch(url, { headers: { Accept: "application/json" }, signal, credentials: "same-origin" });
  } catch (error) {
    if (error.name === "AbortError") throw error;           // la cancelamos nosotros: no es un error
    throw new ErrorApi({ status: 0, title: "Sin conexión", detail: "No se pudo conectar con el servidor." });
  }
  if (!respuesta.ok) {
    let problema = { title: `HTTP ${respuesta.status}` };
    if ((respuesta.headers.get("Content-Type") || "").includes("json")) {
      try { problema = await respuesta.json(); } catch { /* el estado HTTP sigue siendo válido */ }
    }
    throw new ErrorApi({ status: respuesta.status, ...problema });
  }
  return respuesta.json();
}

/** Ejecuta fn solo cuando pasan «ms» milisegundos sin una nueva llamada (debounce). */
export function conPausa(fn, ms) {
  let temporizador;
  return (...args) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => fn(...args), ms);
  };
}

/** "1250.50" → "Bs 1.250,50" (los precios llegan como texto para no perder centavos). */
export function bolivianos(valor) {
  const [entero, decimales = "00"] = String(valor).split(".");
  return `Bs ${Number(entero).toLocaleString("es-BO")},${decimales.padEnd(2, "0").slice(0, 2)}`;
}
