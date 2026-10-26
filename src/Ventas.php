<?php
// src/Ventas.php — Actividad 3 (Laboratorio 11): ventas y acopios en transacciones, y reportes de ventas.
// Regla: o se guarda TODO (cabecera, detalle y stock) o NADA. Las filas de productos se bloquean (FOR UPDATE)
// para que dos ventas simultáneas no vendan la misma última unidad.
declare(strict_types=1);

/**
 * Ejecuta $trabajo dentro de una transacción; si algo falla, deshace todo y vuelve a lanzar la excepción.
 * @template T
 * @param callable(): T $trabajo
 * @return T
 */
function en_transaccion(PDO $pdo, callable $trabajo): mixed
{
    $pdo->beginTransaction();
    try {
        $resultado = $trabajo();
        $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Bloquea y devuelve las prendas de las líneas, comprobando que haya unidades libres (stock − reservado).
 * @param array<string, int> $lineas código => cantidad
 * @return list<array{id: int, codigo: string, nombre: string, precio: string, cantidad: int}>
 */
function bloquear_lineas(PDO $pdo, array $lineas, bool $contarReservado = true): array
{
    if ($lineas === []) {
        throw new InvalidArgumentException('Agrega al menos una prenda.');
    }
    if (count($lineas) > 50) {
        throw new InvalidArgumentException('Una operación admite hasta 50 prendas distintas.');
    }
    $buscar = $pdo->prepare('SELECT id, codigo, nombre, precio, stock, reservado FROM productos WHERE codigo = :codigo FOR UPDATE');
    $resultado = [];
    ksort($lineas);                                   // siempre el mismo orden de bloqueo: evita interbloqueos
    foreach ($lineas as $codigo => $cantidad) {
        $codigo = strtoupper(trim((string) $codigo));
        if (preg_match(Producto::PATRON_CODIGO, $codigo) !== 1) {
            throw new InvalidArgumentException("El código «{$codigo}» no tiene el formato AAA-999.");
        }
        if (!is_int($cantidad) || $cantidad < 1 || $cantidad > 1000) {
            throw new InvalidArgumentException("La cantidad de {$codigo} debe ser un entero entre 1 y 1000.");
        }
        $buscar->execute(['codigo' => $codigo]);
        $p = $buscar->fetch();
        if ($p === false) {
            throw new InvalidArgumentException("No existe la prenda {$codigo}.");
        }
        $libre = (int) $p['stock'] - ($contarReservado ? (int) $p['reservado'] : 0);
        if ($cantidad > $libre) {
            throw new InvalidArgumentException("Stock insuficiente de {$codigo}: pediste {$cantidad} y hay {$libre} disponibles.");
        }
        $resultado[] = ['id' => (int) $p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'precio' => (string) $p['precio'], 'cantidad' => $cantidad];
    }
    return $resultado;
}

/** Total exacto en centavos (sin errores de coma flotante) → texto con 2 decimales. */
function total_lineas(array $lineas): string
{
    $centavos = 0;
    foreach ($lineas as $l) {
        $centavos += (int) round((float) $l['precio'] * 100) * $l['cantidad'];
    }
    return number_format($centavos / 100, 2, '.', '');
}

/** Inserta la venta y su detalle y descuenta el stock. Debe llamarse DENTRO de una transacción. */
function insertar_venta(PDO $pdo, string $cliente, array $lineas, int $usuarioId, bool $liberarReserva = false): int
{
    $pdo->prepare('INSERT INTO ventas (cliente, usuario_id, total) VALUES (:cliente, :usuario, :total)')
        ->execute(['cliente' => $cliente, 'usuario' => $usuarioId, 'total' => total_lineas($lineas)]);
    $ventaId = (int) $pdo->lastInsertId();
    $detalle = $pdo->prepare('INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario) VALUES (:venta, :producto, :cantidad, :precio)');
    // Con consultas preparadas reales un mismo :nombre no puede repetirse: por eso :c1 y :c2
    $restar = $liberarReserva
        ? $pdo->prepare('UPDATE productos SET stock = stock - :c1, reservado = reservado - :c2 WHERE id = :id')
        : $pdo->prepare('UPDATE productos SET stock = stock - :c1 WHERE id = :id');
    foreach ($lineas as $l) {
        $detalle->execute(['venta' => $ventaId, 'producto' => $l['id'], 'cantidad' => $l['cantidad'], 'precio' => $l['precio']]);
        $restar->execute(['c1' => $l['cantidad'], 'id' => $l['id']] + ($liberarReserva ? ['c2' => $l['cantidad']] : []));
    }
    return $ventaId;
}

/** @param array<string, int> $lineas código => cantidad. Devuelve el id de la venta. */
function registrar_venta(PDO $pdo, array $lineas, string $cliente, int $usuarioId): int
{
    $cliente = validar_cliente($cliente);
    return en_transaccion($pdo, fn(): int => insertar_venta($pdo, $cliente, bloquear_lineas($pdo, $lineas), $usuarioId));
}

function validar_cliente(string $cliente): string
{
    $cliente = trim(preg_replace('/\s+/u', ' ', $cliente));
    if (mb_strlen($cliente) < 2 || mb_strlen($cliente) > 80) {
        throw new InvalidArgumentException('Escribe el nombre del cliente (2 a 80 caracteres).');
    }
    return $cliente;
}

/**
 * Acopio con control de calidad: la socia que confeccionó la prenda entrega $entregadas, se aprueban $aprobadas;
 * solo las aprobadas suman stock y se le liquidan a $precio cada una (módulo 1 del trabajo final).
 */
function registrar_acopio(PDO $pdo, string $codigo, int $entregadas, int $aprobadas, float $precio, string $observacion, int $usuarioId): int
{
    $observacion = trim(strip_tags($observacion));
    if ($entregadas < 1 || $entregadas > 1000) {
        throw new InvalidArgumentException('Las prendas entregadas deben ser entre 1 y 1000.');
    }
    if ($aprobadas < 0 || $aprobadas > $entregadas) {
        throw new InvalidArgumentException('Las aprobadas no pueden ser más que las entregadas.');
    }
    if ($precio < 0 || $precio > 10000) {
        throw new InvalidArgumentException('El precio por prenda debe estar entre 0 y 10.000.');
    }
    if ($aprobadas < $entregadas && $observacion === '') {
        throw new InvalidArgumentException('Anota el motivo del rechazo: la socia tiene derecho a saberlo.');
    }
    if (mb_strlen($observacion) > 200) {
        throw new InvalidArgumentException('La observación admite hasta 200 caracteres.');
    }
    return en_transaccion($pdo, function () use ($pdo, $codigo, $entregadas, $aprobadas, $precio, $observacion, $usuarioId): int {
        $buscar = $pdo->prepare('SELECT id, socio_id FROM productos WHERE codigo = :codigo FOR UPDATE');
        $buscar->execute(['codigo' => strtoupper(trim($codigo))]);
        $p = $buscar->fetch();
        if ($p === false) {
            throw new InvalidArgumentException('No existe una prenda con ese código.');
        }
        $pdo->prepare('INSERT INTO acopios (socio_id, producto_id, entregadas, aprobadas, precio_unitario, observacion, usuario_id)
            VALUES (:socio, :producto, :entregadas, :aprobadas, :precio, :observacion, :usuario)')
            ->execute(['socio' => $p['socio_id'], 'producto' => $p['id'], 'entregadas' => $entregadas, 'aprobadas' => $aprobadas,
                'precio' => number_format($precio, 2, '.', ''), 'observacion' => $observacion, 'usuario' => $usuarioId]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE productos SET stock = stock + :cantidad WHERE id = :id')->execute(['cantidad' => $aprobadas, 'id' => $p['id']]);
        return $id;
    });
}

/** Últimas ventas con su cantidad de prendas. @return list<array<string, mixed>> */
function ultimas_ventas(PDO $pdo, int $limite = 10): array
{
    $consulta = $pdo->prepare('SELECT v.id, v.fecha, v.cliente, v.total, u.nombre AS usuario,
            (SELECT SUM(d.cantidad) FROM venta_detalle d WHERE d.venta_id = v.id) AS prendas
        FROM ventas v JOIN usuarios u ON u.id = v.usuario_id ORDER BY v.fecha DESC, v.id DESC LIMIT :limite');
    $consulta->bindValue('limite', $limite, PDO::PARAM_INT);
    $consulta->execute();
    return $consulta->fetchAll();
}

/** @return list<array<string, mixed>> */
function ultimos_acopios(PDO $pdo, int $limite = 10): array
{
    $consulta = $pdo->prepare('SELECT a.id, a.fecha, s.nombre AS socio, p.codigo, p.nombre AS prenda, a.entregadas, a.aprobadas,
            a.precio_unitario, a.aprobadas * a.precio_unitario AS liquidado, a.observacion
        FROM acopios a JOIN socios s ON s.id = a.socio_id JOIN productos p ON p.id = a.producto_id
        ORDER BY a.fecha DESC, a.id DESC LIMIT :limite');
    $consulta->bindValue('limite', $limite, PDO::PARAM_INT);
    $consulta->execute();
    return $consulta->fetchAll();
}

/** Valida un rango de fechas AAAA-MM-DD. @return array{0: string, 1: string} */
function rango_fechas(string $desde, string $hasta): array
{
    foreach (['desde' => $desde, 'hasta' => $hasta] as $nombre => $fecha) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($d === false || $d->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException("La fecha «{$nombre}» debe tener el formato AAAA-MM-DD.");
        }
    }
    if ($desde > $hasta) {
        throw new InvalidArgumentException('La fecha «desde» no puede ser posterior a «hasta».');
    }
    return [$desde, $hasta];
}

/**
 * Reporte de ventas entre dos fechas (ambas incluidas): totales, por mes, las 5 prendas más vendidas y lo vendido
 * por cada socia. Todo lo calcula MySQL con consultas preparadas.
 * @return array{resumen: array<string, mixed>, porMes: list<array>, top: list<array>, porSocio: list<array>}
 */
function reporte_ventas(PDO $pdo, string $desde, string $hasta): array
{
    [$desde, $hasta] = rango_fechas($desde, $hasta);
    $filtro = 'v.fecha >= :desde AND v.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)';
    $p = ['desde' => $desde, 'hasta' => $hasta];
    $ejecutar = function (string $sql) use ($pdo, $p): array {
        $consulta = $pdo->prepare($sql);
        $consulta->execute($p);
        return $consulta->fetchAll();
    };
    $resumen = $ejecutar("SELECT COUNT(*) AS ventas, COALESCE(SUM(v.total), 0) AS total FROM ventas v WHERE $filtro")[0]
        + $ejecutar("SELECT COALESCE(SUM(d.cantidad), 0) AS prendas FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id WHERE $filtro")[0];
    return [
        'resumen' => $resumen,
        'porMes' => $ejecutar("SELECT DATE_FORMAT(v.fecha, '%Y-%m') AS mes, COUNT(*) AS ventas, SUM(v.total) AS total
            FROM ventas v WHERE $filtro GROUP BY mes ORDER BY mes"),
        'top' => $ejecutar("SELECT p.codigo, p.nombre, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
            FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id
            WHERE $filtro GROUP BY p.id, p.codigo, p.nombre ORDER BY unidades DESC, importe DESC, p.codigo LIMIT 5"),
        'porSocio' => $ejecutar("SELECT s.nombre AS socio, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
            FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id JOIN socios s ON s.id = p.socio_id
            WHERE $filtro GROUP BY s.id, s.nombre ORDER BY importe DESC, s.nombre"),
    ];
}
