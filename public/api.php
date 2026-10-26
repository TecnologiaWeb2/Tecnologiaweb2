<?php
// public/api.php — Punto de entrada de la API REST: .htaccess (Apache) y enrutador.php (php -S) envían aquí /api/v1/….
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

try {
    $pdo = Conexion::obtener();
} catch (PDOException $e) {
    error_log((string) $e);
    http_response_code(503);
    header('Content-Type: application/problem+json; charset=UTF-8');
    echo json_encode(['type' => 'about:blank', 'title' => 'Servicio no disponible', 'status' => 503,
        'detail' => 'La base de datos no responde. Intenta de nuevo en unos minutos.'], JSON_UNESCAPED_UNICODE);
    exit;
}
(new Api($pdo))->atender((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), (string) ($_SERVER['REQUEST_URI'] ?? '/'));
