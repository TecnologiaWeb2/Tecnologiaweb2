<?php
// public/producto-api.php — GET /datos/productos/{codigo} (Laboratorio 12): una prenda en JSON, para comparar con SOAP.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';
exigir_metodo('GET', 'HEAD');

$codigo = strtoupper(texto($_GET, 'codigo'));
$p = (new ProductoRepositorio(Conexion::obtener()))->buscarPorCodigo($codigo);
header('Access-Control-Allow-Origin: *');
if ($p === null) {
    http_response_code(404);
    header('Content-Type: application/problem+json; charset=UTF-8');
    echo json_encode(['type' => 'about:blank', 'title' => 'No encontrado', 'status' => 404, 'detail' => "No existe la prenda {$codigo}."],
        JSON_UNESCAPED_UNICODE);
    exit;
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(prenda_publica($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
