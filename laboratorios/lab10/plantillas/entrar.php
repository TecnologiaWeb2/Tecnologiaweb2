<?php /** plantillas/entrar.php — Formulario de ingreso (también lleva token CSRF). */ ?>
<h2>Iniciar sesión</h2>
<?php if ($error !== ''): ?><p class="alerta" role="alert"><?= $error ?></p><?php endif; ?>
<form method="post" action="/entrar">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <input type="hidden" name="volver" value="<?= $volver ?>">
  <p><label for="correo">Correo</label>
     <input id="correo" name="correo" type="email" autocomplete="username" value="<?= $correo ?>" required></p>
  <p><label for="clave">Contraseña</label>
     <input id="clave" name="clave" type="password" autocomplete="current-password" required></p>
  <button type="submit">Entrar</button>
</form>
