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

foreach ($mensajes as $clave => $verbo) {
    avisar('Prenda ' . texto($_GET, $clave) . " $verbo.");
}

$titulo = 'Gestión de prendas';
$seccion = 'admin';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Gestión de prendas</h1>
    <p class="resumen text-secondary mb-0" id="resumen-inventario"><?= $resumen['prendas'] ?> prendas · <?= $resumen['unidades'] ?> unidades · valor del inventario: <strong><?= bs($resumen['valor']) ?></strong></p>
  </div>
  <a class="btn btn-marca" href="/nuevo.php" data-modal data-titulo="Registrar prenda"><i class="bi bi-plus-lg me-1"></i>Nueva prenda</a>
</div>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <form method="get" action="/admin.php" role="search" class="mb-3">
      <label for="q" class="visually-hidden">Buscar prenda</label>
      <div class="input-group buscador">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input id="q" name="q" type="search" class="form-control" value="<?= e($q) ?>" placeholder="Código, nombre o color" maxlength="60">
        <button type="submit" class="btn btn-marca">Buscar</button>
      </div>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <caption class="caption-top"><?= $resultado->total ?> prendas<?= $q !== '' ? ' para «' . e($q) . '»' : '' ?><?= $resultado->paginas() > 1 ? " · página {$resultado->pagina} de {$resultado->paginas()}" : '' ?></caption>
      <thead class="table-light"><tr><th scope="col">Foto</th><th scope="col">Código</th><th scope="col">Prenda</th><th scope="col">Categoría</th><th scope="col">Confeccionó</th><th scope="col">Tallas</th><th scope="col" class="text-end">Precio</th><th scope="col" class="text-end">Stock</th><th scope="col" class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($resultado->elementos as $p): ?>
        <tr<?= $p->disponible() ? '' : ' class="agotado"' ?>>
          <td><?php if ($p->foto !== null): ?><img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="48" height="48" loading="lazy" class="rounded miniatura"><?php else: ?><span class="sin-foto mini" aria-hidden="true"><?= e(mb_substr($p->nombre, 0, 1)) ?></span><?php endif; ?></td>
          <td><span class="badge text-bg-light border font-monospace"><?= e($p->codigo) ?></span></td>
          <td class="fw-semibold"><a class="link-dark text-decoration-none" href="/producto.php?codigo=<?= urlencode($p->codigo) ?>" data-modal data-ancho data-titulo="Detalle de la prenda"><?= e($p->nombre) ?></a></td>
          <td><?= e($p->categoria->nombre) ?></td>
          <td><?= e($p->socio->nombre) ?></td>
          <td class="small"><?= e($p->tallasTexto(', ')) ?></td>
          <td class="text-end text-nowrap"><?= bs($p->precio) ?></td>
          <td class="text-end"><?= $p->disponible() ? $p->stock : '<span class="badge text-bg-danger">Agotado</span>' ?></td>
          <td class="text-end text-nowrap acciones">
            <a class="btn btn-sm btn-outline-secondary" href="/editar.php?codigo=<?= urlencode($p->codigo) ?>" data-modal data-titulo="Editar <?= e($p->codigo) ?>" title="Editar"><i class="bi bi-pencil"></i><span class="visually-hidden">Editar</span></a>
            <form method="post" action="/eliminar.php" class="d-inline" data-confirmar="Se eliminará «<?= e($p->nombre) ?>» (<?= e($p->codigo) ?>) y su foto. Esta acción no se puede deshacer." data-confirmar-titulo="¿Eliminar esta prenda?">
              <?= csrf_campo() ?>
              <input type="hidden" name="codigo" value="<?= e($p->codigo) ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash3"></i><span class="visually-hidden">Eliminar</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?= paginacion($resultado, '/admin.php', ['q' => $q]) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
