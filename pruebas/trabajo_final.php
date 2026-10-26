<?php
// pruebas/trabajo_final.php — Los 2 módulos nuevos del trabajo final, con cifras verificadas a mano (bd/08-demo.sql):
//   Módulo 1 · Liquidación y pagos a socias      Módulo 2 · Pedidos de compradores con reserva de stock
declare(strict_types=1);

instalar_bd();
$pdo = Conexion::obtener();

echo PHP_EOL . '— Trabajo final · Módulo 1: liquidación y pagos a socias —' . PHP_EOL;
$liq = array_column(liquidaciones($pdo), null, 'nombre');
$v->revisar('Liquidado = Σ aprobadas × precio: Rosa 1.700 · Elena 1.065 · Mario 1.120 · Juana 1.200 · Lidia 550 (total Bs 5.635,00)', fn() =>
    $liq['Rosa Chuvé Supepí']['liquidado'] === '1700.00' && $liq['Elena Masabí Tomichá']['liquidado'] === '1065.00'
    && $liq['Mario Surubí Peña']['liquidado'] === '1120.00' && $liq['Juana Pesoa Rojas']['liquidado'] === '1200.00'
    && $liq['Lidia Choré Vaca']['liquidado'] === '550.00' && array_sum(array_column($liq, 'liquidado')) == 5635);
$v->revisar('Saldo = liquidado − pagado: Rosa 700 · Elena 480 · Mario 0 · Juana 600 · Lidia 550 → pendiente total Bs 2.330,00', fn() =>
    array_map(fn(array $f): string => $f['saldo'], $liq) === ['Elena Masabí Tomichá' => '480.00', 'Juana Pesoa Rojas' => '600.00',
        'Lidia Choré Vaca' => '550.00', 'Mario Surubí Peña' => '0.00', 'Rosa Chuvé Supepí' => '700.00', 'Carmen Parapaino Masaí' => '0.00']
    && array_sum(array_column($liq, 'saldo')) == 2330);
$v->revisar('Las prendas rechazadas no se pagan: Rosa entregó 24, se aprobaron 22 (2 con el bordado incompleto)', fn() =>
    (int) $liq['Rosa Chuvé Supepí']['entregadas'] === 24 && (int) $liq['Rosa Chuvé Supepí']['aprobadas'] === 22
    && str_contains(implode(' ', array_column(detalle_liquidacion($pdo, 1)['acopios'], 'observacion')), 'bordado incompleto'));
entrar($v, 'operador@manosdeloriente.bo', 'Manos-Ventas-2026!');
$csrf = token($v->pedir('/liquidaciones.php'));
$v->revisar('Un pago mayor al saldo se rechaza (Rosa: Bs 800 > Bs 700) y no se guarda', function () use ($v, $csrf) {
    $pagos = contar('pagos_socios');
    $r = $v->pedir('/liquidaciones.php', 'POST', ['csrf' => $csrf, 'socio_id' => '1', 'importe' => '800', 'referencia' => 'TRF-1026-01']);
    return $r['estado'] === 422 && str_contains($r['cuerpo'], 'supera el saldo pendiente') && contar('pagos_socios') === $pagos;
});
$v->revisar('Pago válido (Bs 500,50) → 303 y el saldo de Rosa baja a Bs 199,50; la referencia repetida se rechaza', function () use ($v, $csrf, $pdo) {
    $r = $v->pedir('/liquidaciones.php', 'POST', ['csrf' => $csrf, 'socio_id' => '1', 'importe' => '500,50', 'referencia' => 'trf-1026-01']);
    $repetida = $v->pedir('/liquidaciones.php', 'POST', ['csrf' => $csrf, 'socio_id' => '1', 'importe' => '10', 'referencia' => 'TRF-1026-01']);
    return $r['estado'] === 303 && liquidaciones($pdo, 1)[0]['saldo'] === '199.50' && $repetida['estado'] === 422
        && str_contains($repetida['cuerpo'], 'ya se usó');
});
$v->revisar('Pagar exige el permiso: una socia que envía el formulario recibe 403', function () use ($v) {
    entrar($v, 'elena@manosdeloriente.bo', 'Manos-Socia-2026!');
    $r = $v->pedir('/liquidaciones.php', 'POST', ['csrf' => token($v->pedir('/liquidaciones.php')), 'socio_id' => '2', 'importe' => '10', 'referencia' => 'AUTO-PAGO']);
    return $r['estado'] === 403 && contar('pagos_socios') === 5;
});
$v->revisar('API: la socia consulta SU liquidación (200, saldo 480.00) pero no la de otra (403); sin token → 401', function () use ($v, $pdo) {
    $token = crear_token_api($pdo, 4, 'App de Elena');
    $propia = $v->pedir('/api/v1/socios/2/liquidacion', 'GET', null, ["Authorization: Bearer $token"]);
    $ajena = $v->pedir('/api/v1/socios/1/liquidacion', 'GET', null, ["Authorization: Bearer $token"]);
    return $propia['estado'] === 200 && json($propia)['saldo'] === '480.00' && count(json($propia)['acopios']) === 2
        && $ajena['estado'] === 403 && $v->pedir('/api/v1/socios/2/liquidacion')['estado'] === 401;
});

echo PHP_EOL . '— Trabajo final · Módulo 2: pedidos de compradores con reserva de stock —' . PHP_EOL;
$v->revisar('El pedido pendiente de demostración reserva 4 POL-003 y 2 ACC-301; el cancelado no reserva nada', fn() =>
    (int) fila('POL-003')['reservado'] === 4 && (int) fila('ACC-301')['reservado'] === 2 && (int) fila('ABR-200')['reservado'] === 0);
$v->revisar('La base impide reservar más que el stock: CHECK (reservado <= stock)', function () use ($pdo) {
    try {
        $pdo->exec("UPDATE productos SET reservado = stock + 1 WHERE codigo = 'POL-001'");
        return false;
    } catch (PDOException) {
        return (int) fila('POL-001')['reservado'] === 0;
    }
});
entrar($v, 'operador@manosdeloriente.bo', 'Manos-Ventas-2026!');
$csrf = token($v->pedir('/pedidos.php'));
$v->revisar('Crear pedido por la web → 303, número PED-000003, total Bs 635,00 y reserva 3 POL-001 + 1 VES-100 (stock intacto)', function () use ($v, $csrf, $pdo) {
    $r = $v->pedir('/pedidos.php', 'POST', ['csrf' => $csrf, 'accion' => 'crear', 'cliente' => 'Boutique Chiquitana',
        'codigo[0]' => 'POL-001', 'cantidad[0]' => '2', 'codigo[1]' => 'VES-100', 'cantidad[1]' => '1', 'codigo[2]' => 'POL-001', 'cantidad[2]' => '1']);
    $p = $pdo->query("SELECT numero, total, estado FROM pedidos WHERE id = 3")->fetch();
    return $r['estado'] === 303 && $p === ['numero' => 'PED-000003', 'total' => '635.00', 'estado' => 'pendiente']
        && (int) fila('POL-001')['reservado'] === 3 && (int) fila('VES-100')['reservado'] === 1 && (int) fila('POL-001')['stock'] === 24;
});
$v->revisar('No se puede reservar más de lo libre: VES-100 tiene 5 y 1 reservada → pedir 5 se rechaza sin guardar nada', function () use ($v, $csrf) {
    $pedidos = contar('pedidos');
    $r = $v->pedir('/pedidos.php', 'POST', ['csrf' => $csrf, 'accion' => 'crear', 'cliente' => 'Hotel Misiones',
        'codigo[0]' => 'ACC-300', 'cantidad[0]' => '1', 'codigo[1]' => 'VES-100', 'cantidad[1]' => '5']);
    return $r['estado'] === 422 && str_contains($r['cuerpo'], 'hay 4 disponibles') && contar('pedidos') === $pedidos
        && (int) fila('ACC-300')['reservado'] === 0;
});
$v->revisar('ENTREGAR (una transacción): crea la venta por el total del pedido y bajan stock y reserva a la vez', function () use ($v, $csrf, $pdo) {
    $ventas = contar('ventas');
    $r = $v->pedir('/pedidos.php', 'POST', ['csrf' => $csrf, 'accion' => 'estado', 'pedido_id' => '3', 'estado' => 'entregado']);
    $p = $pdo->query('SELECT estado, venta_id FROM pedidos WHERE id = 3')->fetch();
    $venta = $pdo->query("SELECT total, cliente FROM ventas WHERE id = {$p['venta_id']}")->fetch();
    return $r['estado'] === 303 && $p['estado'] === 'entregado' && contar('ventas') === $ventas + 1
        && $venta === ['total' => '635.00', 'cliente' => 'Boutique Chiquitana']
        && (int) fila('POL-001')['stock'] === 21 && (int) fila('POL-001')['reservado'] === 0
        && (int) fila('VES-100')['stock'] === 4 && (int) fila('VES-100')['reservado'] === 0;
});
$v->revisar('CANCELAR libera la reserva (POL-003 vuelve a 0 reservadas, stock 18 intacto); un entregado no cambia de estado', function () use ($v, $csrf) {
    $r = $v->pedir('/pedidos.php', 'POST', ['csrf' => $csrf, 'accion' => 'estado', 'pedido_id' => '1', 'estado' => 'cancelado']);
    $otra = $v->pedir('/pedidos.php', 'POST', ['csrf' => $csrf, 'accion' => 'estado', 'pedido_id' => '3', 'estado' => 'cancelado']);
    return $r['estado'] === 303 && (int) fila('POL-003')['reservado'] === 0 && (int) fila('POL-003')['stock'] === 18
        && (int) fila('ACC-301')['reservado'] === 0 && $otra['estado'] === 422 && str_contains($otra['cuerpo'], 'no puede pasar');
});
$v->revisar('El stock de una prenda no se puede editar por debajo de lo reservado (422 con el motivo)', function () use ($v, $csrf, $pdo) {
    crear_pedido($pdo, 'Feria de prueba', ['ACC-302' => 15], 2);
    $r = $v->pedir('/editar.php?codigo=ACC-302', 'POST', ['csrf' => $csrf, 'nombre' => 'Faja tejida tradicional', 'categoria' => 'accesorios',
        'socio' => '5', 'precio' => '45,50', 'stock' => '10', 'tallas[0]' => 'U']);
    return $r['estado'] === 422 && str_contains($r['cuerpo'], '15 unidades reservadas') && (int) fila('ACC-302')['stock'] === 20;
});
$v->revisar('API: POST /pedidos (operador) → 201 + Location, origen «api» y reserva; número repetido → 409; sin permiso → 403', function () use ($v, $pdo) {
    $token = crear_token_api($pdo, 2, 'Integración tienda');
    $socia = crear_token_api($pdo, 3, 'Rosa');
    $cuerpo = json_encode(['numero' => 'API-0001', 'cliente' => 'Tienda en línea', 'fecha' => '2026-10-26', 'lineas' => [['codigo' => 'ABR-201', 'cantidad' => 2]]]);
    $cab = fn(string $t): array => ["Authorization: Bearer $t", 'Content-Type: application/json'];
    $r = $v->pedir('/api/v1/pedidos', 'POST', $cuerpo, $cab($token));
    $repetido = $v->pedir('/api/v1/pedidos', 'POST', $cuerpo, $cab($token));
    $lista = $v->pedir('/api/v1/pedidos?estado=pendiente', 'GET', null, $cab($token));
    return $r['estado'] === 201 && str_starts_with((string) cabecera($r, 'Location'), '/api/v1/pedidos/') && json($r)['origen'] === 'api'
        && json($r)['total'] === '620.00' && (int) fila('ABR-201')['reservado'] === 2 && $repetido['estado'] === 409
        && $lista['estado'] === 200 && count(json($lista)['datos']) === 2
        && $v->pedir('/api/v1/pedidos', 'POST', $cuerpo, $cab($socia))['estado'] === 403;
});
$v->revisar('La página de pedidos lista el estado de cada uno y lo reservado ahora', function () use ($v) {
    $html = $v->pedir('/pedidos.php')['cuerpo'];
    return str_contains($html, 'PED-000003') && str_contains($html, 'Entregado') && str_contains($html, 'Venta N.º')
        && str_contains($html, 'id="reservas"') && str_contains($html, 'ACC-302');
});

echo PHP_EOL . '— Seguridad del código (lo que revisa la auditoría del docente) —' . PHP_EOL;
$v->revisar('Todos los archivos PHP declaran strict_types', function () {
    $archivos = [...glob(__DIR__ . '/../src/*.php'), ...glob(__DIR__ . '/../src/*/*.php'), ...glob(__DIR__ . '/../public/*.php'),
        ...glob(__DIR__ . '/../plantillas/*.php'), ...glob(__DIR__ . '/../bd/*.php'), ...glob(__DIR__ . '/*.php')];
    return count($archivos) > 40 && array_filter($archivos, fn(string $a): bool => !str_contains((string) file_get_contents($a), 'declare(strict_types=1)')) === [];
});
$v->revisar('No hay credenciales en el código: config.php está en .gitignore y la plantilla no trae clave', fn() =>
    str_contains((string) file_get_contents(__DIR__ . '/../.gitignore'), 'config/config.php')
    && str_contains((string) file_get_contents(__DIR__ . '/../config/config.ejemplo.php'), "'clave' => ''"));
$v->revisar('En Apache/InfinityFree, .htaccess bloquea src/, config/, bd/, plantillas/ y datos/ (solo public/ es visible)', function () {
    $raiz = (string) @file_get_contents(__DIR__ . '/../herramientas/infinityfree/.htaccess');
    $publico = (string) file_get_contents(__DIR__ . '/../public/.htaccess');
    return preg_match('/\(src\|config\|bd\|plantillas\|datos/', $raiz) === 1 && str_contains($raiz, 'public/$1')
        && str_contains($publico, 'HTTP_AUTHORIZATION') && str_contains($publico, 'api.php');
});
