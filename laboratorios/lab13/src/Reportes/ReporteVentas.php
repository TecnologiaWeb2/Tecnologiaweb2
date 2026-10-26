<?php
// src/Reportes/ReporteVentas.php — Las preguntas de la directiva, respondidas con SQL (JOIN, GROUP BY, HAVING, LEFT JOIN).
// Todas reciben un período [desde, hasta] con fechas YA validadas (DateTimeImmutable).
declare(strict_types=1);

namespace Apicultores\Reportes;

use DateTimeImmutable;
use PDO;

final class ReporteVentas
{
    public function __construct(private readonly PDO $bd)
    {
    }

    /** Período cerrado en ambos extremos: hasta 2026-09-30 incluye todo ese día (< 2026-10-01). */
    private function periodo(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        return ['desde' => $desde->format('Y-m-d 00:00:00'), 'hasta' => $hasta->modify('+1 day')->format('Y-m-d 00:00:00')];
    }

    /** 1) ¿Cuánto vendimos cada mes? */
    public function porMes(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $c = $this->bd->prepare(
            "SELECT DATE_FORMAT(v.fecha, '%Y-%m') AS mes, COUNT(*) AS ventas, SUM(v.total) AS total
             FROM ventas v
             WHERE v.fecha >= :desde AND v.fecha < :hasta
             GROUP BY mes
             ORDER BY mes");
        $c->execute($this->periodo($desde, $hasta));
        return $c->fetchAll();
    }

    /** 2) ¿Qué productos se venden más? (unidades e importe) */
    public function topProductos(DateTimeImmutable $desde, DateTimeImmutable $hasta, int $limite = 5): array
    {
        $c = $this->bd->prepare(
            'SELECT p.codigo, p.nombre, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
             FROM venta_detalle d
             JOIN ventas v    ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             WHERE v.fecha >= :desde AND v.fecha < :hasta
             GROUP BY p.id, p.codigo, p.nombre
             ORDER BY importe DESC, p.codigo
             LIMIT :limite');
        foreach ($this->periodo($desde, $hasta) as $k => $valor) {
            $c->bindValue($k, $valor);
        }
        $c->bindValue('limite', $limite, PDO::PARAM_INT);
        $c->execute();
        return $c->fetchAll();
    }

    /** 3) ¿Cuánto vendió la asociación con los productos de cada socio? Solo los que superan un mínimo (HAVING). */
    public function porSocio(DateTimeImmutable $desde, DateTimeImmutable $hasta, float $minimo = 0): array
    {
        $c = $this->bd->prepare(
            'SELECT s.id AS socio_id, s.nombre AS socio, s.comunidad, SUM(d.cantidad * d.precio_unitario) AS importe
             FROM venta_detalle d
             JOIN ventas v    ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             JOIN socios s    ON s.id = p.socio_id
             WHERE v.fecha >= :desde AND v.fecha < :hasta
             GROUP BY s.id, s.nombre, s.comunidad
             HAVING importe >= :minimo
             ORDER BY importe DESC');
        $c->execute($this->periodo($desde, $hasta) + ['minimo' => $minimo]);
        return $c->fetchAll();
    }

    /** 4) ¿Qué productos NO se vendieron en el período? (LEFT JOIN … IS NULL) */
    public function sinVentas(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $c = $this->bd->prepare(
            'SELECT p.codigo, p.nombre, p.stock
             FROM productos p
             LEFT JOIN (SELECT DISTINCT d.producto_id
                        FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id
                        WHERE v.fecha >= :desde AND v.fecha < :hasta) vendidos ON vendidos.producto_id = p.id
             WHERE vendidos.producto_id IS NULL
             ORDER BY p.codigo');
        $c->execute($this->periodo($desde, $hasta));
        return $c->fetchAll();
    }

    /** Detalle línea por línea (para exportar a CSV). */
    public function detalle(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $c = $this->bd->prepare(
            'SELECT v.id AS venta, DATE_FORMAT(v.fecha, \'%Y-%m-%d %H:%i\') AS fecha, v.cliente, p.codigo, p.nombre AS producto,
                    d.cantidad, d.precio_unitario, d.cantidad * d.precio_unitario AS subtotal
             FROM venta_detalle d
             JOIN ventas v    ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             WHERE v.fecha >= :desde AND v.fecha < :hasta
             ORDER BY v.fecha, v.id, p.codigo');
        $c->execute($this->periodo($desde, $hasta));
        return $c->fetchAll();
    }
}
