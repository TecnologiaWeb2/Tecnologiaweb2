<?php
// src/arranque.php — Punto único de carga: cada página hace require de este archivo y obtiene todo lo necesario.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Modelo/Talla.php';
require_once __DIR__ . '/Modelo/Categoria.php';
require_once __DIR__ . '/Modelo/Socio.php';
require_once __DIR__ . '/Modelo/Producto.php';
require_once __DIR__ . '/Repositorio/Pagina.php';
require_once __DIR__ . '/Repositorio/CategoriaRepositorio.php';
require_once __DIR__ . '/Repositorio/SocioRepositorio.php';
require_once __DIR__ . '/Repositorio/ProductoRepositorio.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/validacion.php';
require_once __DIR__ . '/fotos.php';

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    // Un error inesperado (p. ej. la base de datos caída) se registra en el log del servidor, pero al visitante
    // solo se le muestra un mensaje genérico: nunca la consulta SQL, la ruta ni las credenciales.
    set_exception_handler(function (Throwable $e): void {
        error_log((string) $e);
        if (!headers_sent()) {
            http_response_code($e instanceof PDOException ? 503 : 500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Servicio no disponible</title>'
            . '<h1>Lo sentimos, ocurrió un problema</h1><p>Intenta de nuevo en unos minutos.</p></html>';
    });
}
