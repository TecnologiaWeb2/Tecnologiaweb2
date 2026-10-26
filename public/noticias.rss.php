<?php
// public/noticias.rss.php — GET /noticias.rss (Laboratorio 12): canal RSS 2.0 con las prendas nuevas.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
exigir_metodo('GET', 'HEAD');

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');
echo noticias_rss(Conexion::obtener());
