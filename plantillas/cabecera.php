<?php
// plantillas/cabecera.php — Parte común de todas las páginas (se incluye con require).
// Espera $titulo definida por la página; $seccion (opcional) marca el enlace activo del menú.
// Si la página se pide desde un modal (es_parcial()), no se dibuja el esqueleto: solo el contenido.
declare(strict_types=1);
// La sesión (donde vive el token CSRF) debe iniciarse ANTES de enviar HTML: después ya no se puede mandar su cookie
iniciar_sesion();
if (es_parcial()) {
    return;
}
$seccion ??= '';
$menu = ['tienda' => ['/', 'bi-shop', 'Tienda'], 'admin' => ['/admin.php', 'bi-box-seam', 'Prendas'], 'socios' => ['/socios.php', 'bi-people', 'Socios']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e(NOMBRE_TIENDA) ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Ccircle cx=%2716%27 cy=%2716%27 r=%2716%27 fill=%27%23f6dde3%27/%3E%3Ctext x=%2716%27 y=%2722%27 font-size=%2716%27 text-anchor=%27middle%27 font-family=%27Georgia%27 fill=%27%23a0566a%27%3EM%3C/text%3E%3C/svg%3E">
  <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/estilo.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-md navbar-light barra-marca sticky-top" aria-label="Principal">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/"><i class="bi bi-flower1 me-1"></i><?= e(NOMBRE_TIENDA) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-1">
        <?php foreach ($menu as $clave => [$url, $icono, $texto]): ?>
          <li class="nav-item"><a class="nav-link<?= $seccion === $clave ? ' active' : '' ?>" href="<?= $url ?>"<?= $seccion === $clave ? ' aria-current="page"' : '' ?>><i class="bi <?= $icono ?> me-1"></i><?= $texto ?></a></li>
        <?php endforeach; ?>
        <li class="nav-item ms-md-2"><a class="btn btn-marca btn-sm" href="/nuevo.php" data-modal data-titulo="Registrar prenda"<?= $seccion === 'nuevo' ? ' aria-current="page"' : '' ?>><i class="bi bi-plus-lg"></i> Nueva prenda</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4 flex-grow-1">
