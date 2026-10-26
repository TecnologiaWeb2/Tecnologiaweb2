# Laboratorio 11 — Hoja de trabajo: ventas con transacciones, acopios, reportes y CSV

Nombre: ______________________   Fecha: ___/___/2026

```
docker compose up -d && composer dump-autoload
php verificar.php                        # carga 96 ventas (jul-sep 2026) → 4 de 15 al inicio
php -S 127.0.0.1:8000 -t public public/index.php
```
Ventas: operador o admin · Reportes: solo admin (`admin@florchiquitania.bo` / `Panal-2026!`).

## Parte A — El modelo de datos
1. Dibuja las tablas `ventas`, `venta_detalle`, `acopios`, `productos` y `socios` con sus relaciones.
2. ¿Por qué `venta_detalle` guarda `precio_unitario` si el producto ya tiene precio?
3. ¿Por qué la clave primaria de `venta_detalle` es `(venta_id, producto_id)`? ¿Para qué sirve el índice `ix_ventas_fecha`?

## Parte B — Transacciones (TODO 1 y 2 en `src/Ventas/RegistroVenta.php`)
**Antes** de los TODO, desde la consola de MySQL anota el stock de `MIE-500` y la cantidad de ventas. Registra una venta
con **2 MIE-500 + 99 CER-1K** (no hay tanta cera).
1. ¿Qué error aparece? ¿Cuánto stock de MIE-500 quedó? ¿Se creó la venta? ¿Qué problema contable es este?
2. Completa el TODO 1. Repite: ¿qué cambió?
3. TODO 2: ¿por qué es mejor `UPDATE … SET stock = stock - :cantidad WHERE id = :id AND stock >= :minimo` que leer el
   stock con SELECT, comparar en PHP y luego hacer el UPDATE? (Pista: dos vendedoras a la vez.)
4. ¿Qué responde ahora la aplicación? ¿Por qué 409 y no 500?
5. Lee `RegistroAcopio`: ¿qué hace `SELECT … FOR UPDATE`?

## Parte C — Reportes con SQL (TODO 3, 4 y 5 en `src/Reportes/ReporteVentas.php`)
1. TODO 3: ventas por mes. Copia el resultado de julio a septiembre.
2. TODO 4: los 5 productos con más importe. ¿Por qué hay que agrupar por `p.id` y no solo ordenar?
3. Lee `porSocio()`: ¿qué diferencia hay entre `WHERE` y `HAVING`? ¿Cuántos socios superan Bs 3.000?
4. TODO 5: productos sin ventas. ¿Por qué `LEFT JOIN … IS NULL` y no `JOIN`?
5. ¿Por qué el período usa `fecha < hasta + 1 día` en lugar de `fecha <= hasta`?

## Parte D — Fechas y CSV (TODO 6 y 7)
1. `php -r 'echo DateTimeImmutable::createFromFormat("!Y-m-d","2026-02-31")->format("Y-m-d");'` → ¿qué fecha da?
   Completa el TODO 7.
2. Descarga el CSV y ábrelo con Excel o LibreOffice. ¿Se ven bien las tildes y los decimales? ¿Para qué sirven el BOM
   y el separador `;`?
3. Registra una venta con el cliente `=HYPERLINK("http://ejemplo.invalid","Ver factura")` y descarga el CSV antes y
   después del TODO 6. ¿Qué haría Excel con esa celda? ¿Cómo lo evita `celdaSegura()`?

## Parte E — Verificación
`php verificar.php` → objetivo **15 de 15**. Los reportes se comparan con un cálculo independiente en PHP.

## Reflexión
¿Qué preguntas de la directiva responde ahora el sistema que antes se discutían "con el cuaderno"? Propón un reporte
nuevo útil para la asociación y escribe su consulta SQL.
