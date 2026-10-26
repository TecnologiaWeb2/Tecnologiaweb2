// public/js/vista-previa.js — Muestra en el recuadro la imagen elegida ANTES de enviar el formulario.
// Es un archivo aparte porque la política de seguridad (CSP) no permite scripts dentro de la página.
// La vista previa usa una URL temporal (blob:) creada en el navegador: el archivo no se sube hasta pulsar "Registrar".
(function () {
  var entrada = document.getElementById('f-imagen');
  var vista = document.querySelector('.miniatura-grande');
  if (!entrada || !vista || !window.URL || !URL.createObjectURL) { return; }

  var original = vista.getAttribute('src');          // imagen actual (o la genérica "sin imagen")
  var textoOriginal = vista.getAttribute('alt');
  var temporal = null;                                // URL blob: vigente (se libera al cambiar)
  var quitar = document.getElementById('f-quitar');   // casilla "Quitar la imagen actual" (solo al editar)

  function liberar() {
    if (temporal !== null) { URL.revokeObjectURL(temporal); temporal = null; }
  }
  function restaurar() {
    liberar();
    vista.src = original;
    vista.alt = textoOriginal;
    vista.classList.remove('vista-nueva');
  }

  entrada.addEventListener('change', function () {
    var archivo = entrada.files && entrada.files[0];
    if (!archivo) { restaurar(); return; }            // canceló la selección: vuelve la imagen anterior
    if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) {
      restaurar();                                    // el servidor también lo valida; aquí solo evitamos una vista rota
      return;
    }
    liberar();
    temporal = URL.createObjectURL(archivo);
    vista.src = temporal;
    vista.alt = 'Vista previa: ' + archivo.name;
    vista.classList.add('vista-nueva');
    if (quitar) { quitar.checked = false; }           // elegir una foto nueva anula "quitar"
  });

  if (quitar) {
    quitar.addEventListener('change', function () {
      if (quitar.checked) { entrada.value = ''; restaurar(); }
    });
  }
  var formulario = entrada.form;
  if (formulario) {
    formulario.addEventListener('reset', function () { setTimeout(restaurar, 0); });
  }
  window.addEventListener('pagehide', liberar);
})();
