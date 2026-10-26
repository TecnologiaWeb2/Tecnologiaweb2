<?php
// public/pedidos.php — Trabajo final, MÓDULO 2: pedidos de compradores con reserva de stock.
// Crear reserva las prendas; Entregar las convierte en venta (una transacción); Cancelar libera la reserva.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
require_once __DIR__ . '/../plantillas/lineas.php';

$u = exigir_usuario('pedidos.gestionar');
$pdo = Conexion::obtener();
$error = '';
$pedido = ['cliente' => '', 'lineas' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    try {
        if (texto($_POST, 'accion') === 'estado') {
            $ventaId = cambiar_estado_pedido($pdo, (int) texto($_POST, 'pedido_id'), texto($_POST, 'estado'), $u->id);
            redirigir('/pedidos.php?' . http_build_query(['estado_cambiado' => texto($_POST, 'estado'), 'venta' => $ventaId]));
        }
        $pedido = ['cliente' => texto($_POST, 'cliente'), 'lineas' => lineas_formulario($_POST)];
        $id = crear_pedido($pdo, $pedido['cliente'], $pedido['lineas'], $u->id);
        redirigir('/pedidos.php?creado=' . $id);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}
if (($id = texto($_GET, 'creado')) !== '') {
    avisar("Pedido registrado: las prendas quedaron reservadas.");
}
if (($estado = texto($_GET, 'estado_cambiado')) !== '') {
    avisar(match ($estado) {
        'entregado' => 'Pedido entregado: se registró la venta N.º ' . texto($_GET, 'venta') . ' y se descontó el stock.',
        'cancelado' => 'Pedido cancelado: la reserva se liberó.',
        default => 'Pedido confirmado.',
    });
}
$filtro = texto($_GET, 'estado');
$pedidos = listar_pedidos($pdo, $filtro);
$productos = (new ProductoRepositorio($pdo))->paginar(porPagina: 1000)->elementos;
$reservas = array_filter($productos, fn(Producto $p): bool => $p->reservado > 0);

$titulo = 'Pedidos';
$seccion = 'pedidos';
require __DIR__ . '/../plantillas/cabecera.php';
$insignia = ['pendiente' => 'text-bg-warning', 'confirmado' => 'text-bg-info', 'entregado' => 'text-bg-success', 'cancelado' => 'text-bg-secondary'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div><h1 class="h3 mb-1">Pedidos de compradores</h1><p class="text-secondary mb-0">Reserva el stock al registrar; se vende al entregar.</p></div>
  <a class="btn btn-outline-marca" href="/importar.php"><i class="bi bi-upload me-1"></i>Importar pedido JSON/XML</a>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 mb-4"><div class="card-body">
      <h2 class="h5">Nuevo pedido</h2>
      <form method="post" action="/pedidos.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="crear">
        <label class="form-label" for="cliente">Comprador</label>
        <input class="form-control mb-3" id="cliente" name="cliente" value="<?= e($pedido['cliente']) ?>" required maxlength="80" placeholder="Boutique Chiquitana">
        <?= filas_lineas($productos, $pedido['lineas']) ?>
        <button class="btn btn-marca mt-2" type="submit"><i class="bi bi-bag-plus me-1"></i>Reservar pedido</button>
      </form>
    </div></div>
    <div class="card shadow-sm border-0"><div class="card-body">
      <h2 class="h6">Stock reservado ahora</h2>
      <?php if ($reservas === []): ?><p class="text-secondary small mb-0">No hay reservas.</p><?php else: ?>
      <ul class="list-unstyled small mb-0" id="reservas">
        <?php foreach ($reservas as $p): ?><li><span class="font-monospace"><?= e($p->codigo) ?></span> <?= e($p->nombre) ?>: <strong><?= $p->reservado ?></strong> reservadas de <?= $p->stock ?> (<?= $p->libre() ?> libres)</li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm border-0"><div class="card-body">
      <form method="get" action="/pedidos.php" class="d-flex gap-2 mb-3">
        <label class="visually-hidden" for="estado">Estado</label>
        <select class="form-select form-select-sm w-auto" id="estado" name="estado">
          <option value="">Todos los estados</option>
          <?php foreach (ESTADOS_PEDIDO as $valor => $etiqueta): ?><option value="<?= $valor ?>"<?= $filtro === $valor ? ' selected' : '' ?>><?= $etiqueta ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Filtrar</button>
      </form>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th scope="col">Pedido</th><th scope="col">Detalle</th><th scope="col" class="text-end">Total</th><th scope="col">Estado</th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
          <tr>
            <td><span class="fw-semibold"><?= e($p['numero']) ?></span><br><span class="small"><?= e($p['cliente']) ?></span><br><span class="small text-secondary"><?= e(fecha_bo($p['creado_en'])) ?> · <?= e(ORIGENES_PEDIDO[$p['origen']]) ?></span></td>
            <td class="small"><?= e((string) $p['detalle']) ?></td>
            <td class="text-end text-nowrap"><?= bs((float) $p['total']) ?></td>
            <td>
              <span class="badge <?= $insignia[$p['estado']] ?>"><?= e(ESTADOS_PEDIDO[$p['estado']]) ?></span>
              <?php if ($p['venta_id'] !== null): ?><div class="small text-secondary">Venta N.º <?= (int) $p['venta_id'] ?></div><?php endif; ?>
              <?php if (in_array($p['estado'], ['pendiente', 'confirmado'], true)): ?>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <?php foreach (($p['estado'] === 'pendiente' ? ['confirmado' => 'Confirmar'] : []) + ['entregado' => 'Entregar', 'cancelado' => 'Cancelar'] as $nuevo => $texto): ?>
                    <form method="post" action="/pedidos.php"<?= $nuevo === 'cancelado' ? ' data-confirmar="Se liberarán las prendas reservadas." data-confirmar-titulo="¿Cancelar el pedido ' . e($p['numero']) . '?"' : '' ?>>
                      <?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="estado" value="<?= $nuevo ?>">
                      <button class="btn btn-sm <?= $nuevo === 'cancelado' ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" type="submit"><?= $texto ?></button>
                    </form>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($pedidos === []): ?><tr><td colspan="4" class="text-secondary">No hay pedidos.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
