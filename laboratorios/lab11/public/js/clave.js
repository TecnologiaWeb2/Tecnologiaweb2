// public/js/clave.js — Botón "ojo" para mostrar u ocultar la contraseña.
// Es un archivo aparte porque la política de seguridad (CSP) no permite scripts dentro de la página.
(function () {
  var boton = document.getElementById('ver-clave');
  var campo = document.getElementById('clave');
  if (!boton || !campo) { return; }
  boton.hidden = false;                                    // sin JavaScript el botón no aparece
  boton.addEventListener('click', function () {
    var mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
    boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    boton.classList.toggle('activo', mostrar);
    campo.focus();
  });
})();
