<?php /** plantillas/importar.php — Pegar un pedido en XML o JSON (el sistema detecta el formato por el contenido). */ ?>
<div class="titulo-pagina">
  <div><h2>Importar pedido</h2><p class="tenue">XML (validado con XSD) o JSON · el formato se detecta por el contenido</p></div>
  <a class="btn btn-secundario" href="/productos">← Volver a productos</a>
</div>
<?php if ($mensaje !== ''): ?><p class="exito" role="status"><?= $mensaje ?></p><?php endif; ?>
<?php if ($errores !== []): ?><p class="alerta" role="alert"><?= implode(' ', $errores) ?></p><?php endif; ?>
<form method="post" action="/pedidos/importar" class="tarjeta formulario formulario-ancho">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <p class="campo"><label for="contenido">Contenido del pedido</label>
    <textarea id="contenido" name="contenido" rows="14" spellcheck="false" placeholder="Pega aquí un pedido que empiece con &lt; (XML) o { (JSON)"<?= $errores !== [] ? ' aria-invalid="true"' : '' ?>><?= $contenido ?></textarea>
    <small class="ayuda">Máximo 200 KB. Si el pedido es válido se registra como una venta (en una transacción).</small></p>
  <div class="botones">
    <button type="submit" class="btn btn-primario">Importar como venta</button>
    <button type="reset" class="btn btn-secundario">Limpiar</button>
  </div>
</form>
<div class="tarjeta">
  <h3 class="sin-margen">Formatos aceptados</h3>
  <p class="tenue">Ejemplos en la carpeta <code>ejemplos/</code>. El XML se valida contra <code>esquemas/pedido.xsd</code>.</p>
  <ul class="lista-formatos">
    <li><span class="insignia">XML</span> <code>&lt;pedido numero="…"&gt;</code> con <code>cliente</code>, <code>fecha</code> y 1 a 50 <code>linea</code></li>
    <li><span class="insignia">JSON</span> <code>{"numero", "cliente", "fecha", "lineas": [{"codigo", "cantidad"}]}</code> con cantidad entera</li>
  </ul>
</div>
