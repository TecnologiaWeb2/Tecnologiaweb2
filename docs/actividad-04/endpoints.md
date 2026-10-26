# Documentación de los endpoints

Sistema **Manos del Oriente** · Actividad 4 (laboratorios 12, 13 y 14) y módulos del trabajo final.
Contrato formal: [`docs/openapi.yaml`](../openapi.yaml) (OpenAPI 3.1, también en `GET /api/v1/openapi.yaml`).
Todas las respuestas de ejemplo son **salidas reales** guardadas en [`evidencias/`](evidencias/) por
`php herramientas/evidencias_api.php` (los tokens aparecen ocultos como `$TOKEN` / `mdo_****`).

**Convenciones**

- Base: `http://127.0.0.1:8000` (local) · `https://<dominio>.infinityfreeapp.com` (publicado).
- Lectura pública del catálogo. Escribir exige `Authorization: Bearer mdo_…` (emitido en `/tokens.php`; en la base solo
  queda su hash SHA-256). La API no usa cookies, por eso no necesita token CSRF.
- Precios como **texto** con 2 decimales (`"95.00"`), fechas en **ISO 8601** (`2026-10-26T14:56:00-04:00`).
- Errores en `application/problem+json` (RFC 9457) con `type`, `title`, `status`, `detail`, `instance` y, en 422, `errores` por campo.

| Código | Cuándo |
|---|---|
| 400 | Cuerpo que no es un objeto JSON válido |
| 401 | Falta el token, está mal formado, venció o fue revocado (con `WWW-Authenticate: Bearer`) |
| 403 | El rol del token no permite la operación (p. ej. una socia que intenta crear prendas) |
| 404 | El recurso no existe |
| 405 | Método no soportado en esa ruta (con cabecera `Allow`) |
| 409 | Código o número repetido, o prenda con ventas/pedidos que no se puede borrar |
| 415 | Falta `Content-Type: application/json` |
| 422 | Datos o parámetros inválidos: la API **no corrige en silencio** |

---

## Laboratorio 12 · Intercambio de datos

### Catálogo JSON
| Campo | Valor |
|---|---|
| Método y URL | `GET /datos/catalogo.json` |
| Autenticación | Pública (`Access-Control-Allow-Origin: *`) |
| Petición | `Accept: application/json` |
| Respuesta 200 | `{"asociacion": "…", "generado": "2026-10-26T14:56:01-04:00", "total": 14, "productos": [{"codigo": "ABR-200", "precio": "260.00", "moneda": "BOB", "stock": 9, "tallas": ["M","L","XL","XXL"], "confecciono": "Juana Pesoa Rojas", …}]}` |
| Errores | 405 si no es GET/HEAD |
| Evidencia | [E02_catalogo_json.png](evidencias/E02_catalogo_json.png) |

No expone datos personales de las socias (CI ni teléfono): solo nombre y comunidad.

### Catálogo XML
| Campo | Valor |
|---|---|
| Método y URL | `GET /datos/catalogo.xml` |
| Autenticación | Pública |
| Respuesta 200 | `<catalogo asociacion="…" generado="…"><producto codigo="ACC-300"><nombre>Bolso &amp; cartera &lt;tejida&gt; de saó</nombre><precio moneda="BOB">75.00</precio>…` |
| Errores | 405 |
| Evidencia | [E03_xml_escape.png](evidencias/E03_xml_escape.png) — generado con **XMLWriter**: `&` y `<` se escapan solos |

### Canal RSS
| Campo | Valor |
|---|---|
| Método y URL | `GET /noticias.rss` |
| Autenticación | Pública |
| Respuesta 200 | RSS 2.0: `<channel><title>Manos del Oriente · Novedades</title>…<item><title>Nueva prenda: …</title><guid isPermaLink="false">manos-del-oriente:ACC-302</guid><pubDate>Mon, 26 Oct 2026 14:56:00 -0400</pubDate>` |
| Uso | La vitrina lo lee con `fetch` + `DOMParser` (lab 14) |
| Evidencia | [E05_rss.png](evidencias/E05_rss.png) · [E18_rss_en_interfaz.png](evidencias/E18_rss_en_interfaz.png) |

### Prenda por REST y por SOAP
| Campo | REST | SOAP |
|---|---|---|
| Método y URL | `GET /datos/productos/POL-001` | `POST /soap.php` · operación `precio("POL-001")` · uri `urn:manos-del-oriente:precios` |
| Autenticación | Pública | Pública |
| Respuesta | JSON de 253 bytes | Sobre SOAP 1.1 (XML) de 1.745 bytes |
| Errores | 404 problem+json | `SoapFault` «No existe la prenda NOP-000.» |
| Medición propia (26/10/2026) | 38 ms · 253 B | 51 ms · petición 507 B · respuesta 1.745 B |
| Evidencia | [E06_soap_rest_curl.png](evidencias/E06_soap_rest_curl.png) (`php herramientas/soap_cliente.php`) | |

La misma prenda pesa **casi 7 veces más** por SOAP: el sobre XML repite el nombre y el tipo de cada campo.
REST con JSON es más liviano y se prueba con un navegador o `curl`; SOAP aporta un contrato formal (WSDL) que aquí no se usó.

### Importar un pedido de una tienda (JSON o XML)
| Campo | Valor |
|---|---|
| Método y URL | `POST /importar.php` (formulario web, sesión de operador + CSRF) |
| Petición | Archivo o texto JSON (`numero`, `cliente`, `fecha`, `lineas`) o XML que cumpla [`bd/esquemas/pedido.xsd`](../../bd/esquemas/pedido.xsd) |
| Respuesta | 303 a `/pedidos.php` con el pedido registrado y el stock **reservado** |
| Errores | 422 con el motivo: fecha imposible (`2026-02-30`), código fuera del patrón (`pol1`), cantidad negativa, `"2"` en lugar de `2` |
| Seguridad | `DOMDocument::loadXML(…, LIBXML_NONET)` + `schemaValidate()`; sin `LIBXML_NOENT` (no expande entidades: XXE) |
| Evidencia | [E04_validacion_xsd_json.png](evidencias/E04_validacion_xsd_json.png) · [E04_validacion_xsd_rechazada.png](evidencias/E04_validacion_xsd_rechazada.png) |

### Consumir un servicio público con cURL
`php herramientas/consumir_publico.php` → `GET https://jsonplaceholder.typicode.com/todos/1 → HTTP 200 · application/json · 83 bytes`.
Comprueba el código HTTP y el tipo, decodifica con `JSON_THROW_ON_ERROR` y **nunca desactiva** la verificación del certificado.
Evidencia: final de [E06_soap_rest_curl.png](evidencias/E06_soap_rest_curl.png).

---

## Laboratorio 13 · API REST `/api/v1`

### Listar prendas
| Campo | Valor |
|---|---|
| Método y URL | `GET /api/v1/productos?pagina=2&por_pagina=5&orden=precio-asc` |
| Parámetros | `pagina` ≥ 1 · `por_pagina` 1–50 · `q` · `categoria` · `talla` · `disponibles` (0/1) · `orden` (`nombre`, `precio-asc`, `precio-desc`, `stock`) |
| Autenticación | Pública |
| Respuesta 200 | `{"datos": [{"codigo": "POL-002", "precio": "120.00", "disponible": 0, …}], "meta": {"total": 14, "pagina": 2, "por_pagina": 5, "paginas": 3}, "enlaces": {"self": …, "anterior": …, "siguiente": …}}` |
| Errores | 422 `{"errores": {"por_pagina": "Debe ser un entero entre 1 y 50.", "orden": "Valores permitidos: …"}}` |
| Evidencia | [E07_get_paginado.png](evidencias/E07_get_paginado.png) |

### Sugerencias (autocompletar)
| Campo | Valor |
|---|---|
| Método y URL | `GET /api/v1/productos/sugerencias?q=po` |
| Autenticación | Pública (`Cache-Control: public, max-age=60`) |
| Respuesta 200 | `{"datos": [{"codigo": "POL-002", "nombre": "Blusa de tocuyo manga corta", "precio": "120.00"}, …]}` (hasta 8) |
| Errores | 422 si `q` tiene menos de 2 o más de 40 caracteres |
| Nota | Se registra **antes** de `/productos/{codigo}`: si no, «sugerencias» se tomaría como un código |
| Evidencia | [E07_get_paginado.png](evidencias/E07_get_paginado.png) (segundo bloque) |

### Consultar una prenda
| Campo | Valor |
|---|---|
| Método y URL | `GET /api/v1/productos/POL-001` |
| Autenticación | Pública |
| Petición | `Accept: application/json` (opcional `If-None-Match: "<etag>"`) |
| Respuesta 200 | `{"codigo": "POL-001", "nombre": "Polera de algodón con bordado de tajibo", "precio": "95.00", "stock": 24, "reservado": 0, "disponible": 24, …}` + `ETag: "270efeff525362041e37"` |
| Respuesta 304 | Con el mismo ETag: sin cuerpo |
| Errores | 404 `{"title": "No encontrado", "detail": "No existe la prenda NOP-000."}` |
| Evidencia | [E12_openapi_etag_cors.png](evidencias/E12_openapi_etag_cors.png) |

### Crear una prenda
| Campo | Valor |
|---|---|
| Método y URL | `POST /api/v1/productos` |
| Autenticación | Bearer · roles administración u operador |
| Petición | `Content-Type: application/json` · [`ejemplos/producto.json`](ejemplos/producto.json) |
| Respuesta 201 | `Location: /api/v1/productos/POL-050` + la prenda creada |
| Errores | 400 JSON roto · 401 sin token o token inventado · 403 token de socia · 409 código repetido · 415 `text/plain` · 422 por campo |
| Evidencia | [E08_post_201.png](evidencias/E08_post_201.png) · [E11_errores_401_403_422.png](evidencias/E11_errores_401_403_422.png) |

Reutiliza la **misma** validación que el formulario web (`validar_producto()`), así la API y la web nunca discrepan.

### Reemplazar una prenda
| Campo | Valor |
|---|---|
| Método y URL | `PUT /api/v1/productos/POL-050` |
| Autenticación | Bearer · administración u operador |
| Petición | [`ejemplos/actualizacion.json`](ejemplos/actualizacion.json) (el código de la URL manda) |
| Respuesta 200 | La prenda con `"precio": "120.00"`, `"stock": 10`, `"tallas": ["S","M","L","XL"]` |
| Errores | 401 · 403 · 404 · 415 · 422 (también si el stock queda por debajo de lo reservado por pedidos) |
| Evidencia | [E09_put_200.png](evidencias/E09_put_200.png) |

### Eliminar una prenda
| Campo | Valor |
|---|---|
| Método y URL | `DELETE /api/v1/productos/POL-050` |
| Autenticación | Bearer · **solo administración** (el operador recibe 403) |
| Respuesta 204 | Sin cuerpo; después `GET` → 404 |
| Errores | 401 · 403 · 404 · 409 si la prenda tiene ventas, acopios o pedidos (el historial no se borra) |
| Evidencia | [E10_delete_204.png](evidencias/E10_delete_204.png) |

### Preflight CORS
| Campo | Valor |
|---|---|
| Método y URL | `OPTIONS /api/v1/productos` |
| Respuesta 204 | `Access-Control-Allow-Origin: *`, `-Methods: GET, POST, PUT, DELETE, OPTIONS`, `-Headers: Authorization, Content-Type, If-None-Match`, `-Expose-Headers: Location, ETag` |
| Evidencia | [E12_openapi_etag_cors.png](evidencias/E12_openapi_etag_cors.png) |

### Contrato
`GET /api/v1/openapi.yaml` → 200 `application/yaml` (`openapi: 3.1.0`). Validado sin errores en un editor OpenAPI.

---

## Trabajo final · Endpoints de los módulos nuevos

### Liquidación de una socia (módulo 1)
| Campo | Valor |
|---|---|
| Método y URL | `GET /api/v1/socios/2/liquidacion` |
| Autenticación | Bearer · administración y operador: cualquiera · **socia: solo la suya** (si no, 403) |
| Respuesta 200 | `{"socio": {"id": 2, "nombre": "Elena Masabí Tomichá"}, "entregadas": 13, "aprobadas": 12, "liquidado": "1065.00", "pagado": "585.00", "saldo": "480.00", "acopios": […], "pagos": […]}` |
| Errores | 401 · 403 · 404 |

### Pedidos de compradores (módulo 2)
| Campo | Valor |
|---|---|
| Método y URL | `GET /api/v1/pedidos?estado=pendiente` · `GET /api/v1/pedidos/{id}` · `POST /api/v1/pedidos` |
| Autenticación | Bearer · administración u operador |
| Petición (POST) | `{"numero": "API-0001", "cliente": "Tienda en línea", "fecha": "2026-10-26", "lineas": [{"codigo": "ABR-201", "cantidad": 2}]}` |
| Respuesta 201 | `Location: /api/v1/pedidos/4` · `{"origen": "api", "estado": "pendiente", "total": "620.00", …}` y las unidades quedan **reservadas** |
| Errores | 401 · 403 · 409 número repetido · 415 · 422 (formato o stock insuficiente) |

---

## Laboratorio 14 · Consumo desde la interfaz (fetch)

| Pantalla | Petición | Qué hace el JavaScript | Evidencia |
|---|---|---|---|
| Vitrina `/` | `GET /api/v1/productos?…` | Grilla, filtros, orden y paginación sin recargar; `AbortController` en cada carga | [E13](evidencias/E13_grilla_ajax.png) · [E14](evidencias/E14_paginacion_y_orden.png) |
| Buscador | `GET /api/v1/productos/sugerencias?q=` | Pausa de 250 ms, combobox ARIA (flechas, Enter, Escape) | [E15](evidencias/E15_autocompletar_accesible.png) |
| Red lenta | (búsquedas «bo», «bord», «jean») | Las viejas quedan `(canceled)`; gana «jean» (3 canceladas medidas) | [E16](evidencias/E16_busqueda_ultima_prevalece.png) |
| Nueva prenda | `GET /api/v1/productos/{codigo}` | `abc` sin petición · 200 = ocupado · 404 = disponible | [E17 ocupado](evidencias/E17_validacion_en_vivo_ocupado.png) · [E17 libre](evidencias/E17_validacion_en_vivo_disponible.png) |
| Novedades | `GET /noticias.rss` | `DOMParser`, control de `parsererror`, 5 ítems con `textContent` | [E18](evidencias/E18_rss_en_interfaz.png) |

Los datos de la API se insertan **siempre** con `createElement` + `textContent` + `replaceChildren` (ningún script usa `innerHTML`).
