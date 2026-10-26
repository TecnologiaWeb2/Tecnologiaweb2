<?php
// public/reportes.php — Laboratorio 11: reporte de ventas por rango de fechas, verificado contra los datos de prueba
// (julio–septiembre 2026: 6 ventas, Bs 3.287,50). Se descarga en CSV desde /reportes.csv.php.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_usuario('reportes.ver');
$desde = texto($_GET, 'desde', '2026-07-01');
$hasta = texto($_GET, 'hasta', date('Y-m-d'));
$error = '';
try {
    $r = reporte_ventas(Conexion::obtener(), $desde, $hasta);
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
    http_response_code(422);
    [$desde, $hasta] = ['2026-07-01', date('Y-m-d')];
    $r = reporte_ventas(Conexion::obtener(), $desde, $hasta);
}
$liquidaciones = liquidaciones(Conexion::obtener());
$totales = array_reduce($liquidaciones, fn(array $t, array $f): array => [$t[0] + (float) $f['liquidado'], $t[1] + (float) $f['pagado'], $t[2] + (float) $f['saldo']], [0.0, 0.0, 0.0]);

$titulo = 'Reportes';
$seccion = 'reportes';
require __DIR__ . '/../plantillas/cabecera.php';
$consulta = http_build_query(['desde' => $desde, 'hasta' => $hasta]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div><h1 class="h3 mb-1">Reportes de ventas</h1><p class="text-secondary mb-0">Del <?= e(fecha_bo($desde, false)) ?> al <?= e(fecha_bo($hasta, false)) ?></p></div>
  <a class="btn btn-marca" href="/reportes.csv.php?<?= e($consulta) ?>"><i class="bi bi-filetype-csv me-1"></i>Descargar CSV</a>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body shadow-sm border-0 mb-4" method="get" action="/reportes.php">
  <div class="row g-2 align-items-end">
    <div class="col-sm-5"><label class="form-label" for="desde">Desde</label><input class="form-control" type="date" id="desde" name="desde" value="<?= e($desde) ?>"></div>
    <div class="col-sm-5"><label class="form-label" for="hasta">Hasta</label><input class="form-control" type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
    <div class="col-sm-2"><button class="btn btn-marca w-100" type="submit">Filtrar</button></div>
  </div>
</form>
<div class="row g-3 mb-4 text-center">
  <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Ventas</div><div class="fs-4 fw-bold" id="rep-ventas"><?= (int) $r['resumen']['ventas'] ?></div></div></div></div>
  <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Prendas vendidas</div><div class="fs-4 fw-bold" id="rep-prendas"><?= (int) $r['resumen']['prendas'] ?></div></div></div></div>
  <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary small">Total vendido</div><div class="fs-4 fw-bold" id="rep-total"><?= bs((float) $r['resumen']['total']) ?></div></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Ventas por mes</h2>
      <table class="table mb-0"><thead><tr><th scope="col">Mes</th><th scope="col" class="text-end">Ventas</th><th scope="col" class="text-end">Total</th></tr></thead>
        <tbody><?php foreach ($r['porMes'] as $f): ?><tr><td><?= e($f['mes']) ?></td><td class="text-end"><?= (int) $f['ventas'] ?></td><td class="text-end"><?= bs((float) $f['total']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Las 5 prendas más vendidas</h2>
      <table class="table mb-0"><thead><tr><th scope="col">Código</th><th scope="col">Prenda</th><th scope="col" class="text-end">Unidades</th><th scope="col" class="text-end">Importe</th></tr></thead>
        <tbody><?php foreach ($r['top'] as $f): ?><tr><td class="font-monospace small"><?= e($f['codigo']) ?></td><td><?= e($f['nombre']) ?></td><td class="text-end"><?= (int) $f['unidades'] ?></td><td class="text-end text-nowrap"><?= bs((float) $f['importe']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Vendido por socia</h2>
      <table class="table mb-0"><thead><tr><th scope="col">Socia</th><th scope="col" class="text-end">Unidades</th><th scope="col" class="text-end">Importe</th></tr></thead>
        <tbody><?php foreach ($r['porSocio'] as $f): ?><tr><td><?= e($f['socio']) ?></td><td class="text-end"><?= (int) $f['unidades'] ?></td><td class="text-end text-nowrap"><?= bs((float) $f['importe']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100"><div class="card-body">
      <h2 class="h5">Liquidaciones a socias (acumulado)</h2>
      <dl class="row mb-0">
        <dt class="col-7 fw-normal text-secondary">Liquidado por prendas aprobadas</dt><dd class="col-5 text-end" id="rep-liquidado"><?= bs($totales[0]) ?></dd>
        <dt class="col-7 fw-normal text-secondary">Pagado</dt><dd class="col-5 text-end"><?= bs($totales[1]) ?></dd>
        <dt class="col-7">Saldo pendiente</dt><dd class="col-5 text-end fw-bold" id="rep-saldo"><?= bs($totales[2]) ?></dd>
      </dl>
      <a class="small" href="/liquidaciones.php">Ver el detalle por socia →</a>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
