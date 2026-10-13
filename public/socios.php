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

$titulo = 'Socios';
$seccion = 'socios';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<h1>Socios de la asociación</h1>
<?php foreach ($mensajes as $clave => $verbo): ?>
  <p class="exito" role="status">Socio <?= e(texto($_GET, $clave)) ?> <?= $verbo ?>.</p>
<?php endforeach; ?>
<?php if ($error !== ''): ?><p class="alerta" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="barra">
  <form method="get" action="/socios.php" role="search" class="en-linea">
    <label for="q" class="oculto">Buscar socio</label>
    <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Nombre, CI o comunidad" maxlength="60">
    <label for="estado" class="oculto">Estado</label>
    <select id="estado" name="estado">
      <option value="">Todos</option>
      <option value="activos"<?= $estado === 'activos' ? ' selected' : '' ?>>Activos</option>
      <option value="inactivos"<?= $estado === 'inactivos' ? ' selected' : '' ?>>Inactivos</option>
    </select>
    <button type="submit">Buscar</button>
  </form>
  <a class="boton" href="/socio_form.php">+ Nuevo socio</a>
</div>
<div class="tabla">
<table>
  <caption><?= $resultado->total ?> socios<?= $resultado->paginas() > 1 ? " · página {$resultado->pagina} de {$resultado->paginas()}" : '' ?></caption>
  <thead><tr><th scope="col">CI</th><th scope="col">Nombre</th><th scope="col">Celular</th><th scope="col">Comunidad</th><th scope="col">Especialidad</th><th scope="col">Ingreso</th><th scope="col" class="num">Prendas</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($resultado->elementos as ['socio' => $s, 'productos' => $prendas]): ?>
    <tr<?= $s->activo ? '' : ' class="agotado"' ?>>
      <td><?= e($s->ci) ?></td>
      <td><?= e($s->nombre) ?></td>
      <td><?= e($s->telefono) ?></td>
      <td><?= e($s->comunidad) ?></td>
      <td><?= e($s->especialidad) ?></td>
      <td><?= e($s->fechaIngresoTexto()) ?></td>
      <td class="num"><?= $prendas ?></td>
      <td><?= $s->activo ? 'Activo' : '<span class="insignia">Inactivo</span>' ?></td>
      <td class="acciones">
        <a href="/socio_form.php?id=<?= $s->id ?>">Editar</a>
        <form method="post" action="/socio_eliminar.php" class="en-linea" onsubmit="return confirm('¿Eliminar a <?= e($s->nombre) ?>?')">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= $s->id ?>">
          <button type="submit" class="peligro"<?= $prendas > 0 ? ' disabled title="Tiene prendas registradas"' : '' ?>>Eliminar</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= paginacion($resultado, '/socios.php', ['q' => $q, 'estado' => $estado]) ?>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
