<?php
// plantillas/formulario_socio.php — Formulario de socio (registrar y editar), también dentro del modal. Usa campo(), mensaje_error()
// y atributos_error() de plantillas/campos.php. Variables: $accion, $datos, $errores
declare(strict_types=1);
require_once __DIR__ . '/campos.php';
?>
<?= alerta_errores($errores) ?>
<form method="post" action="<?= e($accion) ?>" class="ficha" novalidate>
  <?= csrf_campo() ?>
  <div class="row g-3">
    <?php campo('ci', 'Cédula de identidad (ej. 4589231 o 6234598-1A)', $datos, $errores, 'text', 'maxlength="13" required autocomplete="off"', 6); ?>
    <?php campo('telefono', 'Celular', $datos, $errores, 'tel', 'maxlength="8" inputmode="numeric" required', 6); ?>
    <?php campo('nombre', 'Nombre completo', $datos, $errores, 'text', 'maxlength="80" required'); ?>
    <?php campo('comunidad', 'Comunidad', $datos, $errores, 'text', 'maxlength="60" required', 6); ?>
    <div class="col-md-6">
      <label class="form-label" for="f-especialidad">Especialidad</label>
      <select id="f-especialidad" class="<?= clase_control('especialidad', $errores, 'form-select') ?>" name="especialidad" required<?= atributos_error('especialidad', $errores) ?>>
        <option value="">— elegir —</option>
        <?php foreach (Socio::ESPECIALIDADES as $esp): ?>
          <option value="<?= e($esp) ?>"<?= $datos['especialidad'] === $esp ? ' selected' : '' ?>><?= e($esp) ?></option>
        <?php endforeach; ?>
      </select>
      <?= mensaje_error('especialidad', $errores) ?>
    </div>
    <?php campo('fecha_ingreso', 'Fecha de ingreso', $datos, $errores, 'date', 'required max="' . date('Y-m-d') . '"', 6); ?>
    <div class="col-md-6 d-flex align-items-end">
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" role="switch" id="f-activo" name="activo" value="1"<?= $datos['activo'] === '1' ? ' checked' : '' ?>>
        <label class="form-check-label" for="f-activo">Socio activo (puede registrar prendas)</label>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="/socios.php" data-cerrar>Cancelar</a>
    <button type="submit" class="btn btn-marca"><i class="bi bi-check2 me-1"></i>Guardar socio</button>
  </div>
</form>
