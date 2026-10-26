<?php /** plantillas/formulario.php — Registrar y editar (mismo formulario). Variables escapadas. */
$campo = function (string $nombre, string $etiqueta, string $extra = '') use ($datos, $errores): void {
    $error = $errores[$nombre] ?? null;
    echo "<p class=\"campo\"><label for=\"f-$nombre\">$etiqueta</label>"
        . "<input id=\"f-$nombre\" name=\"$nombre\" value=\"{$datos[$nombre]}\" $extra"
        . ($error ? " aria-invalid=\"true\" aria-describedby=\"f-$nombre-error\">" : '>')
        . ($error ? "<span class=\"error\" id=\"f-$nombre-error\">$error</span>" : '') . '</p>';
};
?>
<div class="titulo-pagina">
  <h2><?= $nuevo ? 'Registrar producto' : "Editar {$datos['codigo']}" ?></h2>
  <a class="btn btn-secundario" href="/productos">← Volver a productos</a>
</div>
<?php if ($errores !== []): ?><p class="alerta" role="alert"><?= $errores['general'] ?? 'Revisa los campos marcados.' ?></p><?php endif; ?>
<form method="post" action="<?= $accion ?>" enctype="multipart/form-data" class="tarjeta formulario" novalidate>
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <?php if ($nuevo) { $campo('codigo', 'Código (ej. MIE-500)', 'maxlength="8" required'); } ?>
  <?php $campo('nombre', 'Nombre', 'maxlength="80" required'); ?>
  <p class="campo"><label for="f-categoria">Categoría</label>
    <select id="f-categoria" name="categoria"><option value="">— elegir —</option>
      <?php foreach ($categorias as $cat): ?><option<?= $datos['categoria'] === $cat ? ' selected' : '' ?>><?= $cat ?></option><?php endforeach; ?>
    </select>
    <?php if (isset($errores['categoria'])): ?><span class="error"><?= $errores['categoria'] ?></span><?php endif; ?></p>
  <?php $campo('precio', 'Precio (Bs)', 'inputmode="decimal" required'); ?>
  <?php $campo('stock', 'Stock', 'inputmode="numeric" required'); ?>
  <p class="campo"><label for="f-socio_id">Socio productor</label>
    <select id="f-socio_id" name="socio_id"><option value="">— elegir —</option>
      <?php foreach ($socios as $id => $nombre): ?><option value="<?= $id ?>"<?= $datos['socio_id'] === (string) $id ? ' selected' : '' ?>><?= $nombre ?></option><?php endforeach; ?>
    </select>
    <?php if (isset($errores['socio_id'])): ?><span class="error" id="f-socio_id-error"><?= $errores['socio_id'] ?></span><?php endif; ?></p>

  <fieldset class="imagen-campo">
    <legend>Imagen del producto</legend>
    <div class="imagen-vista">
      <img class="miniatura-grande" src="<?= $imagenUrl ?>" alt="Imagen actual del producto" width="140" height="140">
      <div>
        <p class="campo"><label for="f-imagen"><?= $tieneImagen ? 'Reemplazar imagen' : 'Subir imagen' ?></label>
          <input id="f-imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp"<?= isset($errores['imagen']) ? ' aria-invalid="true" aria-describedby="f-imagen-error"' : '' ?>>
          <?php if (isset($errores['imagen'])): ?><span class="error" id="f-imagen-error"><?= $errores['imagen'] ?></span><?php endif; ?>
          <small class="ayuda">JPG, PNG o WebP · máximo 2 MB. <?= $nuevo ? 'Es opcional.' : 'Si no eliges un archivo, se conserva la actual.' ?></small></p>
        <?php if (!$nuevo && $tieneImagen): ?>
        <p class="casilla"><input id="f-quitar" type="checkbox" name="quitar_imagen" value="1"> <label for="f-quitar">Quitar la imagen actual</label></p>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>

  <div class="botones">
    <button type="submit" class="btn btn-primario"><?= $nuevo ? 'Registrar' : 'Guardar cambios' ?></button>
    <a class="btn btn-secundario" href="/productos">Cancelar</a>
  </div>
</form>
<script src="/js/vista-previa.js" defer></script>
