<?php
// verificar.php — Comprueba el Laboratorio 14 (fetch/AJAX: sugerencias, catálogo asíncrono, validación en vivo, RSS).
// Uso: php verificar.php    Requiere MySQL (docker compose up -d). Si hay Node + Chrome + "npm install", agrega pruebas en el navegador.
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
restaurar($bd);
// Un producto con HTML en el nombre: el catálogo asíncrono debe mostrarlo como texto
$bd->exec("INSERT INTO productos (codigo, nombre, categoria, precio, stock, socio_id) VALUES ('PRB-01', '<b>Miel</b> de prueba', 'Miel', 20.00, 3, 1)");

$v = new Verificador();
$v->servidor(__DIR__ . '/public', __DIR__ . '/public/index.php');
$js = fn(string $archivo): string => (string) file_get_contents(__DIR__ . "/public/js/$archivo");

$sug = $v->pedir('/api/v1/productos/sugerencias?q=jab');
$v->revisar('API de sugerencias: hasta 8 resultados mínimos (código, nombre, precio) con caché de 60 s', function () use ($sug, $v) {
    $d = json_decode($sug['cuerpo'], true);
    return $sug['estado'] === 200 && array_column($d, 'codigo') === ['JAB-MIE', 'JAB-PRO'] && array_keys($d[0]) === ['codigo', 'nombre', 'precio']
        && str_contains($sug['cabeceras'], 'Cache-Control: public, max-age=60')
        && count(json_decode($v->pedir('/api/v1/productos/sugerencias?q=mi')['cuerpo'] ?? '[]', true) ?? []) <= 8;
});
$v->revisar('Sugerencias con menos de 2 caracteres → 422 problem+json; la ruta no se confunde con un código', fn() =>
    $v->pedir('/api/v1/productos/sugerencias?q=m')['estado'] === 422 && $v->pedir('/api/v1/productos/MIE-500')['estado'] === 200);
$pag = $v->pedir('/catalogo');
$v->revisar('/catalogo: cascarón accesible (combobox, aria-live, tabla con aria-busy) y script como módulo externo', fn() =>
    $pag['estado'] === 200 && str_contains($pag['cuerpo'], 'role="combobox"') && str_contains($pag['cuerpo'], 'aria-controls="sugerencias"')
    && str_contains($pag['cuerpo'], 'id="resumen" aria-live="polite"') && str_contains($pag['cuerpo'], '<script type="module" src="/js/catalogo.js">')
    && !preg_match('/<script(?![^>]*src)[^>]*>/', $pag['cuerpo']));          // sin JavaScript en línea (lo prohíbe la CSP)
$v->revisar('Los módulos JS se sirven como JavaScript', fn() =>
    preg_match('#Content-Type: (text|application)/javascript#i', $v->pedir('/js/catalogo.js')['cabeceras']) === 1);
$sinComentarios = fn(string $codigo): string => (string) preg_replace('#//.*$#m', '', $codigo);
$v->revisar('catalogo.js usa textContent y NUNCA innerHTML/insertAdjacentHTML con datos', fn() =>
    !preg_match('/innerHTML|insertAdjacentHTML|outerHTML|document\.write/', $sinComentarios($js('catalogo.js') . $js('api.js') . $js('validacion-producto.js')))
    && substr_count($js('catalogo.js'), 'textContent') >= 4);
$v->revisar('catalogo.js cancela peticiones viejas con AbortController y espera a que el usuario pare de escribir', fn() =>
    str_contains($js('catalogo.js'), 'controladorGrilla?.abort()') && str_contains($js('catalogo.js'), 'new AbortController()')
    && str_contains($js('api.js'), 'clearTimeout'));
$v->revisar('api.js convierte problem+json en ErrorApi (status, title, detail, errores)', fn() =>
    str_contains($js('api.js'), 'class ErrorApi extends Error') && str_contains($js('api.js'), '!respuesta.ok'));
$v->revisar('El formulario de producto carga la validación en vivo (que usa GET /api/v1/productos/{código}: 404 = libre)', fn() =>
    str_contains((string) file_get_contents(__DIR__ . '/plantillas/formulario.php'), '/js/validacion-producto.js')
    && str_contains($js('validacion-producto.js'), 'e.status === 404'));

// Pruebas en el navegador (opcionales si no hay Node): node probar_navegador.mjs <url>
$salida = [];
$codigo = 1;
// ¿Node responde? (se ejecuta "node -v": es más fiable que buscarlo con where/command -v)
$versionNodo = [];
$codigoNodo = 1;
@exec('node -v 2>&1', $versionNodo, $codigoNodo);
$nodoOk = $codigoNodo === 0 && isset($versionNodo[0]) && preg_match('/^v(\d+)\./', trim($versionNodo[0]), $mNodo) === 1 && (int) $mNodo[1] >= 18;
$hayPlaywright = is_dir(__DIR__ . '/node_modules/playwright-core') || getenv('PLAYWRIGHT_RUTA');
if ($nodoOk && $hayPlaywright) {
    exec('node ' . escapeshellarg(__DIR__ . '/probar_navegador.mjs') . ' ' . escapeshellarg($v->url()) . ' 2>&1', $salida, $codigo);
    $resultados = json_decode((string) end($salida), true);
    if (!is_array($resultados)) {
        $v->revisar('Pruebas en el navegador  (' . implode(' ', array_slice($salida, -3)) . ')', fn() => false);
    } else {
        foreach ($resultados as $r) {
            $v->revisar($r['descripcion'], fn() => $r['ok']);
        }
    }
} else {
    // Dice POR QUÉ se omiten (así no hay que adivinar)
    echo "⚠️  Pruebas en el navegador omitidas:" . PHP_EOL;
    if (!$nodoOk) {
        echo "   - Node 18+ no responde desde PHP (" . (trim($versionNodo[0] ?? '') ?: 'el comando node no se encontró') . ")." . PHP_EOL
            . "     Ejecuta este script en tu PC (no con  docker compose exec ) y abre una terminal nueva tras instalar Node." . PHP_EOL;
    }
    if (!$hayPlaywright) {
        echo "   - Falta playwright-core: ejecuta  npm install  en esta carpeta." . PHP_EOL;
    }
}
@unlink(__DIR__ . '/datos/errores.log');
restaurar($bd);
exit($v->fin());
