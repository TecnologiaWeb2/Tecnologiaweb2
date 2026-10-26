# Laboratorio 13 — Hoja de trabajo (resuelta): API REST con códigos de estado, problem+json y tokens

Nombre: Yordy Tolavi   Fecha: 18/10/2026

> **Cómo se comprobó:** `php verificar.php` → **15 de 15** (PHP 8.3 + MySQL 8 reales). Las respuestas y cabeceras de abajo son salidas reales de `curl`. Los "antes" se obtuvieron ejecutando el `lab13_inicio` original en otro puerto.

## Comandos (con Docker)
```
docker compose down -v && docker compose up -d --build
docker compose exec php php verificar.php        # al inicio: 6 de 15 → ahora: 15 de 15
# Web: http://127.0.0.1:8000  ·  admin@florchiquitania.bo / Panal-2026!
# API: http://127.0.0.1:8000/api/v1/productos     Contrato: /api/v1/openapi.yaml
```
Tokens de prueba (`ejemplos/tokens.txt`): `TOKEN=$(awk '$1=="operador"{print $2}' ejemplos/tokens.txt)`

## Los 7 TODO resueltos
| TODO | Archivo | Cambio |
|---|---|---|
| 1 | `ProductosApi::crear` | `return [201, $creado, ['Location' => RUTA . '/' . rawurlencode($codigo)]]` |
| 2 | `ProductosApi::eliminar` | `return [204, null, []]` |
| 3 | `ProductosApi::ver` | ETag = `"` + 16 primeros hex de `sha256(json)` + `"`; si `If-None-Match` coincide → `[304, null, ETag]`; si no → 200 con `ETag` y `Cache-Control: private, max-age=0, must-revalidate` |
| 4 | `AutenticadorToken` | `execute([hash('sha256', $m[1])])` (se busca por el hash) |
| 5 | `NucleoApi::leerCuerpo` | si el `Content-Type` (sin `; charset=…`) ≠ `application/json` → `TipoNoSoportado` (415) |
| 6 | `Problema::desde` | si es `DatosInvalidos`: `detail = 'Revisa los campos indicados en "errores".'` y `errores = $e->errores()` |
| 7 | `ProductosApi::listar` | `if ($errores !== []) throw new DatosInvalidos($errores);` |

## Parte A — Recursos y lectura
**1.** `GET /api/v1/productos?por_pagina=5&pagina=2&categoria=Miel&orden=precio` → `200`, `Content-Type: application/json`.
- `datos`: lista de 5 productos (`codigo`, `nombre`, `categoria`, `precio` como texto `"45.00"`, `stock`, `disponible`, `socio{id,nombre}` y `enlaces.self`).
- `meta`: `{"total": 13, "pagina": 2, "por_pagina": 5, "paginas": 3}` (13 productos de Miel, 3 páginas).
- `enlaces`: `self`, `anterior` y `siguiente` (HATEOAS simple); conservan los filtros: `/api/v1/productos?categoria=Miel&orden=precio&pagina=3&por_pagina=5`. El cliente no arma URLs a mano.
- **El `id` interno de MySQL no se muestra** porque es un detalle de implementación: si mañana se cambia de base de datos o se reordena, los clientes no se rompen. La identidad pública es el `codigo` (estable, legible) y así no se revela cuántos registros hay ni se facilita adivinar identificadores.

**2.** Antes del TODO 7, `?por_pagina=99&categoria=Hongos&orden=DROP` devolvía **`200`** como si nada: `por_pagina` pasaba a 10, la categoría se ignoraba (36 productos, sin filtrar) y el orden pasaba a `nombre`. Ahora responde **`422`** `problem+json` con `errores`: `por_pagina` ("Debe ser un entero entre 1 y 50."), `categoria` ("Valores permitidos: Miel, Derivados, Cera.") y `orden` ("Valores permitidos: nombre, precio, precio_desc, stock.").
**Por qué 422 y no "corregir":** en la web (lab 9) hay una persona que ve el resultado y puede darse cuenta; en una API el cliente es un **programa**: si "corregimos" en silencio, cree que recibió lo que pidió (p. ej. pidió 99 por página y recibe 10, o cree que filtró por Hongos y recibe todo) y falla más tarde y sin pista. El error explícito se detecta en desarrollo y dice exactamente qué corregir.

**3.** Primera petición: `200` con `ETag: "da284eb7160b3b86"` y `Cache-Control: private, max-age=0, must-revalidate`. Segunda, con `If-None-Match: "da284eb7160b3b86"`: **`304 Not Modified`** sin cuerpo. **Ahorra** el cuerpo (295 bytes aquí; en recursos grandes, mucho más) y el trabajo del cliente de volver a procesarlo: el navegador/cliente reutiliza su copia. Sigue validando contra el servidor (`max-age=0, must-revalidate`), así que nunca ve datos viejos.

## Parte B — Escritura y códigos de estado (TODO 1 y 2)
**1.** Con el lab original **todas** las escrituras daban `401` (TODO 4 pendiente: se buscaba el token en claro y en la tabla está su hash). Con el TODO 4 hecho, el comportamiento sin TODO 1 es `200 OK` sin `Location` (leído en el código); **después del TODO 1:**
```
HTTP/1.1 201 Created
Location: /api/v1/productos/MIE-ROS
Content-Type: application/json; charset=utf-8     (cuerpo: el producto creado)
```
`201` significa "se creó un recurso nuevo" y `Location` dice dónde encontrarlo.

**2.** `DELETE` responde **`204 No Content`**. No lleva cuerpo porque el recurso ya no existe: no hay nada que representar; el 204 dice "hecho, y no tengo nada más que contarte" (antes devolvía `200` con `{"eliminado": true}`, información redundante). Repetir el DELETE da `404`.

**3.** GET lista → **200** · GET uno → **200** · POST → **201** (+ `Location`) · PUT → **200** · DELETE → **204** · sin cambios (ETag) → **304**.

## Parte C — Errores con problem+json (TODO 5 y 6)
**1.** Todos con `Content-Type: application/problem+json`, `type` = `https://florchiquitania.bo/problemas/<tipo>` y además `detail` e `instance`:

| Caso | status | title | type (final) |
|---|---|---|---|
| Sin token (POST) | **401** | No autenticado | `…/no-autenticado` (+ `WWW-Authenticate: Bearer realm="api", error="invalid_token"`) |
| Token de socio (POST) | **403** | Sin permiso | `…/prohibido` ("El rol Socio no permite esta operación.") |
| Producto inexistente | **404** | No encontrado | `…/no-encontrado` |
| `PATCH` | **405** | Método no permitido | `…/metodo-no-permitido` (+ `Allow: GET, PUT, DELETE`) |
| Cuerpo de formulario (sin JSON) | **415** | Tipo de contenido no soportado | `…/tipo-no-soportado` |
| JSON roto | **400** | JSON inválido | `…/json-invalido` ("…no es JSON válido: Syntax error.") |
| Datos inválidos | **422** | Datos inválidos | `…/datos-invalidos` |

**2.** Un cuerpo que no es `application/json` corresponde a **415 Unsupported Media Type** (el servidor entiende la petición, pero no el formato en que viene).

**3.** El TODO 6 agrega la extensión **`errores`**, un objeto con un mensaje por campo, y deja `detail` genérico ("Revisa los campos indicados en "errores""). Ejemplo real: `{"codigo": "El código debe tener el formato AAA-999…", "nombre": "…entre 3 y 80 caracteres.", "categoria": "Elige una categoría de la lista.", "precio": "…", "stock": "…", "socio_id": "Elige un socio de la lista."}`. **Todos los errores tienen la misma forma** (RFC 9457: `type`, `title`, `status`, `detail`, `instance`) para que el cliente escriba **un solo manejador**: mira `status`, muestra `title`/`detail` y, si hay `errores`, los pinta junto a cada campo.

## Parte D — Tokens (TODO 4)
**1.** `tokens_api` guarda: `id`, `usuario_id` (a quién representa), `nombre` (para qué sistema se emitió), `hash` (**SHA-256 en 64 hex**), `expira` y `revocado`. **No guarda el token** porque, si alguien roba la base de datos, solo obtendría hashes: no puede usarlos como `Bearer` (no se pueden invertir). Es el mismo principio de las contraseñas; como el token es aleatorio y largo (160 bits), SHA-256 basta (no hace falta bcrypt).

**2.** La API **no usa cookies** porque el cliente es un programa que envía el token **explícitamente** en `Authorization: Bearer …`. El ataque CSRF se aprovecha de que el navegador **adjunta cookies automáticamente** a cualquier petición hacia el sitio; si no hay cookies, una página maliciosa no puede "hacer actuar" al usuario con sus credenciales, y el token CSRF no tiene nada que proteger.

**3.** **401** = "no sé quién eres" (falta el token, es falso, venció o fue revocado; lleva `WWW-Authenticate`). **403** = "sé quién eres, pero no tienes permiso". Ejemplos: `POST` sin token → 401; `POST` con el token de **socio** → 403; `DELETE` con el token de **operador** → 403 (solo admin elimina).

## Parte E — CORS
**1.** Respuesta: `204 No Content` con `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`, `Access-Control-Allow-Headers: Authorization, Content-Type, If-None-Match` y `Access-Control-Expose-Headers: Location, ETag`. El **preflight** es una consulta previa `OPTIONS` que **envía automáticamente el navegador** (no el programador) antes de una petición entre orígenes "no simple" (con `Authorization`, JSON o PUT/DELETE) para preguntar si el servidor lo permite. Si la respuesta lo autoriza, el navegador envía la petición real.

**2.** `*` es aceptable aquí porque (a) la lectura del catálogo es **pública**; (b) la escritura exige un **token** que el sitio externo no tiene; y (c) **no hay cookies** ni credenciales automáticas que otro sitio pueda aprovechar. No lo pondría en una página con sesión por cookie o datos privados.

## Parte F — Verificación
`php verificar.php` → **15 de 15** ✅

## Reflexión — correo a "Vida Natural"
> **Asunto:** Integración con la API de Flor de la Chiquitanía
> Hola equipo de Vida Natural: el catálogo se consulta sin token en `GET /api/v1/productos` (paginado, con filtros `categoria`, `q`, `orden`).
> Para crear, modificar o eliminar productos les entregaremos un **token** que enviarán en `Authorization: Bearer api_…` (se muestra una sola vez; guárdenlo como una contraseña).
> Todo el contrato (rutas, parámetros y ejemplos) está en `/api/v1/openapi.yaml`; pueden abrirlo en editor.swagger.io.
> Los errores llegan siempre en `application/problem+json` con `status`, `title`, `detail` y, si hay datos inválidos, `errores` por campo: usen `status` para decidir (401 token, 403 permiso, 404, 422 datos).
> Envíen el cuerpo como `Content-Type: application/json` y usen el `ETag` con `If-None-Match` para recibir `304` cuando no haya cambios.
