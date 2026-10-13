<?php
// public/foto.php?f=<nombre> — Entrega una foto guardada fuera de public/, solo si el nombre es válido.
declare(strict_types=1);
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/fotos.php';

$ruta = ruta_foto(is_string($_GET['f'] ?? null) ? $_GET['f'] : '');
if ($ruta === null) {
    http_response_code(404);
    exit('Foto no encontrada');
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($ruta));
header('X-Content-Type-Options: nosniff');         // el navegador no "adivina" otro tipo
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
