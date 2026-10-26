<?php
// src/Ventas/RegistroVenta.php — Registra una venta de VARIOS productos como una sola operación (transacción):
// o se guarda todo (venta, detalle y descuento de stock) o no se guarda nada.
declare(strict_types=1);

namespace Apicultores\Ventas;

use Apicultores\Excepciones\Conflicto;
use Apicultores\Excepciones\DatosInvalidos;
use PDO;
use Throwable;

final class RegistroVenta
{
    public function __construct(private readonly PDO $bd)
    {
    }

    /**
     * @param array<string, int> $lineas código → cantidad
     * @return int id de la venta
     * @throws DatosInvalidos|Conflicto
     */
    public function registrar(array $lineas, string $cliente, int $usuarioId): int
    {
        if ($lineas === [] || trim($cliente) === '') {
            throw new DatosInvalidos(['general' => 'Indica el cliente y al menos un producto con cantidad.']);
        }
        $this->bd->beginTransaction();
        try {
            $buscar = $this->bd->prepare('SELECT id, precio FROM productos WHERE codigo = ?');
            // Descuento ATÓMICO: solo resta si alcanza el stock. Si dos ventas compiten por las últimas unidades,
            // MySQL bloquea la fila y la segunda ve el stock ya descontado (rowCount = 0).
            $descontar = $this->bd->prepare('UPDATE productos SET stock = stock - :cantidad WHERE id = :id AND stock >= :minimo');
            $detalle = [];
            $total = 0.0;
            foreach ($lineas as $codigo => $cantidad) {
                $buscar->execute([$codigo]);
                $p = $buscar->fetch() ?: throw new DatosInvalidos(['general' => "No existe el producto $codigo."]);
                $descontar->execute(['cantidad' => $cantidad, 'id' => $p['id'], 'minimo' => $cantidad]);
                if ($descontar->rowCount() === 0) {
                    throw new Conflicto("Stock insuficiente de $codigo para vender $cantidad.");
                }
                $detalle[] = [$p['id'], $cantidad, $p['precio']];
                $total += $cantidad * (float) $p['precio'];
            }
            $this->bd->prepare('INSERT INTO ventas (fecha, cliente, usuario_id, total) VALUES (NOW(), ?, ?, ?)')
                ->execute([trim($cliente), $usuarioId, number_format($total, 2, '.', '')]);
            $ventaId = (int) $this->bd->lastInsertId();
            $insertar = $this->bd->prepare('INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
            foreach ($detalle as [$productoId, $cantidad, $precio]) {
                $insertar->execute([$ventaId, $productoId, $cantidad, $precio]);
            }
            $this->bd->commit();                 // recién aquí los cambios son definitivos y visibles para otros
            return $ventaId;
        } catch (Throwable $e) {
            $this->bd->rollBack();               // deshace TODO lo hecho desde beginTransaction()
            throw $e;
        }
    }
}
