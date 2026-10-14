<?php
// public/editar.php?codigo=POL-001 — Editar una prenda; si llega una foto nueva, reemplaza a la anterior.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$pdo = Conexion::obtener();
$repo = new ProductoRepositorio($pdo);
$codigo = strtoupper(texto($_GET, 'codigo'));
$actual = $repo->buscarPorCodigo($codigo);
if ($actual === null) {
    http_response_code(404);
    exit('Prenda no encontrada');
}
$categorias = (new CategoriaRepositorio($pdo))->todas();
// Socios activos + el socio actual de la prenda (aunque ya esté inactivo, debe poder conservarse)
$socios = (new SocioRepositorio($pdo))->activos() + [$actual->socio->id => $actual->socio];
$errores = [];
$datos = ['codigo' => $actual->codigo, 'nombre' => $actual->nombre, 'categoria' => $actual->categoria->id,
    'socio' => (string) $actual->socio->id, 'precio' => number_format($actual->precio, 2, ',', ''), 'stock' => (string) $actual->stock,
    'tallas' => array_map(fn(Talla $t): string => $t->value, $actual->tallas), 'color' => $actual->color, 'descripcion' => $actual->descripcion];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $foto = guardar_foto($_FILES);
    // El código no se cambia (codigoFijo) y, si no llegó foto nueva, se conserva la anterior
    $resultado = validar_producto($_POST, $repo, $categorias, $socios, codigoFijo: $codigo, foto: $foto['nombre'] ?? $actual->foto);
    $errores = $resultado['errores'];
    if ($foto['error'] !== null) {
        $errores['foto'] = $foto['error'];
    }
    if ($errores === []) {
        $repo->actualizar($resultado['producto']);
        if ($foto['nombre'] !== null) {
            borrar_foto($actual->foto);             // la foto vieja ya no se usa
        }
        redirigir('/admin.php?actualizado=' . urlencode($codigo));
    }
    borrar_foto($foto['nombre']);
    http_response_code(422);
    $datos = $resultado['datos'];
}

$titulo = 'Editar ' . $codigo;
$seccion = 'admin';
$accion = '/editar.php?codigo=' . urlencode($codigo);
$editando = true;
$fotoActual = $actual->foto;
require __DIR__ . '/../plantillas/cabecera.php';
if (!es_parcial()) {
    echo '<div class="card shadow-sm border-0 mx-auto formulario-pagina"><div class="card-body p-4"><h1 class="h4 mb-4">' . e('Editar ' . $codigo) . '</h1>';
}
require __DIR__ . '/../plantillas/formulario.php';
if (!es_parcial()) {
    echo '</div></div>';
}
require __DIR__ . '/../plantillas/pie.php';
