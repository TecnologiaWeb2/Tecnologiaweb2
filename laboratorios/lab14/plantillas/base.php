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
<header class="cabecera">
  <div class="cabecera-interior">
    <h1 class="marca"><a href="/productos">Apicultores Flor de la Chiquitanía</a></h1>
    <?php if ($usuarioNombre !== ''): ?>
    <div class="cabecera-barra">
      <nav class="menu" aria-label="Principal">
        <a class="menu-enlace" href="/productos">Productos</a>
        <a class="menu-enlace" href="/catalogo">Catálogo en línea</a>
        <?php if ($verSocios): ?><a class="menu-enlace" href="/socios">Socios</a><?php endif; ?>
        <?php if ($verVentas): ?><a class="menu-enlace" href="/ventas/nueva">Ventas</a><?php endif; ?>
        <?php if ($verVentas): ?><a class="menu-enlace" href="/pedidos/importar">Importar pedido</a><?php endif; ?>
        <?php if ($verReportes): ?><a class="menu-enlace" href="/reportes">Reportes</a><?php endif; ?>
      </nav>
      <div class="sesion">
        <span class="usuario"><strong><?= $usuarioNombre ?></strong> <small><?= $usuarioRol ?></small></span>
        <form method="post" action="/salir" class="en-linea">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" class="btn btn-claro btn-sm">Salir</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</header>
<main class="contenido">
<?= $contenido ?>
</main>
<footer class="pie">
  <p>Asociación de Apicultores Flor de la Chiquitanía · Tecnología Web II</p>
  <p class="pie-datos">Datos abiertos:
    <a href="/datos/catalogo.json">JSON</a> · <a href="/datos/catalogo.xml">XML</a> · <a href="/noticias.rss">RSS</a> · <a href="/api/v1/productos">API REST</a> · <a href="/api/v1/openapi.yaml">Contrato OpenAPI</a></p>
</footer>
</body>
</html>
