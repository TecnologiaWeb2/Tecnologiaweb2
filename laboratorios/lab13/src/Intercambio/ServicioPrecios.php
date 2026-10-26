<?php
// src/Intercambio/ServicioPrecios.php — Servicio SOAP (comparación con REST). Cada método público es una "operación".
// Un supermercado con un sistema antiguo (SOAP) consulta precio y stock; los sistemas nuevos usan el JSON/REST.
declare(strict_types=1);

namespace Apicultores\Intercambio;

use Apicultores\Repositorios\ProductoRepositorio;
use SoapFault;

final class ServicioPrecios
{
    public function __construct(private readonly ProductoRepositorio $productos)
    {
    }

    /** @return array{codigo: string, nombre: string, precio: string, stock: int} */
    public function consultarProducto(string $codigo): array
    {
        $p = $this->productos->porCodigo(strtoupper(trim($codigo)))
            ?? throw new SoapFault('Client', "No existe el producto $codigo.");   // error SOAP: <soap:Fault>
        return ['codigo' => $p->codigo, 'nombre' => $p->nombre, 'precio' => $p->precio, 'stock' => $p->stock];
    }
}
