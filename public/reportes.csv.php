<?php
// public/reportes.csv.php — Laboratorio 11: el reporte de ventas en CSV (UTF-8 con BOM y «;» para que Excel lo abra bien).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_usuario('reportes.ver');
try {
    $r = reporte_ventas(Conexion::obtener(), texto($_GET, 'desde', '2026-07-01'), texto($_GET, 'hasta', date('Y-m-d')));
} catch (InvalidArgumentException $e) {
    responder_error(422, 'Fechas inválidas', $e->getMessage());
}

/** Evita la «inyección de fórmulas»: una celda que empieza con = + - @ se abre en Excel como fórmula. */
function celda_csv(string $valor): string
{
    return preg_match('/^[=+\-@\t\r]/', $valor) === 1 ? "'" . $valor : $valor;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte-ventas-' . texto($_GET, 'desde', '2026-07-01') . '.csv"');
header('Cache-Control: no-store');
$salida = fopen('php://output', 'w');
fwrite($salida, "\u{FEFF}");                                    // BOM: Excel reconoce las tildes
fputcsv($salida, ['Sección', 'Concepto', 'Cantidad', 'Importe (Bs)'], ';', '"', '');
fputcsv($salida, ['Resumen', 'Ventas', (string) $r['resumen']['ventas'], number_format((float) $r['resumen']['total'], 2, ',', '')], ';', '"', '');
foreach ($r['porMes'] as $f) {
    fputcsv($salida, ['Por mes', $f['mes'], (string) $f['ventas'], number_format((float) $f['total'], 2, ',', '')], ';', '"', '');
}
foreach ($r['top'] as $f) {
    fputcsv($salida, ['Más vendidas', celda_csv($f['codigo'] . ' ' . $f['nombre']), (string) $f['unidades'], number_format((float) $f['importe'], 2, ',', '')], ';', '"', '');
}
foreach ($r['porSocio'] as $f) {
    fputcsv($salida, ['Por socia', celda_csv($f['socio']), (string) $f['unidades'], number_format((float) $f['importe'], 2, ',', '')], ';', '"', '');
}
fclose($salida);
