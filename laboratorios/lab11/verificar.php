<?php
// verificar.php — Comprueba el Laboratorio 11 (transacciones, acopios, reportes con JOIN/GROUP BY/HAVING, CSV). Uso: php verificar.php
// Requiere MySQL en marcha (docker compose up -d). Restaura los datos al empezar y al terminar.
// Los reportes SQL se comparan con un cálculo INDEPENDIENTE hecho en PHP sobre las filas sin agrupar.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    exit("❌ Falta vendor/autoload.php. Primero ejecuta:  composer dump-autoload" . PHP_EOL);
}
require __DIR__ . '/vendor/autoload.php';

use Apicultores\BaseDatos\Conexion;
use Apicultores\Excepciones\{Conflicto, DatosInvalidos};
use Apicultores\Reportes\{Csv, ReporteVentas};
use Apicultores\Ventas\{RegistroAcopio, RegistroVenta};

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
function valor(PDO $bd, string $sql): mixed
{
    return $bd->query($sql)->fetchColumn();
}
function lanza(string $clase, callable $f): bool
{
    try { $f(); } catch (Throwable $e) { return $e instanceof $clase; }
    return false;
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
$reporte = new ReporteVentas($bd);
$trim = [new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-09-30')];

// Cálculos independientes (PHP, sin GROUP BY) sobre las filas de ventas y detalle
$filas = $bd->query('SELECT v.fecha, v.total, d.cantidad, d.precio_unitario, p.codigo, s.nombre AS socio
                     FROM ventas v JOIN venta_detalle d ON d.venta_id = v.id JOIN productos p ON p.id = d.producto_id
                     JOIN socios s ON s.id = p.socio_id')->fetchAll();
$mes = $prod = $soc = [];
foreach ($bd->query('SELECT fecha, total FROM ventas')->fetchAll() as $f) {
    $m = substr($f['fecha'], 0, 7);
    $mes[$m] = ($mes[$m] ?? 0) + (int) round((float) $f['total'] * 100);
}
foreach ($filas as $f) {
    $centavos = $f['cantidad'] * (int) round((float) $f['precio_unitario'] * 100);
    $prod[$f['codigo']] = ($prod[$f['codigo']] ?? 0) + $centavos;
    $soc[$f['socio']] = ($soc[$f['socio']] ?? 0) + $centavos;
}
ksort($mes);
uksort($prod, fn($a, $b) => [$prod[$b], $a] <=> [$prod[$a], $b]);
arsort($soc);

$v->revisar('Esquema: ventas, venta_detalle (clave compuesta) y acopios con sus claves foráneas', fn() =>
    (int) valor($bd, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('ventas','venta_detalle','acopios')") === 3
    && (int) valor($bd, "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venta_detalle' AND CONSTRAINT_NAME = 'PRIMARY'") === 2);
$v->revisar('Reporte por mes = suma independiente en PHP (jul, ago, sep)', function () use ($reporte, $trim, $mes) {
    $r = $reporte->porMes(...$trim);
    return array_column($r, 'mes') === array_keys($mes)
        && array_map(fn($f) => (int) round((float) $f['total'] * 100), $r) === array_values($mes);
});
$v->revisar('Top 5 productos (JOIN + SUM + ORDER BY + LIMIT) = cálculo independiente', fn() =>
    array_column($reporte->topProductos(...$trim), 'codigo') === array_slice(array_keys($prod), 0, 5));
$v->revisar('Socios con Bs 3.000 o más (HAVING): 4 de 8 = cálculo independiente', function () use ($reporte, $trim, $soc) {
    $esperado = array_keys(array_filter($soc, fn($c) => $c >= 300000));
    return array_column($reporte->porSocio($trim[0], $trim[1], 3000), 'socio') === $esperado && count($esperado) === 4;
});
$v->revisar('Productos sin ventas (LEFT JOIN … IS NULL): CER-BLO, JAL-30, MIE-OR, VIN-MIE', fn() =>
    array_column($reporte->sinVentas(...$trim), 'codigo') === ['CER-BLO', 'JAL-30', 'MIE-OR', 'VIN-MIE']);
$v->revisar('El período incluye el último día completo (ventas del 30/09 entran con hasta=2026-09-30)', function () use ($reporte, $bd) {
    $dia = (int) valor($bd, "SELECT COUNT(*) FROM ventas WHERE DATE(fecha) = '2026-09-30'");
    $r = $reporte->porMes(new DateTimeImmutable('2026-09-30'), new DateTimeImmutable('2026-09-30'));
    return $dia > 0 && (int) ($r[0]['ventas'] ?? 0) === $dia;
});

$ventas = new RegistroVenta($bd);
$antes = [(int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'MIE-500'"), (int) valor($bd, 'SELECT COUNT(*) FROM ventas')];
$v->revisar('Transacción: si una línea falla (99 CER-1K), NO se descuenta la otra ni se crea la venta', fn() =>
    lanza(Conflicto::class, fn() => $ventas->registrar(['MIE-500' => 2, 'CER-1K' => 99], 'Prueba', 1))
    && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'MIE-500'") === $antes[0]
    && (int) valor($bd, 'SELECT COUNT(*) FROM ventas') === $antes[1] && !$bd->inTransaction());
$v->revisar('Venta correcta: descuenta stock, total 128.00 y guarda el precio unitario del día', function () use ($ventas, $bd) {
    $id = $ventas->registrar(['MIE-500' => 2, 'PRO-30' => 1], 'Feria Roboré', 1);
    return (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'MIE-500'") === 30
        && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'PRO-30'") === 24
        && valor($bd, "SELECT total FROM ventas WHERE id = $id") === '128.00'
        && (int) valor($bd, "SELECT COUNT(*) FROM venta_detalle WHERE venta_id = $id") === 2;
});
$v->revisar('Últimas unidades: vender 3 CER-1K (hay 4) funciona; otra vez 3 → Conflicto y el stock queda en 1', fn() =>
    is_int($ventas->registrar(['CER-1K' => 3], 'A', 1)) && lanza(Conflicto::class, fn() => $ventas->registrar(['CER-1K' => 3], 'B', 1))
    && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'CER-1K'") === 1);
$v->revisar('Acopio: +10 CER-1K sube el stock y registra al socio dueño; cantidad 0 → DatosInvalidos', fn() =>
    (new RegistroAcopio($bd))->registrar('CER-1K', 10, '84.00') > 0 && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'CER-1K'") === 11
    && (int) valor($bd, 'SELECT socio_id FROM acopios ORDER BY id DESC LIMIT 1') === 2
    && lanza(DatosInvalidos::class, fn() => (new RegistroAcopio($bd))->registrar('CER-1K', 0, '84.00')));
$v->revisar('Csv::celdaSegura: =, +, - y @ al inicio se neutralizan; decimales con coma', fn() =>
    Csv::celdaSegura('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && Csv::celdaSegura('@SUMA(A1)') === "'@SUMA(A1)"
    && Csv::celdaSegura('45.50') === '45,50' && Csv::celdaSegura('Feria Concepción') === 'Feria Concepción');

$v->servidor(__DIR__ . '/public', __DIR__ . '/public/index.php');
entrar($v, 'operador@florchiquitania.bo', 'Colmena-2026!');
$v->revisar('Operador: registra una venta por el formulario (303) pero /reportes → 403', function () use ($v, $bd) {
    $r = $v->pedir('/ventas', 'POST', ['_csrf' => token($v->pedir('/ventas/nueva')['cuerpo']), 'cliente' => '=HYPERLINK("http://ejemplo.invalid")',
        'codigo[0]' => 'MIE-1K', 'cantidad[0]' => '1', 'codigo[1]' => '', 'cantidad[1]' => '']);
    return $r['estado'] === 303 && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'MIE-1K'") === 17
        && $v->pedir('/reportes')['estado'] === 403;
});
$v->revisar('Formulario: stock insuficiente → 409 y nada cambia', function () use ($v, $bd) {
    $r = $v->pedir('/ventas', 'POST', ['_csrf' => token($v->pedir('/ventas/nueva')['cuerpo']), 'cliente' => 'X',
        'codigo[0]' => 'MIE-1K', 'cantidad[0]' => '1', 'codigo[1]' => 'JAL-30', 'cantidad[1]' => '500']);
    return $r['estado'] === 409 && (int) valor($bd, "SELECT stock FROM productos WHERE codigo = 'MIE-1K'") === 17;
});
entrar($v, 'admin@florchiquitania.bo', 'Panal-2026!');
$v->revisar('Admin: /reportes muestra las 4 tablas; fechas inválidas (2026-02-31, desde > hasta) → 422', function () use ($v) {
    $r = $v->pedir('/reportes');
    return $r['estado'] === 200 && str_contains($r['cuerpo'], 'id="por-mes"') && str_contains($r['cuerpo'], 'id="sin-ventas"')
        && $v->pedir('/reportes?desde=2026-02-31')['estado'] === 422
        && $v->pedir('/reportes?desde=2026-09-01&hasta=2026-08-01')['estado'] === 422;
});
$csv = $v->pedir('/reportes/ventas.csv?desde=2026-07-01&hasta=2026-12-31');
$v->revisar('CSV: text/csv + attachment, BOM, ";" y una línea por detalle; el cliente "=HYPERLINK" queda neutralizado', function () use ($csv, $bd) {
    $lineas = array_values(array_filter(explode("\n", $csv['cuerpo'])));
    return str_contains($csv['cabeceras'], 'Content-Type: text/csv; charset=utf-8')
        && str_contains($csv['cabeceras'], 'attachment; filename="ventas_20260701_20261231.csv"')
        && str_starts_with($csv['cuerpo'], "\xEF\xBB\xBFVenta;Fecha;Cliente")
        && count($lineas) === 1 + (int) valor($bd, 'SELECT COUNT(*) FROM venta_detalle')
        && str_contains($csv['cuerpo'], "'=HYPERLINK") && !preg_match('/;"?=HYPERLINK/', $csv['cuerpo']);
});
@unlink(__DIR__ . '/datos/errores.log');
restaurar($bd);
exit($v->fin());
