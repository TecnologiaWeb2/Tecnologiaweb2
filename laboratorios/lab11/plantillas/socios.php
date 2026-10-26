<?php /** plantillas/socios.php */ ?>
<div class="titulo-pagina"><h2>Socios</h2></div>
<div class="tabla-contenedor">
<table>
  <caption><?= count($socios) ?> socios productores</caption>
  <thead><tr><th scope="col">Socio</th><th scope="col">Comunidad</th><th scope="col">Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($socios as $s): ?>
    <tr>
      <td><strong><?= $s['nombre'] ?></strong></td>
      <td><?= $s['comunidad'] ?></td>
      <td class="acciones"><a class="btn btn-secundario btn-sm" href="/socios/<?= $s['id'] ?>">Ver productos</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
