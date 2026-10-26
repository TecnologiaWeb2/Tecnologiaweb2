<?php
// public/usuarios.php — Administración de usuarios y roles (solo el rol admin). Crear usuario y cambiar rol, con CSRF.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$admin = exigir_usuario('usuarios.gestionar');
$pdo = Conexion::obtener();
$error = '';
$nuevo = ['correo' => '', 'nombre' => '', 'rol' => 'operador', 'socio_id' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $socioId = filter_var(texto($_POST, 'socio_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (texto($_POST, 'accion') === 'crear') {
            $nuevo = ['correo' => texto($_POST, 'correo'), 'nombre' => texto($_POST, 'nombre'), 'rol' => texto($_POST, 'rol'), 'socio_id' => texto($_POST, 'socio_id')];
            crear_usuario($pdo, $nuevo['correo'], $nuevo['nombre'], is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '',
                $nuevo['rol'], $socioId === false ? null : $socioId);
            redirigir('/usuarios.php?creado=1');
        }
        $id = filter_var(texto($_POST, 'id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('El usuario seleccionado no es válido.');
        }
        cambiar_rol_usuario($pdo, $id, texto($_POST, 'rol'), $socioId === false ? null : $socioId, $admin->id);
        redirigir('/usuarios.php?actualizado=1');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}
if (texto($_GET, 'creado') !== '') {
    avisar('Usuario creado. Entrégale su contraseña en persona y pídele que la cambie en «Mi cuenta».');
}
if (texto($_GET, 'actualizado') !== '') {
    avisar('Rol actualizado.');
}
$usuarios = $pdo->query("SELECT u.id, u.correo, u.nombre, u.rol, u.socio_id, u.bloqueado_hasta, s.nombre AS socio
    FROM usuarios u LEFT JOIN socios s ON s.id = u.socio_id ORDER BY FIELD(u.rol, 'admin', 'operador', 'socio'), u.nombre")->fetchAll();
$socios = $pdo->query('SELECT id, nombre FROM socios WHERE activo = 1 ORDER BY nombre')->fetchAll();

$titulo = 'Usuarios';
$seccion = 'usuarios';
require __DIR__ . '/../plantillas/cabecera.php';
$opcionesSocio = function (string $elegido) use ($socios): string {
    $html = '<option value="">Sin socio (personal)</option>';
    foreach ($socios as $s) {
        $html .= '<option value="' . (int) $s['id'] . '"' . ($elegido === (string) $s['id'] ? ' selected' : '') . '>' . e($s['nombre']) . '</option>';
    }
    return $html;
};
?>
<div class="mb-4">
  <h1 class="h3 mb-1">Usuarios y roles</h1>
  <p class="text-secondary mb-0">Administración: todo · Operador: prendas, socios, ventas, acopios, pedidos y pagos · Socia: solo su liquidación.</p>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0"><div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <caption class="caption-top"><?= count($usuarios) ?> usuarios</caption>
          <thead class="table-light"><tr><th scope="col">Usuario</th><th scope="col">Rol y socia vinculada</th></tr></thead>
          <tbody>
          <?php foreach ($usuarios as $usuario): $id = (int) $usuario['id']; ?>
            <tr>
              <td><span class="fw-semibold"><?= e($usuario['nombre']) ?></span><br><span class="small text-secondary"><?= e($usuario['correo']) ?></span>
                <?php if ($usuario['bloqueado_hasta'] !== null && strtotime($usuario['bloqueado_hasta']) > time()): ?><span class="badge text-bg-warning ms-1">Bloqueado</span><?php endif; ?></td>
              <td>
                <?php if ($id === $admin->id): ?>
                  <span class="badge text-bg-light border"><?= e(ROLES[$usuario['rol']]) ?></span> <span class="small text-secondary">(tu sesión)</span>
                <?php else: ?>
                  <form method="post" action="/usuarios.php" class="d-flex flex-wrap gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label class="visually-hidden" for="rol-<?= $id ?>">Rol de <?= e($usuario['nombre']) ?></label>
                    <select class="form-select form-select-sm w-auto" id="rol-<?= $id ?>" name="rol">
                      <?php foreach (ROLES as $valor => $etiqueta): ?><option value="<?= $valor ?>"<?= $usuario['rol'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option><?php endforeach; ?>
                    </select>
                    <label class="visually-hidden" for="socio-<?= $id ?>">Socia vinculada</label>
                    <select class="form-select form-select-sm w-auto" id="socio-<?= $id ?>" name="socio_id"><?= $opcionesSocio((string) $usuario['socio_id']) ?></select>
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Guardar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm border-0"><div class="card-body">
      <h2 class="h5">Nuevo usuario</h2>
      <form method="post" action="/usuarios.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="crear">
        <label class="form-label" for="n-nombre">Nombre</label>
        <input class="form-control mb-2" id="n-nombre" name="nombre" value="<?= e($nuevo['nombre']) ?>" required maxlength="80">
        <label class="form-label" for="n-correo">Correo</label>
        <input class="form-control mb-2" id="n-correo" name="correo" type="email" value="<?= e($nuevo['correo']) ?>" required maxlength="120" autocomplete="off">
        <label class="form-label" for="n-clave">Contraseña inicial</label>
        <input class="form-control mb-1" id="n-clave" name="clave" type="password" required minlength="10" autocomplete="new-password">
        <div class="form-text mb-2">10 caracteres o más, con letras y números.</div>
        <label class="form-label" for="n-rol">Rol</label>
        <select class="form-select mb-2" id="n-rol" name="rol">
          <?php foreach (ROLES as $valor => $etiqueta): ?><option value="<?= $valor ?>"<?= $nuevo['rol'] === $valor ? ' selected' : '' ?>><?= e($etiqueta) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label" for="n-socio">Socia vinculada (solo rol socia)</label>
        <select class="form-select mb-3" id="n-socio" name="socio_id"><?= $opcionesSocio($nuevo['socio_id']) ?></select>
        <button class="btn btn-marca w-100" type="submit"><i class="bi bi-person-plus me-1"></i>Crear usuario</button>
      </form>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
