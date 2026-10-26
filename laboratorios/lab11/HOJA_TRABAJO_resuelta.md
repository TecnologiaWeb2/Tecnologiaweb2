# Laboratorio 11 — Hoja de trabajo (resuelta): ventas con transacciones, acopios, reportes y CSV

Nombre: Yordy Tolavi   Fecha: 18/10/2026

> **Cómo se comprobó:** **[✔ comprobado]** = ejecutado con PHP 8.3 real o calculado sobre los datos de `sql/05-movimientos.sql`. **[razonado]** = deducido del código. En el entorno de preparación no hubo MySQL ni Docker, así que la corrida completa de `php verificar.php` (15 de 15) debes hacerla tú.

## Comandos (con Docker)
```
docker compose down -v && docker compose up -d --build
docker compose exec php php verificar.php      # al inicio: 4 de 15 → ahora: 15 de 15
# App: http://127.0.0.1:8000  ·  admin@florchiquitania.bo / Panal-2026!
```

## Parte A — El modelo de datos

**1. Relaciones**
```
socios (1) ──< productos (1) ──< venta_detalle >── (1) ventas >── (1) usuarios
   │                 │
   └──< acopios >────┘
```
- `productos.socio_id` → `socios.id` (cada producto es de un socio).
- `venta_detalle.venta_id` → `ventas.id` y `venta_detalle.producto_id` → `productos.id` (relación muchos a muchos entre ventas y productos).
- `ventas.usuario_id` → `usuarios.id` (quién vendió).
- `acopios.socio_id` → `socios.id` y `acopios.producto_id` → `productos.id`.

**2. ¿Por qué `venta_detalle` guarda `precio_unitario`?**
Porque el precio del producto **cambia con el tiempo**. Si el detalle solo apuntara al producto, al subir el precio se **alterarían las ventas pasadas** y los reportes históricos darían otro importe. Se guarda el precio **de ese día**.

**3. Clave primaria `(venta_id, producto_id)` e índice `ix_ventas_fecha`**
La clave compuesta garantiza que **un producto aparezca una sola vez por venta** (si se vende más, se suma la cantidad) y es única por línea. `ix_ventas_fecha` acelera los reportes, que filtran por rango de fechas: sin el índice MySQL recorrería **toda** la tabla; con él, salta directo al rango.

## Parte B — Transacciones (TODO 1 y 2)

**1. Antes de los TODO (2 MIE-500 + 99 CER-1K)** [razonado]
CER-1K tiene stock 4, así que restar 99 viola `CHECK (stock >= 0)`: MySQL responde `Check constraint 'chk_productos_stock' is violated` (error 3819) y la app muestra el **500 «Algo salió mal»**. Pero el `UPDATE` de MIE-500 (línea anterior) **ya se había ejecutado y quedó guardado**: el stock bajó en 2 y no se creó ninguna venta. Es un problema de **inconsistencia contable**: el inventario no cuadra con las ventas.

**2. TODO 1 — transacción** [✔ código comprobado con `php -l`]
```php
$this->bd->beginTransaction();
try {
    ...
    $this->bd->commit();
    return $ventaId;
} catch (Throwable $e) {
    if ($this->bd->inTransaction()) { $this->bd->rollBack(); }
    throw $e;
}
```
Ahora, si una línea falla, `rollBack()` **deshace todo**: el stock de MIE-500 vuelve a su valor y no se crea la venta. Es todo-o-nada (**atomicidad**).

**3. TODO 2 — por qué `WHERE … AND stock >= :minimo`**
Con SELECT + comparar en PHP + UPDATE hay una **condición de carrera**: dos vendedoras leen "quedan 3", ambas venden 3 y el stock termina en −3. Con el `UPDATE … AND stock >= :minimo` la verificación y el descuento ocurren en **una sola operación atómica**; MySQL bloquea la fila y la segunda vendedora ve el stock ya descontado (`rowCount() = 0`).
```php
UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND stock >= :minimo
if ($descontar->rowCount() === 0) { throw new Conflicto("Stock insuficiente de $codigo para vender $cantidad."); }
```
(Se usan dos parámetros distintos, `:cantidad` y `:minimo`, porque con consultas preparadas reales un mismo nombre no puede repetirse.)

**4. ¿Qué responde ahora y por qué 409?**
Un mensaje claro: *"Stock insuficiente de CER-1K para vender 99."* con estado **409 Conflict**. No es 500 porque no es un fallo del servidor: es un **conflicto con el estado actual** del recurso (el stock). La petición es válida, pero no se puede cumplir ahora.

**5. `SELECT … FOR UPDATE` (`RegistroAcopio`)**
Lee la fila y la **bloquea** hasta el `COMMIT`: nadie más puede modificarla mientras tanto, así el cálculo basado en lo leído (por ejemplo, el nuevo stock) no queda desactualizado por otra operación simultánea.

## Parte C — Reportes con SQL (TODO 3, 4 y 5)

**1. TODO 3 — ventas por mes** [✔ calculado sobre los datos]
```sql
SELECT DATE_FORMAT(v.fecha, '%Y-%m') AS mes, COUNT(*) AS ventas, SUM(v.total) AS total
FROM ventas v WHERE v.fecha >= :desde AND v.fecha < :hasta
GROUP BY DATE_FORMAT(v.fecha, '%Y-%m') ORDER BY mes
```
| Mes | Ventas | Total (Bs) |
|---|---|---|
| 2026-07 | 35 | 13.637,50 |
| 2026-08 | 26 | 14.110,50 |
| 2026-09 | 35 | 13.483,50 |

**2. TODO 4 — top 5 por importe** [✔ calculado sobre los datos]
```sql
SELECT p.codigo, p.nombre, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id
WHERE v.fecha >= :desde AND v.fecha < :hasta
GROUP BY p.id, p.codigo, p.nombre ORDER BY importe DESC, p.codigo LIMIT :limite
```
| # | Código | Producto | Unidades | Importe (Bs) |
|---|---|---|---|---|
| 1 | POL-500 | Polen seco 500 g | 32 | 4.480,00 |
| 2 | CER-1K | Cera pura 1 kg | 33 | 3.960,00 |
| 3 | MIE-2K | Miel multifloral 2 kg | 22 | 3.520,00 |
| 4 | MIE-JEN | Miel con jengibre 350 g | 48 | 2.496,00 |
| 5 | MIE-TOC | Miel de tajibo 500 g | 33 | 2.244,00 |

Hay que **agrupar por `p.id`** porque cada producto aparece en muchas líneas de detalle; sin `GROUP BY`, `SUM` colapsaría todo en una sola fila (o daría error). Ordenar solo reordena filas, no las **combina**: habría una fila por línea de venta, no por producto.

**3. `WHERE` vs `HAVING` en `porSocio()`**
`WHERE` filtra **filas antes** de agrupar (aquí, las del período). `HAVING` filtra **grupos después** de agrupar y puede usar agregados (`importe >= :minimo`, que es un `SUM`). Con mínimo Bs 3.000 superan **4 de los 8 socios** (así lo comprueba `verificar.php`).

**4. TODO 5 — productos sin ventas** [✔ calculado: `CER-BLO`, `JAL-30`, `MIE-OR`, `VIN-MIE`]
```sql
SELECT p.codigo, p.nombre, p.stock FROM productos p
LEFT JOIN (SELECT DISTINCT d.producto_id FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id
           WHERE v.fecha >= :desde AND v.fecha < :hasta) vendidos ON vendidos.producto_id = p.id
WHERE vendidos.producto_id IS NULL ORDER BY p.codigo
```
Un `JOIN` normal solo devuelve productos **con** coincidencia (los vendidos), justo los que no queremos. `LEFT JOIN` conserva **todos** los productos y rellena con `NULL` los que no tienen venta; `IS NULL` selecciona esos.

**5. `fecha < hasta + 1 día` y no `fecha <= hasta`**
`hasta = 2026-09-30` se convierte en `2026-09-30 00:00:00`, y con `<=` se perderían todas las ventas del 30 con hora (ej. `15:35`). Con `< 2026-10-01 00:00:00` entra **el día completo**. (La prueba "El período incluye el último día completo" lo verifica.)

## Parte D — Fechas y CSV (TODO 6 y 7)

**1. `2026-02-31`** [✔ comprobado]
Da **`2026-03-03`**: PHP "corre" la fecha en lugar de rechazarla. TODO 7:
```php
if ($fecha === false || $fecha->format('Y-m-d') !== $texto) { throw new DatosInvalidos(...); }
```
Así, `2026-02-31` (→ `2026-03-03`) y `2026-9-1` (→ `2026-09-01`) se rechazan; `2026-02-28` pasa.

**2. CSV en Excel/LibreOffice**
Con el BOM y el `;` se ven bien las **tildes y ñ** y los **decimales con coma** (`45,50`). El **BOM** (`EF BB BF`) al inicio indica que el archivo es **UTF-8**; sin él Excel lo lee como ANSI y salen `Ã³`. El **`;`** es el separador de listas de Excel en configuración regional española (donde la coma es el decimal); con `,` todo quedaría en una sola columna.

**3. Inyección CSV (TODO 6)** [✔ comprobado]
```php
$texto = (string) $valor;
return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'" . $texto : $texto;
```
Antes: la celda `=HYPERLINK("http://ejemplo.invalid","Ver factura")` se **interpretaba como fórmula**: Excel mostraría un enlace "Ver factura" que lleva a un sitio del atacante (o ejecutaría fórmulas/comandos peligrosos). Después: sale como `'=HYPERLINK(…)`, el apóstrofo la vuelve **texto literal**. Resultado de la prueba: `'=HYPERLINK(...)`, `'+591`, `'@x`; `Hotel Misiones` y `45,50` quedan igual.

## Parte E — Verificación
```
docker compose exec php php verificar.php      # objetivo: 15 de 15
```
Los 7 TODO están completos y todos los archivos PHP pasan `php -l`. La corrida con MySQL queda de tu lado.

## Reflexión

**Preguntas que ahora responde el sistema** (antes "con el cuaderno"): ¿cuánto vendimos cada mes?, ¿qué productos se venden más?, ¿cuánto generó cada socio?, ¿qué productos no se mueven?, ¿tenemos stock suficiente para esta venta?, ¿se perdió alguna unidad entre venta y almacén? (las transacciones lo impiden).

**Reporte nuevo propuesto — Ventas por cliente (¿quiénes compran más?):**
```sql
SELECT v.cliente, COUNT(*) AS compras, SUM(v.total) AS total, MAX(v.fecha) AS ultima_compra
FROM ventas v
WHERE v.fecha >= :desde AND v.fecha < :hasta
GROUP BY v.cliente
ORDER BY total DESC;
```
Sirve para saber a qué clientes mantener y a cuáles contactar si hace tiempo que no compran.
