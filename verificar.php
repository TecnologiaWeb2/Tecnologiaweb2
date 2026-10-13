<?php
// verificar.php — Comprueba el Laboratorio 5 (POO + formularios + JSON + fotos). Uso: php verificar.php
// Trabaja sobre una COPIA de datos/ en una carpeta temporal: los datos reales no se tocan.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

// ---------- Preparar una carpeta de datos temporal ----------
$datos = sys_get_temp_dir() . '/tienda-pruebas-' . bin2hex(random_bytes(4));
mkdir("$datos/fotos", 0775, true);
copy(__DIR__ . '/datos/categorias.json', "$datos/categorias.json");
copy(__DIR__ . '/datos/productos.inicial.json', "$datos/productos.json");
putenv("TIENDA_DATOS=$datos");
require __DIR__ . '/src/arranque.php';

function fotos(): array { clearstatcache(); return glob(CARPETA_FOTOS . '/*.*'); }
function guardado(string $codigo): ?array
{
    foreach (json_decode((string) file_get_contents(ARCHIVO_PRODUCTOS), true) as $fila) {
        if ($fila['codigo'] === $codigo) {
            return $fila;
        }
    }
    return null;
}
function token(array $respuesta): string
{
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $respuesta['cuerpo'], $m);
    return $m[1] ?? '';
}
function borrar_carpeta(string $dir): void
{
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

$v = new Verificador();
echo "— Clases (POO) —" . PHP_EOL;

$catalogo = new Catalogo();
$poleras = $catalogo->categoria('poleras');
$v->revisar('Catalogo carga 5 categorías y 7 productos desde JSON como objetos', fn() =>
    count($catalogo->categorias()) === 5 && $catalogo->contar() === 7 && $catalogo->buscar('POL-001') instanceof Producto
    && $poleras instanceof Categoria && $catalogo->buscar('POL-001')->categoria === $poleras);
$v->revisar('Producto usa promoción de propiedades en el constructor (readonly)', function () {
    $params = (new ReflectionMethod(Producto::class, '__construct'))->getParameters();
    $promovidos = array_filter($params, fn(ReflectionParameter $p): bool => $p->isPromoted());
    return count($promovidos) === count($params) && (new ReflectionClass(Producto::class))->isReadOnly()
        && (new ReflectionClass(Categoria::class))->isReadOnly();
});
$v->revisar('Producto con parámetros nombrados y valores por defecto (stock 0, talla única, sin foto)', function () use ($poleras) {
    $p = new Producto(codigo: 'POL-900', nombre: 'Polera de prueba', categoria: $poleras, precio: 50.0);
    return $p->stock === 0 && $p->tallas === [Talla::UNICA] && $p->foto === null && !$p->disponible();
});
$v->revisar('El constructor rechaza precio ≤ 0, stock negativo y código inválido', function () use ($poleras) {
    $rechazos = 0;
    foreach ([['precio' => 0.0], ['stock' => -1], ['codigo' => 'polera']] as $malo) {
        try {
            new Producto(...$malo + ['codigo' => 'POL-901', 'nombre' => 'Prueba', 'categoria' => $poleras, 'precio' => 10.0]);
        } catch (InvalidArgumentException) {
            $rechazos++;
        }
    }
    return $rechazos === 3;
});
$v->revisar('con(...) devuelve una copia modificada sin alterar el original (inmutabilidad)', function () use ($catalogo) {
    $original = $catalogo->buscar('PAN-010');
    $copia = $original->con(precio: 199.9, stock: 1);
    return $copia !== $original && $copia->precio === 199.9 && $original->precio === 180.0 && $copia->nombre === $original->nombre;
});
$v->revisar('filtrar(): texto, categoría, talla, solo disponibles y orden por precio', function () use ($catalogo) {
    $jean = $catalogo->filtrar(texto: 'JEAN');
    $xxl = $catalogo->filtrar(talla: Talla::XXL);
    $baratos = $catalogo->filtrar(orden: 'precio-asc');
    $poleras = $catalogo->filtrar(categoria: 'poleras', soloDisponibles: true);
    return count($jean) === 2 && count($xxl) === 1 && $xxl[0]->codigo === 'ABR-200'
        && $baratos[0]->codigo === 'ACC-300' && end($baratos)->codigo === 'VES-100'
        && count($poleras) === 1 && $poleras[0]->codigo === 'POL-001';
});
$v->revisar('valorInventario() = Σ precio × stock (verificado a mano: Bs 12.303,50)', fn() =>
    $catalogo->valorInventario() === 12303.5 && bs($catalogo->valorInventario()) === 'Bs 12.303,50');
$v->revisar('agregar() rechaza un código repetido (DomainException)', function () use ($catalogo) {
    try {
        $catalogo->agregar($catalogo->buscar('POL-001'));
        return false;
    } catch (DomainException) {
        return true;
    }
});
$v->revisar('guardar() persiste en JSON y otro Catalogo lo vuelve a leer igual (ida y vuelta)', function () use ($catalogo, $poleras) {
    $catalogo->agregar(new Producto(codigo: 'POL-777', nombre: 'Polera ñandutí', categoria: $poleras, precio: 45.0,
        stock: 3, tallas: [Talla::S, Talla::M], color: 'Rojo'));
    $catalogo->guardar();
    $releido = (new Catalogo())->buscar('POL-777');
    $json = (string) file_get_contents(ARCHIVO_PRODUCTOS);
    $ok = $releido !== null && $releido->tallas === [Talla::S, Talla::M] && $releido->precio === 45.0
        && str_contains($json, '"precio": 45.0') && str_contains($json, 'ñandutí');
    $catalogo->eliminar('POL-777');
    $catalogo->guardar();
    return $ok && (new Catalogo())->contar() === 7;
});
$v->revisar('validar_producto() sanea (espacios, mayúsculas, coma decimal, etiquetas HTML)', function () use ($catalogo) {
    $r = validar_producto(['codigo' => ' ves-101 ', 'nombre' => '  Vestido   floreado ', 'categoria' => 'vestidos',
        'precio' => '1.250,50', 'stock' => '4', 'tallas' => ['M', 'S'], 'color' => 'rosa palo',
        'descripcion' => '<script>alert(1)</script>Lino'], $catalogo);
    $p = $r['producto'];
    return $r['errores'] === [] && $p->codigo === 'VES-101' && $p->nombre === 'Vestido floreado' && $p->precio === 1250.5
        && $p->tallas === [Talla::S, Talla::M] && $p->color === 'Rosa Palo' && $p->descripcion === 'alert(1)Lino';
});
$v->revisar('Precio con punto de miles: «1.250» = 1250, «45.50» = 45,5', function () use ($catalogo) {
    $precio = fn(string $texto): ?float => validar_producto(['codigo' => 'TST-01', 'nombre' => 'Prueba', 'categoria' => 'poleras',
        'precio' => $texto, 'stock' => '1', 'tallas' => ['M']], $catalogo)['producto']?->precio;
    return $precio('1.250') === 1250.0 && $precio('12.500') === 12500.0 && $precio('45.50') === 45.5 && $precio('1.250,75') === 1250.75;
});
$v->revisar('validar_producto() informa cada campo inválido', function () use ($catalogo) {
    $r = validar_producto(['codigo' => 'POL-001', 'nombre' => 'x', 'categoria' => 'zapatos', 'precio' => 'gratis',
        'stock' => '-2', 'tallas' => ['XXXL']], $catalogo);
    return $r['producto'] === null && array_keys($r['errores']) === ['codigo', 'nombre', 'categoria', 'precio', 'stock', 'tallas']
        && str_contains($r['errores']['codigo'], 'Ya existe');
});

echo PHP_EOL . "— Aplicación web (servidor embebido) —" . PHP_EOL;
$tmp = sys_get_temp_dir();
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents("$tmp/prenda.png", $pngBytes);
file_put_contents("$tmp/foto.jpg", "<?php echo 'hola'; ?>");
file_put_contents("$tmp/grande.png", $pngBytes . str_repeat("\0", 2_200_000));
$png = fn() => new CURLFile("$tmp/prenda.png", 'image/png', 'prenda.png');
$base = ['codigo' => 'VES-200', 'nombre' => 'Vestido de lino bordado', 'categoria' => 'vestidos', 'precio' => '289,90',
    'stock' => '6', 'tallas[0]' => 'S', 'tallas[1]' => 'M', 'color' => 'Verde', 'descripcion' => 'Lino con bordado chiquitano.'];

$v->servidor(__DIR__ . '/public', null, ['TIENDA_DATOS' => $datos]);

$inicio = $v->pedir('/');
$v->revisar('La tienda lista las 7 prendas con precio en formato boliviano y escapa el HTML', fn() =>
    $inicio['estado'] === 200 && substr_count($inicio['cuerpo'], 'class="tarjeta') === 7
    && str_contains($inicio['cuerpo'], 'Bs 140,50') && str_contains($inicio['cuerpo'], 'Bolso tejido de saó'));
$v->revisar('Filtros GET: ?q=jean&talla=XL → solo la chamarra; categoría inventada se ignora', function () use ($v) {
    $r = $v->pedir('/?q=jean&talla=XL');
    $falsa = $v->pedir('/?categoria=%3Cscript%3E');
    return substr_count($r['cuerpo'], 'class="tarjeta') === 1 && str_contains($r['cuerpo'], 'Chamarra de jean forrada')
        && substr_count($falsa['cuerpo'], 'class="tarjeta') === 7 && !str_contains($falsa['cuerpo'], '<script>');
});
$v->revisar('La búsqueda refleja el texto escapado (sin XSS)', fn() =>
    str_contains($v->pedir('/?q=' . urlencode('<b>x</b>'))['cuerpo'], '«&lt;b&gt;x&lt;/b&gt;»'));
$v->revisar('Ficha de producto 200 y código inexistente → 404', fn() =>
    str_contains($v->pedir('/producto.php?codigo=ves-100')['cuerpo'], 'Vestido tipoy bordado')
    && $v->pedir('/producto.php?codigo=NOP-000')['estado'] === 404);

$form = $v->pedir('/nuevo.php');
$csrf = token($form);
$v->revisar('El formulario es multipart/form-data e incluye token CSRF', fn() =>
    str_contains($form['cuerpo'], 'enctype="multipart/form-data"') && strlen($csrf) === 64);
$sinToken = $v->pedir('/nuevo.php', 'POST', $base);
$v->revisar('POST sin token CSRF → 403 y no se guarda nada', fn() => $sinToken['estado'] === 403 && guardado('VES-200') === null);

$malo = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'xx', 'nombre' => '<b>Ok</b> nombre', 'precio' => 'abc'] + $base);
$v->revisar('Datos inválidos → 422, mensajes por campo y valores anteriores escapados', fn() =>
    $malo['estado'] === 422 && str_contains($malo['cuerpo'], 'id="f-codigo-error"') && str_contains($malo['cuerpo'], 'id="f-precio-error"')
    && str_contains($malo['cuerpo'], 'value="&lt;b&gt;Ok&lt;/b&gt; nombre"') && str_contains($malo['cuerpo'], 'value="S" checked'));

$ok = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf] + $base + ['foto' => $png()]);
$fila = guardado('VES-200');
$v->revisar('Registro con foto PNG → 303 (PRG) y se guarda en JSON con tipos correctos', fn() =>
    $ok['estado'] === 303 && str_contains($ok['cabeceras'], 'Location: /admin.php?creado=VES-200') && $fila !== null
    && $fila['precio'] === 289.9 && $fila['stock'] === 6 && $fila['tallas'] === ['S', 'M']
    && preg_match('/^[a-f0-9]{24}\.png$/', (string) $fila['foto']) === 1);
$v->revisar('La foto se guarda fuera de public/ y la tienda la muestra con texto alternativo', fn() =>
    count(fotos()) === 1 && is_file(CARPETA_FOTOS . '/' . $fila['foto'])
    && str_contains($v->pedir('/')['cuerpo'], '<img src="/foto.php?f=' . $fila['foto'] . '" alt="Vestido de lino bordado"'));
$f = $v->pedir('/foto.php?f=' . $fila['foto']);
$v->revisar('foto.php entrega la imagen con su tipo real y nosniff', fn() =>
    $f['estado'] === 200 && str_contains($f['cabeceras'], 'Content-Type: image/png')
    && stripos($f['cabeceras'], 'X-Content-Type-Options: nosniff') !== false && str_starts_with($f['cuerpo'], "\x89PNG"));
$v->revisar('foto.php no permite salir de la carpeta (../)', fn() =>
    $v->pedir('/foto.php?f=../productos.json')['estado'] === 404 && $v->pedir('/foto.php?f=..%2F..%2Fsrc%2Fconfig.php')['estado'] === 404);
$falso = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-901'] + $base + ['foto' => new CURLFile("$tmp/foto.jpg", 'image/jpeg', 'foto.jpg')]);
$v->revisar('Un PHP disfrazado de foto.jpg se rechaza → 422', fn() =>
    $falso['estado'] === 422 && str_contains($falso['cuerpo'], 'no es una imagen') && count(fotos()) === 1 && guardado('ACC-901') === null);
$grande = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-902'] + $base + ['foto' => new CURLFile("$tmp/grande.png", 'image/png', 'grande.png')]);
$v->revisar('Una imagen de más de 2 MB se rechaza', fn() =>
    $grande['estado'] === 422 && str_contains($grande['cuerpo'], 'id="f-foto-error">La foto supera'));
$huerfana = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-903', 'precio' => '0'] + $base + ['foto' => $png()]);
$v->revisar('Si otro campo falla, la foto subida no queda huérfana', fn() => $huerfana['estado'] === 422 && count(fotos()) === 1);
$repetido = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf] + $base);
$v->revisar('Código repetido → 422 «Ya existe»', fn() => $repetido['estado'] === 422 && str_contains($repetido['cuerpo'], 'Ya existe un producto con el código VES-200'));

$ed = $v->pedir('/editar.php?codigo=VES-200', 'POST', ['csrf' => $csrf, 'precio' => '310', 'codigo' => 'HACK-1'] + $base);
$v->revisar('Editar → 303, cambia el precio, el código no se altera y la foto se conserva', fn() =>
    $ed['estado'] === 303 && guardado('VES-200')['precio'] === 310.0 && guardado('VES-200')['foto'] === $fila['foto'] && guardado('HACK-1') === null);
$v->pedir('/editar.php?codigo=VES-200', 'POST', ['csrf' => $csrf] + $base + ['foto' => $png()]);
$v->revisar('Una foto nueva reemplaza a la anterior (la vieja se borra)', fn() =>
    guardado('VES-200')['foto'] !== $fila['foto'] && count(fotos()) === 1 && !is_file(CARPETA_FOTOS . '/' . $fila['foto']));
$v->revisar('Gestión muestra el total de prendas y el valor del inventario', fn() =>
    str_contains($v->pedir('/admin.php')['cuerpo'], '8 prendas · valor del inventario: <strong>Bs 14.042,90</strong>'));

$v->revisar('Eliminar por GET no está permitido (405 + Allow: POST)', function () use ($v) {
    $r = $v->pedir('/eliminar.php?codigo=VES-200');
    return $r['estado'] === 405 && str_contains($r['cabeceras'], 'Allow: POST') && guardado('VES-200') !== null;
});
$v->revisar('Eliminar sin token CSRF → 403', fn() => $v->pedir('/eliminar.php', 'POST', ['codigo' => 'VES-200'])['estado'] === 403 && guardado('VES-200') !== null);
$el = $v->pedir('/eliminar.php', 'POST', ['csrf' => $csrf, 'codigo' => 'VES-200']);
$v->revisar('Eliminar por POST → 303, desaparece la prenda y su foto', fn() =>
    $el['estado'] === 303 && guardado('VES-200') === null && count(fotos()) === 0 && (new Catalogo())->contar() === 7);

$codigo = $v->fin();
borrar_carpeta($datos);
foreach (['prenda.png', 'foto.jpg', 'grande.png'] as $f) {
    @unlink("$tmp/$f");
}
exit($codigo);
