<?php
// public/producto.php?codigo=POL-001 — Ficha de una prenda.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$p = (new ProductoRepositorio(Conexion::obtener()))->buscarPorCodigo(strtoupper(texto($_GET, 'codigo')));
if ($p === null) {
    http_response_code(404);
    $titulo = 'Prenda no encontrada';
    require __DIR__ . '/../plantillas/cabecera.php';
    echo '<div class="text-center py-4"><i class="bi bi-search fs-1 text-secondary"></i><h1 class="h4 mt-2">Prenda no encontrada</h1><p><a href="/">Volver a la tienda</a></p></div>';
    require __DIR__ . '/../plantillas/pie.php';
    exit;
}

$titulo = $p->nombre;
$seccion = 'tienda';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<?php if (!es_parcial()): ?><div class="card shadow-sm border-0"><div class="card-body p-4"><?php endif; ?>
<article class="detalle row g-4 align-items-start">
  <div class="col-md-6">
    <?php if ($p->foto !== null): ?>
      <img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="480" height="480" class="img-fluid rounded-3 w-100 foto-detalle">
    <?php else: ?>
      <span class="sin-foto grande" aria-hidden="true"><?= e(mb_substr($p->nombre, 0, 1)) ?></span>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <a class="badge rounded-pill text-bg-light border text-decoration-none mb-2" href="/?categoria=<?= urlencode($p->categoria->id) ?>"><i class="bi bi-tag me-1"></i><?= e($p->categoria->nombre) ?></a>
    <h1 class="h3 mb-1"><?= e($p->nombre) ?></h1>
    <p class="precio fs-3 fw-bold mb-3"><?= bs($p->precio) ?></p>
    <?php if ($p->descripcion !== ''): ?><p class="text-secondary"><?= nl2br(e($p->descripcion)) ?></p><?php endif; ?>
    <dl class="row small mb-3">
      <dt class="col-5 text-secondary fw-normal">Código</dt><dd class="col-7 font-monospace"><?= e($p->codigo) ?></dd>
      <dt class="col-5 text-secondary fw-normal">Tallas</dt><dd class="col-7"><?php foreach ($p->tallas as $t): ?><span class="badge text-bg-light border me-1"><?= e($t->value) ?></span><?php endforeach; ?></dd>
      <?php if ($p->color !== ''): ?><dt class="col-5 text-secondary fw-normal">Color</dt><dd class="col-7"><?= e($p->color) ?></dd><?php endif; ?>
      <dt class="col-5 text-secondary fw-normal">Confeccionó</dt><dd class="col-7"><?= e($p->socio->nombre) ?> (<?= e($p->socio->comunidad) ?>)</dd>
      <dt class="col-5 text-secondary fw-normal">Disponibilidad</dt><dd class="col-7"><?= $p->disponible() ? '<span class="badge text-bg-success">' . $p->stock . ' unidades</span>' : '<span class="badge text-bg-danger">Agotado</span>' ?></dd>
    </dl>
    <?php if (!es_parcial()): ?><a class="btn btn-outline-secondary" href="/"><i class="bi bi-arrow-left me-1"></i>Volver a la tienda</a><?php endif; ?>
  </div>
</article>
<?php if (!es_parcial()): ?></div></div><?php endif; ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
