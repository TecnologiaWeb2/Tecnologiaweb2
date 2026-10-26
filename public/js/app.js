// public/js/app.js — Modales de Bootstrap 5 para ver, registrar y editar; confirmación al eliminar; avisos (toasts).
// Mejora progresiva: sin JavaScript, los mismos enlaces y formularios funcionan como páginas normales.
// Sin innerHTML: el HTML que devuelve NUESTRO servidor (X-Parcial) se analiza con DOMParser (sus <script> no se
// ejecutan) y los mensajes propios se crean con createElement + textContent.
'use strict';

(() => {
  const modalEl = document.getElementById('modal-contenido');
  if (!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);
  const titulo = modalEl.querySelector('.modal-title');
  const cuerpo = modalEl.querySelector('.modal-body');

  const elemento = (etiqueta, clase, texto = '') => Object.assign(document.createElement(etiqueta), { className: clase, textContent: texto });
  const cargando = () => {
    const caja = elemento('div', 'text-center py-5');
    const giro = elemento('div', 'spinner-border text-marca');
    giro.setAttribute('role', 'status');
    giro.append(elemento('span', 'visually-hidden', 'Cargando…'));
    caja.append(giro);
    return caja;
  };
  const alerta = (texto) => {
    const div = elemento('div', 'alert alert-danger', texto);
    div.setAttribute('role', 'alert');
    return div;
  };

  // El servidor responde solo el contenido (sin cabecera ni pie) cuando recibe X-Parcial: 1
  const pedir = (url, opciones = {}) =>
    fetch(url, { ...opciones, headers: { 'X-Parcial': '1' }, credentials: 'same-origin' });

  const mostrarEnModal = (html) => {
    const documento = new DOMParser().parseFromString(html, 'text/html');
    cuerpo.replaceChildren(...documento.body.childNodes);
    document.dispatchEvent(new CustomEvent('contenido-cargado', { detail: cuerpo }));
    const enfocar = cuerpo.querySelector('[aria-invalid="true"], input:not([type=hidden]):not([readonly]), select, textarea');
    if (enfocar) enfocar.focus();
  };

  // 1) Enlaces con data-modal: Nueva prenda, Editar, Ver detalle, Nuevo socio…
  document.addEventListener('click', async (ev) => {
    const enlace = ev.target.closest('a[data-modal]');
    if (!enlace || ev.ctrlKey || ev.metaKey || ev.shiftKey) return;   // Ctrl+clic: abrir en otra pestaña
    ev.preventDefault();
    titulo.textContent = enlace.dataset.titulo || enlace.textContent.trim();
    modalEl.querySelector('.modal-dialog').classList.toggle('modal-xl', enlace.hasAttribute('data-ancho'));
    cuerpo.replaceChildren(cargando());
    modal.show();
    try {
      const r = await pedir(enlace.href);
      if (r.status === 401) { window.location.href = '/entrar.php'; return; }
      mostrarEnModal(await r.text());
    } catch {
      cuerpo.replaceChildren(alerta('No se pudo cargar. Revisa tu conexión e intenta de nuevo.'));
    }
  });

  // «Cancelar» dentro del modal solo lo cierra (como página completa, vuelve a la lista)
  cuerpo.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-cerrar]')) { ev.preventDefault(); modal.hide(); }
  });

  // 2) Formularios dentro del modal: se envían sin salir de la página
  cuerpo.addEventListener('submit', async (ev) => {
    const form = ev.target;
    if (form.method.toLowerCase() !== 'post') return;
    ev.preventDefault();
    const boton = form.querySelector('[type=submit]');
    if (boton) {
      boton.disabled = true;
      boton.prepend(elemento('span', 'spinner-border spinner-border-sm me-2'));
    }
    try {
      const r = await pedir(form.action, { method: 'POST', body: new FormData(form) });
      if (r.redirected) {                 // guardado (303): ir a la lista, que muestra el aviso
        window.location.href = r.url;
        return;
      }
      mostrarEnModal(await r.text());     // 422: el mismo formulario con los errores por campo
    } catch {
      cuerpo.prepend(alerta('No se pudo guardar. Intenta de nuevo.'));
      if (boton) { boton.disabled = false; boton.querySelector('.spinner-border')?.remove(); }
    }
  });

  // 3) Eliminar / cancelar / revocar: confirmación en un modal en lugar de confirm()
  const confirmarEl = document.getElementById('modal-confirmar');
  const confirmar = new bootstrap.Modal(confirmarEl);
  let pendiente = null;
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (!form.matches('form[data-confirmar]')) return;
    ev.preventDefault();
    pendiente = form;
    document.getElementById('modal-confirmar-titulo').textContent = form.dataset.confirmarTitulo || '¿Eliminar?';
    document.getElementById('modal-confirmar-texto').textContent = form.dataset.confirmar;
    confirmar.show();
  });
  document.getElementById('modal-confirmar-si').addEventListener('click', () => {
    if (pendiente) {
      HTMLFormElement.prototype.submit.call(pendiente);   // envío real (POST + CSRF); vuelve con un aviso
      pendiente = null;
    }
  });

  // 4) Avisos: aparecen en la esquina superior derecha y se ocultan solos
  document.querySelectorAll('#avisos .toast').forEach((t, i) =>
    setTimeout(() => new bootstrap.Toast(t, { delay: 6000 }).show(), i * 250));

  // 5) Quitar ?creado=…, ?eliminado=… de la barra de direcciones (al recargar no se repite el aviso)
  const url = new URL(window.location.href);
  const claves = ['creado', 'actualizado', 'eliminado', 'error', 'venta', 'acopio', 'pagado', 'estado_cambiado', 'revocado'];
  if (claves.some((c) => url.searchParams.has(c))) {
    claves.forEach((c) => url.searchParams.delete(c));
    history.replaceState(null, '', url.pathname + (url.search || ''));
  }
})();
