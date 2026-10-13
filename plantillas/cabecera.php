<?php
// plantillas/cabecera.php — Parte común de todas las páginas (se incluye con require).
// Espera $titulo definida por la página; $seccion (opcional) marca el enlace activo del menú.
declare(strict_types=1);
$seccion ??= '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e(NOMBRE_TIENDA) ?></title>
  <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>
<header class="cabecera">
  <a class="marca" href="/"><?= e(NOMBRE_TIENDA) ?></a>
  <nav aria-label="Principal">
    <a href="/"<?= $seccion === 'tienda' ? ' aria-current="page"' : '' ?>>Tienda</a>
    <a href="/admin.php"<?= $seccion === 'admin' ? ' aria-current="page"' : '' ?>>Gestión</a>
    <a href="/nuevo.php"<?= $seccion === 'nuevo' ? ' aria-current="page"' : '' ?>>Nueva prenda</a>
  </nav>
</header>
<main>
