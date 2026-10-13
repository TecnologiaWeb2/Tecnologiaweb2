<?php
// public/nuevo.php — Registrar una prenda: GET muestra el formulario; POST valida, guarda y redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$catalogo = new Catalogo();
$errores = [];
$datos = ['codigo' => '', 'nombre' => '', 'categoria' => '', 'precio' => '', 'stock' => '', 'tallas' => [], 'color' => '', 'descripcion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $foto = guardar_foto($_FILES);
    $resultado = validar_producto($_POST, $catalogo, foto: $foto['nombre']);
    $errores = $resultado['errores'];
    if ($foto['error'] !== null) {
        $errores['foto'] = $foto['error'];
    }
    if ($errores === []) {
        $catalogo->agregar($resultado['producto']);
        $catalogo->guardar();
        redirigir('/admin.php?creado=' . urlencode($resultado['producto']->codigo));
    }
    borrar_foto($foto['nombre']);                   // si otro campo falló, no dejar una foto huérfana
    http_response_code(422);
    $datos = $resultado['datos'];
}

$titulo = 'Nueva prenda';
$seccion = 'nuevo';
$accion = '/nuevo.php';
$editando = false;
require __DIR__ . '/../plantillas/cabecera.php';
echo '<h1>Registrar prenda</h1>';
require __DIR__ . '/../plantillas/formulario.php';
require __DIR__ . '/../plantillas/pie.php';
