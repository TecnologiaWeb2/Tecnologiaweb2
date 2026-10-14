<?php
// public/socios.php — Listado paginado de socios con búsqueda, filtro de estado y cantidad de prendas de cada uno.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

const POR_PAGINA = 5;
$q = texto($_GET, 'q');
$estado = texto($_GET, 'estado');                    // '', 'activos' o 'inactivos'
$activos = match ($estado) { 'activos' => true, 'inactivos' => false, default => null };
$resultado = (new SocioRepositorio(Conexion::obtener()))->paginar(
    pagina: (int) filter_var(texto($_GET, 'pagina', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]),
    porPagina: POR_PAGINA,
    texto: $q,
    activos: $activos,
);
$mensajes = array_filter(
    ['creado' => 'registrado', 'actualizado' => 'actualizado', 'eliminado' => 'eliminado'],
    fn(string $clave): bool => texto($_GET, $clave) !== '',
    ARRAY_FILTER_USE_KEY,
);
$error = texto($_GET, 'error') === 'tiene-prendas'
    ? 'No se puede eliminar a un socio que tiene prendas registradas. Puedes marcarlo como inactivo.' : '';

foreach ($mensajes as $clave => $verbo) {
    avisar('Socio ' . texto($_GET, $clave) . " $verbo.");
}
if ($error !== '') {
    avisar($error, 'error');
}

$titulo = 'Socios';
$seccion = 'socios';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Socios de la asociación</h1>
    <p class="text-secondary mb-0">Confeccionistas y bordadoras que registran sus prendas en la tienda.</p>
  </div>
  <a class="btn btn-marca" href="/socio_form.php" data-modal data-titulo="Registrar socio"><i class="bi bi-person-plus me-1"></i>Nuevo socio</a>
</div>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <form method="get" action="/socios.php" role="search" class="row g-2 mb-3">
      <div class="col-md-8">
        <label for="q" class="visually-hidden">Buscar socio</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input id="q" name="q" type="search" class="form-control" value="<?= e($q) ?>" placeholder="Nombre, CI o comunidad" maxlength="60">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <label for="estado" class="visually-hidden">Estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="activos"<?= $estado === 'activos' ? ' selected' : '' ?>>Activos</option>
          <option value="inactivos"<?= $estado === 'inactivos' ? ' selected' : '' ?>>Inactivos</option>
        </select>
      </div>
      <div class="col-6 col-md-2 d-grid"><button type="submit" class="btn btn-marca">Buscar</button></div>
    </form>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <caption class="caption-top"><?= $resultado->total ?> socios<?= $resultado->paginas() > 1 ? " · página {$resultado->pagina} de {$resultado->paginas()}" : '' ?></caption>
      <thead class="table-light"><tr><th scope="col">CI</th><th scope="col">Nombre</th><th scope="col">Celular</th><th scope="col">Comunidad</th><th scope="col">Especialidad</th><th scope="col">Ingreso</th><th scope="col" class="text-end">Prendas</th><th scope="col">Estado</th><th scope="col" class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($resultado->elementos as ['socio' => $s, 'productos' => $prendas]): ?>
        <tr<?= $s->activo ? '' : ' class="agotado"' ?>>
          <td class="font-monospace small"><?= e($s->ci) ?></td>
          <td class="fw-semibold"><span class="avatar me-2" aria-hidden="true"><?= e(mb_substr($s->nombre, 0, 1)) ?></span><?= e($s->nombre) ?></td>
          <td><?= e($s->telefono) ?></td>
          <td><?= e($s->comunidad) ?></td>
          <td><?= e($s->especialidad) ?></td>
          <td class="small text-nowrap"><?= e($s->fechaIngresoTexto()) ?></td>
          <td class="text-end"><span class="badge rounded-pill text-bg-light border"><?= $prendas ?></span></td>
          <td><?= $s->activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
          <td class="text-end text-nowrap acciones">
            <a class="btn btn-sm btn-outline-secondary" href="/socio_form.php?id=<?= $s->id ?>" data-modal data-titulo="Editar socio" title="Editar"><i class="bi bi-pencil"></i><span class="visually-hidden">Editar</span></a>
            <form method="post" action="/socio_eliminar.php" class="d-inline" data-confirmar="Se eliminará a <?= e($s->nombre) ?> (CI <?= e($s->ci) ?>). Esta acción no se puede deshacer." data-confirmar-titulo="¿Eliminar este socio?">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= $s->id ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"<?= $prendas > 0 ? ' disabled title="Tiene prendas registradas"' : ' title="Eliminar"' ?>><i class="bi bi-trash3"></i><span class="visually-hidden">Eliminar</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?= paginacion($resultado, '/socios.php', ['q' => $q, 'estado' => $estado]) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
