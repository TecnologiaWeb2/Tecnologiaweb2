<?php
// public/admin.php — Gestión de prendas: tabla paginada con búsqueda, acciones de editar/eliminar y resumen del inventario.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

const POR_PAGINA = 10;
$repo = new ProductoRepositorio(Conexion::obtener());
$q = texto($_GET, 'q');
$resultado = $repo->paginar(
    pagina: (int) filter_var(texto($_GET, 'pagina', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]),
    porPagina: POR_PAGINA,
    texto: $q,
);
$resumen = $repo->resumenInventario();
$mensajes = array_filter(
    ['creado' => 'registrada', 'actualizado' => 'actualizada', 'eliminado' => 'eliminada'],
    fn(string $clave): bool => texto($_GET, $clave) !== '',
    ARRAY_FILTER_USE_KEY,
);

$titulo = 'Gestión de prendas';
$seccion = 'admin';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<h1>Gestión de prendas</h1>
<?php foreach ($mensajes as $clave => $verbo): ?>
  <p class="exito" role="status">Prenda <?= e(texto($_GET, $clave)) ?> <?= $verbo ?>.</p>
<?php endforeach; ?>
<p class="resumen" id="resumen-inventario"><?= $resumen['prendas'] ?> prendas · <?= $resumen['unidades'] ?> unidades · valor del inventario: <strong><?= bs($resumen['valor']) ?></strong></p>
<div class="barra">
  <form method="get" action="/admin.php" role="search" class="en-linea">
    <label for="q" class="oculto">Buscar prenda</label>
    <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Código, nombre o color" maxlength="60">
    <button type="submit">Buscar</button>
  </form>
  <a class="boton" href="/nuevo.php">+ Nueva prenda</a>
</div>
<div class="tabla">
<table>
  <caption><?= $resultado->total ?> prendas<?= $q !== '' ? ' para «' . e($q) . '»' : '' ?><?= $resultado->paginas() > 1 ? " · página {$resultado->pagina} de {$resultado->paginas()}" : '' ?></caption>
  <thead><tr><th scope="col">Foto</th><th scope="col">Código</th><th scope="col">Prenda</th><th scope="col">Categoría</th><th scope="col">Confeccionó</th><th scope="col">Tallas</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Stock</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($resultado->elementos as $p): ?>
    <tr<?= $p->disponible() ? '' : ' class="agotado"' ?>>
      <td><?php if ($p->foto !== null): ?><img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="56" height="56" loading="lazy"><?php endif; ?></td>
      <td><?= e($p->codigo) ?></td>
      <td><?= e($p->nombre) ?></td>
      <td><?= e($p->categoria->nombre) ?></td>
      <td><?= e($p->socio->nombre) ?></td>
      <td><?= e($p->tallasTexto(', ')) ?></td>
      <td class="num"><?= bs($p->precio) ?></td>
      <td class="num"><?= $p->stock ?></td>
      <td class="acciones">
        <a href="/editar.php?codigo=<?= urlencode($p->codigo) ?>">Editar</a>
        <form method="post" action="/eliminar.php" class="en-linea" onsubmit="return confirm('¿Eliminar <?= e($p->codigo) ?>?')">
          <?= csrf_campo() ?>
          <input type="hidden" name="codigo" value="<?= e($p->codigo) ?>">
          <button type="submit" class="peligro">Eliminar</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= paginacion($resultado, '/admin.php', ['q' => $q]) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
