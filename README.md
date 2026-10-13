# Manos del Oriente: tienda virtual de ropa (Laboratorio 5)

Catálogo web de la **Asociación de Confeccionistas Textiles «Manos del Oriente»** (caso verosímil, Santa Cruz, Bolivia),
hecho en PHP 8.4 con POO, formularios validados, subida segura de fotos y persistencia en JSON.
Es la base del proyecto final (luego se migra a MySQL y se agregan roles, API REST y AJAX).

## Cómo ejecutar

```bash
cd lab05
php -S localhost:8000 -t public      # abrir http://localhost:8000
php verificar.php                    # pruebas automáticas: 33 de 33
```

Las pruebas trabajan sobre una copia temporal de `datos/` (variable `TIENDA_DATOS`), así que no modifican el catálogo real.
Para volver a los datos de demostración: `cp datos/productos.inicial.json datos/productos.json`.

## Estructura

```
lab05/
├── public/                 ← única carpeta expuesta por el servidor
│   ├── index.php           vitrina con filtros GET (texto, categoría, talla, stock, orden)
│   ├── producto.php        ficha de una prenda
│   ├── admin.php           gestión: tabla, valor del inventario, editar/eliminar
│   ├── nuevo.php / editar.php / eliminar.php   formularios POST (PRG + CSRF)
│   ├── foto.php            entrega fotos guardadas fuera de public/
│   └── css/estilo.css
├── src/
│   ├── arranque.php        require_once de todo (punto único de carga)
│   ├── config.php          rutas y constantes
│   ├── Modelo/Producto.php, Categoria.php, Catalogo.php, Talla.php (enum)
│   ├── validacion.php      validar_producto(): validación + saneamiento
│   ├── fotos.php           guardar_foto(), ruta_foto(), borrar_foto()
│   └── funciones.php       e(), bs(), texto(), redirigir(), CSRF
├── plantillas/             cabecera, pie y formulario (include/require)
├── datos/                  categorias.json, productos.json, productos.inicial.json, fotos/
├── docs/BITACORA_IA.md
├── Verificador.php         motor de pruebas del curso
└── verificar.php           pruebas del laboratorio
```

## Requisitos del laboratorio y dónde se cumplen

| Requisito | Dónde |
|---|---|
| PHP 8.4, servidor embebido, `declare(strict_types=1)` | todos los `.php`; `php -S … -t public` |
| Control de flujo, arreglos y cadenas | `Catalogo::filtrar()` (`array_filter`, `usort`, `match`), `validar_producto()` (`preg_match`, `mb_*`, `str_replace`) |
| Funciones tipadas, parámetros nombrados, valores por defecto | `bs(float $monto, string $moneda = 'Bs', int $decimales = 2)`, `guardar_foto(…, int $maximo = TAM_MAXIMO)`, `$catalogo->filtrar(talla: …, orden: …)`, `validar_producto(…, codigoFijo: $codigo)` |
| Organización con `include`/`require` | `src/arranque.php`, `plantillas/*.php` |
| Formularios GET y POST, validación y saneamiento | filtros GET en `index.php`; POST en `nuevo.php`/`editar.php`; errores por campo con `aria-invalid`; respuesta 422 |
| Subida segura de la foto | tipo real con `finfo` + `getimagesize`, máximo 2 MB, nombre aleatorio, carpeta fuera de `public/`, `foto.php` con `nosniff` y bloqueo de `../` |
| Clases `Producto`, `Categoria`, `Catalogo` con promoción de propiedades | `src/Modelo/` (`readonly`, `con(...)` para copias inmutables, enum `Talla`) |
| Persistencia en JSON | `Catalogo::guardar()` con `LOCK_EX` + escritura atómica (temporal + `rename`) |
| Seguridad adicional (adelanto del proyecto final) | token CSRF, eliminar solo por POST (405), escape con `e()` en toda salida, cabeceras de seguridad |
| Pruebas en verde | `php verificar.php` → **33 de 33** |
| Bitácora de IA | `docs/BITACORA_IA.md` |

## Datos de demostración

5 categorías (poleras, pantalones, vestidos, abrigos, accesorios) y 7 prendas. Valor del inventario verificado a mano:

| Código | Precio | Stock | Subtotal |
|---|---:|---:|---:|
| POL-001 | 95,00 | 24 | 2.280,00 |
| POL-002 | 120,00 | 0 | 0,00 |
| PAN-010 | 180,00 | 15 | 2.700,00 |
| PAN-011 | 140,50 | 7 | 983,50 |
| VES-100 | 350,00 | 5 | 1.750,00 |
| ABR-200 | 260,00 | 9 | 2.340,00 |
| ACC-300 | 75,00 | 30 | 2.250,00 |
| **Total** | | | **Bs 12.303,50** |
