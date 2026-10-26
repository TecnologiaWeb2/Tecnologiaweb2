<?php /** plantillas/entrar.php — Formulario de ingreso (también lleva token CSRF). */ ?>
<section class="ingreso tarjeta">
  <h2>Iniciar sesión</h2>
  <?php if ($error !== ''): ?><p class="alerta" role="alert"><?= $error ?></p><?php endif; ?>
  <form method="post" action="/entrar" class="formulario">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="volver" value="<?= $volver ?>">
    <p class="campo"><label for="correo">Correo</label>
       <input id="correo" name="correo" type="email" autocomplete="username" value="<?= $correo ?>" required></p>
    <p class="campo"><label for="clave">Contraseña</label>
       <span class="campo-clave">
         <input id="clave" name="clave" type="password" autocomplete="current-password" required>
         <button type="button" class="ver-clave" id="ver-clave" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="clave" hidden>
           <svg class="ojo-abierto" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
           <svg class="ojo-cerrado" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
         </button>
       </span></p>
    <button type="submit" class="btn btn-primario btn-bloque">Entrar</button>
  </form>
</section>
<script src="/js/clave.js" defer></script>
