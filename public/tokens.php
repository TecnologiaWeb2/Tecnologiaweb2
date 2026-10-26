<?php
// public/tokens.php — Tokens de la API REST (Laboratorio 13, solo administración). El token se muestra UNA vez:
// en la base solo queda su hash SHA-256. Al publicar, se revocan los de prueba y se emiten nuevos.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_usuario('tokens.gestionar');
$pdo = Conexion::obtener();
$error = '';
$tokenNuevo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    try {
        if (texto($_POST, 'accion') === 'revocar') {
            $pdo->prepare('UPDATE tokens_api SET revocado = 1 WHERE id = :id')->execute(['id' => (int) texto($_POST, 'id')]);
            redirigir('/tokens.php?revocado=1');
        }
        $usuarioId = filter_var(texto($_POST, 'usuario_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id = :id');
        $existe->execute(['id' => $usuarioId === false ? 0 : $usuarioId]);
        if ((int) $existe->fetchColumn() !== 1) {
            throw new InvalidArgumentException('Elige el usuario con cuyo rol actuará el token.');
        }
        $tokenNuevo = crear_token_api($pdo, $usuarioId, texto($_POST, 'nombre'), (int) texto($_POST, 'dias', '90'));
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}
if (texto($_GET, 'revocado') !== '') {
    avisar('Token revocado: deja de funcionar de inmediato.');
}
$tokens = $pdo->query('SELECT t.id, t.nombre, t.creado_en, t.expira, t.revocado, u.nombre AS usuario, u.rol
    FROM tokens_api t JOIN usuarios u ON u.id = t.usuario_id ORDER BY t.revocado, t.id DESC')->fetchAll();
$usuarios = $pdo->query('SELECT id, nombre, rol FROM usuarios WHERE activo = 1 ORDER BY nombre')->fetchAll();

$titulo = 'Tokens de la API';
$seccion = 'tokens';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="mb-4">
  <h1 class="h3 mb-1">Tokens de la API</h1>
  <p class="text-secondary mb-0">Para escribir en <code>/api/v1</code> se envía <code>Authorization: Bearer mdo_…</code>. Contrato: <a href="/api/v1/openapi.yaml">openapi.yaml</a>.</p>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($tokenNuevo !== null): ?>
  <div class="alert alert-warning" role="status">
    <strong>Copia este token ahora: no se volverá a mostrar.</strong>
    <input class="form-control font-monospace mt-2" id="token-nuevo" value="<?= e($tokenNuevo) ?>" readonly aria-label="Token nuevo">
    <div class="small mt-2">No lo subas a GitHub ni lo pegues en el informe: guárdalo en una variable de la consola.</div>
  </div>
<?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0"><div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <caption class="caption-top"><?= count($tokens) ?> tokens (en la base solo está su hash SHA-256)</caption>
          <thead class="table-light"><tr><th scope="col">Para</th><th scope="col">Actúa como</th><th scope="col">Vence</th><th scope="col"><span class="visually-hidden">Acción</span></th></tr></thead>
          <tbody>
          <?php foreach ($tokens as $t): $vencido = strtotime($t['expira']) < time(); ?>
            <tr<?= $t['revocado'] || $vencido ? ' class="text-secondary"' : '' ?>>
              <td><?= e($t['nombre']) ?></td>
              <td><?= e($t['usuario']) ?> <span class="badge text-bg-light border"><?= e(ROLES[$t['rol']]) ?></span></td>
              <td class="text-nowrap"><?= e(fecha_bo($t['expira'], false)) ?></td>
              <td class="text-end">
                <?php if ($t['revocado']): ?><span class="badge text-bg-secondary">Revocado</span>
                <?php elseif ($vencido): ?><span class="badge text-bg-secondary">Vencido</span>
                <?php else: ?>
                  <form method="post" action="/tokens.php" data-confirmar="El sistema que use este token dejará de funcionar." data-confirmar-titulo="¿Revocar el token?">
                    <?= csrf_campo() ?><input type="hidden" name="accion" value="revocar"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Revocar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($tokens === []): ?><tr><td colspan="4" class="text-secondary">Todavía no hay tokens.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm border-0"><div class="card-body">
      <h2 class="h5">Emitir token</h2>
      <form method="post" action="/tokens.php" novalidate>
        <?= csrf_campo() ?>
        <label class="form-label" for="t-nombre">¿Para qué sistema?</label>
        <input class="form-control mb-2" id="t-nombre" name="nombre" required maxlength="80" placeholder="App de la feria">
        <label class="form-label" for="t-usuario">Actúa con el rol de</label>
        <select class="form-select mb-2" id="t-usuario" name="usuario_id">
          <?php foreach ($usuarios as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e(ROLES[$u['rol']]) ?>)</option><?php endforeach; ?>
        </select>
        <label class="form-label" for="t-dias">Vigencia (días)</label>
        <input class="form-control mb-3" id="t-dias" name="dias" type="number" min="1" max="365" value="90">
        <button class="btn btn-marca w-100" type="submit"><i class="bi bi-key me-1"></i>Emitir token</button>
      </form>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
