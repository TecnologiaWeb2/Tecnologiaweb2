<?php
// public/socio_form.php[?id=3] — Registrar (sin id) o editar (con id) un socio. POST valida, guarda y redirige (PRG).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$repo = new SocioRepositorio(Conexion::obtener());
$idTexto = texto($_GET, 'id');
$id = null;
$datos = ['ci' => '', 'nombre' => '', 'telefono' => '', 'comunidad' => '', 'especialidad' => '', 'fecha_ingreso' => date('Y-m-d'), 'activo' => '1'];
if ($idTexto !== '') {
    $id = filter_var($idTexto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $actual = $id === null ? null : $repo->buscarPorId($id);
    if ($actual === null) {
        http_response_code(404);
        exit('Socio no encontrado');
    }
    $datos = ['ci' => $actual->ci, 'nombre' => $actual->nombre, 'telefono' => $actual->telefono, 'comunidad' => $actual->comunidad,
        'especialidad' => $actual->especialidad, 'fecha_ingreso' => $actual->fechaIngreso, 'activo' => $actual->activo ? '1' : ''];
}
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($_POST);
    $resultado = validar_socio($_POST, $repo, $id);
    $errores = $resultado['errores'];
    if ($errores === []) {
        try {
            if ($id === null) {
                $socio = $repo->crear($resultado['socio']);
                redirigir('/socios.php?creado=' . urlencode($socio->nombre));
            }
            $repo->actualizar($resultado['socio']);
            redirigir('/socios.php?actualizado=' . urlencode($resultado['socio']->nombre));
        } catch (DomainException $e) {
            $errores['ci'] = $e->getMessage();
        }
    }
    http_response_code(422);
    $datos = $resultado['datos'];
}

$titulo = $id === null ? 'Nuevo socio' : 'Editar socio';
$seccion = 'socios';
$accion = '/socio_form.php' . ($id === null ? '' : '?id=' . $id);
require __DIR__ . '/../plantillas/cabecera.php';
if (!es_parcial()) {
    echo '<div class="card shadow-sm border-0 mx-auto formulario-pagina"><div class="card-body p-4"><h1 class="h4 mb-4">' . e($titulo) . '</h1>';
}
require __DIR__ . '/../plantillas/formulario_socio.php';
if (!es_parcial()) {
    echo '</div></div>';
}
require __DIR__ . '/../plantillas/pie.php';
