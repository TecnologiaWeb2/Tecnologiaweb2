<?php /** plantillas/reportes.php — Reportes del período. */ ?>
<div class="titulo-pagina">
  <h2>Reportes</h2>
  <a class="btn btn-primario" href="<?= $csv ?>">⬇ Descargar detalle (CSV)</a>
</div>
<form method="get" action="/reportes" class="filtros tarjeta">
  <div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= $desde ?>"></div>
  <div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= $hasta ?>"></div>
  <div class="botones"><button type="submit" class="btn btn-primario">Ver</button></div>
</form>

<h3>Ventas por mes</h3>
<div class="tabla-contenedor">
<table id="por-mes"><thead><tr><th scope="col">Mes</th><th scope="col" class="num">Ventas</th><th scope="col" class="num">Total</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($porMes as $f): ?><tr><td><?= $f['mes'] ?></td><td class="num"><?= $f['ventas'] ?></td><td class="num"><?= $f['total'] ?></td>
  <td class="acciones"><a class="btn btn-secundario btn-sm" href="<?= $f['csv'] ?>">⬇ CSV del mes</a></td></tr><?php endforeach; ?>
</tbody></table>
</div>

<h3>Los 5 productos que más venden</h3>
<div class="tabla-contenedor">
<table id="top"><thead><tr><th scope="col">Código</th><th scope="col">Producto</th><th scope="col" class="num">Unidades</th><th scope="col" class="num">Importe</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($top as $f): ?><tr><td><code><?= $f['codigo'] ?></code></td><td><?= $f['nombre'] ?></td><td class="num"><?= $f['unidades'] ?></td><td class="num"><?= $f['importe'] ?></td>
  <td class="acciones"><?php if ($puedeEditar): ?><a class="btn btn-secundario btn-sm" href="/productos/<?= $f['codigo'] ?>/editar">Editar</a><?php endif; ?>
  <a class="btn btn-claro-borde btn-sm" href="/productos?q=<?= $f['codigo'] ?>">Ver en catálogo</a></td></tr><?php endforeach; ?>
</tbody></table>
</div>

<h3>Socios con ventas de Bs 3.000 o más (HAVING)</h3>
<div class="tabla-contenedor">
<table id="por-socio"><thead><tr><th scope="col">Socio</th><th scope="col">Comunidad</th><th scope="col" class="num">Importe</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($porSocio as $f): ?><tr><td><?= $f['socio'] ?></td><td><?= $f['comunidad'] ?></td><td class="num"><?= $f['importe'] ?></td>
  <td class="acciones"><a class="btn btn-secundario btn-sm" href="/socios/<?= $f['socio_id'] ?>">Ver socio</a></td></tr><?php endforeach; ?>
</tbody></table>
</div>

<h3>Productos sin ventas en el período</h3>
<div class="tabla-contenedor" id="sin-ventas">
<table><thead><tr><th scope="col">Código</th><th scope="col">Producto</th><th scope="col" class="num">Stock</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($sinVentas as $f): ?><tr><td><code><?= $f['codigo'] ?></code></td><td><?= $f['nombre'] ?></td><td class="num"><?= $f['stock'] ?></td>
  <td class="acciones"><?php if ($puedeEditar): ?><a class="btn btn-secundario btn-sm" href="/productos/<?= $f['codigo'] ?>/editar">Editar</a><?php endif; ?>
  <a class="btn btn-claro-borde btn-sm" href="/productos?q=<?= $f['codigo'] ?>">Ver en catálogo</a></td></tr><?php endforeach; ?>
</tbody></table>
</div>
