<?php /** plantillas/base.php — $contenido llega como Crudo; el resto escapado. $usuarioNombre/$csrf son datos compartidos. */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $titulo ?> · Flor de la Chiquitanía</title>
  <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
<header>
  <h1>Apicultores Flor de la Chiquitanía</h1>
  <?php if ($usuarioNombre !== ''): ?>
  <nav>
    <a href="/productos">Productos</a>
    <?php if ($verSocios): ?> · <a href="/socios">Socios</a><?php endif; ?>
    <span class="usuario">· <?= $usuarioNombre ?> (<?= $usuarioRol ?>)</span>
    <form method="post" action="/salir" class="en-linea">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <button type="submit">Salir</button>
    </form>
  </nav>
  <?php endif; ?>
</header>
<main>
<?= $contenido ?>
</main>
</body>
</html>
