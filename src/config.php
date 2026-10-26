<?php
// src/config.php — Rutas, constantes y lectura de la configuración de la base de datos.
// Las pruebas cambian la carpeta de fotos con TIENDA_DATOS y la base con TIENDA_BD_NOMBRE.
declare(strict_types=1);

date_default_timezone_set('America/La_Paz');          // Bolivia (UTC−4): fechas de ventas, RSS y tokens

define('DIR_DATOS', rtrim((string) (getenv('TIENDA_DATOS') ?: __DIR__ . '/../datos'), '/\\'));
const CARPETA_FOTOS = DIR_DATOS . '/fotos';          // FUERA de public/: no se puede ejecutar ni listar
const TAM_MAXIMO = 2 * 1024 * 1024;                   // 2 MB
const TIPOS_PERMITIDOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const NOMBRE_TIENDA = 'Manos del Oriente';
const NOMBRE_ASOCIACION = 'Asociación de Confeccionistas Textiles «Manos del Oriente»';
const REPOSITORIO_GITHUB = 'https://github.com/TecnologiaWeb2/Tecnologiaweb2';

/**
 * Datos de conexión: config/config.php (fuera de public/ y fuera del repositorio) o, si no existe, la plantilla.
 * Cualquier valor se puede reemplazar con una variable de entorno TIENDA_BD_HOST, _PUERTO, _NOMBRE, _USUARIO, _CLAVE
 * (o BD_HOST, BD_PUERTO… del Docker del curso).
 * @return array{host: string, puerto: int, nombre: string, usuario: string, clave: string}
 */
function configuracion_bd(): array
{
    $archivo = __DIR__ . '/../config/config.php';
    $config = require (is_file($archivo) ? $archivo : __DIR__ . '/../config/config.ejemplo.php');
    $bd = $config['bd'];
    // BD_* son las variables del entorno Docker del curso (entorno/docker-compose.yml); TIENDA_BD_* tiene prioridad
    foreach (['host', 'puerto', 'nombre', 'usuario', 'clave'] as $clave) {
        $entorno = getenv('TIENDA_BD_' . strtoupper($clave));
        if ($entorno === false) {
            $entorno = getenv('BD_' . strtoupper($clave));
        }
        if ($entorno !== false) {
            $bd[$clave] = $entorno;
        }
    }
    return [
        'host' => (string) $bd['host'], 'puerto' => (int) $bd['puerto'], 'nombre' => (string) $bd['nombre'],
        'usuario' => (string) $bd['usuario'], 'clave' => (string) $bd['clave'],
    ];
}
