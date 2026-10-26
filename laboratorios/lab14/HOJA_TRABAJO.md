# Laboratorio 14 — Hoja de trabajo: interfaz asíncrona con fetch (AJAX moderno)

Nombre: ______________________   Fecha: ___/___/2026

```
docker compose up -d                     # MySQL (127.0.0.1:3307) y contenedor PHP
npm install                              # pruebas en tu Chrome (playwright-core, no descarga navegadores)
php verificar.php                        # EN WINDOWS (no dentro de Docker) → 18 de 18 con Node + Chrome; 8 de 8 si Node no responde
php -S 127.0.0.1:8000 -t public public/index.php      → http://127.0.0.1:8000/catalogo
```
> `composer dump-autoload` solo hace falta si ejecutas PHP fuera de Docker y falta `vendor/`; en Docker ya se ejecuta solo.
> No uses `docker compose exec php php verificar.php` para las 18 pruebas: dentro del contenedor no hay Node ni Chrome (solo salen 8 de 8).

## Parte 0 — Verificar el entorno (Windows / PowerShell)
Ejecuta cada comando y anota el resultado:

| Comando | Esperado | Mi resultado |
|---|---|---|
| `docker --version` | Docker 20 o superior | |
| `node -v` | `v18` o superior | |
| `Test-Path "$env:ProgramFiles\Google\Chrome\Application\chrome.exe"` | `True` (basta con que una de las 3 rutas de Chrome dé `True`) | |
| `php -v` | PHP 8.3 o superior | |
| `php -m \| findstr /i "curl mbstring pdo_mysql"` | aparecen las 3 extensiones | |
| `docker compose ps` | `apicultor14-mysql` en estado *Healthy* | |

Si `php verificar.php` dice `could not find driver`, falta activar `extension=pdo_mysql` (y `curl`, `mbstring`) en el `php.ini` de PHP.
Si dice "Pruebas en el navegador omitidas", el mensaje indica el motivo (Node no responde o falta `npm install`).
Atajo que hace todo esto por ti: `powershell -ExecutionPolicy Bypass -File .\verificar-windows.ps1`

Ten abierto DevTools > Red (filtro Fetch/XHR) y > Consola durante todo el laboratorio.

## Parte A — La página cascarón
1. Mira el código fuente de `/catalogo` (Ctrl+U). ¿Hay productos en el HTML? ¿De dónde salen los que ves?
2. En DevTools > Red, ¿qué peticiones hace la página al cargar? Anota URL, código y tipo de cada una.
3. ¿Qué ventaja tiene que la grilla se actualice sin recargar la página? ¿Y qué desventaja (piensa en buscadores y en
   quien tiene JavaScript desactivado)?

## Parte B — Mostrar datos sin abrir agujeros (TODO 1)
1. El verificador agrega un producto llamado `<b>Miel</b> de prueba`. ¿Cómo se ve en la grilla antes del TODO 1?
2. ¿Qué pasaría si el nombre fuera `<img src=x onerror=alert(1)>`? ¿Qué relación tiene con la clase 1 y la clase 7?
3. Completa el TODO 1 con `createElement` + `textContent`.

## Parte C — Peticiones que compiten (TODO 2 y 3)
1. En Red, activa "Slow 3G" y busca rápido `mi`, `mie`, `cera` (Enter después de cada una). Antes del TODO 2, ¿qué
   resultado queda en la grilla? ¿Es siempre el de "cera"?
2. Completa el TODO 2 (`AbortController`). Repite: ¿qué estado muestran las peticiones viejas en DevTools?
3. TODO 3: `fetch` no falla con 404 ni con 500. Detén MySQL (`docker compose stop`) y recarga: ¿qué ve el usuario antes
   y después del TODO 3? Vuelve a iniciarlo.

## Parte D — Autocompletar accesible (TODO 4 y 7)
1. TODO 7: abre `/api/v1/productos/sugerencias?q=jab`. ¿Qué responde antes y después? ¿Por qué importa el ORDEN de las rutas?
2. Escribe `jab`: ¿cuántas peticiones de sugerencias se hacen? ¿Para qué sirve `conPausa` (250 ms)?
3. TODO 4: navega con flechas y elige con Enter. ¿Qué hacen `aria-expanded`, `aria-activedescendant` y `aria-selected`?
   Prueba con el lector de pantalla (NVDA o Narrador) si puedes.

## Parte E — Novedades RSS y validación en vivo (TODO 5 y 6)
1. TODO 5: lee el RSS del lab 12 con `DOMParser`. ¿Por qué aquí no hace falta CORS?
2. TODO 6: en `/productos/nuevo` (como operador) escribe `abc`, `MIE-500` y `POL-999`. ¿Qué petición hace cada una?
   ¿Por qué un **404** significa "disponible"?
3. Si el JavaScript dice "disponible", ¿el servidor deja de validar? ¿Por qué?

## Parte F — Verificación
`php verificar.php` (en Windows) → objetivo **18 de 18** (8 de servidor + 10 con Chrome). Si Node no responde se muestran solo **8 de 8** de servidor; en ese caso haz además las pruebas manuales de las Partes C-E.

## Reflexión
¿Qué parte del catálogo asíncrono mejora más la experiencia de los compradores de la feria con conexión móvil lenta?
¿Qué riesgos agregó el JavaScript y cómo los controlaste?
