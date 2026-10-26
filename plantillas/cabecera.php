<?php
// plantillas/cabecera.php — Parte común de todas las páginas (se incluye con require).
// Espera $titulo definida por la página; $seccion (opcional) marca el enlace activo del menú.
// Si la página se pide desde un modal (es_parcial()), no se dibuja el esqueleto: solo el contenido.
// El menú muestra solo lo que el rol puede usar; cada página igual se protege sola con exigir_usuario().
declare(strict_types=1);
// La sesión (donde vive el token CSRF) debe iniciarse ANTES de enviar HTML: después ya no se puede mandar su cookie
iniciar_sesion();
$usuarioSesion = usuario_actual();
if (es_parcial()) {
    return;
}
$seccion ??= '';
$menu = ['tienda' => ['/', 'bi-shop', 'Tienda']];
$opciones = [
    'admin' => ['productos.ver', '/admin.php', 'bi-box-seam', 'Prendas'],
    'socios' => ['socios.ver', '/socios.php', 'bi-people', 'Socios'],
    'ventas' => ['ventas.registrar', '/ventas.php', 'bi-cart', 'Ventas'],
    'pedidos' => ['pedidos.gestionar', '/pedidos.php', 'bi-bag-check', 'Pedidos'],
    'liquidaciones' => ['liquidaciones.ver', '/liquidaciones.php', 'bi-cash-coin', $usuarioSesion?->rol === 'socio' ? 'Mi liquidación' : 'Liquidaciones'],
    'reportes' => ['reportes.ver', '/reportes.php', 'bi-bar-chart', 'Reportes'],
];
foreach ($opciones as $clave => [$permiso, $url, $icono, $texto]) {
    if ($usuarioSesion?->puede($permiso)) {
        $menu[$clave] = [$url, $icono, $texto];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e(NOMBRE_TIENDA) ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 32 32%27%3E%3Ccircle cx=%2716%27 cy=%2716%27 r=%2716%27 fill=%27%23f6dde3%27/%3E%3Ctext x=%2716%27 y=%2722%27 font-size=%2716%27 text-anchor=%27middle%27 font-family=%27Georgia%27 fill=%27%23a0566a%27%3EM%3C/text%3E%3C/svg%3E">
  <link rel="alternate" type="application/rss+xml" title="Novedades de <?= e(NOMBRE_TIENDA) ?>" href="/noticias.rss">
  <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/estilo.css">
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#principal">Saltar al contenido</a>
<nav class="navbar navbar-expand-lg navbar-light barra-marca sticky-top" aria-label="Principal">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/"><i class="bi bi-flower1 me-1"></i><?= e(NOMBRE_TIENDA) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php foreach ($menu as $clave => [$url, $icono, $texto]): ?>
          <li class="nav-item"><a class="nav-link<?= $seccion === $clave ? ' active' : '' ?>" href="<?= $url ?>"<?= $seccion === $clave ? ' aria-current="page"' : '' ?>><i class="bi <?= $icono ?> me-1"></i><?= e($texto) ?></a></li>
        <?php endforeach; ?>
        <?php if ($usuarioSesion !== null): ?>
          <li class="nav-item dropdown ms-lg-2">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="menu-usuario">
              <i class="bi bi-person-circle me-1"></i><?= e($usuarioSesion->nombre) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="menu-usuario">
              <li><span class="dropdown-item-text small text-secondary"><?= e($usuarioSesion->etiqueta()) ?></span></li>
              <li><a class="dropdown-item" href="/cuenta.php"><i class="bi bi-key me-2"></i>Mi cuenta</a></li>
              <?php if ($usuarioSesion->puede('usuarios.gestionar')): ?><li><a class="dropdown-item" href="/usuarios.php"><i class="bi bi-person-gear me-2"></i>Usuarios y roles</a></li><?php endif; ?>
              <?php if ($usuarioSesion->puede('tokens.gestionar')): ?><li><a class="dropdown-item" href="/tokens.php"><i class="bi bi-braces me-2"></i>Tokens de la API</a></li><?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><form method="post" action="/salir.php"><?= csrf_campo() ?><button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2"><a class="btn btn-outline-secondary btn-sm" href="/entrar.php"><i class="bi bi-person me-1"></i>Ingresar</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4 flex-grow-1" id="principal">
