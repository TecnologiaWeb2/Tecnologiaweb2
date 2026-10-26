<?php
// src/Intercambio/PedidoJson.php — El mismo pedido, pero en JSON. Sin esquema XSD: la estructura se valida en código.
declare(strict_types=1);

namespace Apicultores\Intercambio;

use Apicultores\Excepciones\DatosInvalidos;
use JsonException;

final class PedidoJson
{
    /** @return array{numero: string, cliente: string, fecha: string, lineas: array<string, int>} */
    public function leer(string $json): array
    {
        try {
            $d = json_decode($json, true, 8, JSON_THROW_ON_ERROR);   // profundidad máxima 8: nada de anidamientos absurdos
        } catch (JsonException $e) {
            throw new DatosInvalidos(['general' => 'El archivo no es JSON válido: ' . $e->getMessage() . '.']);
        }
        $errores = [];
        if (!is_array($d)) {
            throw new DatosInvalidos(['general' => 'Se esperaba un objeto JSON.']);
        }
        if (!is_string($d['numero'] ?? null) || $d['numero'] === '') {
            $errores[] = 'Falta "numero" (texto).';
        }
        if (!is_string($d['cliente'] ?? null) || mb_strlen(trim($d['cliente'])) < 2) {
            $errores[] = 'Falta "cliente" (texto de al menos 2 caracteres).';
        }
        if (!is_string($d['fecha'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha'])) {
            $errores[] = '"fecha" debe tener el formato AAAA-MM-DD.';
        }
        $lineas = [];
        if (!is_array($d['lineas'] ?? null) || $d['lineas'] === [] || !array_is_list($d['lineas'])) {
            $errores[] = '"lineas" debe ser una lista con al menos un elemento.';
        } else {
            foreach ($d['lineas'] as $i => $l) {
                $codigo = $l['codigo'] ?? null;
                $cantidad = $l['cantidad'] ?? null;
                // is_int: "5" (texto) o 5.5 NO son cantidades válidas — JSON distingue tipos, aprovechémoslo
                if (!is_string($codigo) || !preg_match('/^[A-Z]{3}-[A-Z0-9]{2,4}$/', $codigo) || !is_int($cantidad) || $cantidad < 1 || $cantidad > 1000) {
                    $errores[] = sprintf('Línea %d: se espera {"codigo": "AAA-999", "cantidad": entero de 1 a 1000}.', $i + 1);
                    continue;
                }
                $lineas[$codigo] = ($lineas[$codigo] ?? 0) + (int) $cantidad;
            }
        }
        if ($errores !== []) {
            throw new DatosInvalidos(['general' => implode(' ', $errores)]);
        }
        return ['numero' => $d['numero'], 'cliente' => trim($d['cliente']), 'fecha' => $d['fecha'], 'lineas' => $lineas];
    }
}
