<?php
// public/index.php — Vitrina de la tienda: filtros por GET (texto, categoría, talla, disponibilidad, orden) y paginación.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

const POR_PAGINA = 8;
$pdo = Conexion::obtener();
$categorias = (new CategoriaRepositorio($pdo))->todas();
$conteo = (new CategoriaRepositorio($pdo))->conteoProductos();

$q = texto($_GET, 'q');
$categoria = texto($_GET, 'categoria');
$talla = Talla::tryFrom(texto($_GET, 'talla'));
$orden = array_key_exists(texto($_GET, 'orden'), ProductoRepositorio::ORDENES) ? texto($_GET, 'orden') : 'nombre';
$soloDisponibles = texto($_GET, 'disponibles') === '1';
if (!isset($categorias[$categoria])) {
    $categoria = '';                                  // una categoría inventada en la URL se ignora
}

$resultado = (new ProductoRepositorio($pdo))->paginar(
    pagina: (int) filter_var(texto($_GET, 'pagina', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]),
    porPagina: POR_PAGINA,
    texto: $q,
    categoria: $categoria,
    talla: $talla,
    soloDisponibles: $soloDisponibles,
    orden: $orden,
);
$filtros = ['q' => $q, 'categoria' => $categoria, 'talla' => $talla?->value, 'orden' => $orden === 'nombre' ? '' : $orden,
    'disponibles' => $soloDisponibles ? '1' : ''];

$titulo = 'Tienda';
$seccion = 'tienda';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<section class="portada rounded-4 p-4 p-md-5 mb-4">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <span class="badge rounded-pill etiqueta-hero mb-3 px-3 py-2"><i class="bi bi-stars me-1"></i>Hecho a mano en la Chiquitanía</span>
      <h1 class="display-6 fw-bold mb-2">Ropa hecha a mano en el oriente boliviano</h1>
      <p class="lead mb-4">Prendas confeccionadas por las socias de la asociación, con telas y bordados de la región.</p>
      <a class="btn btn-marca btn-lg" href="#catalogo">Ver la colección <i class="bi bi-arrow-down-short"></i></a>
    </div>
    <div class="col-lg-4 text-lg-end d-none d-lg-block"><i class="bi bi-flower3 portada-icono"></i></div>
  </div>
</section>

<form method="get" action="/" class="filtros card card-body shadow-sm border-0 mb-4" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-md-4 col-lg-3">
      <label class="form-label small text-secondary mb-1" for="q">Buscar</label>
      <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="q" name="q" type="search" class="form-control" value="<?= e($q) ?>" placeholder="jean, bordado, azul…" maxlength="60"></div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-secondary mb-1" for="categoria">Categoría</label>
      <select id="categoria" name="categoria" class="form-select">
        <option value="">Todas</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= e($c->id) ?>"<?= $categoria === $c->id ? ' selected' : '' ?>><?= e($c->nombre) ?> (<?= $conteo[$c->id] ?? 0 ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-secondary mb-1" for="talla">Talla</label>
      <select id="talla" name="talla" class="form-select">
        <option value="">Todas</option>
        <?php foreach (Talla::cases() as $t): ?>
          <option value="<?= e($t->value) ?>"<?= $talla === $t ? ' selected' : '' ?>><?= e($t->etiqueta()) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <label class="form-label small text-secondary mb-1" for="orden">Ordenar por</label>
      <select id="orden" name="orden" class="form-select">
        <?php foreach (ProductoRepositorio::ORDENES as $clave => $etiqueta): ?>
          <option value="<?= e($clave) ?>"<?= $orden === $clave ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-4 col-lg-1">
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="disponibles" name="disponibles" value="1"<?= $soloDisponibles ? ' checked' : '' ?>><label class="form-check-label small" for="disponibles">Con stock</label></div>
    </div>
    <div class="col-md-4 col-lg-2 d-flex gap-2">
      <button type="submit" class="btn btn-marca flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
      <a class="btn btn-outline-secondary" href="/" title="Limpiar filtros"><i class="bi bi-x-lg"></i><span class="visually-hidden">Limpiar</span></a>
    </div>
  </div>
</form>

<p class="resumen text-secondary" role="status" id="catalogo">
  <?= $resultado->total ?> <?= $resultado->total === 1 ? 'prenda' : 'prendas' ?><?= $q !== '' ? ' para «' . e($q) . '»' : '' ?>
  <?php if ($resultado->paginas() > 1): ?> · mostrando <?= $resultado->desde() ?>–<?= $resultado->hasta() ?><?php endif; ?>
</p>

<?php if ($resultado->elementos === []): ?>
  <div class="vacio text-center p-5 bg-white rounded-4 shadow-sm"><i class="bi bi-bag-x fs-1 text-secondary"></i><p class="mt-2 mb-0">No encontramos prendas con esos filtros.</p></div>
<?php else: ?>
<ul class="grilla row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4 list-unstyled">
  <?php foreach ($resultado->elementos as $p): ?>
    <li class="tarjeta col<?= $p->disponible() ? '' : ' agotado' ?>">
      <article class="card h-100 border-0 shadow-sm">
        <a class="stretched-link text-decoration-none" href="/producto.php?codigo=<?= urlencode($p->codigo) ?>" data-modal data-ancho data-titulo="Detalle de la prenda">
          <div class="ratio ratio-1x1 card-img-top overflow-hidden rounded-top">
            <?php if ($p->foto !== null): ?>
              <img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="320" height="320" loading="lazy" class="object-fit-cover">
            <?php else: ?>
              <span class="sin-foto" aria-hidden="true"><?= e(mb_substr($p->nombre, 0, 1)) ?></span>
            <?php endif; ?>
          </div>
          <span class="visually-hidden">Ver <?= e($p->nombre) ?></span>
        </a>
        <?php if (!$p->disponible()): ?><span class="badge text-bg-danger position-absolute top-0 start-0 m-2">Agotado</span><?php endif; ?>
        <div class="card-body">
          <span class="categoria small text-secondary d-block"><?= e($p->categoria->nombre) ?></span>
          <h2 class="nombre h6 fw-semibold mb-1"><?= e($p->nombre) ?></h2>
          <span class="precio fw-bold d-block"><?= bs($p->precio) ?></span>
          <span class="tallas small text-secondary">Tallas: <?= e($p->tallasTexto()) ?></span>
        </div>
      </article>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?= paginacion($resultado, '/', $filtros) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
