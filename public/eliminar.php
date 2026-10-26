<?php
// public/eliminar.php — Elimina una prenda (solo por POST y con token CSRF) junto con su foto; luego redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
exigir_usuario('productos.eliminar');

exigir_metodo('POST');                              // un enlace GET podría borrar datos sin querer (precarga, bots)
exigir_csrf($_POST);
try {
    $eliminado = (new ProductoRepositorio(Conexion::obtener()))->eliminar(texto($_POST, 'codigo'));
} catch (DomainException $e) {                      // tiene ventas, acopios o pedidos: el historial no se borra
    redirigir('/admin.php?error=' . urlencode($e->getMessage()));
}
borrar_foto($eliminado?->foto);
redirigir('/admin.php?eliminado=' . urlencode($eliminado?->codigo ?? ''));
