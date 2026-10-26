// probar_navegador.mjs — Pruebas en un navegador REAL (Chrome) del catálogo asíncrono y la validación en vivo.
// Lo ejecuta verificar.php:  node probar_navegador.mjs http://127.0.0.1:PUERTO
// Requisitos: Node 18+, Google Chrome y  npm install  (instala playwright-core, que usa el Chrome ya instalado).
import { pathToFileURL } from "node:url";

const base = process.argv[2] ?? "http://127.0.0.1:8000";
const API_LISTA = /\/api\/v1\/productos\?/;   // la lista paginada (no las sugerencias)
const { chromium } = await import(process.env.PLAYWRIGHT_RUTA ? pathToFileURL(process.env.PLAYWRIGHT_RUTA).href : "playwright-core");
const nav = await chromium.launch(process.env.CHROME_RUTA ? { executablePath: process.env.CHROME_RUTA } : { channel: "chrome" });
const resultados = [];
const revisar = async (descripcion, prueba) => {
  let ok = false;
  try { ok = Boolean(await prueba()); } catch (e) { descripcion += `  (${e.message.split("\n")[0]})`; }
  resultados.push({ descripcion, ok });
};

const p = await nav.newPage();
const erroresJs = [];
p.on("pageerror", (e) => erroresJs.push(e.message));
const filas = () => p.locator("#grilla tbody tr");
const primerCodigo = () => p.locator("#grilla tbody tr:first-child td:first-child").textContent();

await p.goto(`${base}/catalogo`);
await p.waitForFunction(() => document.querySelector("#grilla").getAttribute("aria-busy") === "false" && document.querySelectorAll("#grilla tbody tr").length > 0);

await revisar("Navegador: la grilla carga 10 filas desde la API y muestra el resumen", async () =>
  (await filas().count()) === 10 && (await p.locator("#resumen").textContent()).startsWith("Mostrando 1–10 de 37"));

await revisar("Navegador: el nombre con HTML se muestra como TEXTO (textContent), sin crear etiquetas", async () => {
  const texto = await p.locator("#grilla tbody").textContent();
  return texto.includes("<b>Miel</b> de prueba") && (await p.locator("#grilla tbody b").count()) === 0;
});

await revisar("Navegador: 'Siguiente' carga la página 2 sin recargar la página", async () => {
  const antes = await primerCodigo();
  await p.click("#siguiente");
  await p.waitForFunction(() => document.querySelector("#resumen").textContent.startsWith("Mostrando 11–20"));
  return (await primerCodigo()) !== antes && (await p.locator("#anterior").isEnabled());
});

await revisar("Navegador: ordenar por precio (CAR-MIE primero) y otra vez (CER-BLO primero), con aria-sort", async () => {
  await p.click("button[data-orden=precio]");
  await p.waitForFunction(() => document.querySelector("#grilla tbody tr td").textContent === "CAR-MIE");
  await p.click("button[data-orden=precio]");
  await p.waitForFunction(() => document.querySelector("#grilla tbody tr td").textContent === "CER-BLO");
  return (await p.locator("th:has(button[data-orden=precio])").getAttribute("aria-sort")) === "descending";
});

await revisar("Navegador: autocompletar muestra sugerencias (combobox) y se elige con flechas + Enter", async () => {
  await p.fill("#buscar", "");
  await p.type("#buscar", "jab", { delay: 30 });
  await p.waitForSelector("#sugerencias li");
  const expandido = await p.getAttribute("#buscar", "aria-expanded");
  const opciones = await p.locator("#sugerencias li[role=option]").count();
  await p.keyboard.press("ArrowDown");
  const activa = await p.getAttribute("#buscar", "aria-activedescendant");
  await p.keyboard.press("Enter");
  await p.waitForFunction(() => document.querySelector("#resumen").textContent.includes("de 1 ·"));
  return expandido === "true" && opciones === 2 && activa === "sugerencia-0" && (await p.inputValue("#buscar")).startsWith("Jabón");
});

await revisar("Navegador: escribir rápido cancela peticiones viejas (AbortController) y gana la última", async () => {
  await p.fill("#buscar", "");
  await p.keyboard.press("Enter");                                           // vuelve a todos los productos
  await p.waitForFunction(() => document.querySelector("#resumen").textContent.includes("de 37"));
  // Se demora la API para provocar la "carrera": tres búsquedas seguidas
  await p.route(API_LISTA, async (ruta) => { await new Promise((r) => setTimeout(r, 400)); await ruta.continue(); });
  for (const t of ["mi", "mie", "cera"]) {
    await p.fill("#buscar", t);
    await p.keyboard.press("Enter");
  }
  await p.waitForFunction(() => document.querySelector("#grilla").getAttribute("aria-busy") === "false"
    && document.querySelector("#resumen").textContent.includes("de 10 "), null, { timeout: 5000 });
  await p.unroute(API_LISTA);
  const m = await p.evaluate(() => window.catalogoMetricas);
  return m.canceladas >= 2;
});

await revisar("Navegador: un error de la API (problem+json 500) se muestra en un aviso role=alert", async () => {
  await p.route(API_LISTA, (ruta) => ruta.fulfill({ status: 500, contentType: "application/problem+json",
    body: JSON.stringify({ type: "x", title: "Error interno", status: 500, detail: "Referencia: prueba." }) }));
  await p.fill("#buscar", "polen");
  await p.keyboard.press("Enter");
  await p.waitForSelector("#aviso:not([hidden])");
  await p.unroute(API_LISTA);
  return (await p.textContent("#aviso")) === "Error interno: Referencia: prueba.";
});

await revisar("Navegador: el panel lee el RSS con fetch + DOMParser (5 novedades)", async () =>
  (await p.locator("#novedades li").count()) === 5 && (await p.locator("#novedades li").first().textContent()).includes("×"));

// Validación en vivo (requiere sesión de operador)
const f = await nav.newPage();
f.on("pageerror", (e) => erroresJs.push(e.message));
await f.goto(`${base}/entrar`);
await f.fill("#correo", "operador@florchiquitania.bo");
await f.fill("#clave", "Colmena-2026!");
await Promise.all([f.waitForURL("**/productos**"), f.click("button[type=submit]")]);
await f.goto(`${base}/productos/nuevo`);
const vivo = (id) => f.textContent(`#${id}-vivo`);

await revisar("Navegador: validación en vivo del código — formato, ocupado (200) y disponible (404)", async () => {
  await f.type("#f-codigo", "abc", { delay: 20 });
  await f.waitForFunction(() => document.querySelector("#f-codigo-vivo")?.textContent.startsWith("Formato"));
  await f.fill("#f-codigo", "");
  await f.type("#f-codigo", "MIE-500", { delay: 20 });
  await f.waitForFunction(() => document.querySelector("#f-codigo-vivo")?.textContent.startsWith("Ya existe"));
  const ocupado = await vivo("f-codigo");
  await f.fill("#f-codigo", "");
  await f.type("#f-codigo", "POL-999", { delay: 20 });
  await f.waitForFunction(() => document.querySelector("#f-codigo-vivo")?.textContent.includes("disponible"));
  return ocupado === "Ya existe un producto con el código MIE-500." && (await f.getAttribute("#f-codigo", "aria-describedby")) === "f-codigo-vivo";
});

await revisar("Navegador: sin errores de JavaScript en las páginas", async () => erroresJs.length === 0);

await nav.close();
console.log(JSON.stringify(resultados));
