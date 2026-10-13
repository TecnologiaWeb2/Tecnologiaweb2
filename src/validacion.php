<?php
// src/validacion.php — Validación y saneamiento del formulario de prendas: NUNCA se confía en lo que envía el navegador.
declare(strict_types=1);

/**
 * Valida $entrada (normalmente $_POST, donde todo llega como texto) y, si todo está bien, construye el Producto.
 * @param ?string $codigoFijo al editar, el código no se puede cambiar y no cuenta como repetido
 * @param ?string $foto       nombre de la foto que tendrá el producto
 * @return array{producto: ?Producto, errores: array<string, string>, datos: array<string, mixed>}
 */
function validar_producto(array $entrada, Catalogo $catalogo, ?string $codigoFijo = null, ?string $foto = null): array
{
    $errores = [];
    // Texto que no es UTF-8 válido rompería json_encode y mb_*: se detecta antes de procesarlo
    foreach (['codigo', 'nombre', 'categoria', 'color', 'descripcion'] as $campo) {
        if (!mb_check_encoding(texto($entrada, $campo), 'UTF-8')) {
            $errores[$campo] = 'El texto tiene caracteres con una codificación inválida (se espera UTF-8).';
            $entrada[$campo] = '';
        }
    }
    $codigo = $codigoFijo ?? strtoupper(texto($entrada, 'codigo'));
    $nombre = preg_replace('/\s+/u', ' ', texto($entrada, 'nombre'));
    $idCategoria = texto($entrada, 'categoria');
    $color = mb_convert_case(texto($entrada, 'color'), MB_CASE_TITLE);
    $descripcion = strip_tags(texto($entrada, 'descripcion'));
    // En Bolivia se escribe 45,50: se acepta la coma decimal (y el punto de miles "1.250,00")
    $precioTexto = texto($entrada, 'precio');
    if (str_contains($precioTexto, ',')) {
        $precioTexto = str_replace(['.', ','], ['', '.'], $precioTexto);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $precioTexto) === 1) {
        $precioTexto = str_replace('.', '', $precioTexto);   // "1.250" es mil doscientos cincuenta, no 1,25
    }
    $stockTexto = texto($entrada, 'stock');
    $tallasTexto = is_array($entrada['tallas'] ?? null) ? $entrada['tallas'] : [];
    $tallas = Talla::lista($tallasTexto);

    if ($codigoFijo === null) {
        if (preg_match(Producto::PATRON_CODIGO, $codigo) !== 1) {
            $errores['codigo'] ??= 'El código debe tener el formato AAA-999 (ej. POL-001).';
        } elseif ($catalogo->existe($codigo)) {
            $errores['codigo'] = "Ya existe un producto con el código $codigo.";
        }
    }
    $largo = mb_strlen($nombre);
    if ($largo < 3 || $largo > 80) {
        $errores['nombre'] ??= 'El nombre debe tener entre 3 y 80 caracteres.';
    }
    $categoria = $catalogo->categoria($idCategoria);
    if ($categoria === null) {
        $errores['categoria'] ??= 'Elige una categoría de la lista.';
    }
    $precio = filter_var($precioTexto, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01, 'max_range' => 100000]]);
    if ($precio === false) {
        $errores['precio'] = 'El precio debe ser un número entre 0,01 y 100.000.';
    }
    $stock = filter_var($stockTexto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    if ($stock === false) {
        $errores['stock'] = 'El stock debe ser un número entero mayor o igual a 0.';
    }
    if ($tallas === [] || count($tallas) !== count(array_unique(array_map('strval', $tallasTexto)))) {
        $errores['tallas'] = 'Marca al menos una talla válida.';
    }
    if (mb_strlen($color) > 30) {
        $errores['color'] ??= 'El color admite hasta 30 caracteres.';
    }
    if (mb_strlen($descripcion) > 500) {
        $errores['descripcion'] ??= 'La descripción admite hasta 500 caracteres.';
    }

    $datos = [
        'codigo' => $codigo, 'nombre' => $nombre, 'categoria' => $idCategoria, 'precio' => texto($entrada, 'precio'),
        'stock' => $stockTexto, 'tallas' => array_map(fn(Talla $t): string => $t->value, $tallas),
        'color' => $color, 'descripcion' => $descripcion,
    ];
    if ($errores !== []) {
        return ['producto' => null, 'errores' => $errores, 'datos' => $datos];
    }
    $producto = new Producto(
        codigo: $codigo,
        nombre: $nombre,
        categoria: $categoria,
        precio: round((float) $precio, 2),
        stock: (int) $stock,
        tallas: $tallas,
        color: $color,
        descripcion: $descripcion,
        foto: $foto,
    );
    return ['producto' => $producto, 'errores' => [], 'datos' => $datos];
}
