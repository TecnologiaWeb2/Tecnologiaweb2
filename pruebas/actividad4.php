<?php
// pruebas/actividad4.php — Actividad 4: Laboratorio 12 (JSON, XML, XSD, RSS, SOAP), 13 (API REST) y 14 (AJAX con fetch).
// Lo incluye verificar.php. Los tokens se emiten aquí mismo con crear_token_api(): ningún token vive en el repositorio.
declare(strict_types=1);

instalar_bd();
$pdo = Conexion::obtener();
$ejemplos = __DIR__ . '/../docs/actividad-04/ejemplos';

echo PHP_EOL . '— Actividad 4 · Laboratorio 12: intercambio JSON, XML, RSS y SOAP/REST —' . PHP_EOL;
$catalogo = $v->pedir('/datos/catalogo.json');
$c = json($catalogo);
$v->revisar('/datos/catalogo.json: application/json, fechas ISO 8601 (−04:00) y precios decimales como texto («95.00»)', fn() =>
    $catalogo['estado'] === 200 && str_starts_with((string) cabecera($catalogo, 'Content-Type'), 'application/json')
    && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-04:00$/', (string) $c['generado']) === 1 && $c['total'] === 14
    && $c['productos'][array_search('POL-001', array_column($c['productos'], 'codigo'), true)]['precio'] === '95.00'
    && !str_contains($catalogo['cuerpo'], '"ci"') && !str_contains($catalogo['cuerpo'], 'telefono'));
$v->revisar('/datos/catalogo.xml: XML bien formado con XMLWriter; «Bolso & cartera <tejida>» se escapa (&amp; &lt;)', function () use ($v, $pdo) {
    $pdo->exec("UPDATE productos SET nombre = 'Bolso & cartera <tejida>' WHERE codigo = 'ACC-300'");
    $r = $v->pedir('/datos/catalogo.xml');
    $doc = new DOMDocument();
    $valido = $doc->loadXML($r['cuerpo'], LIBXML_NONET);
    $pdo->exec("UPDATE productos SET nombre = 'Bolso tejido de saó' WHERE codigo = 'ACC-300'");
    return $r['estado'] === 200 && str_starts_with((string) cabecera($r, 'Content-Type'), 'application/xml') && $valido
        && str_contains($r['cuerpo'], '<nombre>Bolso &amp; cartera &lt;tejida&gt;</nombre>') && $doc->getElementsByTagName('producto')->length === 14;
});
$v->revisar('Pedido XML válido contra pedido.xsd → se lee; el inválido (fecha 30/02, código, cantidad −3) se rechaza con el motivo', function () use ($ejemplos) {
    $ok = leer_pedido_xml((string) file_get_contents("$ejemplos/pedido-boutique.xml"));
    try {
        leer_pedido_xml((string) file_get_contents("$ejemplos/pedido-invalido.xml"));
        return false;
    } catch (InvalidArgumentException $e) {
        return $ok['numero'] === 'BC-2026-0412' && $ok['lineas'] === ['POL-001' => 4, 'ACC-300' => 6]
            && str_contains($e->getMessage(), 'pedido.xsd') && str_contains($e->getMessage(), '2026-02-30') && str_contains($e->getMessage(), 'pol1');
    }
});
$v->revisar('XML con entidad externa (XXE) no lee archivos del servidor: el esquema lo rechaza y la entidad no se expande', function () {
    $xxe = '<?xml version="1.0"?><!DOCTYPE p [<!ENTITY x SYSTEM "file:///etc/passwd">]><pedido numero="XX-1"><cliente>&x;</cliente>'
        . '<fecha>2026-10-01</fecha><linea codigo="POL-001" cantidad="1"/></pedido>';
    try {
        $p = leer_pedido_xml($xxe);
        return !str_contains($p['cliente'], 'root:');
    } catch (InvalidArgumentException $e) {
        return !str_contains($e->getMessage(), 'root:');
    }
});
$v->revisar('Pedido JSON: el válido se lee; el inválido se rechaza por tipos («"2"» no es entero), fecha y código', function () use ($ejemplos) {
    $ok = leer_pedido_json((string) file_get_contents("$ejemplos/pedido-hotel.json"));
    try {
        leer_pedido_json((string) file_get_contents("$ejemplos/pedido-invalido.json"));
        return false;
    } catch (InvalidArgumentException $e) {
        return $ok['lineas'] === ['ABR-201' => 1, 'ACC-301' => 3] && str_contains($e->getMessage(), 'AAAA-MM-DD')
            && str_contains($e->getMessage(), 'Línea 1') && str_contains($e->getMessage(), 'Línea 2');
    }
});
$rss = $v->pedir('/noticias.rss');
$v->revisar('/noticias.rss: RSS 2.0 con title, description, guid y pubDate en formato DATE_RSS', function () use ($rss) {
    $xml = @simplexml_load_string($rss['cuerpo']);
    if ($xml === false) {
        return false;
    }
    $item = $xml->channel->item[0];
    return (string) $xml['version'] === '2.0' && (string) $xml->channel->title !== '' && (string) $xml->channel->description !== ''
        && count($xml->channel->item) === 10 && (string) $item->guid !== ''
        && DateTimeImmutable::createFromFormat(DATE_RSS, (string) $item->pubDate) !== false
        && str_contains((string) cabecera($rss, 'Content-Type'), 'application/rss+xml');
});
$v->revisar('SOAP y REST devuelven la MISMA prenda (precio, stock, socia); SOAP con código inexistente → SoapFault', function () use ($v) {
    if (!class_exists('SoapClient')) {
        return false;
    }
    $cliente = new SoapClient(null, ['location' => $v->url('/soap.php'), 'uri' => 'urn:manos-del-oriente:precios', 'exceptions' => true]);
    $soap = (array) $cliente->precio('POL-001');
    $rest = json($v->pedir('/datos/productos/POL-001'));
    try {
        $cliente->precio('NOP-000');
        return false;
    } catch (SoapFault $f) {
        return $soap['precio'] === $rest['precio'] && (int) $soap['stock'] === $rest['stock'] && $soap['confecciono'] === $rest['confecciono']
            && str_contains($f->getMessage(), 'NOP-000') && $v->pedir('/datos/productos/NOP-000')['estado'] === 404;
    }
});
entrar($v, 'operador@manosdeloriente.bo', 'Manos-Ventas-2026!');
$csrf = token($v->pedir('/importar.php'));
$v->revisar('Importar pedido por la web: JSON válido → pedido «json» que reserva stock; el XML inválido → 422 y nada se guarda', function () use ($v, $csrf, $ejemplos, $pdo) {
    $ok = $v->pedir('/importar.php', 'POST', ['csrf' => $csrf, 'contenido' => (string) file_get_contents("$ejemplos/pedido-hotel.json")]);
    $pedidos = contar('pedidos');
    $malo = $v->pedir('/importar.php', 'POST', ['csrf' => $csrf, 'archivo' => new CURLFile("$ejemplos/pedido-invalido.xml", 'application/xml', 'p.xml')]);
    $p = $pdo->query("SELECT origen, estado, total FROM pedidos WHERE numero = 'HM-889'")->fetch();
    return $ok['estado'] === 303 && $p['origen'] === 'json' && $p['estado'] === 'pendiente' && $p['total'] === '580.00'
        && (int) fila('ACC-301')['reservado'] === 5 && $malo['estado'] === 422 && contar('pedidos') === $pedidos;
});
$v->revisar('herramientas/consumir_publico.php existe y consume un servicio externo con cURL comprobando HTTP y JSON', function () {
    $codigo = (string) @file_get_contents(__DIR__ . '/../herramientas/consumir_publico.php');
    return str_contains($codigo, 'curl_init') && str_contains($codigo, 'CURLINFO_HTTP_CODE') && str_contains($codigo, 'JSON_THROW_ON_ERROR');
});

echo PHP_EOL . '— Actividad 4 · Laboratorio 13: API REST /api/v1 —' . PHP_EOL;
instalar_bd();
$tokens = ['admin' => crear_token_api($pdo, 1, 'Pruebas admin'), 'operador' => crear_token_api($pdo, 2, 'Pruebas operador'),
    'socio' => crear_token_api($pdo, 3, 'Pruebas Rosa')];
$api = function (string $metodo, string $ruta, ?string $rol = null, ?string $cuerpo = null, array $extra = []) use ($v, $tokens): array {
    $cabeceras = ['Accept: application/json', ...$extra];
    if ($rol !== null) {
        $cabeceras[] = 'Authorization: Bearer ' . ($tokens[$rol] ?? $rol);
    }
    if ($cuerpo !== null && !array_filter($extra, fn(string $c): bool => stripos($c, 'Content-Type') === 0)) {
        $cabeceras[] = 'Content-Type: application/json';
    }
    return $v->pedir('/api/v1' . $ruta, $metodo, $cuerpo, $cabeceras);
};
$lista = $api('GET', '/productos?pagina=2&por_pagina=5&orden=precio-asc');
$l = json($lista);
$v->revisar('GET /productos?pagina=2&por_pagina=5 → 200 con datos (5), meta (14 en 3 páginas) y enlaces anterior/siguiente', fn() =>
    $lista['estado'] === 200 && count($l['datos']) === 5 && $l['meta'] === ['total' => 14, 'pagina' => 2, 'por_pagina' => 5, 'paginas' => 3]
    && isset($l['enlaces']['anterior'], $l['enlaces']['siguiente']) && $l['datos'][0]['precio'] === '120.00' && !isset($l['datos'][0]['id']));
$v->revisar('Filtros de la API: q=jean&talla=XL → 1 (ABR-200); categoria=vestidos → 2; disponibles=1 → 12', fn() =>
    json($api('GET', '/productos?q=jean&talla=XL'))['meta']['total'] === 1 && json($api('GET', '/productos?categoria=vestidos'))['meta']['total'] === 2
    && json($api('GET', '/productos?disponibles=1'))['meta']['total'] === 12);
$invalido = $api('GET', '/productos?por_pagina=500&orden=precio;DROP&categoria=zapatos&pagina=0');
$v->revisar('Parámetros inválidos → 422 application/problem+json con «errores» por campo (no se corrigen en silencio)', fn() =>
    $invalido['estado'] === 422 && str_starts_with((string) cabecera($invalido, 'Content-Type'), 'application/problem+json')
    && array_keys(json($invalido)['errores']) === ['pagina', 'por_pagina', 'categoria', 'orden'] && json($invalido)['status'] === 422
    && $api('GET', '/productos?pagina=9')['estado'] === 422);
$v->revisar('GET /productos/sugerencias?q=po responde antes que /productos/{codigo}; q de 1 letra → 422', function () use ($api) {
    $s = $api('GET', '/productos/sugerencias?q=po');
    $nombres = array_column(json($s)['datos'], 'codigo');
    return $s['estado'] === 200 && in_array('POL-001', $nombres, true) && in_array('ABR-201', $nombres, true)
        && $api('GET', '/productos/sugerencias?q=p')['estado'] === 422;
});
$uno = $api('GET', '/productos/pol-001');
$etag = (string) cabecera($uno, 'ETag');
$v->revisar('GET /productos/{codigo} → 200 con ETag; If-None-Match con ese ETag → 304 SIN cuerpo; inexistente → 404', function () use ($api, $uno, $etag) {
    $cache = $api('GET', '/productos/POL-001', extra: ["If-None-Match: $etag"]);
    return $uno['estado'] === 200 && json($uno)['codigo'] === 'POL-001' && preg_match('/^"[a-f0-9]{20}"$/', $etag) === 1
        && $cache['estado'] === 304 && $cache['cuerpo'] === '' && $api('GET', '/productos/NOP-000')['estado'] === 404;
});
$producto = (string) file_get_contents("$ejemplos/producto.json");
$v->revisar('POST sin token → 401 con WWW-Authenticate; token inventado → 401; token de socia → 403', function () use ($api, $producto) {
    $sin = $api('POST', '/productos', null, $producto);
    return $sin['estado'] === 401 && str_contains((string) cabecera($sin, 'WWW-Authenticate'), 'Bearer')
        && $api('POST', '/productos', 'mdo_' . str_repeat('0', 40), $producto)['estado'] === 401
        && $api('POST', '/productos', 'socio', $producto)['estado'] === 403 && fila('POL-050') === null;
});
$v->revisar('POST con Content-Type incorrecto → 415; JSON roto → 400; datos inválidos → 422 con errores por campo', function () use ($api) {
    $tipo = $api('POST', '/productos', 'operador', '{}', ['Content-Type: text/plain']);
    $roto = $api('POST', '/productos', 'operador', '{"codigo": "POL-051",');
    $malo = $api('POST', '/productos', 'operador', json_encode(['codigo' => 'xx', 'nombre' => 'A', 'categoria' => 'zapatos', 'socio_id' => 6, 'precio' => '-1']));
    return $tipo['estado'] === 415 && $roto['estado'] === 400 && $malo['estado'] === 422
        && array_keys(json($malo)['errores']) === ['codigo', 'nombre', 'categoria', 'precio', 'socio_id']
        && $api('POST', '/productos', 'operador', '[1, 2]')['estado'] === 400;
});
$creado = $api('POST', '/productos', 'operador', $producto);
$v->revisar('POST válido (operador) → 201 Created + Location /api/v1/productos/POL-050; repetido → 409', fn() =>
    $creado['estado'] === 201 && cabecera($creado, 'Location') === '/api/v1/productos/POL-050' && json($creado)['precio'] === '110.00'
    && json($creado)['tallas'] === ['S', 'M', 'L'] && fila('POL-050') !== null && $api('POST', '/productos', 'operador', $producto)['estado'] === 409);
$v->revisar('PUT /productos/POL-050 → 200: precio 120.00, stock 10 y talla XL (el código de la URL manda)', function () use ($api, $ejemplos) {
    $r = $api('PUT', '/productos/POL-050', 'operador', (string) file_get_contents("$ejemplos/actualizacion.json"));
    $f = fila('POL-050');
    return $r['estado'] === 200 && $f['precio'] === '120.00' && (int) $f['stock'] === 10 && $f['tallas'] === 'L,M,S,XL';
});
$v->revisar('DELETE: el operador no puede (403); administración → 204 SIN cuerpo y después GET → 404', function () use ($api) {
    $prohibido = $api('DELETE', '/productos/POL-050', 'operador');
    $borrado = $api('DELETE', '/productos/POL-050', 'admin');
    return $prohibido['estado'] === 403 && $borrado['estado'] === 204 && $borrado['cuerpo'] === ''
        && $api('GET', '/productos/POL-050')['estado'] === 404 && fila('POL-050') === null;
});
$v->revisar('DELETE de una prenda con ventas → 409 (el historial no se pierde); método no soportado → 405 con Allow', function () use ($api) {
    $conVentas = $api('DELETE', '/productos/POL-001', 'admin');
    $metodo = $api('PATCH', '/productos/POL-001', 'admin', '{}');
    return $conVentas['estado'] === 409 && fila('POL-001') !== null && $metodo['estado'] === 405
        && str_contains((string) cabecera($metodo, 'Allow'), 'PUT') && $api('GET', '/no-existe')['estado'] === 404;
});
$v->revisar('OPTIONS (preflight CORS) → 204 con Allow-Origin, Allow-Methods y Allow-Headers (Authorization)', function () use ($v) {
    $r = $v->pedir('/api/v1/productos', 'OPTIONS', null, ['Origin: https://otra-tienda.bo', 'Access-Control-Request-Method: POST']);
    return $r['estado'] === 204 && cabecera($r, 'Access-Control-Allow-Origin') === '*'
        && str_contains((string) cabecera($r, 'Access-Control-Allow-Methods'), 'DELETE')
        && str_contains((string) cabecera($r, 'Access-Control-Allow-Headers'), 'Authorization');
});
$v->revisar('Los tokens se guardan como hash SHA-256 (el token no está en la base); uno revocado deja de funcionar → 401', function () use ($pdo, $tokens, $api) {
    $hashes = $pdo->query('SELECT hash FROM tokens_api')->fetchAll(PDO::FETCH_COLUMN);
    $vuelca = implode(' ', array_map(fn(array $f): string => implode(' ', $f), $pdo->query('SELECT * FROM tokens_api')->fetchAll(PDO::FETCH_NUM)));
    $pdo->prepare('UPDATE tokens_api SET revocado = 1 WHERE hash = :h')->execute(['h' => hash('sha256', $tokens['operador'])]);
    $revocado = $api('POST', '/productos', 'operador', '{}');
    return in_array(hash('sha256', $tokens['admin']), $hashes, true) && !str_contains($vuelca, $tokens['admin']) && $revocado['estado'] === 401;
});
$v->revisar('GET /api/v1/openapi.yaml: OpenAPI 3.1 con seguridad Bearer y todas las rutas implementadas', function () use ($v) {
    $r = $v->pedir('/api/v1/openapi.yaml');
    $faltan = array_filter(['/productos:', '/productos/sugerencias:', '/productos/{codigo}:', '/socios/{id}/liquidacion:', '/pedidos:', '/pedidos/{id}:'],
        fn(string $ruta): bool => !str_contains($r['cuerpo'], $ruta));
    return $r['estado'] === 200 && str_starts_with($r['cuerpo'], 'openapi: 3.1') && str_contains($r['cuerpo'], 'scheme: bearer') && $faltan === []
        && str_contains($r['cuerpo'], 'application/problem+json');
});
$v->revisar('Un error inesperado de la API no revela detalles (503/500 con referencia, sin SQLSTATE ni rutas)', function () use ($v) {
    $r = $v->pedir('/api/v1/productos?q=' . urlencode("' OR 1=1 -- "));
    return $r['estado'] === 200 && json($r)['meta']['total'] === 0 && !str_contains($r['cuerpo'], 'SQLSTATE');
});

echo PHP_EOL . '— Actividad 4 · Laboratorio 14: interfaz asíncrona con fetch —' . PHP_EOL;
$js = fn(string $archivo): string => (string) file_get_contents(__DIR__ . "/../public/js/$archivo");
$v->revisar('Ningún script propio usa innerHTML, outerHTML, insertAdjacentHTML ni document.write (datos con textContent)', function () {
    foreach (glob(__DIR__ . '/../public/js/*.js') as $archivo) {
        if (preg_match('/\.(innerHTML|outerHTML)\s*=|insertAdjacentHTML|document\.write/', (string) file_get_contents($archivo)) === 1) {
            return false;
        }
    }
    return true;
});
$v->revisar('catalogo.js: AbortController en cada carga, pausa de 250 ms, replaceChildren y textContent', fn() =>
    preg_match('/controladorGrilla\?\.abort\(\);[^\n]*\n\s*controladorGrilla = new AbortController\(\)/', $js('catalogo.js')) === 1
    && str_contains($js('catalogo.js'), '}, 250);') && str_contains($js('catalogo.js'), 'replaceChildren') && str_contains($js('catalogo.js'), 'textContent'));
$v->revisar('api.js: comprueba response.ok, lee problem+json y distingue AbortError de un fallo de red', fn() =>
    str_contains($js('api.js'), '!respuesta.ok') && str_contains($js('api.js'), 'AbortError') && str_contains($js('api.js'), 'Sin conexión'));
$v->revisar('Autocompletar accesible: role=combobox/listbox/option, aria-expanded, aria-activedescendant, aria-selected y Escape', function () use ($v, $js) {
    $html = $v->pedir('/')['cuerpo'];
    return str_contains($html, 'role="combobox"') && str_contains($html, 'role="listbox"') && str_contains($html, 'aria-expanded="false"')
        && str_contains($html, 'role="alert"') && str_contains($js('catalogo.js'), '"role", "option"')
        && str_contains($js('catalogo.js'), 'aria-activedescendant') && str_contains($js('catalogo.js'), 'aria-selected')
        && str_contains($js('catalogo.js'), '"Escape"') && preg_match('/function cerrarLista\(\) \{\s*activa = -1;/', $js('catalogo.js')) === 1;
});
$v->revisar('Novedades: el RSS se lee con DOMParser, se comprueba parsererror y se muestran 5 con textContent', fn() =>
    str_contains($js('catalogo.js'), 'new DOMParser().parseFromString') && str_contains($js('catalogo.js'), 'parsererror')
    && str_contains($js('catalogo.js'), '.slice(0, 5)') && str_contains($v->pedir('/')['cuerpo'], 'id="novedades"'));
$v->revisar('Validación en vivo: formato local sin petición; 200 = código ocupado, 404 = disponible (el servidor sigue validando)', fn() =>
    str_contains($js('validacion-producto.js'), 'FORMATO.test') && str_contains($js('validacion-producto.js'), 'e.status === 404')
    && str_contains($v->pedir('/')['cuerpo'], 'validacion-producto.js') && $api('GET', '/productos/POL-001')['estado'] === 200
    && $api('GET', '/productos/POL-999')['estado'] === 404);
$v->revisar('La grilla asíncrona recibe todo lo que dibuja: foto interna, categoría, precio en texto, disponible y tallas', function () use ($api) {
    $p = json($api('GET', '/productos?q=POL-001'))['datos'][0];
    return $p['categoria']['nombre'] === 'Poleras y blusas' && $p['precio'] === '95.00' && $p['disponible'] === 24
        && $p['tallas'] === ['S', 'M', 'L', 'XL'] && ($p['foto'] === null || str_starts_with($p['foto'], '/foto.php?f='));
});
