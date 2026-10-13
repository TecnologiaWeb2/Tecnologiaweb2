<?php
// src/validacion.php — Validación y saneamiento de formularios: NUNCA se confía en lo que envía el navegador.
// La validación protege la CALIDAD de los datos; la protección contra inyección SQL la dan las consultas preparadas.
declare(strict_types=1);

/** Marca como error los campos de texto que no son UTF-8 válido (romperían mb_* y la base de datos). */
function validar_codificacion(array &$entrada, array $campos, array &$errores): void
{
    foreach ($campos as $campo) {
        if (!mb_check_encoding(texto($entrada, $campo), 'UTF-8')) {
            $errores[$campo] = 'El texto tiene caracteres con una codificación inválida (se espera UTF-8).';
            $entrada[$campo] = '';
        }
    }
}

/**
 * Valida $entrada (normalmente $_POST, donde todo llega como texto) y, si todo está bien, construye el Producto.
 * @param array<string, Categoria> $categorias categorías válidas indexadas por id
 * @param array<int, Socio>        $socios     socios que se pueden elegir, indexados por id
 * @param ?string $codigoFijo al editar, el código no se puede cambiar y no cuenta como repetido
 * @param ?string $foto       nombre de la foto que tendrá el producto
 * @return array{producto: ?Producto, errores: array<string, string>, datos: array<string, mixed>}
 */
function validar_producto(
    array $entrada,
    ProductoRepositorio $productos,
    array $categorias,
    array $socios,
    ?string $codigoFijo = null,
    ?string $foto = null,
): array {
    $errores = [];
    validar_codificacion($entrada, ['codigo', 'nombre', 'categoria', 'color', 'descripcion'], $errores);
    $codigo = $codigoFijo ?? strtoupper(texto($entrada, 'codigo'));
    $nombre = preg_replace('/\s+/u', ' ', texto($entrada, 'nombre'));
    $idCategoria = texto($entrada, 'categoria');
    $idSocio = filter_var(texto($entrada, 'socio'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
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
        } elseif ($productos->existeCodigo($codigo)) {
            $errores['codigo'] = "Ya existe un producto con el código $codigo.";
        }
    }
    $largo = mb_strlen($nombre);
    if ($largo < 3 || $largo > 80) {
        $errores['nombre'] ??= 'El nombre debe tener entre 3 y 80 caracteres.';
    }
    $categoria = $categorias[$idCategoria] ?? null;
    if ($categoria === null) {
        $errores['categoria'] ??= 'Elige una categoría de la lista.';
    }
    $socio = $idSocio === false ? null : ($socios[$idSocio] ?? null);
    if ($socio === null) {
        $errores['socio'] = 'Elige la socia o el socio que confeccionó la prenda.';
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
        'codigo' => $codigo, 'nombre' => $nombre, 'categoria' => $idCategoria, 'socio' => texto($entrada, 'socio'),
        'precio' => texto($entrada, 'precio'), 'stock' => $stockTexto,
        'tallas' => array_map(fn(Talla $t): string => $t->value, $tallas), 'color' => $color, 'descripcion' => $descripcion,
    ];
    if ($errores !== []) {
        return ['producto' => null, 'errores' => $errores, 'datos' => $datos];
    }
    $producto = new Producto(
        codigo: $codigo,
        nombre: $nombre,
        categoria: $categoria,
        socio: $socio,
        precio: round((float) $precio, 2),
        stock: (int) $stock,
        tallas: $tallas,
        color: $color,
        descripcion: $descripcion,
        foto: $foto,
    );
    return ['producto' => $producto, 'errores' => [], 'datos' => $datos];
}

/**
 * Valida el formulario de socios.
 * @param ?int $id al editar, el id del socio (su propio CI no cuenta como repetido)
 * @return array{socio: ?Socio, errores: array<string, string>, datos: array<string, string>}
 */
function validar_socio(array $entrada, SocioRepositorio $socios, ?int $id = null, string $hoy = 'today'): array
{
    $errores = [];
    validar_codificacion($entrada, ['ci', 'nombre', 'telefono', 'comunidad', 'especialidad'], $errores);
    $ci = strtoupper(str_replace(' ', '', texto($entrada, 'ci')));
    $nombre = mb_convert_case(preg_replace('/\s+/u', ' ', texto($entrada, 'nombre')), MB_CASE_TITLE);
    $telefono = preg_replace('/[\s-]/', '', texto($entrada, 'telefono'));
    $comunidad = preg_replace('/\s+/u', ' ', texto($entrada, 'comunidad'));
    $especialidad = texto($entrada, 'especialidad');
    $fecha = texto($entrada, 'fecha_ingreso');
    $activo = texto($entrada, 'activo') === '1';

    if (preg_match(Socio::PATRON_CI, $ci) !== 1) {
        $errores['ci'] ??= 'El CI debe tener de 5 a 10 dígitos y, si corresponde, su complemento (ej. 6234598-1A).';
    } elseif ($socios->existeCi($ci, $id)) {
        $errores['ci'] = "Ya existe un socio con el CI $ci.";
    }
    $largo = mb_strlen($nombre);
    if ($largo < 5 || $largo > 80 || preg_match('/^[\p{L} .\'-]+$/u', $nombre) !== 1) {
        $errores['nombre'] ??= 'Escribe el nombre completo (5 a 80 letras, sin números).';
    }
    if (preg_match(Socio::PATRON_TELEFONO, $telefono) !== 1) {
        $errores['telefono'] ??= 'El celular debe tener 8 dígitos y empezar con 6 o 7.';
    }
    $largo = mb_strlen($comunidad);
    if ($largo < 3 || $largo > 60) {
        $errores['comunidad'] ??= 'Indica la comunidad (3 a 60 caracteres).';
    }
    if (!in_array($especialidad, Socio::ESPECIALIDADES, true)) {
        $errores['especialidad'] ??= 'Elige una especialidad de la lista.';
    }
    $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if ($fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha) {
        $errores['fecha_ingreso'] = 'Indica una fecha válida (aaaa-mm-dd).';
    } elseif ($fechaValida > new DateTimeImmutable($hoy) || $fecha < '1990-01-01') {
        $errores['fecha_ingreso'] = 'La fecha de ingreso no puede ser futura ni anterior a 1990.';
    }

    $datos = ['ci' => $ci, 'nombre' => $nombre, 'telefono' => $telefono, 'comunidad' => $comunidad,
        'especialidad' => $especialidad, 'fecha_ingreso' => $fecha, 'activo' => $activo ? '1' : ''];
    if ($errores !== []) {
        return ['socio' => null, 'errores' => $errores, 'datos' => $datos];
    }
    return ['socio' => new Socio(ci: $ci, nombre: $nombre, telefono: $telefono, comunidad: $comunidad,
        especialidad: $especialidad, fechaIngreso: $fecha, activo: $activo, id: $id), 'errores' => [], 'datos' => $datos];
}
