<?php
// src/Reportes/Csv.php — Exportación a CSV que Excel en español abre bien y que no ejecuta fórmulas.
declare(strict_types=1);

namespace Apicultores\Reportes;

final class Csv
{
    /** Genera el contenido CSV (separador ";" y coma decimal, como espera Excel configurado en español). */
    public static function generar(array $encabezados, array $filas): string
    {
        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, "\xEF\xBB\xBF");                       // BOM: Excel reconoce UTF-8 (tildes y ñ)
        fputcsv($flujo, $encabezados, ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($flujo, array_map(self::celdaSegura(...), array_values($fila)), ';', '"', '');
        }
        rewind($flujo);
        return (string) stream_get_contents($flujo);
    }

    /**
     * Inyección CSV: un cliente llamado "=HYPERLINK(...)" se convertiría en FÓRMULA al abrir el archivo en Excel.
     * Se antepone un apóstrofo a los textos que empiezan con = + - @ (o tabulador / retorno).
     */
    public static function celdaSegura(mixed $valor): string
    {
        if (is_float($valor) || (is_string($valor) && preg_match('/^-?\d+\.\d+$/', $valor))) {
            return str_replace('.', ',', (string) $valor);     // 45.50 → 45,50
        }
        // TODO 6: si el texto empieza con = + - @ (o tabulador / retorno), antepone un apóstrofo (').
        $texto = (string) $valor;
        return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'" . $texto : $texto;
    }
}
