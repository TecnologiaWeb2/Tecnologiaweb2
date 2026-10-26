<?php /** plantillas/socio.php — Un socio con sus productos (relación 1 a N). */ ?>
<div class="titulo-pagina">
  <div><h2><?= $nombre ?></h2><p class="tenue"><?= $comunidad ?> · <?= $telefono ?></p></div>
  <a class="btn btn-secundario" href="/socios">← Volver a socios</a>
</div>
<div class="tabla-contenedor">
<table>
  <caption><?= count($productos) ?> productos · valor en stock <?= $valor ?></caption>
  <thead><tr><th scope="col">Imagen</th><th scope="col">Código</th><th scope="col">Producto</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Stock</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): ?>
    <tr<?= $p['stock'] === 0 ? ' class="agotado"' : '' ?>>
      <td><img class="miniatura" src="<?= $p['imagen'] ?>" alt="Foto de <?= $p['nombre'] ?>" width="56" height="56" loading="lazy"></td>
      <td><code><?= $p['codigo'] ?></code></td>
      <td><strong><?= $p['nombre'] ?></strong></td>
      <td class="num"><?= $p['precio_bs'] ?></td>
      <td class="num"><?= $p['stock'] ?></td>
      <td class="acciones">
        <?php if ($puedeEditar): ?><a class="btn btn-secundario btn-sm" href="/productos/<?= $p['codigo'] ?>/editar">Editar</a><?php endif; ?>
        <?php if ($puedeEliminar): ?>
        <form method="post" action="/productos/<?= $p['codigo'] ?>/eliminar" class="en-linea">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" class="btn btn-peligro btn-sm">Eliminar</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
