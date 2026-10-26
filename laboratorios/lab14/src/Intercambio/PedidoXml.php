<?php
// src/Intercambio/PedidoXml.php — Lee el pedido XML de un supermercado: primero VALIDA contra el XSD, después lo usa.
declare(strict_types=1);

namespace Apicultores\Intercambio;

use Apicultores\Excepciones\DatosInvalidos;
use DOMDocument;

final class PedidoXml
{
    public const ESQUEMA = __DIR__ . '/../../esquemas/pedido.xsd';

    /**
     * @return array{numero: string, cliente: string, fecha: string, lineas: array<string, int>}
     * @throws DatosInvalidos con los errores del validador (línea y mensaje)
     */
    public function leer(string $xml): array
    {
        $anterior = libxml_use_internal_errors(true);       // capturamos los errores en lugar de mostrar avisos
        try {
            $doc = new DOMDocument();
            if (!$doc->loadXML($xml, LIBXML_NONET)) {        // LIBXML_NONET: el analizador nunca sale a la red
                throw new DatosInvalidos(['general' => 'El archivo no es XML bien formado. ' . $this->errores()]);
            }
            if (!$doc->schemaValidate(self::ESQUEMA)) {
                throw new DatosInvalidos(['general' => 'El pedido no cumple el esquema. ' . $this->errores()]);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $raiz = simplexml_import_dom($doc);
        $lineas = [];
        foreach ($raiz->linea as $l) {
            $codigo = (string) $l['codigo'];
            $lineas[$codigo] = ($lineas[$codigo] ?? 0) + (int) $l['cantidad'];
        }
        return ['numero' => (string) $raiz['numero'], 'cliente' => trim((string) $raiz->cliente),
                'fecha' => (string) $raiz->fecha, 'lineas' => $lineas];
    }

    private function errores(): string
    {
        return implode(' ', array_map(fn($e) => sprintf('Línea %d: %s', $e->line, trim($e->message)), libxml_get_errors()));
    }
}
