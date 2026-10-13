<?php
// public/producto.php?codigo=POL-001 — Ficha de una prenda.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$catalogo = new Catalogo();
$p = $catalogo->buscar(strtoupper(texto($_GET, 'codigo')));
if ($p === null) {
    http_response_code(404);
    $titulo = 'Prenda no encontrada';
    require __DIR__ . '/../plantillas/cabecera.php';
    echo '<h1>Prenda no encontrada</h1><p><a href="/">Volver a la tienda</a></p>';
    require __DIR__ . '/../plantillas/pie.php';
    exit;
}

$titulo = $p->nombre;
$seccion = 'tienda';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<article class="detalle">
  <?php if ($p->foto !== null): ?>
    <img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="480" height="480">
  <?php else: ?>
    <span class="sin-foto grande" aria-hidden="true"><?= e(mb_substr($p->nombre, 0, 1)) ?></span>
  <?php endif; ?>
  <div>
    <p class="categoria"><a href="/?categoria=<?= urlencode($p->categoria->id) ?>"><?= e($p->categoria->nombre) ?></a></p>
    <h1><?= e($p->nombre) ?></h1>
    <p class="precio"><?= bs($p->precio) ?></p>
    <?php if ($p->descripcion !== ''): ?><p><?= nl2br(e($p->descripcion)) ?></p><?php endif; ?>
    <dl>
      <dt>Código</dt><dd><?= e($p->codigo) ?></dd>
      <dt>Tallas</dt><dd><?= e($p->tallasTexto()) ?></dd>
      <?php if ($p->color !== ''): ?><dt>Color</dt><dd><?= e($p->color) ?></dd><?php endif; ?>
      <dt>Disponibilidad</dt><dd><?= $p->disponible() ? $p->stock . ' unidades' : '<span class="insignia">Agotado</span>' ?></dd>
    </dl>
    <p><a href="/">← Volver a la tienda</a></p>
  </div>
</article>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
