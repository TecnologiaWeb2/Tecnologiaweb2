<?php
// public/eliminar.php — Elimina una prenda (solo por POST y con token CSRF) junto con su foto; luego redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_metodo('POST');                              // un enlace GET podría borrar datos sin querer (precarga, bots)
exigir_csrf($_POST);
$eliminado = (new ProductoRepositorio(Conexion::obtener()))->eliminar(texto($_POST, 'codigo'));
borrar_foto($eliminado?->foto);
redirigir('/admin.php?eliminado=' . urlencode($eliminado?->codigo ?? ''));
