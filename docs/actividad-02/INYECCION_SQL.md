# Demostración: la aplicación resiste la inyección SQL (Actividad 2)

## Por qué es segura

1. **Consultas preparadas reales.** Todos los datos del usuario viajan como parámetros (`:codigo`, `:t1`…).
   La conexión desactiva la emulación (`PDO::ATTR_EMULATE_PREPARES => false`), así MySQL recibe la consulta y
   los datos **por separado** y un dato nunca se interpreta como SQL ([`src/Conexion.php`](../../src/Conexion.php)).
2. **Lista blanca para lo que no puede ser parámetro.** `ORDER BY` no admite parámetros: el orden se elige de
   `ProductoRepositorio::SQL_ORDEN`; cualquier otro valor usa el orden por nombre.
3. **Comodines escapados.** En las búsquedas `LIKE`, los caracteres `%` y `_` que escribe el usuario se escapan
   con `addcslashes()`, así «%» busca un signo de porcentaje y no «todo».
4. **Enteros validados.** Página e ids pasan por `filter_var(..., FILTER_VALIDATE_INT)` y se enlazan con `PDO::PARAM_INT`.
5. **Errores sin detalles.** Si algo falla, el visitante ve un mensaje genérico (503); la consulta, la ruta y las
   credenciales solo van al log del servidor.

## Cómo una consulta VULNERABLE habría caído (no está en el proyecto)

```php
// ❌ MAL: el texto del usuario se pega dentro del SQL
$sql = "SELECT * FROM productos WHERE codigo = '" . $_GET['codigo'] . "'";
// Con ?codigo=' OR '1'='1 la consulta queda:
//   SELECT * FROM productos WHERE codigo = '' OR '1'='1'   → devuelve TODAS las prendas
```

```php
// ✅ BIEN (así está en ProductoRepositorio::buscarPorCodigo)
$consulta = $pdo->prepare('... WHERE p.codigo = :codigo');
$consulta->execute(['codigo' => $codigo]);   // ' OR '1'='1 se busca como un código literal → no existe → 404
```

## Ataques probados (automáticos en `php verificar.php`)

| # | Ataque | Dónde | Resultado |
|---|---|---|---|
| 1 | `' OR '1'='1` | Búsqueda de la tienda `/?q=` | 0 prendas, sin error SQL |
| 2 | `POL-001' OR '1'='1` | Ficha `/producto.php?codigo=` | 404, no muestra otra prenda |
| 3 | `precio; DROP TABLE productos; --` | Orden `/?orden=` | Se ignora (lista blanca); tablas intactas |
| 4 | `1 OR 1=1` | Paginación `/admin.php?pagina=` | Página 1 normal |
| 5 | `' UNION SELECT 1,2,3,... --` | Búsqueda de socios `/socios.php?q=` | 0 socios |
| 6 | `Polera'); DROP TABLE socios;-- <script>` | Nombre en el formulario de alta | Se guarda literal y se muestra escapado |
| 7 | `' OR 1=1 -- ` / `%` / `_` | `ProductoRepositorio::paginar(texto: …)` | 0 resultados |
| 8 | `' OR ''='` | `SocioRepositorio::existeCi()` | `false` |

Además, una prueba revisa el código fuente de `src/Repositorio/` y falla si algún `prepare()`, `query()` o
`exec()` interpola una variable que no sea de las listas blancas (`$donde`, `$ordenSql`).

## Para la defensa (en vivo)

1. Abrir `http://127.0.0.1:8000/?q=' OR '1'='1` → «0 prendas».
2. Abrir `http://127.0.0.1:8000/producto.php?codigo=POL-001' OR '1'='1` → «Prenda no encontrada».
3. Registrar una prenda llamada `Robert'); DROP TABLE productos;--` → aparece con ese nombre literal y el catálogo sigue completo.
4. Ejecutar `php verificar.php` → todas las comprobaciones en verde.
