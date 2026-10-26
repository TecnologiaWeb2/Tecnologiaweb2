<?php
// pruebas/actividad3.php — Actividad 3: Laboratorio 10 (usuarios, sesiones, roles, CSRF) y 11 (ventas, acopios, reportes).
// Lo incluye verificar.php: usa su $v (Verificador con el servidor encendido) y sus funciones de ayuda.
// Las cifras esperadas salen de bd/08-demo.sql y están calculadas a mano en docs/proyecto-final/MODELO_DATOS.md.
declare(strict_types=1);

instalar_bd();                                           // datos de demostración intactos
$pdo = Conexion::obtener();
const CLAVE_ADMIN = 'Manos-Admin-2026!';
const CLAVE_OPERADOR = 'Manos-Ventas-2026!';
const CLAVE_SOCIA = 'Manos-Socia-2026!';

echo PHP_EOL . '— Actividad 3 · Laboratorio 10: usuarios, sesiones, roles y CSRF —' . PHP_EOL;
$v->revisar('Las contraseñas se guardan con password_hash (bcrypt), nunca en texto ni con md5/sha1', function () use ($pdo) {
    $hashes = $pdo->query('SELECT clave_hash FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    $codigo = implode('', array_map('file_get_contents', [...glob(__DIR__ . '/../src/*.php'), ...glob(__DIR__ . '/../public/*.php')]));
    return count($hashes) === 4 && array_filter($hashes, fn(string $h): bool => !str_starts_with($h, '$2y$')) === []
        && !in_array(CLAVE_ADMIN, $hashes, true) && preg_match('/\b(md5|sha1)\s*\(/i', $codigo) !== 1;
});
$v->revisar('La base exige que un usuario «socio» esté vinculado a su ficha (CHECK)', function () use ($pdo) {
    try {
        $pdo->exec("INSERT INTO usuarios (correo, nombre, clave_hash, rol) VALUES ('x@x.bo', 'Sin ficha', 'x', 'socio')");
        return false;
    } catch (PDOException) {
        return true;
    }
});
$v->olvidarCookies();
$v->revisar('Sin sesión, las 12 páginas internas redirigen a /entrar.php (y no muestran datos)', function () use ($v) {
    foreach (['/admin.php', '/nuevo.php', '/socios.php', '/socio_form.php', '/ventas.php', '/reportes.php', '/reportes.csv.php',
        '/pedidos.php', '/importar.php', '/liquidaciones.php', '/usuarios.php', '/tokens.php'] as $ruta) {
        $r = $v->pedir($ruta);
        if ($r['estado'] !== 303 || !str_contains((string) cabecera($r, 'Location'), '/entrar.php') || str_contains($r['cuerpo'], 'Bs ')) {
            return false;
        }
    }
    return true;
});
$form = $v->pedir('/entrar.php');
$v->revisar('Iniciar sesión sin token CSRF → 403', fn() =>
    $v->pedir('/entrar.php', 'POST', ['correo' => 'admin@manosdeloriente.bo', 'clave' => CLAVE_ADMIN])['estado'] === 403);
$v->revisar('Contraseña incorrecta y correo inexistente → 401 con el MISMO mensaje (no revela qué correos existen)', function () use ($v, $form) {
    $mala = $v->pedir('/entrar.php', 'POST', ['csrf' => token($form), 'correo' => 'admin@manosdeloriente.bo', 'clave' => 'otra-clave-1']);
    $nadie = $v->pedir('/entrar.php', 'POST', ['csrf' => token($form), 'correo' => 'nadie@manosdeloriente.bo', 'clave' => 'otra-clave-1']);
    return $mala['estado'] === 401 && $nadie['estado'] === 401 && str_contains($mala['cuerpo'], 'Correo o contraseña incorrectos.')
        && str_contains($nadie['cuerpo'], 'Correo o contraseña incorrectos.');
});
$cookieAntes = (string) file_get_contents((fn() => $this->galletas)->call($v));
$ok = $v->pedir('/entrar.php', 'POST', ['csrf' => token($form), 'correo' => 'ADMIN@manosdeloriente.bo ', 'clave' => CLAVE_ADMIN]);
$v->revisar('Login correcto → 303 al panel; cookie MANOS_SESION con HttpOnly y SameSite=Lax; el id de sesión cambia', function () use ($ok, $cookieAntes, $v) {
    preg_match('/MANOS_SESION\t(\S+)/', $cookieAntes, $antes);
    preg_match('/MANOS_SESION\t(\S+)/', (string) file_get_contents((fn() => $this->galletas)->call($v)), $despues);
    $cookie = (string) cabecera($ok, 'Set-Cookie');
    return $ok['estado'] === 303 && cabecera($ok, 'Location') === '/admin.php' && str_contains($cookie, 'MANOS_SESION=')
        && stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false
        && isset($antes[1], $despues[1]) && $antes[1] !== $despues[1];
});
$v->revisar('Todas las páginas responden con cabeceras de seguridad (CSP, X-Frame-Options, nosniff, Referrer-Policy)', function () use ($v) {
    foreach (['/', '/admin.php', '/api/v1/productos'] as $ruta) {
        $r = $v->pedir($ruta);
        if (!str_contains((string) cabecera($r, 'Content-Security-Policy'), "script-src 'self'") || cabecera($r, 'X-Frame-Options') !== 'DENY'
            || cabecera($r, 'X-Content-Type-Options') !== 'nosniff' || cabecera($r, 'Referrer-Policy') === null) {
            return false;
        }
    }
    return true;
});
$v->revisar('Cada formulario POST de las páginas internas lleva su token CSRF', function () use ($v) {
    foreach (['/admin.php', '/socios.php', '/ventas.php', '/pedidos.php', '/importar.php', '/liquidaciones.php', '/usuarios.php',
        '/tokens.php', '/cuenta.php', '/nuevo.php', '/socio_form.php'] as $ruta) {
        $html = $v->pedir($ruta)['cuerpo'];
        preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/si', $html, $formularios);
        if ($formularios[0] === []) {
            return false;
        }
        foreach ($formularios[1] as $contenido) {
            if (preg_match('/name="csrf" value="[a-f0-9]{64}"/', $contenido) !== 1) {
                return false;
            }
        }
    }
    return true;
});
$v->revisar('«volver» solo acepta rutas internas: ?volver=//sitio-malo.com se ignora', function () use ($v) {
    $v->olvidarCookies();
    $f = $v->pedir('/entrar.php?volver=' . urlencode('//sitio-malo.com/robar'));
    $r = $v->pedir('/entrar.php', 'POST', ['csrf' => token($f), 'correo' => 'admin@manosdeloriente.bo', 'clave' => CLAVE_ADMIN, 'volver' => '//sitio-malo.com/robar']);
    $bueno = entrar($v, 'admin@manosdeloriente.bo', CLAVE_ADMIN);
    return $r['estado'] === 303 && cabecera($r, 'Location') === '/admin.php' && $bueno['estado'] === 303;
});
$v->revisar('Cerrar sesión: GET → 405; POST con CSRF → 303 y la sesión deja de valer', function () use ($v) {
    $get = $v->pedir('/salir.php');
    $post = $v->pedir('/salir.php', 'POST', ['csrf' => token($v->pedir('/admin.php'))]);
    return $get['estado'] === 405 && $post['estado'] === 303 && $v->pedir('/admin.php')['estado'] === 303;
});
$v->revisar('5 contraseñas incorrectas seguidas bloquean la cuenta 15 minutos (ni la correcta entra)', function () use ($v, $pdo) {
    for ($i = 0; $i < 5; $i++) {
        entrar($v, 'elena@manosdeloriente.bo', 'no-es-la-clave-' . $i);
    }
    $r = entrar($v, 'elena@manosdeloriente.bo', CLAVE_SOCIA);
    $bloqueada = $pdo->query("SELECT bloqueado_hasta > NOW() FROM usuarios WHERE correo = 'elena@manosdeloriente.bo'")->fetchColumn();
    $pdo->exec("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE correo = 'elena@manosdeloriente.bo'");
    return $r['estado'] === 401 && str_contains($r['cuerpo'], 'Demasiados intentos') && (int) $bloqueada === 1
        && entrar($v, 'elena@manosdeloriente.bo', CLAVE_SOCIA)['estado'] === 303;
});
$v->revisar('Rol operador: vende y gestiona prendas, pero NO administra usuarios ni tokens ni elimina prendas (403)', function () use ($v) {
    entrar($v, 'operador@manosdeloriente.bo', CLAVE_OPERADOR);
    $csrf = token($v->pedir('/ventas.php'));
    return $v->pedir('/ventas.php')['estado'] === 200 && $v->pedir('/admin.php')['estado'] === 200
        && $v->pedir('/usuarios.php')['estado'] === 403 && $v->pedir('/tokens.php')['estado'] === 403
        && $v->pedir('/eliminar.php', 'POST', ['csrf' => $csrf, 'codigo' => 'ACC-302'])['estado'] === 403 && fila('ACC-302') !== null
        && !str_contains($v->pedir('/admin.php')['cuerpo'], 'action="/eliminar.php"');
});
$v->revisar('Rol socia: entra directo a SU liquidación; no ve prendas, ventas ni la liquidación de otra socia', function () use ($v) {
    $r = entrar($v, 'rosa@manosdeloriente.bo', CLAVE_SOCIA);
    $propia = $v->pedir('/liquidaciones.php');
    $ajena = $v->pedir('/liquidaciones.php?socio=2');
    return cabecera($r, 'Location') === '/liquidaciones.php' && str_contains($propia['cuerpo'], 'Rosa Chuvé Supepí')
        && !str_contains($propia['cuerpo'], 'Elena Masabí') && !str_contains($ajena['cuerpo'], 'Elena Masabí')
        && $v->pedir('/admin.php')['estado'] === 403 && $v->pedir('/ventas.php')['estado'] === 403 && $v->pedir('/reportes.php')['estado'] === 403;
});
$v->revisar('Mi cuenta: la contraseña actual errónea o una nueva débil → 422; una válida se guarda y sirve para entrar', function () use ($v, $pdo) {
    $csrf = token($v->pedir('/cuenta.php'));
    $mala = $v->pedir('/cuenta.php', 'POST', ['csrf' => $csrf, 'actual' => 'otra', 'nueva' => 'Telar-Rosa-2027', 'repetir' => 'Telar-Rosa-2027']);
    $debil = $v->pedir('/cuenta.php', 'POST', ['csrf' => $csrf, 'actual' => CLAVE_SOCIA, 'nueva' => 'corta', 'repetir' => 'corta']);
    $buena = $v->pedir('/cuenta.php', 'POST', ['csrf' => $csrf, 'actual' => CLAVE_SOCIA, 'nueva' => 'Telar-Rosa-2027', 'repetir' => 'Telar-Rosa-2027']);
    $entra = entrar($v, 'rosa@manosdeloriente.bo', 'Telar-Rosa-2027')['estado'] === 303;
    $vieja = entrar($v, 'rosa@manosdeloriente.bo', CLAVE_SOCIA)['estado'] === 401;
    $pdo->prepare("UPDATE usuarios SET clave_hash = :h WHERE correo = 'rosa@manosdeloriente.bo'")->execute(['h' => password_hash(CLAVE_SOCIA, PASSWORD_DEFAULT)]);
    return $mala['estado'] === 422 && $debil['estado'] === 422 && $buena['estado'] === 303 && $entra && $vieja;
});
$v->revisar('Administración crea usuarios (correo único, rol socia exige ficha) y no puede cambiar su propio rol', function () use ($v, $pdo) {
    entrar($v, 'admin@manosdeloriente.bo', CLAVE_ADMIN);
    $csrf = token($v->pedir('/usuarios.php'));
    $alta = ['csrf' => $csrf, 'accion' => 'crear', 'nombre' => 'Juana Pesoa Rojas', 'correo' => 'juana@manosdeloriente.bo', 'clave' => 'Costura-2026-JP'];
    $sinFicha = $v->pedir('/usuarios.php', 'POST', $alta + ['rol' => 'socio', 'socio_id' => '']);
    $ok = $v->pedir('/usuarios.php', 'POST', $alta + ['rol' => 'socio', 'socio_id' => '4']);
    $repetido = $v->pedir('/usuarios.php', 'POST', $alta + ['rol' => 'socio', 'socio_id' => '4']);
    $propio = $v->pedir('/usuarios.php', 'POST', ['csrf' => $csrf, 'id' => '1', 'rol' => 'operador', 'socio_id' => '']);
    return $sinFicha['estado'] === 422 && $ok['estado'] === 303 && $repetido['estado'] === 422 && str_contains($repetido['cuerpo'], 'Ya existe')
        && $propio['estado'] === 422 && $pdo->query('SELECT rol FROM usuarios WHERE id = 1')->fetchColumn() === 'admin'
        && entrar($v, 'juana@manosdeloriente.bo', 'Costura-2026-JP')['estado'] === 303;
});

echo PHP_EOL . '— Actividad 3 · Laboratorio 11: ventas, acopios, transacciones y reportes —' . PHP_EOL;
$r = reporte_ventas($pdo, '2026-07-01', '2026-09-30');
$v->revisar('Reporte jul–sep = verificado a mano: 6 ventas, 27 prendas, Bs 3.287,50 (965,00 · 1.280,00 · 1.042,50)', fn() =>
    (int) $r['resumen']['ventas'] === 6 && (int) $r['resumen']['prendas'] === 27 && $r['resumen']['total'] === '3287.50'
    && array_column($r['porMes'], 'total', 'mes') === ['2026-07' => '965.00', '2026-08' => '1280.00', '2026-09' => '1042.50']);
$v->revisar('Top 5 por unidades: ACC-300 (6), POL-001 (5), ACC-302 (4), POL-003 (3), ABR-201 (2, desempata por importe)', fn() =>
    array_column($r['top'], 'unidades', 'codigo') === ['ACC-300' => '6', 'POL-001' => '5', 'ACC-302' => '4', 'POL-003' => '3', 'ABR-201' => '2']);
$v->revisar('Vendido por socia: Mario 1.250,00 · Rosa 825,00 · Lidia 437,00 · Elena 415,50 · Juana 360,00 (suma = total)', fn() =>
    array_column($r['porSocio'], 'importe') === ['1250.00', '825.00', '437.00', '415.50', '360.00']);
$v->revisar('El rango se filtra por fecha (solo agosto: 2 ventas, Bs 1.280,00) y una fecha inválida se rechaza', function () use ($pdo) {
    $agosto = reporte_ventas($pdo, '2026-08-01', '2026-08-31');
    try {
        reporte_ventas($pdo, '2026-13-01', '2026-08-31');
        return false;
    } catch (InvalidArgumentException) {
        return (int) $agosto['resumen']['ventas'] === 2 && $agosto['resumen']['total'] === '1280.00';
    }
});
entrar($v, 'operador@manosdeloriente.bo', CLAVE_OPERADOR);
$csrf = token($v->pedir('/ventas.php'));
$v->revisar('La página de reportes muestra las mismas cifras y el CSV (UTF-8 con BOM, «;») también', function () use ($v) {
    $html = $v->pedir('/reportes.php?desde=2026-07-01&hasta=2026-09-30')['cuerpo'];
    $csv = $v->pedir('/reportes.csv.php?desde=2026-07-01&hasta=2026-09-30');
    return str_contains($html, 'id="rep-total">Bs 3.287,50') && str_contains($html, 'id="rep-prendas">27')
        && str_contains((string) cabecera($csv, 'Content-Type'), 'text/csv') && str_starts_with($csv['cuerpo'], "\u{FEFF}")
        && str_contains($csv['cuerpo'], 'Resumen;Ventas;6;3287,50') && str_contains($csv['cuerpo'], '"Por mes";2026-08;2;1280,00');
});
$v->revisar('Venta web: POST con CSRF → 303; total = Σ precio × cantidad y el stock baja (POL-001 24→21, ACC-300 30→28)', function () use ($v, $csrf, $pdo) {
    $r = $v->pedir('/ventas.php', 'POST', ['csrf' => $csrf, 'accion' => 'venta', 'cliente' => 'Feria de prueba',
        'codigo[0]' => 'POL-001', 'cantidad[0]' => '3', 'codigo[1]' => 'ACC-300', 'cantidad[1]' => '2']);
    $venta = $pdo->query('SELECT total FROM ventas ORDER BY id DESC LIMIT 1')->fetchColumn();
    return $r['estado'] === 303 && $venta === '435.00' && (int) fila('POL-001')['stock'] === 21 && (int) fila('ACC-300')['stock'] === 28;
});
$v->revisar('Venta sin token CSRF → 403 y no se registra', function () use ($v, $pdo) {
    $antes = contar('ventas');
    return $v->pedir('/ventas.php', 'POST', ['accion' => 'venta', 'cliente' => 'X', 'codigo[0]' => 'POL-001', 'cantidad[0]' => '1'])['estado'] === 403
        && contar('ventas') === $antes;
});
$v->revisar('TRANSACCIÓN: si la 2.ª línea no tiene stock, no se guarda NADA (ni la venta ni el descuento de la 1.ª)', function () use ($v, $csrf) {
    $ventas = contar('ventas');
    $r = $v->pedir('/ventas.php', 'POST', ['csrf' => $csrf, 'accion' => 'venta', 'cliente' => 'Hotel de prueba',
        'codigo[0]' => 'ACC-300', 'cantidad[0]' => '1', 'codigo[1]' => 'VES-101', 'cantidad[1]' => '50']);
    return $r['estado'] === 422 && str_contains($r['cuerpo'], 'Stock insuficiente de VES-101') && contar('ventas') === $ventas
        && contar('venta_detalle') === 14 && (int) fila('ACC-300')['stock'] === 28 && !Conexion::obtener()->inTransaction();
});
$v->revisar('Una venta respeta lo reservado por pedidos: POL-003 tiene 18 pero 4 reservadas → vender 15 se rechaza', function () use ($pdo) {
    try {
        registrar_venta($pdo, ['POL-003' => 15], 'Cliente', 2);
        return false;
    } catch (InvalidArgumentException $e) {
        return str_contains($e->getMessage(), 'hay 14 disponibles') && (int) fila('POL-003')['stock'] === 18;
    }
});
$v->revisar('Cantidades inválidas (0, negativa, texto) y códigos mal formados se rechazan', function () use ($pdo) {
    $rechazos = 0;
    foreach ([['POL-001' => 0], ['POL-001' => -2], ['pol1' => 1], ['ZZZ-999' => 1], []] as $lineas) {
        try {
            registrar_venta($pdo, $lineas, 'Cliente', 2);
        } catch (InvalidArgumentException) {
            $rechazos++;
        }
    }
    return $rechazos === 5;
});
$v->revisar('Acopio con control de calidad: 10 entregadas, 8 aprobadas a Bs 48 → stock +8 y Bs 384,00 para la socia', function () use ($v, $csrf, $pdo) {
    $antes = (int) fila('POL-003')['stock'];
    $r = $v->pedir('/ventas.php', 'POST', ['csrf' => $csrf, 'accion' => 'acopio', 'acopio_codigo' => 'POL-003', 'entregadas' => '10',
        'aprobadas' => '8', 'precio' => '48,00', 'observacion' => '2 con el estampado corrido']);
    $a = $pdo->query('SELECT socio_id, aprobadas * precio_unitario AS liquidado FROM acopios ORDER BY id DESC LIMIT 1')->fetch();
    return $r['estado'] === 303 && (int) fila('POL-003')['stock'] === $antes + 8 && (int) $a['socio_id'] === 5 && $a['liquidado'] === '384.00';
});
$v->revisar('Acopio: más aprobadas que entregadas, o rechazos sin motivo → 422 (y la base también lo impide con CHECK)', function () use ($v, $csrf, $pdo) {
    $mas = $v->pedir('/ventas.php', 'POST', ['csrf' => $csrf, 'accion' => 'acopio', 'acopio_codigo' => 'POL-003', 'entregadas' => '5', 'aprobadas' => '6', 'precio' => '48']);
    $sinMotivo = $v->pedir('/ventas.php', 'POST', ['csrf' => $csrf, 'accion' => 'acopio', 'acopio_codigo' => 'POL-003', 'entregadas' => '5', 'aprobadas' => '4', 'precio' => '48']);
    try {
        $pdo->exec('INSERT INTO acopios (socio_id, producto_id, entregadas, aprobadas, precio_unitario, usuario_id) VALUES (5, 3, 2, 3, 10, 2)');
        $check = false;
    } catch (PDOException) {
        $check = true;
    }
    return $mas['estado'] === 422 && $sinMotivo['estado'] === 422 && str_contains($sinMotivo['cuerpo'], 'motivo del rechazo') && $check;
});
