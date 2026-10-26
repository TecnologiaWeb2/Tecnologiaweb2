<?php
// public/catalogo.json.php — GET /datos/catalogo.json (Laboratorio 12): catálogo completo en JSON para otros sistemas.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
exigir_metodo('GET', 'HEAD');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');                 // datos públicos: cualquier sitio puede leerlos
header('Cache-Control: public, max-age=300');
echo catalogo_json(Conexion::obtener());
