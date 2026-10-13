<?php
// src/arranque.php — Punto único de carga: cada página hace require de este archivo y obtiene todo lo necesario.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Modelo/Talla.php';
require_once __DIR__ . '/Modelo/Categoria.php';
require_once __DIR__ . '/Modelo/Producto.php';
require_once __DIR__ . '/Modelo/Catalogo.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/validacion.php';
require_once __DIR__ . '/fotos.php';

// productos.json no se sube al repositorio: en una instalación nueva se crea a partir de los datos de demostración
if (!is_file(ARCHIVO_PRODUCTOS) && is_file(DIR_DATOS . '/productos.inicial.json')) {
    copy(DIR_DATOS . '/productos.inicial.json', ARCHIVO_PRODUCTOS);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
