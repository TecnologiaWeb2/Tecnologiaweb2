<?php
// src/Intercambio/ImportadorPedidos.php — Convierte un pedido (XML o JSON) en una venta, usando la transacción del lab 11.
declare(strict_types=1);

namespace Apicultores\Intercambio;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Ventas\RegistroVenta;

final class ImportadorPedidos
{
    public function __construct(
        private readonly PedidoXml $xml,
        private readonly PedidoJson $json,
        private readonly RegistroVenta $ventas,
    ) {
    }

    /** Elige el lector según el CONTENIDO (no según la extensión del nombre). @return int id de la venta */
    public function importar(string $contenido, int $usuarioId): int
    {
        $inicio = ltrim($contenido, "\xEF\xBB\xBF \t\r\n");
        $pedido = match ($inicio[0] ?? '') {
            '<' => $this->xml->leer($contenido),
            '{' => $this->json->leer($contenido),
            default => throw new DatosInvalidos(['general' => 'Formato desconocido: el pedido debe empezar con "<" (XML) o "{" (JSON).']),
        };
        return $this->ventas->registrar($pedido['lineas'], "{$pedido['cliente']} (pedido {$pedido['numero']})", $usuarioId);
    }
}
