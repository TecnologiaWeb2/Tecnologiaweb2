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
require_once __DIR__ . '/Seguridad.php';         // actividad 3: sesión, roles, CSRF y tokens
require_once __DIR__ . '/Ventas.php';            // actividad 3: ventas, acopios y reportes
require_once __DIR__ . '/Intercambio.php';       // actividad 4: JSON, XML, RSS y SOAP
require_once __DIR__ . '/Api.php';               // actividad 4: API REST
require_once __DIR__ . '/Pedidos.php';           // trabajo final, módulo 2: pedidos con reserva de stock
require_once __DIR__ . '/Liquidaciones.php';     // trabajo final, módulo 1: liquidación y pagos a socias

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');                 // en el hosting, un aviso de PHP nunca se muestra al visitante
    // Cabeceras de seguridad en TODAS las respuestas (el hosting puede no ponerlas)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CSP: solo scripts y estilos propios (Bootstrap está en public/vendor); ningún script en línea
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (es_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
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
