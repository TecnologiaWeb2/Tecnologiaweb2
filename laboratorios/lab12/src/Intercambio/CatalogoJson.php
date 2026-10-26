<?php
// src/Intercambio/CatalogoJson.php — El catálogo en JSON para otros sistemas (una tienda en línea, una app de la feria).
declare(strict_types=1);

namespace Apicultores\Intercambio;

use Apicultores\Modelo\Producto;
use Apicultores\Repositorios\ProductoRepositorio;
use DateTimeImmutable;

final class CatalogoJson
{
    public function __construct(private readonly ProductoRepositorio $productos)
    {
    }

    /** Estructura estable y documentada: versión, fecha ISO 8601 y la lista de productos. */
    public function datos(): array
    {
        $lista = $this->productos->buscar('', null, 'nombre', 1, 1000)->elementos;
        return [
            'version' => 1,
            'generado' => (new DateTimeImmutable('now'))->format(DATE_ATOM),     // 2026-10-01T10:15:00-04:00
            'moneda' => 'BOB',
            'productos' => array_map(fn(Producto $p): array => [
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria->value,
                //         ¿Qué pasaría con 0.1 + 0.2 si otro sistema lo suma como número binario?
                'precio' => $p->precio,   // texto exacto ("45.00"): DECIMAL de MySQL, sin pasar por float
                'disponible' => $p->estaDisponible(),
                'stock' => $p->stock,
                'socio' => ['nombre' => $p->socio->nombre, 'comunidad' => $p->socio->comunidad],
            ], $lista),
        ];
    }

    public function json(): string
    {
        return json_encode($this->datos(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
