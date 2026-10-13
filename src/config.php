<?php
// src/config.php — Rutas y constantes de la tienda. Las pruebas pueden cambiar la carpeta de datos con TIENDA_DATOS.
declare(strict_types=1);

define('DIR_DATOS', rtrim((string) (getenv('TIENDA_DATOS') ?: __DIR__ . '/../datos'), '/\\'));
const ARCHIVO_PRODUCTOS = DIR_DATOS . '/productos.json';
const ARCHIVO_CATEGORIAS = DIR_DATOS . '/categorias.json';
const CARPETA_FOTOS = DIR_DATOS . '/fotos';          // FUERA de public/: no se puede ejecutar ni listar
const TAM_MAXIMO = 2 * 1024 * 1024;                   // 2 MB
const TIPOS_PERMITIDOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const NOMBRE_TIENDA = 'Manos del Oriente';
const NOMBRE_ASOCIACION = 'Asociación de Confeccionistas Textiles «Manos del Oriente»';
