<?php
// src/Liquidaciones.php — Trabajo final, MÓDULO 1: liquidación y pagos a socias.
//   liquidado = Σ (prendas APROBADAS en el control de calidad × precio por prenda)   (tabla acopios)
//   pagado    = Σ pagos registrados                                                  (tabla pagos_socios)
//   saldo     = liquidado − pagado    → se calcula siempre, no se guarda (no puede quedar desactualizado)
// Privacidad: una socia solo ve SU liquidación; el filtro va en la consulta, no en la vista.
declare(strict_types=1);

/**
 * @param ?int $socioId null = todas (directiva); un id = solo esa socia
 * @return list<array{id: int, nombre: string, comunidad: string, activo: int, entregadas: int, aprobadas: int, liquidado: string, pagado: string, saldo: string}>
 */
function liquidaciones(PDO $pdo, ?int $socioId = null): array
{
    // Subconsultas por separado: un JOIN de acopios con pagos multiplicaría las filas y duplicaría los montos
    $sql = 'SELECT s.id, s.nombre, s.comunidad, s.activo,
            COALESCE((SELECT SUM(a.entregadas) FROM acopios a WHERE a.socio_id = s.id), 0) AS entregadas,
            COALESCE((SELECT SUM(a.aprobadas) FROM acopios a WHERE a.socio_id = s.id), 0) AS aprobadas,
            COALESCE((SELECT SUM(a.aprobadas * a.precio_unitario) FROM acopios a WHERE a.socio_id = s.id), 0) AS liquidado,
            COALESCE((SELECT SUM(p.importe) FROM pagos_socios p WHERE p.socio_id = s.id), 0) AS pagado
        FROM socios s' . ($socioId === null ? '' : ' WHERE s.id = :socio') . ' ORDER BY s.activo DESC, s.nombre';
    $consulta = $pdo->prepare($sql);
    $consulta->execute($socioId === null ? [] : ['socio' => $socioId]);
    return array_map(static function (array $f): array {
        $f['saldo'] = number_format(((int) round((float) $f['liquidado'] * 100) - (int) round((float) $f['pagado'] * 100)) / 100, 2, '.', '');
        return $f;
    }, $consulta->fetchAll());
}

/** Detalle de una socia: sus acopios (con lo rechazado y por qué) y sus pagos. */
function detalle_liquidacion(PDO $pdo, int $socioId): array
{
    $acopios = $pdo->prepare('SELECT a.fecha, p.codigo, p.nombre AS prenda, a.entregadas, a.aprobadas, a.precio_unitario,
            a.aprobadas * a.precio_unitario AS liquidado, a.observacion
        FROM acopios a JOIN productos p ON p.id = a.producto_id WHERE a.socio_id = :socio ORDER BY a.fecha DESC, a.id DESC');
    $acopios->execute(['socio' => $socioId]);
    $pagos = $pdo->prepare('SELECT fecha, importe, referencia FROM pagos_socios WHERE socio_id = :socio ORDER BY fecha DESC, id DESC');
    $pagos->execute(['socio' => $socioId]);
    return ['acopios' => $acopios->fetchAll(), 'pagos' => $pagos->fetchAll()];
}

/**
 * Registra un pago. Se bloquea la fila de la socia (FOR UPDATE) para que dos pagos simultáneos no superen el saldo.
 * @return int id del pago
 */
function registrar_pago_socio(PDO $pdo, int $socioId, float $importe, string $referencia, int $usuarioId): int
{
    $referencia = strtoupper(trim($referencia));
    if ($importe <= 0 || $importe > 1_000_000) {
        throw new InvalidArgumentException('El importe debe ser mayor que cero.');
    }
    if (preg_match('/^[A-Z0-9][A-Z0-9\-\/]{2,59}$/', $referencia) !== 1) {
        throw new InvalidArgumentException('Anota la referencia del pago (n.º de transferencia o recibo, 3 a 60 caracteres).');
    }
    try {
        return en_transaccion($pdo, function () use ($pdo, $socioId, $importe, $referencia, $usuarioId): int {
            $socio = $pdo->prepare('SELECT id FROM socios WHERE id = :id FOR UPDATE');
            $socio->execute(['id' => $socioId]);
            if ($socio->fetchColumn() === false) {
                throw new InvalidArgumentException('La socia o socio no existe.');
            }
            $saldo = (float) liquidaciones($pdo, $socioId)[0]['saldo'];
            $centavos = (int) round($importe * 100);
            if ($centavos > (int) round($saldo * 100)) {
                throw new InvalidArgumentException('El pago (' . bs($importe) . ') supera el saldo pendiente (' . bs($saldo) . ').');
            }
            $pdo->prepare('INSERT INTO pagos_socios (socio_id, importe, referencia, usuario_id) VALUES (:socio, :importe, :referencia, :usuario)')
                ->execute(['socio' => $socioId, 'importe' => number_format($centavos / 100, 2, '.', ''), 'referencia' => $referencia, 'usuario' => $usuarioId]);
            return (int) $pdo->lastInsertId();
        });
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException("La referencia {$referencia} ya se usó en otro pago.");
        }
        throw $e;
    }
}
