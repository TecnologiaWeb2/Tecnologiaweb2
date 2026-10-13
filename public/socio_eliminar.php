<?php
// public/socio_eliminar.php — Elimina un socio (POST + CSRF). Si tiene prendas, la clave foránea lo impide.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_metodo('POST');
exigir_csrf($_POST);
$repo = new SocioRepositorio(Conexion::obtener());
$id = filter_var(texto($_POST, 'id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$socio = $id === false ? null : $repo->buscarPorId($id);
if ($socio === null) {
    redirigir('/socios.php');
}
try {
    $repo->eliminar($socio->id);
    redirigir('/socios.php?eliminado=' . urlencode($socio->nombre));
} catch (DomainException) {
    redirigir('/socios.php?error=tiene-prendas');
}
