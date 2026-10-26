<?php
// herramientas/evidencias_api.php — Genera las evidencias E01–E12 de la Actividad 4 con peticiones REALES al sistema en marcha.
// Cada evidencia queda en docs/actividad-04/evidencias/Exx_*.txt (comando + respuesta como «curl -i»).
// Los tokens se emiten en el momento, se OCULTAN en la salida y se revocan al terminar: ninguno llega a GitHub.
//
//   php herramientas/evidencias_api.php http://127.0.0.1:8000      (con la base de demostración recién instalada)
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$carpeta = __DIR__ . '/../docs/actividad-04/evidencias';
@mkdir($carpeta, 0775, true);
$pdo = Conexion::obtener();
$tokens = ['admin' => crear_token_api($pdo, 1, 'Evidencias admin', 1), 'operador' => crear_token_api($pdo, 2, 'Evidencias operador', 1),
    'socio' => crear_token_api($pdo, 3, 'Evidencias Rosa', 1)];
$ejemplos = __DIR__ . '/../docs/actividad-04/ejemplos';

/** Petición HTTP mostrada como «curl.exe -i»: cabeceras útiles y cuerpo (recortado si es largo). */
function http(string $metodo, string $ruta, ?string $rol = null, ?string $cuerpo = null, array $extra = [], int $lineas = 40): string
{
    global $base, $tokens;
    $cabeceras = $extra;
    $nota = '';
    $comando = 'curl.exe -i' . ($metodo === 'GET' ? '' : " -X $metodo") . " \"$base$ruta\"";
    if ($rol !== null) {
        $cabeceras[] = 'Authorization: Bearer ' . ($tokens[$rol] ?? $rol);
        $comando .= ' -H "Authorization: Bearer $TOKEN"';
        $nota = isset($tokens[$rol]) ? "   # \$TOKEN = token de $rol (oculto)" : '   # $TOKEN = token inventado';
    }
    if ($cuerpo !== null && !array_filter($extra, fn(string $c): bool => stripos($c, 'Content-Type') === 0)) {
        $cabeceras[] = 'Content-Type: application/json';
    }
    foreach ($extra as $c) {
        $comando .= " -H \"$c\"";
    }
    if ($cuerpo !== null) {
        $comando .= is_file($cuerpo) ? ' --data-binary "@' . basename($cuerpo) . '"' : " --data-binary '" . $cuerpo . "'";
    }
    $comando .= $nota;
    $ch = curl_init($base . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $cabeceras]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_file($cuerpo) ? (string) file_get_contents($cuerpo) : $cuerpo);
    }
    $t = microtime(true);
    $r = (string) curl_exec($ch);
    $ms = (microtime(true) - $t) * 1000;
    $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $mostrar = $metodo === 'OPTIONS' ? '/^(HTTP\/|Access-Control|Allow)/i' : '/^(HTTP\/|Content-Type|Location|ETag|Allow:|WWW-Authenticate|Cache-Control)/i';
    $cab = implode("\n", array_filter(preg_split('/\r?\n/', trim(substr($r, 0, $tam))), fn(string $l): bool => preg_match($mostrar, $l) === 1));
    $texto = substr($r, $tam);
    $partes = explode("\n", $texto);
    if (count($partes) > $lineas) {
        $texto = implode("\n", array_slice($partes, 0, $lineas)) . "\n… (" . (count($partes) - $lineas) . ' líneas más)';
    }
    foreach ($tokens as $token) {
        $texto = str_replace($token, 'mdo_****', $texto);
    }
    return "PS> $comando\n$cab\n" . sprintf('(respuesta: %d bytes en %.0f ms)', strlen(substr($r, $tam)), $ms) . "\n\n" . $texto . "\n";
}

function guardar(string $archivo, string $titulo, string ...$bloques): void
{
    global $carpeta;
    $contenido = "# $titulo\n# Generado el " . date('d/m/Y H:i') . " contra el sistema en marcha (herramientas/evidencias_api.php)\n\n" . implode("\n" . str_repeat('─', 100) . "\n\n", $bloques);
    file_put_contents("$carpeta/$archivo", $contenido);
    echo "✔ $archivo\n";
}

function consola(string $comando): string
{
    $salida = [];
    exec($comando . ' 2>&1', $salida);
    return "PS> $comando\n" . implode("\n", $salida) . "\n";
}

guardar('E01_versiones_entorno.txt', 'E01 · Versiones y entorno',
    consola('php -v'), consola('php -m'), consola('docker --version'), consola('docker compose version'), consola('git --version'),
    consola('node -v'), "PS> (base de datos)\n" . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n");
guardar('E02_catalogo_json.txt', 'E02 · Catálogo JSON (ISO 8601 y precios como texto)', http('GET', '/datos/catalogo.json', lineas: 34));
$pdo->exec("UPDATE productos SET nombre = 'Bolso & cartera <tejida> de saó' WHERE codigo = 'ACC-300'");
guardar('E03_xml_escape.txt', 'E03 · Catálogo XML con XMLWriter: «&» y «<» escapados', http('GET', '/datos/catalogo.xml', lineas: 200));
$pdo->exec("UPDATE productos SET nombre = 'Bolso tejido de saó' WHERE codigo = 'ACC-300'");
$validar = function (string $archivo): string {
    $contenido = (string) file_get_contents($archivo);
    try {
        $p = str_ends_with($archivo, '.xml') ? leer_pedido_xml($contenido) : leer_pedido_json($contenido);
        $r = 'VÁLIDO → ' . json_encode($p, JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $e) {
        $r = 'RECHAZADO → ' . $e->getMessage();
    }
    return "PS> php -r \"leer_pedido_" . (str_ends_with($archivo, '.xml') ? 'xml' : 'json') . "('" . basename($archivo) . "')\"\n--- " . basename($archivo) . " ---\n$contenido\n\n$r\n";
};
guardar('E04_validacion_xsd_json.txt', 'E04 · Validación de pedidos: XML con XSD (LIBXML_NONET + schemaValidate) y JSON por tipos',
    $validar("$ejemplos/pedido-boutique.xml"), $validar("$ejemplos/pedido-invalido.xml"), $validar("$ejemplos/pedido-hotel.json"), $validar("$ejemplos/pedido-invalido.json"));
guardar('E05_rss.txt', 'E05 · Canal RSS 2.0 (title, description, guid, pubDate DATE_RSS)', http('GET', '/noticias.rss', lineas: 40));
guardar('E06_soap_rest_curl.txt', 'E06 · SOAP frente a REST (medido en este equipo) y consumo de un servicio público con cURL',
    consola('php herramientas/soap_cliente.php POL-001 ' . $base), http('GET', '/datos/productos/POL-001'), consola('php herramientas/consumir_publico.php'));
guardar('E07_get_paginado.txt', 'E07 · GET paginado con filtros: datos, meta y enlaces',
    http('GET', '/api/v1/productos?pagina=2&por_pagina=5&orden=precio-asc', lineas: 30), http('GET', '/api/v1/productos/sugerencias?q=po'));
guardar('E08_post_201.txt', 'E08 · POST con token → 201 Created + Location', "PS> Get-Content producto.json\n" . file_get_contents("$ejemplos/producto.json") . "\n",
    http('POST', '/api/v1/productos', 'operador', "$ejemplos/producto.json"));
guardar('E09_put_200.txt', 'E09 · PUT → 200 (precio, stock y tallas reemplazados)', "PS> Get-Content actualizacion.json\n" . file_get_contents("$ejemplos/actualizacion.json") . "\n",
    http('PUT', '/api/v1/productos/POL-050', 'operador', "$ejemplos/actualizacion.json"));
guardar('E10_delete_204.txt', 'E10 · DELETE → 204 sin cuerpo; después GET → 404',
    http('DELETE', '/api/v1/productos/POL-050', 'operador'), http('DELETE', '/api/v1/productos/POL-050', 'admin'), http('GET', '/api/v1/productos/POL-050'));
guardar('E11_errores_401_403_422.txt', 'E11 · Errores en application/problem+json: 400, 401, 403, 404, 405, 409, 415 y 422',
    http('POST', '/api/v1/productos', null, "$ejemplos/producto.json"),
    http('POST', '/api/v1/productos', 'mdo_' . str_repeat('0', 40), "$ejemplos/producto.json"),
    http('POST', '/api/v1/productos', 'socio', "$ejemplos/producto.json"),
    http('POST', '/api/v1/productos', 'operador', '{"codigo": "POL-051",'),
    http('POST', '/api/v1/productos', 'operador', '{"codigo":"POL-051"}', ['Content-Type: text/plain']),
    http('POST', '/api/v1/productos', 'operador', '{"codigo":"xx","nombre":"A","categoria":"zapatos","socio_id":6,"precio":"-1"}'),
    http('GET', '/api/v1/productos?por_pagina=500&orden=barato'),
    http('PATCH', '/api/v1/productos/POL-001', 'admin', '{}'),
    http('DELETE', '/api/v1/productos/POL-001', 'admin'),
    http('GET', '/api/v1/productos/NOP-000'));
$etag = '';
$ch = curl_init("$base/api/v1/productos/POL-001");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true]);
preg_match('/^ETag:\s*(\S+)/mi', (string) curl_exec($ch), $m);
$etag = $m[1] ?? '';
$hashes = $pdo->query('SELECT id, nombre, hash, revocado FROM tokens_api ORDER BY id DESC LIMIT 3')->fetchAll();
guardar('E12_openapi_etag_cors.txt', 'E12 · ETag/If-None-Match → 304, OPTIONS (CORS), OpenAPI 3.1 y tokens guardados como SHA-256',
    http('GET', '/api/v1/productos/POL-001', lineas: 12), http('GET', '/api/v1/productos/POL-001', extra: ["If-None-Match: $etag"]),
    http('OPTIONS', '/api/v1/productos', extra: ['Origin: https://otra-tienda.bo', 'Access-Control-Request-Method: POST']),
    http('GET', '/api/v1/openapi.yaml', lineas: 14),
    "PS> SELECT id, nombre, hash, revocado FROM tokens_api   # en la base solo está el hash SHA-256 (64 hex)\n"
    . implode("\n", array_map(fn(array $f): string => sprintf('%3d  %-22s %s  %d', $f['id'], $f['nombre'], $f['hash'], $f['revocado']), $hashes)) . "\n");

$pdo->exec("UPDATE tokens_api SET revocado = 1 WHERE nombre LIKE 'Evidencias%'");
echo "Tokens de evidencias revocados.\n";
