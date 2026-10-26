<?php
// public/importar.php — Laboratorio 12 + módulo 2: importar el pedido de una tienda en JSON (validado en código) o en XML
// (validado contra bd/esquemas/pedido.xsd). Si es válido, se registra como pedido y reserva el stock.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$u = exigir_usuario('pedidos.gestionar');
$error = '';
$contenido = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $contenido = is_string($_POST['contenido'] ?? null) ? $_POST['contenido'] : '';
    $archivo = $_FILES['archivo'] ?? null;
    if (is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_string($archivo['tmp_name'])) {
        $contenido = $archivo['size'] <= 100_000 ? (string) file_get_contents($archivo['tmp_name']) : str_repeat(' ', 100_001);
    }
    try {
        $id = importar_pedido(Conexion::obtener(), $contenido, $u->id);
        redirigir('/pedidos.php?creado=' . $id);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    }
}

$titulo = 'Importar pedido';
$seccion = 'pedidos';
require __DIR__ . '/../plantillas/cabecera.php';
?>
<div class="card shadow-sm border-0 mx-auto formulario-pagina"><div class="card-body p-4">
  <h1 class="h4 mb-1">Importar pedido de una tienda</h1>
  <p class="text-secondary">JSON con <code>numero</code>, <code>cliente</code>, <code>fecha</code> (AAAA-MM-DD) y <code>lineas</code>, o XML que cumpla <code>pedido.xsd</code>.
    Ejemplos en <code>docs/actividad-04/ejemplos/</code>.</p>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><strong>Pedido rechazado.</strong> <?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/importar.php" enctype="multipart/form-data" novalidate>
    <?= csrf_campo() ?>
    <label class="form-label" for="archivo">Archivo (.json o .xml, hasta 100 KB)</label>
    <input class="form-control mb-3" id="archivo" name="archivo" type="file" accept=".json,.xml,application/json,application/xml,text/xml">
    <label class="form-label" for="contenido">… o pega el contenido</label>
    <textarea class="form-control font-monospace mb-3" id="contenido" name="contenido" rows="10" placeholder='{"numero": "HM-889", "cliente": "Hotel Misiones", "fecha": "2026-10-24", "lineas": [{"codigo": "ACC-301", "cantidad": 3}]}'><?= e($contenido) ?></textarea>
    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-outline-secondary" href="/pedidos.php">Volver</a>
      <button class="btn btn-marca" type="submit"><i class="bi bi-upload me-1"></i>Validar e importar</button>
    </div>
  </form>
</div></div>
<?php require __DIR__ . '/../plantillas/pie.php'; ?>
