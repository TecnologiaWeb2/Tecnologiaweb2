<?php
declare(strict_types=1);

require_once __DIR__ . '/../bd/instalar.php';

$pdo = Conexion::crear(configuracion_bd());
$tablaExiste = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'productos'"
)->fetchColumn();

if (!$tablaExiste) {
    instalar_bd();
}
copiar_fotos_demo();
