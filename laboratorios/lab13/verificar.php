<?php
// verificar.php — Comprueba el Laboratorio 13 (API REST: métodos, códigos, problem+json, tokens, ETag, CORS). Uso: php verificar.php
// Requiere MySQL en marcha (docker compose up -d). Restaura los datos al empezar y al terminar.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    exit("❌ Falta vendor/autoload.php. Primero ejecuta:  composer dump-autoload" . PHP_EOL);
}
require __DIR__ . '/vendor/autoload.php';

use Apicultores\BaseDatos\Conexion;

try {
    $bd = Conexion::crear(require __DIR__ . '/config/bd.php');
} catch (PDOException $e) {
    exit("❌ No hay conexión con MySQL ({$e->getMessage()}).\n   Inicia la base de datos:  docker compose up -d" . PHP_EOL);
}
function restaurar(PDO $bd): void
{
    foreach (['01-esquema.sql', '02-datos.sql', '03-mas-datos.sql', '04-usuarios.sql', '05-movimientos.sql', '06-tokens.sql'] as $archivo) {
        $bd->exec((string) file_get_contents(__DIR__ . "/sql/$archivo"));
    }
}
function tokenDe(string $rol): string
{
    preg_match('/^' . $rol . '\s+(api_[a-f0-9]{40})$/m', (string) file_get_contents(__DIR__ . '/ejemplos/tokens.txt'), $m);
    return $m[1];
}
/** Petición a la API: $cuerpo array → JSON; devuelve la respuesta con 'json' decodificado. */
function api(Verificador $v, string $metodo, string $ruta, ?array $cuerpo = null, ?string $rol = null, array $extra = []): array
{
    $cab = $extra;
    if ($rol !== null) {
        $cab[] = 'Authorization: Bearer ' . tokenDe($rol);
    }
    if ($cuerpo !== null) {
        $cab[] = 'Content-Type: application/json';
    }
    $r = $v->pedir($ruta, $metodo, $cuerpo === null ? null : json_encode($cuerpo), $cab);
    $r['json'] = json_decode($r['cuerpo'], true);
    return $r;
}
function esProblema(array $r, int $estado): bool
{
    return $r['estado'] === $estado && str_contains($r['cabeceras'], 'Content-Type: application/problem+json')
        && ($r['json']['status'] ?? null) === $estado && str_starts_with((string) ($r['json']['type'] ?? ''), 'https://')
        && isset($r['json']['title'], $r['json']['instance']);
}
restaurar($bd);
$v = new Verificador();
$v->servidor(__DIR__ . '/public', __DIR__ . '/public/index.php');
$nuevo = ['codigo' => 'MIE-ROS', 'nombre' => 'Miel de romero 250 g', 'categoria' => 'Miel', 'precio' => '30.00', 'stock' => 5, 'socio_id' => 4];

$v->revisar('Tokens guardados como hash SHA-256 (64 hex), nunca el token', fn() =>
    (int) $bd->query("SELECT COUNT(*) FROM tokens_api WHERE hash REGEXP '^[a-f0-9]{64}$'")->fetchColumn() === 3
    && (int) $bd->query("SELECT COUNT(*) FROM tokens_api WHERE hash LIKE 'api_%'")->fetchColumn() === 0);
$lista = api($v, 'GET', '/api/v1/productos?por_pagina=5&pagina=2&categoria=Miel&orden=precio');
$v->revisar('GET lista: datos + meta (total 13 de Miel, página 2 de 3) + enlaces que conservan filtros', fn() =>
    $lista['estado'] === 200 && count($lista['json']['datos']) === 5 && $lista['json']['meta'] === ['total' => 13, 'pagina' => 2, 'por_pagina' => 5, 'paginas' => 3]
    && $lista['json']['enlaces']['siguiente'] === '/api/v1/productos?categoria=Miel&orden=precio&pagina=3&por_pagina=5'
    && $lista['json']['datos'][0]['precio'] === '55.50' && !isset($lista['json']['datos'][0]['id']));
$malo = api($v, 'GET', '/api/v1/productos?por_pagina=99&categoria=Hongos&orden=DROP');
$v->revisar('Parámetros inválidos → 422 problem+json con un error por parámetro (la API no "corrige" en silencio)', fn() =>
    esProblema($malo, 422) && array_keys($malo['json']['errores']) === ['por_pagina', 'categoria', 'orden']);
$uno = api($v, 'GET', '/api/v1/productos/mie-500');
$v->revisar('GET un producto: 200 con ETag; If-None-Match igual → 304 sin cuerpo', function () use ($v, $uno) {
    preg_match('/ETag: ("[a-f0-9]{16}")/', $uno['cabeceras'], $m);
    $r304 = api($v, 'GET', '/api/v1/productos/MIE-500', null, null, ['If-None-Match: ' . ($m[1] ?? '')]);
    return $uno['estado'] === 200 && $uno['json']['codigo'] === 'MIE-500' && isset($m[1]) && $r304['estado'] === 304 && $r304['cuerpo'] === '';
});
$v->revisar('Producto inexistente → 404 problem+json (type …/no-encontrado)', function () use ($v) {
    $r = api($v, 'GET', '/api/v1/productos/NO-EXISTE');
    return esProblema($r, 404) && $r['json']['type'] === 'https://florchiquitania.bo/problemas/no-encontrado';
});
$v->revisar('Escribir sin token → 401 con WWW-Authenticate; token falso → 401', function () use ($v, $nuevo) {
    $sin = api($v, 'POST', '/api/v1/productos', $nuevo);
    $falso = $v->pedir('/api/v1/productos', 'POST', json_encode($nuevo), ['Content-Type: application/json', 'Authorization: Bearer api_' . str_repeat('0', 40)]);
    return esProblema($sin, 401) && str_contains($sin['cabeceras'], 'WWW-Authenticate: Bearer') && $falso['estado'] === 401;
});
$v->revisar('Token de socio → 403 al crear', fn() => esProblema(api($v, 'POST', '/api/v1/productos', $nuevo, 'socio'), 403));
$v->revisar('Cuerpo sin Content-Type JSON → 415; JSON roto → 400; lista en vez de objeto → 422', function () use ($v) {
    $t = 'Authorization: Bearer ' . tokenDe('operador');
    $r415 = $v->pedir('/api/v1/productos', 'POST', 'codigo=MIE-ROS', [$t, 'Content-Type: application/x-www-form-urlencoded']);
    $r400 = $v->pedir('/api/v1/productos', 'POST', '{"codigo": "MIE-ROS",', [$t, 'Content-Type: application/json']);
    $r422 = $v->pedir('/api/v1/productos', 'POST', '[1, 2, 3]', [$t, 'Content-Type: application/json']);
    return $r415['estado'] === 415 && $r400['estado'] === 400 && $r422['estado'] === 422
        && str_contains($r400['cabeceras'], 'application/problem+json');
});
$creado = api($v, 'POST', '/api/v1/productos', $nuevo, 'operador');
$v->revisar('POST válido (operador) → 201 Created + Location y el recurso creado en el cuerpo', fn() =>
    $creado['estado'] === 201 && str_contains($creado['cabeceras'], 'Location: /api/v1/productos/MIE-ROS')
    && $creado['json']['precio'] === '30.00' && $creado['json']['socio']['nombre'] === 'Julián Pesoa');
$v->revisar('POST con datos inválidos → 422 con errores por campo (código repetido, precio, socio)', function () use ($v, $nuevo) {
    $r = api($v, 'POST', '/api/v1/productos', ['precio' => '-1', 'socio_id' => 999] + $nuevo, 'operador');
    return esProblema($r, 422) && isset($r['json']['errores']['codigo'], $r['json']['errores']['precio'], $r['json']['errores']['socio_id']);
});
$v->revisar('PUT (operador) reemplaza: precio 32.50 y stock 8; el código de la URL manda', function () use ($v, $nuevo, $bd) {
    $r = api($v, 'PUT', '/api/v1/productos/MIE-ROS', ['codigo' => 'OTRO-1', 'precio' => 32.5, 'stock' => 8] + $nuevo, 'operador');
    return $r['estado'] === 200 && $r['json']['codigo'] === 'MIE-ROS' && $r['json']['precio'] === '32.50' && $r['json']['stock'] === 8
        && (int) $bd->query("SELECT COUNT(*) FROM productos WHERE codigo = 'OTRO-1'")->fetchColumn() === 0;
});
$v->revisar('DELETE: operador → 403; admin → 204 sin cuerpo; repetir → 404', function () use ($v) {
    $op = api($v, 'DELETE', '/api/v1/productos/MIE-ROS', null, 'operador');
    $ad = api($v, 'DELETE', '/api/v1/productos/MIE-ROS', null, 'admin');
    return $op['estado'] === 403 && $ad['estado'] === 204 && $ad['cuerpo'] === ''
        && api($v, 'DELETE', '/api/v1/productos/MIE-ROS', null, 'admin')['estado'] === 404;
});
$v->revisar('Método no soportado (PATCH) → 405 con Allow; ruta inexistente → 404 en JSON (nunca HTML)', function () use ($v) {
    $r = api($v, 'PATCH', '/api/v1/productos/MIE-500', ['stock' => 1], 'admin');
    $n = api($v, 'GET', '/api/v1/nada');
    return esProblema($r, 405) && str_contains($r['cabeceras'], 'Allow: GET, PUT, DELETE') && esProblema($n, 404) && !str_contains($n['cuerpo'], '<html');
});
$v->revisar('CORS: preflight OPTIONS → 204 con métodos y cabeceras permitidas', function () use ($v) {
    $r = $v->pedir('/api/v1/productos', 'OPTIONS', null, ['Origin: https://tienda.ejemplo', 'Access-Control-Request-Method: POST']);
    return $r['estado'] === 204 && str_contains($r['cabeceras'], 'Access-Control-Allow-Origin: *')
        && str_contains($r['cabeceras'], 'Access-Control-Allow-Headers: Authorization, Content-Type');
});
$v->revisar('Contrato OpenAPI publicado con las rutas y el esquema Problema', function () use ($v) {
    $y = $v->pedir('/api/v1/openapi.yaml')['cuerpo'];
    return str_contains($y, 'openapi: 3.1.0') && str_contains($y, '/productos/{codigo}:') && str_contains($y, 'Problema:');
});
@unlink(__DIR__ . '/datos/errores.log');
restaurar($bd);
exit($v->fin());
