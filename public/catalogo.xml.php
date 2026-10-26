<?php
// public/catalogo.xml.php — GET /datos/catalogo.xml (Laboratorio 12): el mismo catálogo en XML, generado con XMLWriter.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
exigir_metodo('GET', 'HEAD');

header('Content-Type: application/xml; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');
echo catalogo_xml(Conexion::obtener());
