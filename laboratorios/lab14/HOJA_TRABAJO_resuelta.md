# Laboratorio 14 — Hoja de trabajo (resuelta): interfaz asíncrona con fetch (AJAX moderno)

Nombre: Yordy Tolavi   Fecha: 18/10/2026

> **Cómo se comprobó:** `php verificar.php` → **8 de 8** comprobaciones de servidor (PHP 8.3 + MySQL 8 reales). Las respuestas HTTP de abajo son salidas reales de `curl`; el comportamiento de `api.js` se probó con Node y un `fetch` simulado (200, 404 `problem+json`, 500 sin JSON y red caída). Lo que depende de DevTools o del navegador (pestaña Red, "Slow 3G", lector de pantalla) está marcado **[razonado]**: se deduce del código, y las **10 pruebas con Chrome** (`npm run probar`) no se pudieron ejecutar aquí porque no hay Chrome; con Node + Chrome en tu PC el objetivo es **18 de 18**.

## Comandos (con Docker)
```
docker compose down -v && docker compose up -d --build
php verificar.php                                 # en Windows (con Node + Chrome): 18 de 18; si Node no responde: 8 de 8
# Web:      http://127.0.0.1:8000/catalogo   ·   admin@florchiquitania.bo / Panal-2026!
# Pruebas con navegador (en tu PC, no en el contenedor):  npm install && npm run probar
```
`Dockerfile`, `.dockerignore` y `docker-compose.yml` siguen el modelo del lab 13 (PHP 8.4 con `pdo_mysql` y `soap` + MySQL 8.4).

## Los 7 TODO resueltos
| TODO | Archivo | Cambio |
|---|---|---|
| 1 | `public/js/catalogo.js` `dibujarFilas` | `document.createElement("tr"/"td")` + `td.textContent = valor` y `tabla.replaceChildren(...filas)` (se acabó `innerHTML`) |
| 2 | `public/js/catalogo.js` `cargarGrilla` | `controladorGrilla?.abort(); controladorGrilla = new AbortController();` |
| 3 | `public/js/api.js` `obtener` | `if (!respuesta.ok)` → lee el `problem+json` (si el tipo incluye `json`) y `throw new ErrorApi({ status: respuesta.status, ...problema })` |
| 4 | `public/js/catalogo.js` `marcar` | `activa = (i + n) % n`; `aria-selected` solo en la activa; `aria-activedescendant` = id de la opción activa |
| 5 | `public/js/catalogo.js` `cargarNovedades` | `new DOMParser().parseFromString(texto, "application/xml")`; 5 primeros `<item>` → `<li>` con `título · fecha.toLocaleDateString('es-BO')` |
| 6 | `public/js/validacion-producto.js` | `ErrorApi` con `status === 404` → "El código … está disponible." (`ok = true`); cualquier otro error → "No se pudo comprobar ahora; se revisará al guardar." (`ok = null`) |
| 7 | `src/Api/NucleoApi.php` | la ruta `/api/v1/productos/sugerencias` se registra **antes** de `/api/v1/productos/{codigo}` |

## Parte A — La página cascarón
**1.** En Ctrl+U de `/catalogo` **no hay productos**: el `<tbody>` está vacío (solo el cascarón: buscador, tabla con `aria-busy="true"`, paginación y la lista de novedades con "Cargando…"). Los productos los **dibuja JavaScript** (`catalogo.js`) con los datos que trae `fetch`. [✔ comprobado en el HTML servido]

**2.** Al cargar, la página pide **[razonado]**:
| URL | Código | Tipo |
|---|---|---|
| `/catalogo` | 200 | document |
| `/css/estilo.css` | 200 | stylesheet |
| `/js/catalogo.js` (módulo) y `/js/api.js` (importado por el módulo) | 200 | script (`text/javascript`) |
| `/api/v1/productos?pagina=1&por_pagina=10&orden=nombre` | 200 | fetch · `application/json` |
| `/noticias.rss` | 200 | fetch · XML (RSS) |
Al escribir en el buscador se suma `/api/v1/productos/sugerencias?q=…`.

**3.** *Ventajas:* no se recarga la página entera (se conserva el foco, el buscador y el orden), solo viajan los datos (JSON pequeño, no todo el HTML) y se siente más rápida con conexión móvil lenta. *Desventajas:* los buscadores que no ejecutan JavaScript ven una tabla vacía, quien lo tiene desactivado (o falla el script) no ve nada, no hay URL compartible para "página 2 con filtro" y hay que cuidar la accesibilidad (`aria-live`, `aria-busy`) a mano. Por eso `/productos` (renderizado en el servidor) sigue existiendo.

## Parte B — Mostrar datos sin abrir agujeros (TODO 1)
**1.** Antes del TODO 1, `<b>Miel</b> de prueba` se veía como **"Miel" en negrita** + " de prueba": el navegador interpretaba el texto de la API como HTML. Después se ve literal: `<b>Miel</b> de prueba`. [razonado; el verificador comprueba que el código no usa `innerHTML`]

**2.** `<img src=x onerror=alert(1)>` se insertaría como elemento: la imagen falla y se ejecuta `alert(1)` → **XSS** (en un caso real, robar datos o actuar como el usuario). Relación: la **clase 1** (escapar la salida: en las plantillas PHP todo pasa por `htmlspecialchars`, aquí el equivalente en JS es `textContent`) y la **clase 7** (XSS y defensa en profundidad). Aquí la CSP `default-src 'self'` bloquearía el `onerror` en línea, pero **no hay que depender de ella**: la defensa principal es no interpretar datos como HTML.

**3.** Hecho: cada celda se crea con `createElement("td")` y se llena con `textContent`; las filas con `stock === 0` reciben la clase `agotado`.

## Parte C — Peticiones que compiten (TODO 2 y 3)
**1.** Antes del TODO 2 cada búsqueda lanzaba su petición y **la última en llegar, no la última escrita, pintaba la grilla**. Con "Slow 3G" la respuesta de `mi` (más lenta, más resultados) puede llegar **después** de la de `cera` y dejar en pantalla productos de `mi` mientras el cuadro dice "cera". **No siempre** es la de "cera": depende del orden de llegada (condición de carrera). [razonado]

**2.** Con el TODO 2, `controladorGrilla?.abort()` cancela la anterior: en DevTools las viejas aparecen en rojo con estado **`(canceled)`** y el `catch` las ignora (`AbortError` → solo cuenta en `metricas.canceladas`). Solo la última petición pinta la grilla. [razonado]

**3.** `fetch` **solo rechaza** la promesa si falla la red; un 404 o 500 es una respuesta "exitosa" para `fetch`. Con MySQL detenido la API responde **500** `application/problem+json`. *Antes del TODO 3:* `respuesta.json()` devolvía el problema como si fueran datos, `datos.datos.map` fallaba con un `TypeError` y el usuario veía "Ocurrió un error inesperado." (o la grilla vacía/vieja). *Después:* `obtener` lanza `ErrorApi` con `status`, `titulo` y `detail` y se muestra el aviso `role="alert"` con el título y el detalle de la API. Probado con un `fetch` simulado: `404 → ErrorApi status=404 "No existe MIE-9"`, `500 sin JSON → ErrorApi status=500`, red caída → `ErrorApi status=0 "Sin conexión"`. [✔ comprobado con Node]

## Parte D — Autocompletar accesible (TODO 4 y 7)
**1.** `GET /api/v1/productos/sugerencias?q=jab`: **antes → `404 Not Found`** (`application/problem+json`), porque el router prueba las rutas **en orden** y `/productos/{codigo}` ya aceptaba la palabra `sugerencias` como si fuera un código (que no existe → 404). **Después → `200 OK`** con `Cache-Control: public, max-age=60` y `JAB-MIE`, `JAB-PRO`. El orden importa: gana la **primera** ruta que coincide, así que las rutas específicas van antes que las que tienen parámetros. [✔ comprobado con `curl` en el lab original y en el resuelto]

**2.** Al escribir `jab` rápido se hace **una sola** petición de sugerencias (`q=jab`): `conPausa(…, 250)` reinicia el temporizador con cada tecla y solo llama a la función cuando el usuario **deja de escribir 250 ms**; además, con menos de 2 letras no se pide nada y el `AbortController` cancela la anterior. Ahorra peticiones, carga del servidor y parpadeo. [razonado]

**3.** Navegación con ↑/↓/Enter (circular con `% n`). `aria-expanded` avisa si la lista está abierta; `aria-activedescendant` dice al lector de pantalla **cuál opción está activa** mientras el foco se queda en el campo de texto; `aria-selected="true"` marca la opción resaltada. Así NVDA/Narrador anuncia "Jabón de miel, 1 de 2" sin mover el foco. [razonado]

## Parte E — Novedades RSS y validación en vivo (TODO 5 y 6)
**1.** `fetch("/noticias.rss")` + `DOMParser` convierte el texto XML en un documento que se recorre con `querySelectorAll("item")`. **No hace falta CORS** porque el RSS es del **mismo origen** (mismo esquema, host y puerto que la página); CORS solo controla lecturas **entre orígenes distintos**. Si hay error de XML (`parsererror`) se muestra "No se pudieron cargar las novedades."

**2.** En `/productos/nuevo` (como operador): `abc` → **ninguna petición** (no cumple `AAA-999`; mensaje "Formato AAA-999"). `MIE-500` → `GET /api/v1/productos/MIE-500` → **200** → "Ya existe un producto con el código MIE-500." `POL-999` → `GET /api/v1/productos/POL-999` → **404** → "El código POL-999 está disponible." Un **404 significa "disponible"** porque dice "ese recurso no existe": si el producto no existe, el código está libre. [`MIE-500` = 200 y la ruta no se confunde con otra: ✔ comprobado en el verificador]

**3.** **No.** La validación en el navegador es solo una **ayuda**: se salta con DevTools, `curl` o desactivando JavaScript, y puede quedar desactualizada (alguien registra `POL-999` entre la comprobación y el guardado). El servidor vuelve a validar (`ValidadorProducto`) y la base de datos tiene `UNIQUE` en `codigo`: son las barreras reales.

## Parte F — Verificación
`php verificar.php` → **8 de 8** de servidor [✔ comprobado]. Las pruebas con navegador (Partes C–E: peticiones canceladas, ARIA, validación en vivo) agregan 10 más → **18 de 18** al ejecutar `npm install && npm run probar` con Chrome. Las pruebas manuales de las Partes C–E se hacen en DevTools (Red y Consola).

## Imágenes de producto (igual que el lab 13)
Se portó el soporte de fotos del lab 13 y se integró con el catálogo asíncrono:
- **Base de datos:** columna `productos.imagen VARCHAR(40) NULL` (`sql/01-esquema.sql`); guarda solo el nombre del archivo.
- **Subida segura** (`src/Archivos/GuardaImagen.php`): el tipo se decide leyendo el **contenido** (`finfo` + `getimagesize`), no la extensión ni lo que dice el navegador; solo JPG, PNG o WebP, máximo 2 MB y 6000 × 6000 px; el nombre final lo inventa el servidor (16 hex aleatorios), así no se puede subir `foto.php` ni pisar archivos. Al reemplazar o eliminar un producto se borra la foto anterior.
- **Formulario:** `enctype="multipart/form-data"`, campo de archivo, casilla "Quitar la imagen actual" y **vista previa** con `blob:` antes de enviar (`public/js/vista-previa.js`; por eso la CSP ahora dice `img-src 'self' blob:`).
- **Listas:** miniatura en `/productos` y `/socios/{id}`; sin foto se muestra `public/img/sin-imagen.svg`.
- **API y catálogo asíncrono:** `GET /api/v1/productos` incluye `"imagen": "/uploads/productos/….png"` (o `/img/sin-imagen.svg`) y está documentado en `openapi.yaml`. `catalogo.js` crea el `<img>` con `createElement` (sin `innerHTML`), con `alt`, `loading="lazy"` y solo acepta rutas internas (empiezan con `/`, no con `//`). La miniatura va dentro de la celda "Producto" para que la primera columna siga siendo el código.
- **Comprobado** con PHP + MySQL reales y `curl`: crear con foto (`303`, archivo guardado y servido como `image/png`), reemplazar (nombre nuevo, la foto vieja se borra), un `.png` falso con código PHP dentro → `422` "El archivo debe ser una imagen JPG, PNG o WebP.", quitar (`imagen = NULL`) y eliminar (la carpeta queda solo con `.gitkeep`). `php verificar.php` sigue en **8 de 8**.

## Diseño (igual que el lab 13)
Se reutiliza el tema "miel" del lab 13: `estilo.css` completo (cabecera con menú, tarjetas, botones `btn-*`, `tabla-contenedor`, insignias) más las reglas propias del lab 14 (autocompletar `#sugerencias`, `#grilla[aria-busy]`, indicadores ▲/▼ de orden, `.validacion.ok/.mal`). Se actualizaron `base`, `entrar`, `error`, `productos`, `formulario`, `socio`, `socios`, `venta`, `importar`, `reportes` y `catalogo`, y se agregó el enlace **Catálogo en línea** al menú. Las imágenes de producto también se portaron (ver la sección anterior).

## Reflexión
**¿Qué mejora más la experiencia con conexión móvil lenta?** (1) El **autocompletar con pausa y cancelación**: pocas peticiones, pequeñas, y nunca una respuesta vieja pisando a la nueva. (2) La **grilla asíncrona**: solo viaja JSON, no toda la página, y el usuario ve `aria-busy`/"Cargando…" en lugar de una pantalla en blanco. (3) Los errores claros (TODO 3) en vez de un fallo silencioso.

**Riesgos que agregó JavaScript y cómo se controlaron:**
| Riesgo | Control |
|---|---|
| XSS al pintar datos de la API | `textContent`/`createElement`, nunca `innerHTML` (TODO 1) + CSP sin scripts en línea |
| Respuestas viejas que pisan a las nuevas | `AbortController` (TODO 2) y `conPausa` |
| Errores HTTP que `fetch` no lanza | `if (!respuesta.ok)` → `ErrorApi` con el `problem+json` (TODO 3) |
| Confiar en la validación del cliente | El servidor y el `UNIQUE` de MySQL siguen validando |
| Usuarios sin JavaScript o con lector de pantalla | `/productos` en el servidor sigue funcionando; `role="combobox"`, `aria-live`, `aria-busy` (TODO 4) |
