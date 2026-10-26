<?php
// public/ventas.php — Laboratorio 11: registrar ventas (descuentan stock) y acopios con control de calidad (suman stock).
// Cada registro es UNA transacción: si una línea falla, no se guarda nada.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
require_once __DIR__ . '/../plantillas/lineas.php';

$u = exigir_usuario('ventas.registrar');
$pdo = Conexion::obtener();
$errorVenta = '';
$errorAcopio = '';
$venta = ['cliente' => '', 'lineas' => []];
$acopio = ['codigo' => '', 'entregadas' => '', 'aprobadas' => '', 'precio' => '', 'observacion' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    if (texto($_POST, 'accion') === 'acopio') {
        exigir_usuario('acopios.registrar');
        $acopio = ['codigo' => texto($_POST, 'acopio_codigo'), 'entregadas' => texto($_POST, 'entregadas'), 'aprobadas' => texto($_POST, 'aprobadas'),
            'precio' => texto($_POST, 'precio'), 'observacion' => texto($_POST, 'observacion')];
        try {
            $entregadas = filter_var($acopio['entregadas'], FILTER_VALIDATE_INT);
            $aprobadas = filter_var($acopio['aprobadas'], FILTER_VALIDATE_INT);
            $precio = numero_bo($acopio['precio']);
            if ($entregadas === false || $aprobadas === false || $precio === null) {
                throw new InvalidArgumentException('Completa las cantidades (números enteros) y el precio por prenda.');
            }
            $id = registrar_acopio($pdo, $acopio['codigo'], $entregadas, $aprobadas, $precio, $acopio['observacion'], $u->id);
            redirigir('/ventas.php?acopio=' . $id);
        } catch (InvalidArgumentException $e) {
            $errorAcopio = $e->getMessage();
            http_response_code(422);
        }
    } else {
        $venta = ['cliente' => texto($_POST, 'cliente'), 'lineas' => lineas_formulario($_POST)];
        try {
            $id = registrar_venta($pdo, $venta['lineas'], $venta['cliente'], $u->id);
            redirigir('/ventas.php?venta=' . $id);
        } catch (InvalidArgumentException $e) {
            $errorVenta = $e->getMessage();
            http_response_code(422);
        }
    }
}
if (($id = texto($_GET, 'venta')) !== '') {
    avisar("Venta N.º $id registrada: el stock ya se descontó.");
}
if (($id = texto($_GET, 'acopio')) !== '') {
    avisar("Acopio N.º $id registrado: las prendas aprobadas se sumaron al stock y a la liquidación de la socia.");
}
$productos = (new ProductoRepositorio($pdo))->paginar(porPagina: 1000)->elementos;
$ventas = ultimas_ventas($pdo, 8);
$acopios = ultimos_acopios($pdo, 8);

$titulo = 'Ventas y acopios';
$seccion = 'ventas';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="mb-4">
  <h1 class="h3 mb-1">Ventas y acopios</h1>
  <p class="text-secondary mb-0">La venta descuenta stock; el acopio suma solo las prendas que pasan el control de calidad.</p>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5"><i class="bi bi-cart me-1"></i>Registrar venta</h2>
      <?php if ($errorVenta !== ''): ?><div class="alert alert-danger py-2" role="alert"><?= e($errorVenta) ?></div><?php endif; ?>
      <form method="post" action="/ventas.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="venta">
        <label class="form-label" for="cliente">Cliente</label>
        <input class="form-control mb-3" id="cliente" name="cliente" value="<?= e($venta['cliente']) ?>" required maxlength="80" placeholder="Feria de Concepción">
        <p class="form-label mb-1">Prendas</p>
        <?= filas_lineas($productos, $venta['lineas']) ?>
        <button class="btn btn-marca mt-2" type="submit"><i class="bi bi-check2 me-1"></i>Registrar venta</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5"><i class="bi bi-box-arrow-in-down me-1"></i>Registrar acopio (control de calidad)</h2>
      <?php if ($errorAcopio !== ''): ?><div class="alert alert-danger py-2" role="alert"><?= e($errorAcopio) ?></div><?php endif; ?>
      <form method="post" action="/ventas.php" class="row g-2" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="acopio">
        <div class="col-12">
          <label class="form-label" for="acopio_codigo">Prenda entregada (la socia es quien la confecciona)</label>
          <select class="form-select" id="acopio_codigo" name="acopio_codigo" required>
            <option value="">— prenda —</option>
            <?php foreach ($productos as $p): ?><option value="<?= e($p->codigo) ?>"<?= $acopio['codigo'] === $p->codigo ? ' selected' : '' ?>><?= e($p->codigo . ' · ' . $p->nombre . ' · ' . $p->socio->nombre) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-4"><label class="form-label" for="entregadas">Entregadas</label><input class="form-control" id="entregadas" name="entregadas" type="number" min="1" max="1000" value="<?= e($acopio['entregadas']) ?>" required></div>
        <div class="col-4"><label class="form-label" for="aprobadas">Aprobadas</label><input class="form-control" id="aprobadas" name="aprobadas" type="number" min="0" max="1000" value="<?= e($acopio['aprobadas']) ?>" required></div>
        <div class="col-4"><label class="form-label" for="precio">Bs por prenda</label><input class="form-control" id="precio" name="precio" inputmode="decimal" value="<?= e($acopio['precio']) ?>" required placeholder="50,00"></div>
        <div class="col-12"><label class="form-label" for="observacion">Motivo del rechazo (si hay prendas no aprobadas)</label><input class="form-control" id="observacion" name="observacion" maxlength="200" value="<?= e($acopio['observacion']) ?>" placeholder="2 con la costura abierta"></div>
        <div class="col-12"><button class="btn btn-outline-marca mt-2" type="submit"><i class="bi bi-check2 me-1"></i>Registrar acopio</button></div>
      </form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0"><div class="card-body">
      <h2 class="h6">Últimas ventas</h2>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th scope="col">N.º</th><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col" class="text-end">Prendas</th><th scope="col" class="text-end">Total</th></tr></thead>
        <tbody><?php foreach ($ventas as $v): ?><tr><td><?= (int) $v['id'] ?></td><td class="text-nowrap"><?= e(fecha_bo($v['fecha'])) ?></td><td><?= e($v['cliente']) ?></td><td class="text-end"><?= (int) $v['prendas'] ?></td><td class="text-end text-nowrap"><?= bs((float) $v['total']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0"><div class="card-body">
      <h2 class="h6">Últimos acopios</h2>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th scope="col">Fecha</th><th scope="col">Socia</th><th scope="col">Prenda</th><th scope="col" class="text-end">Aprob./Entr.</th><th scope="col" class="text-end">Liquidado</th></tr></thead>
        <tbody><?php foreach ($acopios as $a): ?><tr><td class="text-nowrap"><?= e(fecha_bo($a['fecha'], false)) ?></td><td><?= e($a['socio']) ?></td><td><span class="font-monospace small"><?= e($a['codigo']) ?></span></td><td class="text-end" title="<?= e($a['observacion']) ?>"><?= (int) $a['aprobadas'] ?>/<?= (int) $a['entregadas'] ?></td><td class="text-end text-nowrap"><?= bs((float) $a['liquidado']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
