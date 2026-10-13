<?php
// public/eliminar.php — Elimina una prenda (solo por POST y con token CSRF) junto con su foto; luego redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_metodo('POST');                              // un enlace GET podría borrar datos sin querer (precarga, bots)
exigir_csrf($_POST);
$catalogo = new Catalogo();
$eliminado = $catalogo->eliminar(texto($_POST, 'codigo'));
if ($eliminado !== null) {
    borrar_foto($eliminado->foto);
    $catalogo->guardar();
}
redirigir('/admin.php?eliminado=' . urlencode($eliminado?->codigo ?? ''));
