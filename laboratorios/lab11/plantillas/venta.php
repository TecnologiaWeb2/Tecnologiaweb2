<?php /** plantillas/venta.php — Venta de hasta 3 productos (una transacción) y acopio rápido. */ ?>
<div class="titulo-pagina">
  <h2>Nueva venta</h2>
  <a class="btn btn-secundario" href="/productos">← Volver a productos</a>
</div>
<?php if ($mensaje !== ''): ?><p class="exito" role="status"><?= $mensaje ?></p><?php endif; ?>
<?php if ($errores !== []): ?><p class="alerta" role="alert"><?= implode(' ', $errores) ?></p><?php endif; ?>
<form method="post" action="/ventas" class="tarjeta formulario">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <p class="campo"><label for="cliente">Cliente</label> <input id="cliente" name="cliente" value="<?= $cliente ?>" required maxlength="80"></p>
  <div class="tabla-contenedor">
  <table>
    <thead><tr><th scope="col">Código</th><th scope="col">Cantidad</th></tr></thead>
    <tbody>
    <?php foreach ($lineas as $i => $l): ?>
      <tr>
        <td><input name="codigo[<?= $i ?>]" value="<?= $l['codigo'] ?>" aria-label="Código del producto <?= $i + 1 ?>" maxlength="8" placeholder="MIE-500"></td>
        <td><input name="cantidad[<?= $i ?>]" value="<?= $l['cantidad'] ?>" aria-label="Cantidad <?= $i + 1 ?>" inputmode="numeric" size="4" placeholder="1"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="botones">
    <button type="submit" class="btn btn-primario">Registrar venta</button>
    <button type="reset" class="btn btn-secundario">Limpiar</button>
  </div>
</form>

<div class="titulo-pagina"><h2>Acopio</h2><span class="tenue">Un socio entrega producto a la asociación</span></div>
<form method="post" action="/acopios" class="filtros tarjeta">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <div class="campo"><label for="a-codigo">Código</label><input id="a-codigo" name="codigo" maxlength="8" placeholder="MIE-500"></div>
  <div class="campo"><label for="a-cantidad">Cantidad</label><input id="a-cantidad" name="cantidad" inputmode="numeric" size="4"></div>
  <div class="campo"><label for="a-precio">Precio pagado (Bs)</label><input id="a-precio" name="precio" inputmode="decimal" size="6"></div>
  <div class="botones"><button type="submit" class="btn btn-primario">Registrar acopio</button></div>
</form>
