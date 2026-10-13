<?php
// plantillas/formulario_socio.php — Formulario de socio (registrar y editar). Usa campo(), mensaje_error() y
// atributos_error() de plantillas/campos.php. Variables: $accion, $datos, $errores
declare(strict_types=1);
require_once __DIR__ . '/campos.php';
?>
<?php if ($errores !== []): ?>
  <p class="alerta" role="alert">Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los ' . count($errores) . ' campos marcados' ?>.</p>
<?php endif; ?>
<form method="post" action="<?= e($accion) ?>" class="ficha" novalidate>
  <?= csrf_campo() ?>
  <?php campo('ci', 'Cédula de identidad (ej. 4589231 o 6234598-1A)', $datos, $errores, 'text', 'maxlength="13" required autocomplete="off"'); ?>
  <?php campo('nombre', 'Nombre completo', $datos, $errores, 'text', 'maxlength="80" required'); ?>
  <?php campo('telefono', 'Celular', $datos, $errores, 'tel', 'maxlength="8" inputmode="numeric" required'); ?>
  <?php campo('comunidad', 'Comunidad', $datos, $errores, 'text', 'maxlength="60" required'); ?>
  <p>
    <label for="f-especialidad">Especialidad</label>
    <select id="f-especialidad" name="especialidad" required<?= atributos_error('especialidad', $errores) ?>>
      <option value="">— elegir —</option>
      <?php foreach (Socio::ESPECIALIDADES as $esp): ?>
        <option value="<?= e($esp) ?>"<?= $datos['especialidad'] === $esp ? ' selected' : '' ?>><?= e($esp) ?></option>
      <?php endforeach; ?>
    </select>
    <?= mensaje_error('especialidad', $errores) ?>
  </p>
  <?php campo('fecha_ingreso', 'Fecha de ingreso', $datos, $errores, 'date', 'required max="' . date('Y-m-d') . '"'); ?>
  <p class="opcion"><label><input type="checkbox" name="activo" value="1"<?= $datos['activo'] === '1' ? ' checked' : '' ?>> Socio activo (puede registrar prendas)</label></p>
  <p><button type="submit">Guardar socio</button> <a href="/socios.php">Cancelar</a></p>
</form>
