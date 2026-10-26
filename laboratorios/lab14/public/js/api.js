// public/js/api.js — Cliente mínimo de NUESTRA API (lab 13). Un solo lugar para fetch, JSON y errores problem+json.

/** Error de la API con los datos del problem+json (status, title, detail, errores). */
export class ErrorApi extends Error {
  constructor(problema) {
    super(problema.detail || problema.title || "Error de la API");
    this.status = problema.status ?? 0;
    this.titulo = problema.title ?? "Error";
    this.errores = problema.errores ?? {};
  }
}

/**
 * GET a la API. Devuelve { estado, datos }.
 * @param {string} ruta    por ejemplo "/api/v1/productos?pagina=2"
 * @param {AbortSignal} [signal]  para cancelar peticiones viejas (AbortController)
 */
export async function obtener(ruta, signal) {
  let respuesta;
  try {
    respuesta = await fetch(ruta, { headers: { Accept: "application/json" }, signal });
  } catch (e) {
    if (e.name === "AbortError") throw e;                      // cancelada a propósito: no es un error para el usuario
    throw new ErrorApi({ status: 0, title: "Sin conexión", detail: "No se pudo conectar con el servidor. Revisa tu conexión." });
  }
  const tipo = respuesta.headers.get("Content-Type") ?? "";
  // fetch NO lanza error con 404 o 500: hay que revisar respuesta.ok y convertir el problem+json en ErrorApi.
  if (!respuesta.ok) {
    let problema = {};
    if (tipo.includes("json")) {
      try { problema = await respuesta.json(); } catch { problema = {}; }
    }
    throw new ErrorApi({ status: respuesta.status, ...problema });
  }
  return { estado: respuesta.status, datos: await respuesta.json() };
}

/** Ejecuta fn solo cuando el usuario deja de escribir durante `ms` milisegundos. */
export function conPausa(fn, ms) {
  let temporizador;
  return (...args) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => fn(...args), ms);
  };
}

/** Formato boliviano del precio: "45.00" → "Bs 45,00" (sin pasar por float). */
export function bolivianos(precioTexto) {
  const [entero, decimales = "00"] = String(precioTexto).split(".");
  return "Bs " + Number(entero).toLocaleString("es-BO") + "," + decimales.padEnd(2, "0");
}
