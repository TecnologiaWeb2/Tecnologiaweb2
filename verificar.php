<?php
// verificar.php — Comprueba la Actividad 2 (MySQL + PDO + repositorios + inyección SQL). Uso: php verificar.php
// Requiere MySQL/MariaDB encendido. Usa una base de datos SEPARADA (manos_del_oriente_pruebas) y una carpeta
// de fotos temporal: los datos reales no se tocan. La base de pruebas se borra al terminar.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

// ---------- Preparar entorno aislado ----------
const BD_PRUEBAS = 'manos_del_oriente_pruebas';
$datos = sys_get_temp_dir() . '/tienda-pruebas-' . bin2hex(random_bytes(4));
mkdir("$datos/fotos", 0775, true);
putenv("TIENDA_DATOS=$datos");
putenv('TIENDA_BD_NOMBRE=' . BD_PRUEBAS);
require __DIR__ . '/src/arranque.php';
require __DIR__ . '/bd/instalar.php';
instalar_bd();
$pdo = Conexion::obtener();

function fotos(): array { clearstatcache(); return glob(CARPETA_FOTOS . '/*.*'); }
function fila(string $codigo): ?array
{
    $c = Conexion::obtener()->prepare('SELECT p.*, (SELECT GROUP_CONCAT(talla ORDER BY talla) FROM producto_tallas WHERE producto_id = p.id) AS tallas
        FROM productos p WHERE codigo = :c');
    $c->execute(['c' => $codigo]);
    return $c->fetch() ?: null;
}
function contar(string $tabla): int { return (int) Conexion::obtener()->query("SELECT COUNT(*) FROM $tabla")->fetchColumn(); }
function tablas_intactas(): bool { return contar('productos') > 0 && contar('socios') > 0 && contar('categorias') === 5; }
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
$productos = new ProductoRepositorio($pdo);
$socios = new SocioRepositorio($pdo);
$categorias = (new CategoriaRepositorio($pdo))->todas();

echo '— Base de datos (Laboratorio 8) —' . PHP_EOL;
$v->revisar('El script SQL crea 5 tablas InnoDB con 4 claves foráneas', function () use ($pdo) {
    $t = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'InnoDB'")->fetchColumn();
    $fk = $pdo->query('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')->fetchColumn();
    return (int) $t === 5 && (int) $fk === 4;
});
$v->revisar('Datos de ejemplo: 14 prendas, 6 socios, 5 categorías, 7 tallas, 34 tallas asignadas', fn() =>
    contar('productos') === 14 && contar('socios') === 6 && contar('categorias') === 5 && contar('tallas') === 7 && contar('producto_tallas') === 34);
$v->revisar('Las claves foráneas rechazan una prenda con un socio inexistente', function () use ($pdo) {
    try {
        $pdo->exec("INSERT INTO productos (codigo, nombre, categoria_id, socio_id, precio) VALUES ('FKX-01', 'Prueba', 'poleras', 999, 10)");
        return false;
    } catch (PDOException $e) {
        return $e->errorInfo[1] === 1452;
    }
});
$v->revisar('La conexión PDO usa excepciones y consultas preparadas reales (sin emulación)', fn() =>
    $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION && !$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)); // PHP 8.3 devuelve 0; 8.4, false
$v->revisar('La configuración de la conexión vive fuera de public/ (config/) y config.php no se sube a git', fn() =>
    is_file(__DIR__ . '/config/config.ejemplo.php') && !is_file(__DIR__ . '/public/config.php')
    && str_contains((string) file_get_contents(__DIR__ . '/.gitignore'), 'config/config.php'));

echo PHP_EOL . '— Modelo y repositorios (Laboratorio 9) —' . PHP_EOL;
$rosa = $socios->buscarPorId(1);
$v->revisar('Producto: promoción de propiedades readonly y valores por defecto', function () use ($categorias, $rosa) {
    $params = (new ReflectionMethod(Producto::class, '__construct'))->getParameters();
    $p = new Producto(codigo: 'POL-900', nombre: 'Polera de prueba', categoria: $categorias['poleras'], socio: $rosa, precio: 50.0);
    return count(array_filter($params, fn(ReflectionParameter $x): bool => $x->isPromoted())) === count($params)
        && (new ReflectionClass(Producto::class))->isReadOnly() && (new ReflectionClass(Socio::class))->isReadOnly()
        && $p->stock === 0 && $p->tallas === [Talla::UNICA] && $p->foto === null && $p->id === null;
});
$v->revisar('Los constructores rechazan datos inválidos (precio ≤ 0, CI y celular mal formados)', function () use ($categorias, $rosa) {
    $rechazos = 0;
    $intentos = [
        fn() => new Producto(codigo: 'POL-901', nombre: 'Prueba', categoria: $categorias['poleras'], socio: $rosa, precio: 0.0),
        fn() => new Socio(ci: '12-AB', nombre: 'Ana Pérez', telefono: '71234567', comunidad: 'Concepción', especialidad: 'Costura', fechaIngreso: '2020-01-01'),
        fn() => new Socio(ci: '1234567', nombre: 'Ana Pérez', telefono: '41234567', comunidad: 'Concepción', especialidad: 'Costura', fechaIngreso: '2020-01-01'),
    ];
    foreach ($intentos as $intento) {
        try {
            $intento();
        } catch (InvalidArgumentException) {
            $rechazos++;
        }
    }
    return $rechazos === 3;
});
$v->revisar('ProductoRepositorio::buscarPorCodigo() arma el objeto con su categoría, socio y tallas', function () use ($productos) {
    $p = $productos->buscarPorCodigo('ABR-200');
    return $p !== null && $p->precio === 260.0 && $p->categoria->nombre === 'Abrigos y chamarras'
        && $p->socio->nombre === 'Juana Pesoa Rojas' && $p->tallas === [Talla::M, Talla::L, Talla::XL, Talla::XXL];
});
$v->revisar('paginar(): texto, categoría, talla, solo disponibles y orden por precio', function () use ($productos) {
    $jean = $productos->paginar(texto: 'JEAN');
    $xxl = $productos->paginar(talla: Talla::XXL);
    $baratos = $productos->paginar(orden: 'precio-asc', porPagina: 20)->elementos;
    $poleras = $productos->paginar(categoria: 'poleras', soloDisponibles: true);
    return $jean->total === 2 && $xxl->total === 1 && $xxl->elementos[0]->codigo === 'ABR-200'
        && $baratos[0]->codigo === 'ACC-302' && end($baratos)->codigo === 'VES-100'
        && $poleras->total === 2 && array_map(fn(Producto $p): string => $p->codigo, $poleras->elementos) === ['POL-001', 'POL-003'];
});
$v->revisar('Paginación: 14 prendas de 5 en 5 → 3 páginas; la página 99 y la -1 se corrigen', function () use ($productos) {
    $p3 = $productos->paginar(pagina: 3, porPagina: 5);
    $p99 = $productos->paginar(pagina: 99, porPagina: 5);
    $pNeg = $productos->paginar(pagina: -1, porPagina: 5);
    return $p3->paginas() === 3 && count($p3->elementos) === 4 && $p3->desde() === 11 && $p3->hasta() === 14
        && $p99->pagina === 3 && $pNeg->pagina === 1 && count($pNeg->elementos) === 5;
});
$v->revisar('Inventario calculado por MySQL = verificado a mano (14 prendas, 157 u., Bs 19.538,50)', fn() =>
    $productos->resumenInventario() === ['prendas' => 14, 'unidades' => 157, 'valor' => 19538.5]
    && bs($productos->resumenInventario()['valor']) === 'Bs 19.538,50');
$v->revisar('crear() guarda la prenda y sus tallas; actualizar() reemplaza las tallas; eliminar() las borra en cascada', function () use ($productos, $categorias, $rosa) {
    $nuevo = $productos->crear(new Producto(codigo: 'TST-100', nombre: 'Polera ñandutí', categoria: $categorias['poleras'],
        socio: $rosa, precio: 45.0, stock: 3, tallas: [Talla::S, Talla::M], color: 'Rojo'));
    $creado = fila('TST-100');
    $productos->actualizar($nuevo->con(precio: 49.9, tallas: [Talla::L]));
    $editado = fila('TST-100');
    $productos->eliminar('TST-100');
    $huerfanas = (int) Conexion::obtener()->query("SELECT COUNT(*) FROM producto_tallas WHERE producto_id = {$nuevo->id}")->fetchColumn();
    return $nuevo->id > 14 && $creado['tallas'] === 'M,S' && $creado['nombre'] === 'Polera ñandutí'
        && $editado['precio'] === '49.90' && $editado['tallas'] === 'L' && fila('TST-100') === null && $huerfanas === 0;
});
$v->revisar('Transacción: si falla una parte (socio inexistente), no queda nada guardado (ROLLBACK)', function () use ($productos, $categorias) {
    $fantasma = new Socio(ci: '9999999', nombre: 'Socio Fantasma', telefono: '79999999', comunidad: 'Ninguna', especialidad: 'Costura',
        fechaIngreso: '2020-01-01', id: 999);
    try {
        $productos->crear(new Producto(codigo: 'TST-101', nombre: 'No debe existir', categoria: $categorias['poleras'], socio: $fantasma, precio: 10.0));
        return false;
    } catch (PDOException) {
        return fila('TST-101') === null && !Conexion::obtener()->inTransaction();
    }
});
$v->revisar('Un código repetido se rechaza con DomainException (UNIQUE en la base)', function () use ($productos) {
    try {
        $productos->crear($productos->buscarPorCodigo('POL-001'));
        return false;
    } catch (DomainException) {
        return contar('productos') === 14;
    }
});
$v->revisar('SocioRepositorio: búsqueda, filtro de estado y conteo de prendas con LEFT JOIN', function () use ($socios) {
    $concepcion = $socios->paginar(texto: 'Concep');
    $inactivos = $socios->paginar(activos: false);
    $pag2 = $socios->paginar(pagina: 2, porPagina: 5);
    return $concepcion->total === 2 && $inactivos->total === 1 && $inactivos->elementos[0]['productos'] === 0
        && $pag2->total === 6 && count($pag2->elementos) === 1
        && array_sum(array_column($socios->paginar(porPagina: 10)->elementos, 'productos')) === 14;
});
$v->revisar('SocioRepositorio: crear, actualizar y CI duplicado', function () use ($socios) {
    $ana = $socios->crear(new Socio(ci: '9876543', nombre: 'Ana Tomichá Rivero', telefono: '78887766', comunidad: 'San Miguel',
        especialidad: 'Tejido', fechaIngreso: '2024-04-01'));
    $socios->actualizar(new Socio(ci: '9876543', nombre: 'Ana Tomichá Rivero', telefono: '68887766', comunidad: 'San Miguel de Velasco',
        especialidad: 'Tejido', fechaIngreso: '2024-04-01', activo: false, id: $ana->id));
    $editada = $socios->buscarPorId($ana->id);
    try {
        $socios->crear(new Socio(ci: '9876543', nombre: 'Otra Persona', telefono: '71112222', comunidad: 'X', especialidad: 'Costura', fechaIngreso: '2024-01-01'));
        $duplicado = false;
    } catch (DomainException) {
        $duplicado = true;
    }
    $ok = $ana->id === 7 && $editada->telefono === '68887766' && !$editada->activo && $duplicado
        && $socios->existeCi('9876543') && !$socios->existeCi('9876543', excepto: $ana->id);
    $socios->eliminar($ana->id);
    return $ok && $socios->buscarPorId(7) === null;
});
$v->revisar('Un socio con prendas no se puede eliminar (FOREIGN KEY ... ON DELETE RESTRICT)', function () use ($socios) {
    try {
        $socios->eliminar(1);
        return false;
    } catch (DomainException $e) {
        return str_contains($e->getMessage(), 'tiene prendas') && $socios->buscarPorId(1) !== null;
    }
});
$v->revisar('validar_producto() sanea (espacios, mayúsculas, coma decimal, punto de miles, etiquetas HTML)', function () use ($productos, $categorias, $socios) {
    $r = validar_producto(['codigo' => ' ves-101x ', 'nombre' => '  Vestido   floreado ', 'categoria' => 'vestidos', 'socio' => '2',
        'precio' => '1.250', 'stock' => '4', 'tallas' => ['M', 'S'], 'color' => 'rosa palo',
        'descripcion' => '<script>alert(1)</script>Lino'], $productos, $categorias, $socios->activos());
    $p = $r['producto'];
    return $r['errores'] === [] && $p->codigo === 'VES-101X' && $p->nombre === 'Vestido floreado' && $p->precio === 1250.0
        && $p->socio->id === 2 && $p->tallas === [Talla::S, Talla::M] && $p->color === 'Rosa Palo' && $p->descripcion === 'alert(1)Lino';
});
$v->revisar('validar_producto() informa cada campo inválido (incluido un socio inactivo)', function () use ($productos, $categorias, $socios) {
    $r = validar_producto(['codigo' => 'POL-001', 'nombre' => 'x', 'categoria' => 'zapatos', 'socio' => '6', 'precio' => 'gratis',
        'stock' => '-2', 'tallas' => ['XXXL']], $productos, $categorias, $socios->activos());
    return $r['producto'] === null && str_contains($r['errores']['codigo'], 'Ya existe')
        && array_keys($r['errores']) === ['codigo', 'nombre', 'categoria', 'socio', 'precio', 'stock', 'tallas'];
});
$v->revisar('validar_socio() normaliza (CI, celular, nombre) y rechaza fecha futura y CI repetido', function () use ($socios) {
    $ok = validar_socio(['ci' => '7654321-1a', 'nombre' => '  maría   pesoa ', 'telefono' => '7123 4567', 'comunidad' => 'Concepción',
        'especialidad' => 'Bordado', 'fecha_ingreso' => '2023-05-10', 'activo' => '1'], $socios);
    $malo = validar_socio(['ci' => '4589231', 'nombre' => 'R2D2', 'telefono' => '123', 'comunidad' => 'X',
        'especialidad' => 'Pintura', 'fecha_ingreso' => '2099-01-01'], $socios);
    $s = $ok['socio'];
    return $s !== null && $s->ci === '7654321-1A' && $s->nombre === 'María Pesoa' && $s->telefono === '71234567' && $s->activo
        && array_keys($malo['errores']) === ['ci', 'nombre', 'telefono', 'comunidad', 'especialidad', 'fecha_ingreso']
        && str_contains($malo['errores']['ci'], 'Ya existe');
});

echo PHP_EOL . '— Intentos de inyección SQL contra los repositorios —' . PHP_EOL;
$v->revisar("buscarPorCodigo(\"' OR '1'='1\") no devuelve nada", fn() => $productos->buscarPorCodigo("' OR '1'='1") === null);
$v->revisar("paginar(texto: \"' OR 1=1 -- \") devuelve 0 prendas (no las 14)", fn() => $productos->paginar(texto: "' OR 1=1 -- ")->total === 0);
$v->revisar('Los comodines del usuario se buscan literalmente: «%» y «_» devuelven 0 prendas', fn() =>
    $productos->paginar(texto: '%')->total === 0 && $productos->paginar(texto: '_')->total === 0 && $socios->paginar(texto: '%')->total === 0);
$v->revisar('Un ORDER BY malicioso se ignora (lista blanca) y las tablas siguen intactas', function () use ($productos) {
    $r = $productos->paginar(orden: 'precio; DROP TABLE productos; --');
    return $r->total === 14 && $r->elementos[0]->nombre === 'Blusa de tocuyo manga corta' && tablas_intactas();
});
$v->revisar('Un nombre con SQL se guarda como texto literal, sin ejecutarse', function () use ($productos, $categorias, $rosa) {
    $productos->crear(new Producto(codigo: 'INY-001', nombre: "Robert'); DROP TABLE productos;--", categoria: $categorias['poleras'], socio: $rosa, precio: 10.0));
    $ok = fila('INY-001')['nombre'] === "Robert'); DROP TABLE productos;--" && tablas_intactas() && contar('productos') === 15;
    $productos->eliminar('INY-001');
    return $ok;
});
$v->revisar("existeCi(\"' OR ''='\") es falso (el CI malicioso no coincide con nadie)", fn() => !$socios->existeCi("' OR ''='"));
$v->revisar('Ningún archivo de src/ concatena variables de PHP dentro de un SQL con datos del usuario', function () {
    foreach (glob(__DIR__ . '/src/Repositorio/*.php') as $archivo) {
        // Busca prepare("...$variable...") o query("...$variable...") con variables que NO sean las listas blancas permitidas
        if (preg_match('/->(prepare|query|exec)\(\s*"[^"]*\$(?!donde\b|ordenSql\b)\w+/', (string) file_get_contents($archivo)) === 1) {
            return false;
        }
    }
    return true;
});

// ---------- Aplicación web ----------
echo PHP_EOL . '— Aplicación web (servidor embebido) —' . PHP_EOL;
instalar_bd();                                       // la parte web empieza con los datos de demostración intactos
$tmp = sys_get_temp_dir();
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
file_put_contents("$tmp/prenda.png", $pngBytes);
file_put_contents("$tmp/foto.jpg", "<?php echo 'hola'; ?>");
file_put_contents("$tmp/grande.png", $pngBytes . str_repeat("\0", 2_200_000));
$png = fn() => new CURLFile("$tmp/prenda.png", 'image/png', 'prenda.png');
$base = ['codigo' => 'VES-200', 'nombre' => 'Vestido de lino bordado', 'categoria' => 'vestidos', 'socio' => '1', 'precio' => '289,90',
    'stock' => '6', 'tallas[0]' => 'S', 'tallas[1]' => 'M', 'color' => 'Verde', 'descripcion' => 'Lino con bordado chiquitano.'];
$socioBase = ['ci' => '5556667', 'nombre' => 'Teresa Supepí Chuvé', 'telefono' => '77712345', 'comunidad' => 'Lomerío',
    'especialidad' => 'Bordado', 'fecha_ingreso' => '2025-03-01', 'activo' => '1'];

$v->servidor(__DIR__ . '/public', null, ['TIENDA_DATOS' => $datos, 'TIENDA_BD_NOMBRE' => BD_PRUEBAS]);

$inicio = $v->pedir('/');
$v->revisar('La tienda pagina de 8 en 8: página 1 con 8 prendas, página 2 con 6', fn() =>
    $inicio['estado'] === 200 && substr_count($inicio['cuerpo'], 'class="tarjeta') === 8 && str_contains($inicio['cuerpo'], 'aria-label="Paginación"')
    && substr_count($v->pedir('/?pagina=2')['cuerpo'], 'class="tarjeta') === 6 && str_contains($inicio['cuerpo'], 'Bs 140,50'));
$v->revisar('Filtros GET: ?q=jean&talla=XL → solo la chamarra; una categoría inventada se ignora', function () use ($v) {
    $r = $v->pedir('/?q=jean&talla=XL');
    $falsa = $v->pedir('/?categoria=%3Cscript%3E');
    return substr_count($r['cuerpo'], 'class="tarjeta') === 1 && str_contains($r['cuerpo'], 'Chamarra de jean forrada')
        && str_contains($falsa['cuerpo'], '14 prendas') && !str_contains($falsa['cuerpo'], '<script>');
});
$v->revisar('La búsqueda refleja el texto escapado (sin XSS)', fn() =>
    str_contains($v->pedir('/?q=' . urlencode('<b>x</b>'))['cuerpo'], '«&lt;b&gt;x&lt;/b&gt;»'));
$v->revisar('Ficha de producto con su socio y código inexistente → 404', fn() =>
    str_contains($v->pedir('/producto.php?codigo=ves-100')['cuerpo'], 'Rosa Chuvé Supepí (Concepción)')
    && $v->pedir('/producto.php?codigo=NOP-000')['estado'] === 404);

$form = $v->pedir('/nuevo.php');
$csrf = token($form);
$v->revisar('El formulario de prendas es multipart, tiene token CSRF y lista solo socios activos', fn() =>
    str_contains($form['cuerpo'], 'enctype="multipart/form-data"') && strlen($csrf) === 64
    && str_contains($form['cuerpo'], 'Rosa Chuvé Supepí') && !str_contains($form['cuerpo'], 'Carmen Parapaino'));
$v->revisar('POST sin token CSRF → 403 y no se guarda nada', fn() => $v->pedir('/nuevo.php', 'POST', $base)['estado'] === 403 && fila('VES-200') === null);
$malo = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'xx', 'nombre' => '<b>Ok</b> nombre', 'precio' => 'abc', 'socio' => '6'] + $base);
$v->revisar('Datos inválidos → 422, mensajes por campo (socio inactivo incluido) y valores escapados', fn() =>
    $malo['estado'] === 422 && str_contains($malo['cuerpo'], 'id="f-codigo-error"') && str_contains($malo['cuerpo'], 'id="f-socio-error"')
    && str_contains($malo['cuerpo'], 'value="&lt;b&gt;Ok&lt;/b&gt; nombre"') && str_contains($malo['cuerpo'], 'value="S" checked'));
$ok = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf] + $base + ['foto' => $png()]);
$f = fila('VES-200');
$v->revisar('Registro con foto PNG → 303 (PRG) y se guarda en MySQL con sus tallas', fn() =>
    $ok['estado'] === 303 && str_contains($ok['cabeceras'], 'Location: /admin.php?creado=VES-200') && $f !== null
    && $f['precio'] === '289.90' && (int) $f['stock'] === 6 && (int) $f['socio_id'] === 1 && $f['tallas'] === 'M,S'
    && preg_match('/^[a-f0-9]{24}\.png$/', (string) $f['foto']) === 1);
$v->revisar('La foto se guarda fuera de public/ y la tienda la muestra con texto alternativo', fn() =>
    count(fotos()) === 1 && is_file(CARPETA_FOTOS . '/' . $f['foto'])
    && str_contains($v->pedir('/?q=lino+bordado')['cuerpo'], '<img src="/foto.php?f=' . $f['foto'] . '" alt="Vestido de lino bordado"'));
$foto = $v->pedir('/foto.php?f=' . $f['foto']);
$v->revisar('foto.php entrega la imagen con su tipo real y nosniff; no permite salir de la carpeta (../)', fn() =>
    $foto['estado'] === 200 && str_contains($foto['cabeceras'], 'Content-Type: image/png') && str_starts_with($foto['cuerpo'], "\x89PNG")
    && stripos($foto['cabeceras'], 'X-Content-Type-Options: nosniff') !== false
    && $v->pedir('/foto.php?f=../../config/config.ejemplo.php')['estado'] === 404);
$falso = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-901'] + $base + ['foto' => new CURLFile("$tmp/foto.jpg", 'image/jpeg', 'foto.jpg')]);
$grande = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-902'] + $base + ['foto' => new CURLFile("$tmp/grande.png", 'image/png', 'grande.png')]);
$huerfana = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-903', 'precio' => '0'] + $base + ['foto' => $png()]);
$v->revisar('Se rechazan un PHP disfrazado de JPG y una imagen de más de 2 MB; si otro campo falla, no quedan fotos huérfanas', fn() =>
    $falso['estado'] === 422 && str_contains($falso['cuerpo'], 'no es una imagen') && $grande['estado'] === 422
    && str_contains($grande['cuerpo'], 'id="f-foto-error">La foto supera') && $huerfana['estado'] === 422
    && count(fotos()) === 1 && fila('ACC-901') === null);
$v->revisar('Código repetido → 422 «Ya existe»', fn() =>
    str_contains($v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf] + $base)['cuerpo'], 'Ya existe un producto con el código VES-200'));

$ed = $v->pedir('/editar.php?codigo=VES-200', 'POST', ['csrf' => $csrf, 'precio' => '310', 'codigo' => 'HACK-1', 'tallas[0]' => 'L', 'tallas[1]' => 'XL'] + $base);
$v->revisar('Editar → 303: cambian precio y tallas, el código no se altera y la foto se conserva', fn() =>
    $ed['estado'] === 303 && fila('VES-200')['precio'] === '310.00' && fila('VES-200')['tallas'] === 'L,XL'
    && fila('VES-200')['foto'] === $f['foto'] && fila('HACK-1') === null);
$v->pedir('/editar.php?codigo=VES-200', 'POST', ['csrf' => $csrf] + $base + ['foto' => $png()]);
$v->revisar('Una foto nueva reemplaza a la anterior (la vieja se borra)', fn() =>
    fila('VES-200')['foto'] !== $f['foto'] && count(fotos()) === 1 && !is_file(CARPETA_FOTOS . '/' . $f['foto']));
$v->revisar('Gestión muestra el resumen verificado a mano: 15 prendas · 163 unidades · Bs 21.277,90', fn() =>
    str_contains($v->pedir('/admin.php')['cuerpo'], '15 prendas · 163 unidades · valor del inventario: <strong>Bs 21.277,90</strong>'));
$v->revisar('Gestión pagina de 10 en 10 y la búsqueda filtra', fn() =>
    substr_count($v->pedir('/admin.php')['cuerpo'], '<tr') === 11 && substr_count($v->pedir('/admin.php?pagina=2')['cuerpo'], '<tr') === 6
    && str_contains($v->pedir('/admin.php?q=' . urlencode('saó'))['cuerpo'], '2 prendas para «saó»'));

$v->revisar('Eliminar prenda: GET → 405, sin token → 403, POST → 303 y se borran la fila, sus tallas y su foto', function () use ($v, $csrf) {
    $get = $v->pedir('/eliminar.php?codigo=VES-200');
    $sin = $v->pedir('/eliminar.php', 'POST', ['codigo' => 'VES-200']);
    $id = (int) fila('VES-200')['id'];
    $ok = $v->pedir('/eliminar.php', 'POST', ['csrf' => $csrf, 'codigo' => 'VES-200']);
    $tallas = (int) Conexion::obtener()->query("SELECT COUNT(*) FROM producto_tallas WHERE producto_id = $id")->fetchColumn();
    return $get['estado'] === 405 && str_contains($get['cabeceras'], 'Allow: POST') && $sin['estado'] === 403
        && $ok['estado'] === 303 && fila('VES-200') === null && $tallas === 0 && count(fotos()) === 0;
});

echo PHP_EOL . '— CRUD de socios (web) —' . PHP_EOL;
$lista = $v->pedir('/socios.php');
$v->revisar('Socios: listado paginado de 5 en 5 con cantidad de prendas; el botón eliminar se desactiva si tiene prendas', fn() =>
    $lista['estado'] === 200 && substr_count($lista['cuerpo'], '<tr') === 6 && substr_count($v->pedir('/socios.php?pagina=2')['cuerpo'], '<tr') === 2
    && substr_count($lista['cuerpo'], 'disabled title="Tiene prendas registradas"') === 5);
$nuevoSocio = $v->pedir('/socio_form.php', 'POST', ['csrf' => $csrf] + $socioBase);
$idTeresa = (int) Conexion::obtener()->query("SELECT id FROM socios WHERE ci = '5556667'")->fetchColumn();
$v->revisar('Registrar socio → 303 y queda en la base', fn() => $nuevoSocio['estado'] === 303 && $idTeresa === 7);
$dup = $v->pedir('/socio_form.php', 'POST', ['csrf' => $csrf] + $socioBase);
$v->revisar('CI repetido o datos inválidos → 422 con mensajes', fn() =>
    $dup['estado'] === 422 && str_contains($dup['cuerpo'], 'Ya existe un socio con el CI 5556667')
    && str_contains($v->pedir('/socio_form.php', 'POST', ['csrf' => $csrf, 'telefono' => '12'] + $socioBase)['cuerpo'], 'id="f-telefono-error"'));
$v->revisar('Editar socio → 303 y se actualiza', function () use ($v, $csrf, $socioBase, $idTeresa) {
    $r = $v->pedir("/socio_form.php?id=$idTeresa", 'POST', ['csrf' => $csrf, 'comunidad' => 'Lomerío Norte'] + $socioBase);
    return $r['estado'] === 303 && (new SocioRepositorio(Conexion::obtener()))->buscarPorId($idTeresa)->comunidad === 'Lomerío Norte';
});
$v->revisar('Eliminar socio con prendas → se impide y se avisa; sin prendas → se elimina', function () use ($v, $csrf, $idTeresa) {
    $conPrendas = $v->pedir('/socio_eliminar.php', 'POST', ['csrf' => $csrf, 'id' => '1']);
    $sinPrendas = $v->pedir('/socio_eliminar.php', 'POST', ['csrf' => $csrf, 'id' => (string) $idTeresa]);
    $repo = new SocioRepositorio(Conexion::obtener());
    return str_contains($conPrendas['cabeceras'], 'error=tiene-prendas') && $repo->buscarPorId(1) !== null
        && $sinPrendas['estado'] === 303 && $repo->buscarPorId($idTeresa) === null
        && str_contains($v->pedir('/socios.php?error=tiene-prendas')['cuerpo'], 'No se puede eliminar');
});
$v->revisar('Eliminar socio exige POST y token CSRF', fn() =>
    $v->pedir('/socio_eliminar.php?id=6')['estado'] === 405 && $v->pedir('/socio_eliminar.php', 'POST', ['id' => '6'])['estado'] === 403
    && (new SocioRepositorio(Conexion::obtener()))->buscarPorId(6) !== null);

echo PHP_EOL . '— Intentos de inyección SQL por la web —' . PHP_EOL;
$v->revisar("Tienda: ?q=' OR '1'='1 → 0 prendas, sin error SQL", function () use ($v) {
    $r = $v->pedir('/?q=' . urlencode("' OR '1'='1"));
    return $r['estado'] === 200 && str_contains($r['cuerpo'], '0 prendas') && stripos($r['cuerpo'], 'SQLSTATE') === false;
});
$v->revisar("Ficha: ?codigo=POL-001' OR '1'='1 → 404 (no muestra otra prenda)", fn() =>
    $v->pedir('/producto.php?codigo=' . urlencode("POL-001' OR '1'='1"))['estado'] === 404);
$v->revisar('Tienda: ?orden=precio;DROP TABLE productos y ?pagina=1 OR 1=1 → 200 y las tablas siguen intactas', function () use ($v) {
    $a = $v->pedir('/?orden=' . urlencode('precio; DROP TABLE productos; --'));
    $b = $v->pedir('/admin.php?pagina=' . urlencode('1 OR 1=1'));
    return $a['estado'] === 200 && $b['estado'] === 200 && tablas_intactas() && contar('productos') === 14;
});
$v->revisar("Socios: ?q=' UNION SELECT ci, ... → 0 socios", fn() =>
    str_contains($v->pedir('/socios.php?q=' . urlencode("' UNION SELECT 1,2,3,4,5,6,7,8,9,10 -- "))['cuerpo'], '0 socios'));
$v->revisar('Formulario: un nombre con SQL se guarda literal y se muestra escapado', function () use ($v, $csrf, $base) {
    $nombre = "Polera'); DROP TABLE socios;-- <script>";
    $r = $v->pedir('/nuevo.php', 'POST', ['csrf' => $csrf, 'codigo' => 'INY-002', 'nombre' => $nombre] + $base);
    return $r['estado'] === 303 && fila('INY-002')['nombre'] === $nombre && tablas_intactas()
        && str_contains($v->pedir('/admin.php?q=INY')['cuerpo'], 'Polera&#039;); DROP TABLE socios;-- &lt;script&gt;');
});

echo PHP_EOL . '— Configuración protegida y errores —' . PHP_EOL;
$v->revisar('El código, la configuración y el SQL no se pueden descargar por URL (solo public/ es accesible)', function () use ($v) {
    foreach (['/../config/config.ejemplo.php', '/config/config.ejemplo.php', '/../src/Conexion.php', '/../bd/esquema.sql'] as $ruta) {
        $cuerpo = $v->pedir($ruta)['cuerpo'];
        if (str_contains($cuerpo, "'clave'") || str_contains($cuerpo, '<?php') || str_contains($cuerpo, 'CREATE TABLE')) {
            return false;
        }
    }
    return true;
});
$v->servidor(__DIR__ . '/public', null, ['TIENDA_DATOS' => $datos, 'TIENDA_BD_NOMBRE' => 'base_que_no_existe']);
$caida = $v->pedir('/');
$v->revisar('Si la base de datos no responde: 503 con un mensaje genérico (sin SQLSTATE, rutas ni credenciales)', fn() =>
    $caida['estado'] === 503 && str_contains($caida['cuerpo'], 'ocurrió un problema')
    && !str_contains($caida['cuerpo'], 'SQLSTATE') && !str_contains($caida['cuerpo'], 'root') && !str_contains($caida['cuerpo'], '.php'));

$codigo = $v->fin();
$pdo->exec('DROP DATABASE IF EXISTS ' . BD_PRUEBAS);
borrar_carpeta($datos);
foreach (['prenda.png', 'foto.jpg', 'grande.png'] as $archivo) {
    @unlink("$tmp/$archivo");
}
exit($codigo);
