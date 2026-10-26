# Laboratorio 12 — Hoja de trabajo (resuelta): intercambio de datos (JSON, XML, RSS) y SOAP frente a REST

Nombre: Yordy Tolavi   Fecha: 18/10/2026

> **Cómo se comprobó:** `docker compose exec php php verificar.php` → **15 de 15** (PHP 8.3 + MySQL 8 reales). Los tamaños y mensajes de error son salidas reales.

## Comandos (con Docker)
```
docker compose down -v && docker compose up -d --build
docker compose exec php php -m | grep -i soap        # soap habilitada (la instala el Dockerfile)
docker compose exec php php verificar.php            # al inicio: 7 de 15 → ahora: 15 de 15
docker compose exec php php soap_cliente.php MIE-500
# App: http://127.0.0.1:8000  ·  operador@florchiquitania.bo / Colmena-2026!
```

## Parte A — El catálogo en JSON (TODO 1)
1. Antes del TODO 1, `precio` era un **número** (`(float)`), p. ej. `15`, y `BAL-LAB` salía como `15.0`/`15` (se perdían los ceros: no es `"15.00"`). Después: `"precio": "15.00"`.
2. **Cambio:** `'precio' => $p->precio` (ya es el texto exacto de MySQL). El dinero viaja como texto porque los `float` son binarios: `0.1 + 0.2` da `float(0.30000000000000004)`. Un sistema que sume como número acumula errores de centavos; como texto, cada receptor decide cómo convertirlo (decimal exacto).
3. `generado` usa ISO 8601 con zona (`2026-10-19T01:41:00+00:00`, `DATE_ATOM`). Con zona horaria no hay ambigüedad entre sistemas en distintos países; además se ordena alfabéticamente y todos los lenguajes lo leen.
4. `Access-Control-Allow-Origin: *` permite que **JavaScript de cualquier sitio** lea la respuesta (CORS). Sirve para datos públicos como el catálogo. **No** en páginas con datos privados: cualquier web podría leerlos usando la sesión del visitante.

## Parte B — El catálogo en XML (TODO 2)
1. Antes del TODO 2 el navegador mostraba un error de XML mal formado: `Polen & propóleo` tiene un `&` sin escapar (el producto `POL-250` rompía el documento).
2. **Cambio:** `$x->writeElement('nombre', $p['nombre'])`. `writeRaw` escribe el texto **tal cual** (no escapa); `writeElement` convierte `&` en `&amp;`, `<` en `&lt;`, etc. Es el mismo concepto de la clase 1: **escapar la salida** (como `htmlspecialchars`, evitar inyección/XSS).
3. Ejemplo `POL-250`: en XML (`<nombre>Polen &amp; propóleo…</nombre>`) las etiquetas de cierre y el escape lo hacen un poco más largo por campo; JSON es más compacto y trae tipos (número, booleano). **JSON:** ligero, directo en JavaScript. **XML:** atributos, esquemas (XSD), espacios de nombres, documentos mixtos.

## Parte C — Pedidos de los clientes (TODO 3, 4 y 6)
1. El XSD exige: `numero` obligatorio; `cliente` de 2 a 80 caracteres; `fecha` tipo `xs:date` (fecha real); de 1 a 50 `linea`, cada una con `codigo` (patrón `[A-Z]{3}-[A-Z0-9]{2,4}`) y `cantidad` entera positiva ≤ 1000; y en ese orden.
2. Antes del TODO 3 el pedido inválido **no se rechazaba** (se leía sin validar). Después:
   `El pedido no cumple el esquema. Línea 5: Element 'fecha': '2026-02-31' is not a valid value of the atomic type 'xs:date'. Línea 6: … attribute 'codigo': [facet 'pattern'] The value 'mie-500' is not accepted by the pattern '[A-Z]{3}-[A-Z0-9]{2,4}'. Línea 6: … attribute 'cantidad': '-3' is not a valid value of the local atomic type.`
3. Después del TODO 4 (`is_int`): `"fecha" debe tener el formato AAAA-MM-DD. Línea 1: se espera {"codigo": "AAA-999", "cantidad": entero de 1 a 1000}. Línea 2: se espera …`. `"2"` es **texto** y `2.5` es **decimal**: JSON distingue tipos y una cantidad de unidades debe ser entero; `is_numeric` aceptaba ambos (y `(int) 2.5` se truncaba a 2 sin avisar).
4. **TODO 6:** el importador mira el primer carácter (`<` → XML, `{` → JSON, otro → `DatosInvalidos`). Se detecta por el **contenido** porque la extensión la elige quien envía: puede ser `.xml` con JSON dentro, no tener extensión (se pega en un textarea) o ser mentira. El contenido es lo que realmente se va a interpretar.
5. Las reglas del JSON están **en el código** (`PedidoJson`): tipos, patrones y rangos. `2026-02-31` **no** lo detecta: la regex solo comprueba la forma `AAAA-MM-DD`. El XSD **sí** (`xs:date` valida que la fecha exista). *(Mejora posible: `DateTimeImmutable::createFromFormat('Y-m-d', …)` y comparar.)*

## Parte D — RSS (TODO 5)
1. Cada `<item>` tiene `title`, `description`, `link`, `guid` (`isPermaLink="false"`, `acopio-N`) y `pubDate`.
2. **Cambio:** `->format(DATE_RSS)`. RSS 2.0 exige fecha **RFC 822**: `Wed, 30 Sep 2026 09:00:00 +0000`.
3. Un lector de RSS muestra una lista de novedades ("24 × Miel con jengibre 350 g — Entregado por Hernán Parapaino (San Miguel de Velasco)"), ordenadas por `pubDate`, y evita duplicados con el `guid`. El cliente consulta periódicamente (el usuario se "suscribe", sin que la asociación envíe nada).

## Parte E — SOAP frente a REST
1. `php soap_cliente.php MIE-500` → **petición 523 bytes**, **respuesta 960 bytes**.
2. REST `/datos/productos/MIE-500`: **82 bytes** (`{"codigo":"MIE-500","nombre":"Miel multifloral 500 g","precio":"45.00","stock":32}`). La respuesta SOAP es ≈ **11,7 veces** más grande para el mismo dato.
3. `NO-EXISTE`: SOAP responde un **Fault** (`SoapFault (SOAP-ENV:Client): No existe el producto NO-EXISTE.`) dentro de un sobre XML; REST responde **HTTP 404** con la URL como recurso inexistente.

| | SOAP | REST |
|---|---|---|
| Formato | Solo XML (sobre `Envelope/Body`) | Normalmente JSON (también XML, etc.) |
| Cómo se elige la operación | Por el nombre del método dentro del XML (`consultarProducto`), siempre al mismo endpoint `/soap` con POST | Por la URL (recurso) + el verbo HTTP (`GET /datos/productos/MIE-500`) |
| Cómo se informa un error | `<soap:Fault>` (`faultcode` Client/Server) en el XML | Código de estado HTTP (404, 422, 500…) |
| Contrato | WSDL/XSD formal, generable y verificable | OpenAPI (opcional) / convención; más flexible |
| Dónde se sigue usando | Bancos, gobierno, ERP y sistemas antiguos/empresariales | Apps web y móviles, APIs públicas, microservicios |

## Parte F — Verificación
`docker compose exec php php verificar.php` → **15 de 15** ✅

## Reflexión
**Ambos, con prioridad XML + XSD para el supermercado.** Un supermercado suele tener sistemas empresariales que ya generan XML; el **XSD es un contrato** que rechaza automáticamente errores (fechas imposibles, códigos mal escritos, cantidades negativas) con línea y causa, antes de tocar el stock. El JSON se ofrece para tiendas pequeñas, apps y sistemas modernos: es más liviano y fácil, pero hay que validarlo en código (o con JSON Schema). Como el importador detecta el formato por el contenido, aceptar ambos cuesta poco; en los dos casos se valida **antes** de registrar la venta y todo ocurre dentro de una transacción.
