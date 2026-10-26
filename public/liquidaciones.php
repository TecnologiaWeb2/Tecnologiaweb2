<?php
// public/liquidaciones.php — Trabajo final, MÓDULO 1: liquidación y pagos a socias.
// La directiva ve a todas y registra pagos; una socia SOLO ve su propia liquidación (el filtro está en la consulta).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$u = exigir_usuario('liquidaciones.ver');
$pdo = Conexion::obtener();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_usuario('liquidaciones.pagar');
    exigir_csrf($_POST);
    try {
        $importe = numero_bo(texto($_POST, 'importe'));
        if ($importe === null) {
            throw new InvalidArgumentException('Escribe el importe como 500 o 500,50.');
        }
        registrar_pago_socio($pdo, (int) texto($_POST, 'socio_id'), $importe, texto($_POST, 'referencia'), $u->id);
        redirigir('/liquidaciones.php?pagado=1&socio=' . (int) texto($_POST, 'socio_id'));
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}
if (texto($_GET, 'pagado') !== '') {
    avisar('Pago registrado: el saldo de la socia ya se actualizó.');
}
// Socia: siempre la suya (aunque cambie ?socio= en la URL). Directiva: todas, y el detalle de la elegida.
$soloSuya = $u->rol === 'socio';
$filas = liquidaciones($pdo, $soloSuya ? $u->socioId : null);
$elegida = $soloSuya ? $u->socioId : (filter_var(texto($_GET, 'socio'), FILTER_VALIDATE_INT) ?: null);
$detalle = $elegida === null ? null : detalle_liquidacion($pdo, $elegida);
$filaElegida = current(array_filter($filas, fn(array $f): bool => (int) $f['id'] === $elegida)) ?: null;
$totales = array_reduce($filas, fn(array $t, array $f): array => [$t[0] + (float) $f['liquidado'], $t[1] + (float) $f['pagado'], $t[2] + (float) $f['saldo']], [0.0, 0.0, 0.0]);

$titulo = $soloSuya ? 'Mi liquidación' : 'Liquidaciones';
$seccion = 'liquidaciones';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="mb-4">
  <h1 class="h3 mb-1"><?= $soloSuya ? 'Mi liquidación' : 'Liquidación y pagos a socias' ?></h1>
  <p class="text-secondary mb-0">Se paga cada prenda <strong>aprobada</strong> en el control de calidad. Saldo = liquidado − pagado.</p>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
  <div class="table-responsive"><table class="table align-middle mb-0" id="tabla-liquidaciones">
    <thead class="table-light"><tr><th scope="col">Socia</th><th scope="col" class="text-end">Aprobadas / entregadas</th><th scope="col" class="text-end">Liquidado</th><th scope="col" class="text-end">Pagado</th><th scope="col" class="text-end">Saldo</th><?php if ($u->puede('liquidaciones.pagar')): ?><th scope="col">Registrar pago</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr<?= (int) $f['id'] === $elegida ? ' class="table-active"' : '' ?>>
        <td><?php if (!$soloSuya): ?><a href="/liquidaciones.php?socio=<?= (int) $f['id'] ?>" class="fw-semibold text-decoration-none"><?= e($f['nombre']) ?></a><?php else: ?><span class="fw-semibold"><?= e($f['nombre']) ?></span><?php endif; ?>
          <br><span class="small text-secondary"><?= e($f['comunidad']) ?><?= $f['activo'] ? '' : ' · inactiva' ?></span></td>
        <td class="text-end"><?= (int) $f['aprobadas'] ?> / <?= (int) $f['entregadas'] ?></td>
        <td class="text-end text-nowrap"><?= bs((float) $f['liquidado']) ?></td>
        <td class="text-end text-nowrap"><?= bs((float) $f['pagado']) ?></td>
        <td class="text-end text-nowrap fw-bold"><?= bs((float) $f['saldo']) ?></td>
        <?php if ($u->puede('liquidaciones.pagar')): ?>
          <td><?php if ((float) $f['saldo'] > 0): ?>
            <form method="post" action="/liquidaciones.php" class="d-flex gap-1" novalidate>
              <?= csrf_campo() ?><input type="hidden" name="socio_id" value="<?= (int) $f['id'] ?>">
              <label class="visually-hidden" for="importe-<?= (int) $f['id'] ?>">Importe para <?= e($f['nombre']) ?></label>
              <input class="form-control form-control-sm" id="importe-<?= (int) $f['id'] ?>" name="importe" inputmode="decimal" placeholder="Importe" required style="max-width: 7rem">
              <label class="visually-hidden" for="ref-<?= (int) $f['id'] ?>">Referencia</label>
              <input class="form-control form-control-sm" id="ref-<?= (int) $f['id'] ?>" name="referencia" maxlength="60" placeholder="N.º transf." required style="max-width: 8rem">
              <button class="btn btn-sm btn-marca" type="submit">Pagar</button>
            </form>
          <?php else: ?><span class="small text-secondary">Sin saldo</span><?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if (!$soloSuya): ?>
    <tfoot><tr class="fw-bold"><td>Total</td><td></td><td class="text-end text-nowrap"><?= bs($totales[0]) ?></td><td class="text-end text-nowrap"><?= bs($totales[1]) ?></td><td class="text-end text-nowrap" id="saldo-total"><?= bs($totales[2]) ?></td><?php if ($u->puede('liquidaciones.pagar')): ?><td></td><?php endif; ?></tr></tfoot>
    <?php endif; ?>
  </table></div>
</div></div>
<?php if ($detalle !== null && $filaElegida !== null): ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Entregas de <?= e($filaElegida['nombre']) ?></h2>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Fecha</th><th scope="col">Prenda</th><th scope="col" class="text-end">Aprob./Entr.</th><th scope="col" class="text-end">Bs/prenda</th><th scope="col" class="text-end">Liquidado</th></tr></thead>
        <tbody><?php foreach ($detalle['acopios'] as $a): ?>
          <tr><td class="text-nowrap"><?= e(fecha_bo($a['fecha'], false)) ?></td><td><?= e($a['prenda']) ?><?php if ($a['observacion'] !== ''): ?><br><span class="small text-secondary">Rechazo: <?= e($a['observacion']) ?></span><?php endif; ?></td>
            <td class="text-end"><?= (int) $a['aprobadas'] ?>/<?= (int) $a['entregadas'] ?></td><td class="text-end"><?= bs((float) $a['precio_unitario']) ?></td><td class="text-end text-nowrap"><?= bs((float) $a['liquidado']) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($detalle['acopios'] === []): ?><tr><td colspan="5" class="text-secondary">Sin entregas registradas.</td></tr><?php endif; ?></tbody>
      </table></div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Pagos recibidos</h2>
      <table class="table table-sm mb-0"><thead><tr><th scope="col">Fecha</th><th scope="col">Referencia</th><th scope="col" class="text-end">Importe</th></tr></thead>
        <tbody><?php foreach ($detalle['pagos'] as $p): ?><tr><td><?= e(fecha_bo($p['fecha'], false)) ?></td><td class="font-monospace small"><?= e($p['referencia']) ?></td><td class="text-end text-nowrap"><?= bs((float) $p['importe']) ?></td></tr><?php endforeach; ?>
        <?php if ($detalle['pagos'] === []): ?><tr><td colspan="3" class="text-secondary">Sin pagos.</td></tr><?php endif; ?></tbody></table>
    </div></div>
  </div>
</div>
<?php elseif (!$soloSuya): ?>
  <p class="text-secondary small">Elige una socia para ver sus entregas, lo rechazado en el control de calidad y sus pagos.</p>
<?php endif; ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
