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
<section class="portada">
  <h1>Ropa hecha a mano en el oriente boliviano</h1>
  <p>Prendas confeccionadas por las socias de la asociación, con telas y bordados de la región.</p>
</section>

<form method="get" action="/" class="filtros" role="search">
  <p><label for="q">Buscar</label><input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="jean, bordado, azul…" maxlength="60"></p>
  <p>
    <label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <option value="">Todas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= e($c->id) ?>"<?= $categoria === $c->id ? ' selected' : '' ?>><?= e($c->nombre) ?> (<?= $conteo[$c->id] ?? 0 ?>)</option>
      <?php endforeach; ?>
    </select>
  </p>
  <p>
    <label for="talla">Talla</label>
    <select id="talla" name="talla">
      <option value="">Todas</option>
      <?php foreach (Talla::cases() as $t): ?>
        <option value="<?= e($t->value) ?>"<?= $talla === $t ? ' selected' : '' ?>><?= e($t->etiqueta()) ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p>
    <label for="orden">Ordenar por</label>
    <select id="orden" name="orden">
      <?php foreach (ProductoRepositorio::ORDENES as $clave => $etiqueta): ?>
        <option value="<?= e($clave) ?>"<?= $orden === $clave ? ' selected' : '' ?>><?= e($etiqueta) ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p class="opcion"><label><input type="checkbox" name="disponibles" value="1"<?= $soloDisponibles ? ' checked' : '' ?>> Solo con stock</label></p>
  <p><button type="submit">Filtrar</button> <a href="/">Limpiar</a></p>
</form>

<p class="resumen" role="status">
  <?= $resultado->total ?> <?= $resultado->total === 1 ? 'prenda' : 'prendas' ?><?= $q !== '' ? ' para «' . e($q) . '»' : '' ?>
  <?php if ($resultado->paginas() > 1): ?> · mostrando <?= $resultado->desde() ?>–<?= $resultado->hasta() ?><?php endif; ?>
</p>

<?php if ($resultado->elementos === []): ?>
  <p class="vacio">No encontramos prendas con esos filtros.</p>
<?php else: ?>
<ul class="grilla">
  <?php foreach ($resultado->elementos as $p): ?>
    <li class="tarjeta<?= $p->disponible() ? '' : ' agotado' ?>">
      <a href="/producto.php?codigo=<?= urlencode($p->codigo) ?>">
        <?php if ($p->foto !== null): ?>
          <img src="/foto.php?f=<?= e($p->foto) ?>" alt="<?= e($p->nombre) ?>" width="320" height="320" loading="lazy">
        <?php else: ?>
          <span class="sin-foto" aria-hidden="true"><?= e(mb_substr($p->nombre, 0, 1)) ?></span>
        <?php endif; ?>
        <span class="nombre"><?= e($p->nombre) ?></span>
      </a>
      <span class="categoria"><?= e($p->categoria->nombre) ?></span>
      <span class="precio"><?= bs($p->precio) ?></span>
      <span class="tallas">Tallas: <?= e($p->tallasTexto()) ?></span>
      <?php if (!$p->disponible()): ?><span class="insignia">Agotado</span><?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?= paginacion($resultado, '/', $filtros) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
