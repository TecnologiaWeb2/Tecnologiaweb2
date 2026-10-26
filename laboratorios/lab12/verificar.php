<?php
// verificar.php — Comprueba el Laboratorio 12 (JSON, XML con XSD, RSS, SOAP frente a REST, importación). Uso: php verificar.php
// Requiere MySQL en marcha (docker compose up -d) y la extensión soap. Restaura los datos al empezar y al terminar.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    exit("❌ Falta vendor/autoload.php. Primero ejecuta:  composer dump-autoload" . PHP_EOL);
}
require __DIR__ . '/vendor/autoload.php';

use Apicultores\BaseDatos\Conexion;
use Apicultores\Excepciones\{Conflicto, DatosInvalidos};
use Apicultores\Intercambio\{ImportadorPedidos, PedidoJson, PedidoXml};
use Apicultores\Ventas\RegistroVenta;

try {
    $bd = Conexion::crear(require __DIR__ . '/config/bd.php');
} catch (PDOException $e) {
    exit("❌ No hay conexión con MySQL ({$e->getMessage()}).\n   Inicia la base de datos:  docker compose up -d" . PHP_EOL);
}
function restaurar(PDO $bd): void
{
    foreach (['01-esquema.sql', '02-datos.sql', '03-mas-datos.sql', '04-usuarios.sql', '05-movimientos.sql'] as $archivo) {
        $bd->exec((string) file_get_contents(__DIR__ . "/sql/$archivo"));
    }
}
function stock(PDO $bd, string $codigo): int
{
    $c = $bd->prepare('SELECT stock FROM productos WHERE codigo = ?');
    $c->execute([$codigo]);
    return (int) $c->fetchColumn();
}
/** Mensaje de DatosInvalidos (o null si no se lanzó) */
function errorDe(callable $f): ?string
{
    try { $f(); } catch (DatosInvalidos $e) { return implode(' ', $e->errores()); }
    return null;
}
function ejemplo(string $nombre): string
{
    return (string) file_get_contents(__DIR__ . "/ejemplos/$nombre");
}
function token(string $html): ?string
{
    return preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : null;
}
function entrar(Verificador $v, string $correo, string $clave): void
{
    $v->olvidarCookies();
    $v->pedir('/entrar', 'POST', ['_csrf' => token($v->pedir('/entrar')['cuerpo']), 'correo' => $correo, 'clave' => $clave]);
}
restaurar($bd);
$v = new Verificador();
$xml = new PedidoXml();
$json = new PedidoJson();

// --- Lectura de pedidos ---
$v->revisar('Pedido XML válido: pasa el XSD y da MIE-500 × 12 y PRO-30 × 6', fn() =>
    $xml->leer(ejemplo('pedido-supermercado.xml'))['lineas'] === ['MIE-500' => 12, 'PRO-30' => 6]);
$v->revisar('Pedido XML inválido: el XSD informa fecha, código y cantidad, con número de línea', function () use ($xml) {
    $m = (string) errorDe(fn() => $xml->leer(ejemplo('pedido-invalido.xml')));
    return str_contains($m, 'no cumple el esquema') && str_contains($m, 'Línea 5') && str_contains($m, 'Línea 6')
        && str_contains($m, "'2026-02-31'") && str_contains($m, "'mie-500'");
});
$v->revisar('XML mal formado (etiqueta sin cerrar) → "no es XML bien formado"', fn() =>
    str_contains((string) errorDe(fn() => $xml->leer('<pedido numero="1"><cliente>X</pedido>')), 'no es XML bien formado'));
$v->revisar('Pedido JSON válido → POL-250 × 2 y CRE-MIE × 5', fn() =>
    $json->leer(ejemplo('pedido-tienda.json'))['lineas'] === ['POL-250' => 2, 'CRE-MIE' => 5]);
$v->revisar('Pedido JSON inválido: fecha, cantidad "2" (texto) y 2.5 (decimal) rechazadas', function () use ($json) {
    $m = (string) errorDe(fn() => $json->leer(ejemplo('pedido-invalido.json')));
    return str_contains($m, '"fecha"') && str_contains($m, 'Línea 1') && str_contains($m, 'Línea 2');
});
$v->revisar('JSON con sintaxis rota o anidado más de 8 niveles → DatosInvalidos', fn() =>
    str_contains((string) errorDe(fn() => $json->leer('{"numero": "1",')), 'no es JSON válido')
    && str_contains((string) errorDe(fn() => $json->leer(str_repeat('[', 20) . str_repeat(']', 20))), 'no es JSON válido'));

$importador = new ImportadorPedidos($xml, $json, new RegistroVenta($bd));
$v->revisar('Importador: detecta el formato por el CONTENIDO y registra la venta (stock 32 → 20)', function () use ($importador, $bd) {
    $id = $importador->importar("\xEF\xBB\xBF" . ejemplo('pedido-supermercado.xml'), 2);
    $c = $bd->prepare('SELECT cliente FROM ventas WHERE id = ?');
    $c->execute([$id]);
    return stock($bd, 'MIE-500') === 20 && $c->fetchColumn() === 'Supermercado El Sol (pedido ES-2026-0412)'
        && is_int($importador->importar(ejemplo('pedido-tienda.json'), 2)) && stock($bd, 'CRE-MIE') === 11;
});
$v->revisar('Importador: sin stock suficiente → Conflicto y la venta no se registra (transacción)', function () use ($importador, $bd) {
    $ventas = (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
    try {
        $importador->importar('{"numero":"X","cliente":"Grande","fecha":"2026-10-02","lineas":[{"codigo":"MIE-500","cantidad":5},{"codigo":"CER-BLO","cantidad":9}]}', 2);
    } catch (Conflicto) {
        return stock($bd, 'MIE-500') === 20 && (int) $bd->query('SELECT COUNT(*) FROM ventas')->fetchColumn() === $ventas;
    }
    return false;
});

// --- Datos abiertos por HTTP ---
restaurar($bd);
$v->servidor(__DIR__ . '/public', __DIR__ . '/public/index.php');
$cj = $v->pedir('/datos/catalogo.json');
$datos = json_decode($cj['cuerpo'], true);
$v->revisar('catalogo.json: application/json, CORS abierto, 36 productos, precio como texto y fecha ISO 8601', fn() =>
    str_contains($cj['cabeceras'], 'Content-Type: application/json') && str_contains($cj['cabeceras'], 'Access-Control-Allow-Origin: *')
    && count($datos['productos']) === 36 && $datos['productos'][0]['precio'] === '15.00'
    && DateTimeImmutable::createFromFormat(DATE_ATOM, $datos['generado']) !== false && str_contains($cj['cuerpo'], 'Poñé'));
$cx = $v->pedir('/datos/catalogo.xml');
$v->revisar('catalogo.xml: XML bien formado con los MISMOS 36 códigos y precios que el JSON; & escapado', function () use ($cx, $datos) {
    $x = simplexml_load_string($cx['cuerpo']);
    $deXml = [];
    foreach ($x->producto as $p) {
        $deXml[(string) $p['codigo']] = (string) $p->precio;
    }
    return $deXml === array_column($datos['productos'], 'precio', 'codigo') && str_contains($cx['cuerpo'], 'Polen &amp; propóleo')
        && (string) $x->producto[0]->nombre === 'Bálsamo labial de cera';
});
$rss = $v->pedir('/noticias.rss');
$v->revisar('noticias.rss: RSS 2.0 con canal y 10 ítems, guid y pubDate en formato RFC 822', function () use ($rss) {
    $x = simplexml_load_string($rss['cuerpo']);
    $fecha = (string) $x->channel->item[0]->pubDate;
    return str_contains($rss['cabeceras'], 'application/rss+xml') && (string) $x['version'] === '2.0'
        && count($x->channel->item) === 10 && DateTimeImmutable::createFromFormat(DATE_RSS, $fecha) !== false
        && str_starts_with((string) $x->channel->item[0]->guid, 'acopio-');
});
$rest = $v->pedir('/datos/productos/MIE-500');
$v->revisar('SOAP y REST devuelven el mismo dato; la respuesta SOAP es más de 5 veces más grande', function () use ($v, $rest) {
    $cliente = new SoapClient(null, ['location' => $v->url('/soap'), 'uri' => 'urn:apicultores-precios', 'trace' => true]);
    $r = $cliente->consultarProducto('MIE-500');
    return $r === json_decode($rest['cuerpo'], true) && strlen((string) $cliente->__getLastResponse()) > 5 * strlen($rest['cuerpo']);
});
$v->revisar('Errores: SOAP responde un Fault "Client"; REST responde 404', function () use ($v) {
    $cliente = new SoapClient(null, ['location' => $v->url('/soap'), 'uri' => 'urn:apicultores-precios']);
    try {
        $cliente->consultarProducto('NO-EXISTE');
        return false;
    } catch (SoapFault $f) {
        return str_contains($f->faultcode, 'Client') && $v->pedir('/datos/productos/NO-EXISTE')['estado'] === 404;
    }
});
entrar($v, 'operador@florchiquitania.bo', 'Colmena-2026!');
$v->revisar('Página de importación: operador importa (303); un XML inválido vuelve con 422 y los errores', function () use ($v, $bd) {
    $ok = $v->pedir('/pedidos/importar', 'POST', ['_csrf' => token($v->pedir('/pedidos/importar')['cuerpo']), 'contenido' => ejemplo('pedido-supermercado.xml')]);
    $mal = $v->pedir('/pedidos/importar', 'POST', ['_csrf' => token($v->pedir('/pedidos/importar')['cuerpo']), 'contenido' => ejemplo('pedido-invalido.xml')]);
    return $ok['estado'] === 303 && stock($bd, 'MIE-500') === 20 && $mal['estado'] === 422 && str_contains($mal['cuerpo'], 'no cumple el esquema');
});
entrar($v, 'elena@florchiquitania.bo', 'Polen-2026!');
$v->revisar('Socio (Elena) no puede importar pedidos → 403', fn() => $v->pedir('/pedidos/importar')['estado'] === 403);
@unlink(__DIR__ . '/datos/errores.log');
restaurar($bd);
exit($v->fin());
