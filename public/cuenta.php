<?php
// public/cuenta.php — Mi cuenta: cambiar la contraseña propia (obligatorio al publicar: las de demostración son públicas).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$u = exigir_usuario();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
    try {
        if ($nueva !== ($_POST['repetir'] ?? null)) {
            throw new InvalidArgumentException('Las dos contraseñas nuevas no coinciden.');
        }
        cambiar_clave(Conexion::obtener(), $u->id, is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '', $nueva);
        redirigir('/cuenta.php?actualizado=1');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}
if (texto($_GET, 'actualizado') !== '') {
    avisar('Contraseña actualizada.');
}

$titulo = 'Mi cuenta';
$seccion = 'cuenta';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="card shadow-sm border-0 mx-auto formulario-pagina" style="max-width: 520px">
  <div class="card-body p-4">
    <h1 class="h4 mb-1">Mi cuenta</h1>
    <p class="text-secondary mb-4"><?= e($u->nombre) ?> · <?= e($u->correo) ?> · <span class="badge text-bg-light border"><?= e($u->etiqueta()) ?></span></p>
    <h2 class="h6">Cambiar contraseña</h2>
    <?php if ($error !== ''): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/cuenta.php" novalidate>
      <?= csrf_campo() ?>
      <label class="form-label" for="actual">Contraseña actual</label>
      <input class="form-control mb-3" id="actual" name="actual" type="password" required autocomplete="current-password">
      <label class="form-label" for="nueva">Contraseña nueva</label>
      <input class="form-control mb-1" id="nueva" name="nueva" type="password" required minlength="10" autocomplete="new-password" aria-describedby="ayuda-clave">
      <div class="form-text mb-3" id="ayuda-clave">Al menos 10 caracteres, con letras y números.</div>
      <label class="form-label" for="repetir">Repetir la contraseña nueva</label>
      <input class="form-control mb-4" id="repetir" name="repetir" type="password" required autocomplete="new-password">
      <button class="btn btn-marca" type="submit"><i class="bi bi-key me-1"></i>Guardar contraseña</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
