<?php
// src/Pedidos.php — Trabajo final, MÓDULO 2: pedidos de compradores (tiendas, hoteles, ferias) con reserva de stock.
//   crear      → reserva las unidades (productos.reservado += cantidad); ya no se pueden vender a otro cliente
//   confirmar  → el comprador aceptó el pedido (la reserva sigue)
//   entregar   → se convierte en VENTA: descuenta stock y reserva, en UNA transacción
//   cancelar   → libera la reserva
// La base de datos es la última defensa: CHECK (reservado <= stock).
declare(strict_types=1);

const ESTADOS_PEDIDO = ['pendiente' => 'Pendiente', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado'];
const ORIGENES_PEDIDO = ['web' => 'Formulario', 'json' => 'Archivo JSON', 'xml' => 'Archivo XML', 'api' => 'API REST'];

/**
 * @param array<string, int> $lineas código => cantidad
 * @param ?string $numero número de orden del comprador (si no llega, se genera PED-000001)
 * @return int id del pedido
 */
function crear_pedido(PDO $pdo, string $cliente, array $lineas, int $usuarioId, string $origen = 'web', ?string $numero = null): int
{
    $cliente = validar_cliente($cliente);
    if (!isset(ORIGENES_PEDIDO[$origen])) {
        throw new InvalidArgumentException('Origen de pedido desconocido.');
    }
    if ($numero !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/]{1,29}$/', $numero) !== 1) {
        throw new InvalidArgumentException('El número de pedido admite letras, números y guiones (2 a 30 caracteres).');
    }
    try {
        return en_transaccion($pdo, function () use ($pdo, $cliente, $lineas, $usuarioId, $origen, $numero): int {
            $validas = bloquear_lineas($pdo, $lineas);            // stock − reservado alcanza para cada línea
            $pdo->prepare('INSERT INTO pedidos (numero, cliente, origen, total, usuario_id) VALUES (:numero, :cliente, :origen, :total, :usuario)')
                ->execute(['numero' => $numero ?? 'TMP-' . bin2hex(random_bytes(8)), 'cliente' => $cliente, 'origen' => $origen,
                    'total' => total_lineas($validas), 'usuario' => $usuarioId]);
            $id = (int) $pdo->lastInsertId();
            if ($numero === null) {
                $pdo->prepare('UPDATE pedidos SET numero = :numero WHERE id = :id')->execute(['numero' => sprintf('PED-%06d', $id), 'id' => $id]);
            }
            $detalle = $pdo->prepare('INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario) VALUES (:pedido, :producto, :cantidad, :precio)');
            $reservar = $pdo->prepare('UPDATE productos SET reservado = reservado + :cantidad WHERE id = :id');
            foreach ($validas as $l) {
                $detalle->execute(['pedido' => $id, 'producto' => $l['id'], 'cantidad' => $l['cantidad'], 'precio' => $l['precio']]);
                $reservar->execute(['cantidad' => $l['cantidad'], 'id' => $l['id']]);
            }
            return $id;
        });
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException("Ya se registró un pedido con el número {$numero}.");
        }
        throw $e;
    }
}

/** Cambia el estado respetando el ciclo de vida. Devuelve el id de la venta si se entregó. */
function cambiar_estado_pedido(PDO $pdo, int $pedidoId, string $estado, int $usuarioId): ?int
{
    $permitidos = ['pendiente' => ['confirmado', 'entregado', 'cancelado'], 'confirmado' => ['entregado', 'cancelado']];
    return en_transaccion($pdo, function () use ($pdo, $pedidoId, $estado, $usuarioId, $permitidos): ?int {
        $consulta = $pdo->prepare('SELECT id, cliente, estado FROM pedidos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $pedidoId]);
        $pedido = $consulta->fetch();
        if ($pedido === false) {
            throw new InvalidArgumentException('El pedido no existe.');
        }
        if (!in_array($estado, $permitidos[$pedido['estado']] ?? [], true)) {
            throw new InvalidArgumentException('Un pedido ' . mb_strtolower(ESTADOS_PEDIDO[$pedido['estado']]) . ' no puede pasar a «' . (ESTADOS_PEDIDO[$estado] ?? $estado) . '».');
        }
        $detalle = $pdo->prepare('SELECT d.producto_id AS id, p.codigo, p.nombre, d.cantidad, d.precio_unitario AS precio
            FROM pedido_detalle d JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = :id ORDER BY p.codigo FOR UPDATE');
        $detalle->execute(['id' => $pedidoId]);
        $lineas = array_map(fn(array $l): array => ['cantidad' => (int) $l['cantidad'], 'id' => (int) $l['id']] + $l, $detalle->fetchAll());
        $ventaId = null;
        if ($estado === 'entregado') {
            // El precio es el pactado en el pedido; stock y reserva bajan juntos
            $ventaId = insertar_venta($pdo, (string) $pedido['cliente'], $lineas, $usuarioId, liberarReserva: true);
        } elseif ($estado === 'cancelado') {
            $liberar = $pdo->prepare('UPDATE productos SET reservado = reservado - :cantidad WHERE id = :id');
            foreach ($lineas as $l) {
                $liberar->execute(['cantidad' => $l['cantidad'], 'id' => $l['id']]);
            }
        }
        $pdo->prepare('UPDATE pedidos SET estado = :estado, venta_id = :venta WHERE id = :id')
            ->execute(['estado' => $estado, 'venta' => $ventaId, 'id' => $pedidoId]);
        return $ventaId;
    });
}

/** @return list<array<string, mixed>> pedidos con su detalle en texto */
function listar_pedidos(PDO $pdo, string $estado = '', int $limite = 50): array
{
    $donde = isset(ESTADOS_PEDIDO[$estado]) ? 'WHERE pe.estado = :estado' : '';
    $consulta = $pdo->prepare("SELECT pe.id, pe.numero, pe.cliente, pe.origen, pe.estado, pe.total, pe.creado_en, pe.venta_id, u.nombre AS usuario,
            (SELECT GROUP_CONCAT(CONCAT(d.cantidad, ' × ', p.codigo) ORDER BY p.codigo SEPARATOR ', ')
               FROM pedido_detalle d JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = pe.id) AS detalle
        FROM pedidos pe JOIN usuarios u ON u.id = pe.usuario_id $donde ORDER BY pe.id DESC LIMIT :limite");
    if ($donde !== '') {
        $consulta->bindValue('estado', $estado);
    }
    $consulta->bindValue('limite', $limite, PDO::PARAM_INT);
    $consulta->execute();
    return $consulta->fetchAll();
}

/** @return ?array<string, mixed> pedido con sus líneas (para la API) */
function buscar_pedido(PDO $pdo, int $id): ?array
{
    $consulta = $pdo->prepare('SELECT id, numero, cliente, origen, estado, total, creado_en, venta_id FROM pedidos WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $pedido = $consulta->fetch();
    if ($pedido === false) {
        return null;
    }
    $lineas = $pdo->prepare('SELECT p.codigo, p.nombre, d.cantidad, d.precio_unitario FROM pedido_detalle d
        JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = :id ORDER BY p.codigo');
    $lineas->execute(['id' => $id]);
    return $pedido + ['lineas' => $lineas->fetchAll()];
}

// ---------- Importación de pedidos de compradores (Laboratorio 12): JSON validado en código, XML validado con XSD ----------

const ESQUEMA_PEDIDO = __DIR__ . '/../bd/esquemas/pedido.xsd';

/** @return array{numero: string, cliente: string, fecha: string, lineas: array<string, int>} */
function leer_pedido_json(string $json): array
{
    try {
        $d = json_decode($json, true, 8, JSON_THROW_ON_ERROR);    // profundidad máxima 8: nada de anidamientos absurdos
    } catch (JsonException $e) {
        throw new InvalidArgumentException('El archivo no es JSON válido: ' . $e->getMessage() . '.');
    }
    if (!is_array($d) || array_is_list($d)) {
        throw new InvalidArgumentException('Se esperaba un objeto JSON {"numero": …, "cliente": …, "fecha": …, "lineas": […]}.');
    }
    $errores = [];
    if (!is_string($d['numero'] ?? null) || preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/]{1,29}$/', $d['numero']) !== 1) {
        $errores[] = '"numero" debe ser un texto de 2 a 30 letras, números o guiones.';
    }
    if (!is_string($d['cliente'] ?? null) || mb_strlen(trim($d['cliente'])) < 2 || mb_strlen($d['cliente']) > 80) {
        $errores[] = '"cliente" debe ser un texto de 2 a 80 caracteres.';
    }
    $fecha = is_string($d['fecha'] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d', $d['fecha']) : false;
    if ($fecha === false || $fecha->format('Y-m-d') !== $d['fecha']) {
        $errores[] = '"fecha" debe ser una fecha real con formato AAAA-MM-DD.';
    }
    $lineas = [];
    if (!is_array($d['lineas'] ?? null) || $d['lineas'] === [] || !array_is_list($d['lineas']) || count($d['lineas']) > 50) {
        $errores[] = '"lineas" debe ser una lista de 1 a 50 elementos.';
    } else {
        foreach ($d['lineas'] as $i => $l) {
            $codigo = is_array($l) ? ($l['codigo'] ?? null) : null;
            $cantidad = is_array($l) ? ($l['cantidad'] ?? null) : null;
            // is_int: "5" (texto) o 5.5 NO son cantidades válidas — JSON distingue tipos y se aprovecha
            if (!is_string($codigo) || preg_match(Producto::PATRON_CODIGO, $codigo) !== 1 || !is_int($cantidad) || $cantidad < 1 || $cantidad > 1000) {
                $errores[] = sprintf('Línea %d: se espera {"codigo": "AAA-999", "cantidad": entero de 1 a 1000}.', $i + 1);
                continue;
            }
            $lineas[$codigo] = ($lineas[$codigo] ?? 0) + $cantidad;
        }
    }
    if ($errores !== []) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }
    return ['numero' => $d['numero'], 'cliente' => trim($d['cliente']), 'fecha' => $d['fecha'], 'lineas' => $lineas];
}

/** Valida el XML contra bd/esquemas/pedido.xsd ANTES de usarlo. @return array{numero: string, cliente: string, fecha: string, lineas: array<string, int>} */
function leer_pedido_xml(string $xml): array
{
    $anterior = libxml_use_internal_errors(true);         // capturamos los errores en lugar de mostrar avisos
    try {
        $doc = new DOMDocument();
        // LIBXML_NONET: el analizador nunca descarga nada de la red; sin LIBXML_NOENT no expande entidades (XXE)
        if (!$doc->loadXML($xml, LIBXML_NONET)) {
            throw new InvalidArgumentException('El archivo no es XML bien formado. ' . errores_libxml());
        }
        if (!$doc->schemaValidate(ESQUEMA_PEDIDO)) {
            throw new InvalidArgumentException('El pedido no cumple el esquema pedido.xsd. ' . errores_libxml());
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
    return ['numero' => (string) $raiz['numero'], 'cliente' => trim((string) $raiz->cliente), 'fecha' => (string) $raiz->fecha, 'lineas' => $lineas];
}

function errores_libxml(): string
{
    return implode(' ', array_map(fn(LibXMLError $e): string => sprintf('Línea %d: %s.', $e->line, rtrim(trim($e->message), '.')), libxml_get_errors()));
}

/** Detecta el formato por el primer carácter, valida e importa como pedido con reserva. @return int id del pedido */
function importar_pedido(PDO $pdo, string $contenido, int $usuarioId): int
{
    $contenido = trim($contenido);
    if ($contenido === '' || strlen($contenido) > 100_000) {
        throw new InvalidArgumentException('Pega o sube un pedido JSON o XML (hasta 100 KB).');
    }
    $esXml = str_starts_with($contenido, '<');
    $pedido = $esXml ? leer_pedido_xml($contenido) : leer_pedido_json($contenido);
    return crear_pedido($pdo, $pedido['cliente'], $pedido['lineas'], $usuarioId, $esXml ? 'xml' : 'json', $pedido['numero']);
}
