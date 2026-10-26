<?php /** plantillas/error.php — Página de error amable. Nunca muestra rutas, clases ni trazas. */ ?>
<section class="error-pagina tarjeta">
  <p class="codigo"><?= $codigo ?></p>
  <h2><?= $titulo ?></h2>
  <p><?= $mensaje ?></p>
  <p class="botones"><a class="btn btn-primario" href="/">Volver al catálogo</a></p>
</section>
