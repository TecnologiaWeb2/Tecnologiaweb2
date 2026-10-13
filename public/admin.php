<?php
// public/admin.php — Gestión del catálogo: tabla con acciones de editar y eliminar, y resumen del inventario.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$catalogo = new Catalogo();
$productos = $catalogo->filtrar(orden: 'nombre');
$mensajes = array_filter(
    ['creado' => 'registrada', 'actualizado' => 'actualizada', 'eliminado' => 'eliminada'],
    fn(string $clave): bool => texto($_GET, $clave) !== '',
    ARRAY_FILTER_USE_KEY,
);

$titulo = 'Gestión del catálogo';
$seccion = 'admin';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<h1>Gestión del catálogo</h1>
<?php foreach ($mensajes as $clave => $verbo): ?>
  <p class="exito" role="status">Prenda <?= e(texto($_GET, $clave)) ?> <?= $verbo ?>.</p>
<?php endforeach; ?>
<p class="resumen"><?= $catalogo->contar() ?> prendas · valor del inventario: <strong><?= bs($catalogo->valorInventario()) ?></strong></p>
<div class="tabla">
<table>
  <caption>Prendas registradas</caption>
  <thead><tr><th scope="col">Foto</th><th scope="col">Código</th><th scope="col">Prenda</th><th scope="col">Categoría</th><th scope="col">Tallas</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Stock</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): ?>
    <tr<?= $p->disponible() ? '' : ' class="agotado"' ?>>
      <td><?php if ($p->foto !== null): ?><img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="56" height="56" loading="lazy"><?php endif; ?></td>
      <td><?= e($p->codigo) ?></td>
      <td><?= e($p->nombre) ?></td>
      <td><?= e($p->categoria->nombre) ?></td>
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
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
