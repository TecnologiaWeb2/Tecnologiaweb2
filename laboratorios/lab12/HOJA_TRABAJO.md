# Laboratorio 12 — Hoja de trabajo: intercambio de datos (JSON, XML, RSS) y SOAP frente a REST

Nombre: ______________________   Fecha: ___/___/2026

```
docker compose up -d && composer dump-autoload
docker compose exec php php -m | grep -i soap  # SOAP se instala en la imagen del laboratorio
docker compose exec php php verificar.php      # → objetivo: 15 de 15
php -S 127.0.0.1:8000 -t public public/index.php
```

Si ejecutas el verificador directamente en Windows en lugar de usar el contenedor,
habilita `extension=soap` en el `php.ini` que muestra `php --ini` y vuelve a abrir
la terminal. La extensión debe aparecer con:

```powershell
php -m | Select-String soap
php verificar.php
```

## Parte A — El catálogo en JSON (TODO 1)
1. Abre `http://127.0.0.1:8000/datos/catalogo.json`. ¿Qué tipo de dato tiene `precio` antes del TODO 1? ¿Cómo aparece
   el precio de `BAL-LAB`?
2. Completa el TODO 1. ¿Por qué el dinero viaja como texto? (Prueba `php -r 'var_dump(0.1 + 0.2);'`.)
3. ¿Qué formato tiene `generado`? ¿Por qué ISO 8601 con zona horaria?
4. Mira las cabeceras: ¿para qué sirve `Access-Control-Allow-Origin: *`? ¿Lo pondrías en una página con datos privados?

## Parte B — El catálogo en XML (TODO 2)
1. Abre `/datos/catalogo.xml` antes del TODO 2. ¿Qué dice el navegador? Busca el producto `POL-250`.
2. Completa el TODO 2. ¿Qué diferencia hay entre `writeRaw` y `writeElement`? ¿Con qué concepto de la clase 1 se relaciona?
3. Compara el mismo producto en JSON y en XML: ¿cuál ocupa más? ¿Qué ventaja tiene cada uno?

## Parte C — Pedidos de los clientes (TODO 3, 4 y 6)
1. Lee `esquemas/pedido.xsd`. ¿Qué reglas impone al pedido?
2. Importa `ejemplos/pedido-invalido.xml` en `/pedidos/importar` antes y después del TODO 3. Copia los errores.
3. Importa `ejemplos/pedido-invalido.json` antes y después del TODO 4. ¿Por qué `"2"` y `2.5` no son cantidades válidas?
4. TODO 6: ¿por qué detectar el formato por el **contenido** y no por la extensión del archivo?
5. El JSON no tiene XSD: ¿dónde están sus reglas? ¿Detecta la fecha `2026-02-31`? ¿Y el XSD?

## Parte D — RSS (TODO 5)
1. Abre `/noticias.rss`. ¿Qué elementos tiene cada `<item>`?
2. Completa el TODO 5. ¿Qué formato exige RSS 2.0 para `pubDate`?
3. Suscríbete al canal con un lector de RSS (o una extensión del navegador). ¿Qué ve el cliente?

## Parte E — SOAP frente a REST
1. Ejecuta `php soap_cliente.php MIE-500` con el servidor encendido. Copia el tamaño de la petición y de la respuesta.
2. Abre `/datos/productos/MIE-500` (REST). ¿Cuántos bytes tiene? Compara.
3. Ejecuta `php soap_cliente.php NO-EXISTE`. ¿Cómo informa SOAP el error? ¿Y REST (`/datos/productos/NO-EXISTE`)?
4. Completa la tabla:

| | SOAP | REST |
|---|---|---|
| Formato | | |
| Cómo se elige la operación | | |
| Cómo se informa un error | | |
| Contrato | | |
| Dónde se sigue usando | | |

## Parte F — Verificación
`php verificar.php` → objetivo **15 de 15**.

## Reflexión
Un supermercado de Santa Cruz quiere enviar sus pedidos automáticamente. ¿Le pedirías XML con XSD, JSON o ambos? Justifica.
