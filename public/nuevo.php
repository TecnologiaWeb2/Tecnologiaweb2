<?php
// public/nuevo.php — Registrar una prenda: GET muestra el formulario; POST valida, guarda en MySQL y redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$pdo = Conexion::obtener();
$repo = new ProductoRepositorio($pdo);
$categorias = (new CategoriaRepositorio($pdo))->todas();
$socios = (new SocioRepositorio($pdo))->activos();
$errores = [];
$datos = ['codigo' => '', 'nombre' => '', 'categoria' => '', 'socio' => '', 'precio' => '', 'stock' => '', 'tallas' => [], 'color' => '', 'descripcion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $foto = guardar_foto($_FILES);
    $resultado = validar_producto($_POST, $repo, $categorias, $socios, foto: $foto['nombre']);
    $errores = $resultado['errores'];
    if ($foto['error'] !== null) {
        $errores['foto'] = $foto['error'];
    }
    if ($errores === []) {
        try {
            $repo->crear($resultado['producto']);
            redirigir('/admin.php?creado=' . urlencode($resultado['producto']->codigo));
        } catch (DomainException $e) {              // otro usuario registró el mismo código un instante antes
            $errores['codigo'] = $e->getMessage();
        }
    }
    borrar_foto($foto['nombre']);                   // si algo falló, no dejar una foto huérfana
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
