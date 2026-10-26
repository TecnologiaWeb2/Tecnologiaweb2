<?php /** plantillas/productos.php — Lista paginada con filtros. Todas las variables llegan escapadas. */ ?>
<div class="titulo-pagina">
  <h2>Productos</h2>
  <?php if ($puedeCrear): ?><a class="btn btn-primario" href="/productos/nuevo">+ Registrar producto</a><?php endif; ?>
</div>
<?php if ($mensaje !== ''): ?><p class="exito" role="status"><?= $mensaje ?></p><?php endif; ?>
<form method="get" action="/productos" role="search" class="filtros tarjeta">
  <div class="campo"><label for="q">Buscar</label>
    <input id="q" name="q" type="search" value="<?= $q ?>" placeholder="Nombre o código"></div>
  <div class="campo"><label for="categoria">Categoría</label>
    <select id="categoria" name="categoria"><option value="">Todas</option>
      <?php foreach ($categorias as $cat): ?><option<?= $categoria === $cat ? ' selected' : '' ?>><?= $cat ?></option><?php endforeach; ?>
    </select></div>
  <div class="campo"><label for="orden">Ordenar</label>
    <select id="orden" name="orden">
      <?php foreach ($ordenes as $clave => $texto): ?><option value="<?= $clave ?>"<?= $orden === $clave ? ' selected' : '' ?>><?= $texto ?></option><?php endforeach; ?>
    </select></div>
  <div class="botones">
    <button type="submit" class="btn btn-primario">Aplicar</button>
    <a class="btn btn-secundario" href="/productos">Limpiar</a>
  </div>
</form>
<div class="tabla-contenedor">
<table>
  <caption><?= $rango ?></caption>
  <thead><tr><th scope="col">Imagen</th><th scope="col">Código</th><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col" class="num">Precio</th><th scope="col" class="num">Stock</th><th scope="col">Socio</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): ?>
    <tr<?= $p['stock'] === 0 ? ' class="agotado"' : '' ?>>
      <td><img class="miniatura" src="<?= $p['imagen'] ?>" alt="Foto de <?= $p['nombre'] ?>" width="56" height="56" loading="lazy"></td>
      <td><code><?= $p['codigo'] ?></code></td>
      <td><strong><?= $p['nombre'] ?></strong></td>
      <td><span class="insignia cat-<?= strtolower($p['categoria']) ?>"><?= $p['categoria'] ?></span></td>
      <td class="num"><?= $p['precio_bs'] ?></td>
      <td class="num"><?php if ($p['stock'] === 0): ?><span class="insignia insignia-agotado">Agotado</span><?php elseif ($p['stock'] <= 5): ?><span class="insignia insignia-bajo"><?= $p['stock'] ?> · bajo</span><?php else: ?><?= $p['stock'] ?><?php endif; ?></td>
      <td><a href="/socios/<?= $p['socio_id'] ?>"><?= $p['socio'] ?></a></td>
      <td class="acciones">
        <?php if ($puedeEditar): ?><a class="btn btn-secundario btn-sm" href="/productos/<?= $p['codigo'] ?>/editar">Editar</a><?php endif; ?>
        <?php if ($puedeEliminar): ?>
        <form method="post" action="/productos/<?= $p['codigo'] ?>/eliminar" class="en-linea">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" class="btn btn-peligro btn-sm">Eliminar</button>
        </form>
        <?php endif; ?>
        <?php if (!$puedeEditar && !$puedeEliminar): ?><span class="tenue">—</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<nav aria-label="Paginación" class="paginacion">
  <?php if ($anterior !== null): ?><a class="btn btn-secundario" href="<?= $anterior ?>" rel="prev">← Anterior</a><?php endif; ?>
  <span aria-current="page">Página <?= $numero ?> de <?= $totalPaginas ?></span>
  <?php if ($siguiente !== null): ?><a class="btn btn-secundario" href="<?= $siguiente ?>" rel="next">Siguiente →</a><?php endif; ?>
</nav>
