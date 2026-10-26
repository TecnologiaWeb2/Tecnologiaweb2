<?php
// public/entrar.php — Iniciar sesión (Laboratorio 10). POST con CSRF; tras 5 fallos la cuenta se bloquea 15 minutos.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$volver = ruta_interna(texto($_POST ?: $_GET, 'volver'), '');
if (($u = usuario_actual()) !== null) {
    redirigir($volver !== '' ? $volver : inicio_de($u));
}
$error = '';
$correo = '';
if (!empty($_SESSION['expirada'])) {
    $error = 'Tu sesión se cerró por inactividad. Vuelve a ingresar.';
    unset($_SESSION['expirada']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $correo = texto($_POST, 'correo');
    $resultado = autenticar_usuario(Conexion::obtener(), $correo, is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '');
    if ($resultado['usuario'] !== null) {
        redirigir($volver !== '' ? $volver : inicio_de($resultado['usuario']));
    }
    $error = (string) $resultado['error'];
    http_response_code(401);
}

$titulo = 'Iniciar sesión';
$seccion = 'entrar';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="card shadow-sm border-0 mx-auto formulario-pagina" style="max-width: 440px">
  <div class="card-body p-4">
    <h1 class="h4 mb-1">Iniciar sesión</h1>
    <p class="text-secondary small mb-4">Personal de la asociación y socias.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/entrar.php" novalidate>
      <?= csrf_campo() ?>
      <input type="hidden" name="volver" value="<?= e($volver) ?>">
      <label class="form-label" for="correo">Correo</label>
      <input class="form-control mb-3" id="correo" name="correo" type="email" value="<?= e($correo) ?>" required maxlength="120" autocomplete="username">
      <label class="form-label" for="clave">Contraseña</label>
      <input class="form-control mb-4" id="clave" name="clave" type="password" required autocomplete="current-password">
      <button class="btn btn-marca w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
