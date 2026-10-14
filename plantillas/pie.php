<?php
// plantillas/pie.php — Cierre común: pie, avisos (toasts), los dos modales que usa public/js/app.js y los scripts.
declare(strict_types=1);
if (es_parcial()) {
    return;
}
?>
</main>
<footer class="pie py-4 mt-auto">
  <div class="container text-center small"><?= e(NOMBRE_ASOCIACION) ?> · Tecnología Web II · UPDS</div>
</footer>

<!-- Avisos en la esquina superior derecha (registrado, actualizado, eliminado, errores) -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="avisos">
  <?php foreach ($GLOBALS['avisos'] ?? [] as $a): $error = $a['tipo'] === 'error'; ?>
    <div class="toast align-items-center <?= $error ? 'aviso-error' : 'aviso-exito' ?>" role="<?= $error ? 'alert' : 'status' ?>" aria-live="<?= $error ? 'assertive' : 'polite' ?>" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body"><i class="bi <?= $error ? 'bi-exclamation-octagon' : 'bi-check-circle' ?> me-2"></i><?= e($a['mensaje']) ?></div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal para registrar, editar y ver el detalle: su contenido se carga desde la página real (X-Parcial) -->
<div class="modal fade" id="modal-contenido" tabindex="-1" aria-labelledby="modal-contenido-titulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="modal-contenido-titulo"></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

<!-- Modal de confirmación antes de eliminar -->
<div class="modal fade" id="modal-confirmar" tabindex="-1" aria-labelledby="modal-confirmar-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="icono-peligro mb-3"><i class="bi bi-trash3"></i></div>
        <h2 class="fs-5" id="modal-confirmar-titulo">¿Eliminar?</h2>
        <p class="text-secondary mb-0" id="modal-confirmar-texto"></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger px-4" id="modal-confirmar-si"><i class="bi bi-trash3 me-1"></i>Sí, eliminar</button>
      </div>
    </div>
  </div>
</div>

<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>
</body>
</html>
