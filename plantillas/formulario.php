<?php
// plantillas/formulario.php — Formulario de prenda compartido por nuevo.php y editar.php (include). Se usa también dentro del modal.
// Variables que debe definir la página: $categorias, $socios, $accion, $datos, $errores, $editando (bool); opcional $fotoActual
declare(strict_types=1);
require_once __DIR__ . '/campos.php';
?>
<?= alerta_errores($errores) ?>
<!-- enctype multipart/form-data: OBLIGATORIO para enviar archivos -->
<form method="post" action="<?= e($accion) ?>" enctype="multipart/form-data" class="ficha" novalidate>
  <?= csrf_campo() ?>
  <div class="row g-3">
    <?php campo('codigo', 'Código (ej. POL-001)', $datos, $errores, 'text', 'maxlength="9" required' . ($editando ? ' readonly' : ''), 4); ?>
    <?php campo('nombre', 'Nombre de la prenda', $datos, $errores, 'text', 'maxlength="80" required', 8); ?>
    <div class="col-md-6">
      <label class="form-label" for="f-categoria">Categoría</label>
      <select id="f-categoria" class="<?= clase_control('categoria', $errores, 'form-select') ?>" name="categoria" required<?= atributos_error('categoria', $errores) ?>>
        <option value="">— elegir —</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= e($c->id) ?>"<?= ($datos['categoria'] ?? '') === $c->id ? ' selected' : '' ?>><?= e($c->nombre) ?></option>
        <?php endforeach; ?>
      </select>
      <?= mensaje_error('categoria', $errores) ?>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="f-socio">Confeccionada por</label>
      <select id="f-socio" class="<?= clase_control('socio', $errores, 'form-select') ?>" name="socio" required<?= atributos_error('socio', $errores) ?>>
        <option value="">— elegir socio —</option>
        <?php foreach ($socios as $s): ?>
          <option value="<?= $s->id ?>"<?= ($datos['socio'] ?? '') === (string) $s->id ? ' selected' : '' ?>><?= e($s->nombre) ?> · <?= e($s->comunidad) ?></option>
        <?php endforeach; ?>
      </select>
      <?= mensaje_error('socio', $errores) ?>
    </div>
    <?php campo('precio', 'Precio (Bs)', $datos, $errores, 'text', 'inputmode="decimal" required', 4); ?>
    <?php campo('stock', 'Stock (unidades)', $datos, $errores, 'number', 'min="0" step="1" required', 4); ?>
    <?php campo('color', 'Color', $datos, $errores, 'text', 'maxlength="30"', 4); ?>
    <div class="col-12">
      <fieldset<?= atributos_error('tallas', $errores) ?>>
        <legend class="form-label fs-6 mb-1">Tallas disponibles</legend>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach (Talla::cases() as $t): ?>
            <input type="checkbox" class="btn-check" id="t-<?= e($t->value) ?>" name="tallas[]" value="<?= e($t->value) ?>"<?= in_array($t->value, $datos['tallas'] ?? [], true) ? ' checked' : '' ?> autocomplete="off">
            <label class="btn btn-outline-marca btn-sm px-3" for="t-<?= e($t->value) ?>"><?= e($t->etiqueta()) ?></label>
          <?php endforeach; ?>
        </div>
        <?= mensaje_error('tallas', $errores) ?>
      </fieldset>
    </div>
    <div class="col-12">
      <label class="form-label" for="f-descripcion">Descripción</label>
      <textarea id="f-descripcion" class="<?= clase_control('descripcion', $errores) ?>" name="descripcion" rows="3" maxlength="500"<?= atributos_error('descripcion', $errores) ?>><?= e((string) ($datos['descripcion'] ?? '')) ?></textarea>
      <?= mensaje_error('descripcion', $errores) ?>
    </div>
    <div class="col-12">
      <label class="form-label" for="f-foto">Foto (JPG, PNG o WebP, hasta 2 MB)</label>
      <div class="d-flex gap-3 align-items-center">
        <?php if (!empty($fotoActual)): ?>
          <img src="/foto.php?f=<?= e($fotoActual) ?>" alt="Foto actual" width="72" height="72" class="rounded border">
        <?php endif; ?>
        <input id="f-foto" class="<?= clase_control('foto', $errores) ?>" name="foto" type="file" accept="image/jpeg,image/png,image/webp"<?= atributos_error('foto', $errores) ?>>
      </div>
      <?php if (!empty($fotoActual)): ?><div class="form-text">Si no eliges otra, se conserva la foto actual.</div><?php endif; ?>
      <?= mensaje_error('foto', $errores) ?>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="/admin.php" data-cerrar>Cancelar</a>
    <button type="submit" class="btn btn-marca"><i class="bi <?= $editando ? 'bi-check2' : 'bi-plus-lg' ?> me-1"></i><?= $editando ? 'Guardar cambios' : 'Registrar prenda' ?></button>
  </div>
</form>
