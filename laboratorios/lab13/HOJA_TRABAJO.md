# Laboratorio 13 — Hoja de trabajo: API REST con códigos de estado, problem+json y tokens

Nombre: ______________________   Fecha: ___/___/2026

```
docker compose up -d && composer dump-autoload
php verificar.php                        # carga tokens_api → 6 de 15 al inicio
php -S 127.0.0.1:8000 -t public public/index.php
```
Tokens de prueba: `ejemplos/tokens.txt`. Herramientas: `curl -i`, la extensión REST Client de VS Code, Postman o Bruno.
Contrato: `public/api/v1/openapi.yaml` (ábrelo en https://editor.swagger.io).

## Parte A — Recursos y lectura
1. `curl -i "http://127.0.0.1:8000/api/v1/productos?por_pagina=5&pagina=2&categoria=Miel&orden=precio"`. Describe
   `datos`, `meta` y `enlaces`. ¿Por qué no se muestra el `id` interno de MySQL?
2. Antes del TODO 7, prueba `?por_pagina=99&categoria=Hongos&orden=DROP`. ¿Qué hace la API? ¿Por qué en una API es
   mejor responder 422 que "corregir" en silencio (como hacía la web en el lab 9)?
3. TODO 3: completa el ETag. Pide el mismo producto dos veces enviando `If-None-Match` con el ETag recibido. ¿Qué
   código llega la segunda vez? ¿Qué ahorra?

## Parte B — Escritura y códigos de estado (TODO 1 y 2)
1. Crea un producto con el token del operador:
   ```
   curl -i -X POST -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
        -d '{"codigo":"MIE-ROS","nombre":"Miel de romero 250 g","categoria":"Miel","precio":"30.00","stock":5,"socio_id":4}' \
        http://127.0.0.1:8000/api/v1/productos
   ```
   ¿Qué código y cabeceras recibes antes y después del TODO 1?
2. TODO 2: ¿qué responde `DELETE` y por qué 204 no lleva cuerpo?
3. Completa: GET lista →  · GET uno →  · POST →  · PUT →  · DELETE →  · sin cambios (ETag) → 

## Parte C — Errores con problem+json (TODO 5 y 6)
1. Provoca cada error y copia `status`, `title` y `type`: sin token, token de socio, producto inexistente, `PATCH`,
   cuerpo de formulario (sin JSON), JSON roto y datos inválidos.
2. TODO 5: ¿qué código corresponde a un cuerpo que no es `application/json`?
3. TODO 6: ¿qué agrega `errores` a la respuesta 422? ¿Por qué todos los errores tienen la misma forma?

## Parte D — Tokens (TODO 4)
1. Mira la tabla `tokens_api` en MySQL. ¿Qué se guarda? ¿Por qué no el token?
2. ¿Por qué la API no usa cookies de sesión ni token CSRF?
3. ¿Qué diferencia hay entre 401 y 403? Da un ejemplo de cada uno con esta API.

## Parte E — CORS
1. `curl -i -X OPTIONS -H "Origin: https://tienda.ejemplo" -H "Access-Control-Request-Method: POST" http://127.0.0.1:8000/api/v1/productos`
   ¿Qué es esta petición "preflight" y quién la envía?
2. ¿Por qué aquí es aceptable `Access-Control-Allow-Origin: *`?

## Parte F — Verificación
`php verificar.php` → objetivo **15 de 15**.

## Reflexión
La tienda en línea "Vida Natural" quiere integrarse. Escríbele un correo corto (5 líneas) explicando cómo autenticarse,
dónde está el contrato y cómo interpretar los errores.
