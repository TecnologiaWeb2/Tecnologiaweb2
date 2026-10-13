<?php
// plantillas/formulario.php — Formulario de prenda compartido por nuevo.php y editar.php (include).
// Variables que debe definir la página: $catalogo, $accion, $datos, $errores, $editando (bool)
declare(strict_types=1);

/** Atributos de accesibilidad y mensaje de error de un campo. */
function atributos_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? " aria-invalid=\"true\" aria-describedby=\"f-$nombre-error\"" : '';
}

function mensaje_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? "<span class=\"error\" id=\"f-$nombre-error\">" . e($errores[$nombre]) . '</span>' : '';
}

/** Campo de texto con etiqueta, valor anterior (escapado) y error asociado. */
function campo(string $nombre, string $etiqueta, array $datos, array $errores, string $tipo = 'text', string $extra = ''): void
{
    echo "<p><label for=\"f-$nombre\">$etiqueta</label>"
        . "<input id=\"f-$nombre\" name=\"$nombre\" type=\"$tipo\" value=\"" . e((string) ($datos[$nombre] ?? '')) . "\" $extra"
        . atributos_error($nombre, $errores) . '>' . mensaje_error($nombre, $errores) . '</p>';
}
?>
<?php if ($errores !== []): ?>
  <p class="alerta" role="alert">Revisa <?= count($errores) === 1 ? 'el campo marcado' : 'los ' . count($errores) . ' campos marcados' ?>.</p>
<?php endif; ?>
<!-- enctype multipart/form-data: OBLIGATORIO para enviar archivos -->
<form method="post" action="<?= e($accion) ?>" enctype="multipart/form-data" class="ficha" novalidate>
  <?= csrf_campo() ?>
  <?php campo('codigo', 'Código (ej. POL-001)', $datos, $errores, 'text', 'maxlength="9" required' . ($editando ? ' readonly' : '')); ?>
  <?php campo('nombre', 'Nombre de la prenda', $datos, $errores, 'text', 'maxlength="80" required'); ?>
  <p>
    <label for="f-categoria">Categoría</label>
    <select id="f-categoria" name="categoria" required<?= atributos_error('categoria', $errores) ?>>
      <option value="">— elegir —</option>
      <?php foreach ($catalogo->categorias() as $c): ?>
        <option value="<?= e($c->id) ?>"<?= ($datos['categoria'] ?? '') === $c->id ? ' selected' : '' ?>><?= e($c->nombre) ?></option>
      <?php endforeach; ?>
    </select>
    <?= mensaje_error('categoria', $errores) ?>
  </p>
  <?php campo('precio', 'Precio (Bs)', $datos, $errores, 'text', 'inputmode="decimal" required'); ?>
  <?php campo('stock', 'Stock (unidades)', $datos, $errores, 'number', 'min="0" step="1" required'); ?>
  <fieldset<?= atributos_error('tallas', $errores) ?>>
    <legend>Tallas disponibles</legend>
    <?php foreach (Talla::cases() as $t): ?>
      <label class="opcion"><input type="checkbox" name="tallas[]" value="<?= e($t->value) ?>"<?= in_array($t->value, $datos['tallas'] ?? [], true) ? ' checked' : '' ?>> <?= e($t->etiqueta()) ?></label>
    <?php endforeach; ?>
    <?= mensaje_error('tallas', $errores) ?>
  </fieldset>
  <?php campo('color', 'Color', $datos, $errores, 'text', 'maxlength="30"'); ?>
  <p>
    <label for="f-descripcion">Descripción</label>
    <textarea id="f-descripcion" name="descripcion" rows="3" maxlength="500"<?= atributos_error('descripcion', $errores) ?>><?= e((string) ($datos['descripcion'] ?? '')) ?></textarea>
    <?= mensaje_error('descripcion', $errores) ?>
  </p>
  <p>
    <label for="f-foto">Foto (JPG, PNG o WebP, hasta 2 MB)</label>
    <input id="f-foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"<?= atributos_error('foto', $errores) ?>>
    <?= mensaje_error('foto', $errores) ?>
  </p>
  <button type="submit"><?= $editando ? 'Guardar cambios' : 'Registrar prenda' ?></button>
</form>
