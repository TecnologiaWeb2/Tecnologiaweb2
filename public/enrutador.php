<?php
// public/enrutador.php — Solo para el servidor integrado de PHP (php -S 127.0.0.1:8000 -t public public/enrutador.php).
// Hace lo mismo que public/.htaccess en Apache/InfinityFree: rutas «bonitas» de la API y del intercambio de datos.
declare(strict_types=1);

$ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rutas = [
    '#^/api/v1(/.*)?$#' => 'api.php',
    '#^/datos/catalogo\.json$#' => 'catalogo.json.php',
    '#^/datos/catalogo\.xml$#' => 'catalogo.xml.php',
    '#^/datos/productos/([A-Za-z0-9-]{1,12})$#' => 'producto-api.php',
    '#^/noticias\.rss$#' => 'noticias.rss.php',
];
foreach ($rutas as $patron => $archivo) {
    if (preg_match($patron, $ruta, $m) === 1) {
        if ($archivo === 'producto-api.php') {
            $_GET['codigo'] = $m[1];
        }
        $_SERVER['SCRIPT_NAME'] = '/' . $archivo;
        require __DIR__ . '/' . $archivo;
        return true;
    }
}
// Los archivos .ht* y el propio enrutador no se sirven; el resto (CSS, JS, páginas .php) lo atiende php -S
if (preg_match('#/\.|^/enrutador\.php$#', $ruta) === 1) {
    http_response_code(404);
    return true;
}
return false;
