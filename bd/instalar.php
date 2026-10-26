<?php
// bd/instalar.php — Crea la base de datos (si no existe) y carga el esquema y los datos de demostración.
// Uso: php bd/instalar.php            → usa config/config.php (o la plantilla)
//      TIENDA_BD_NOMBRE=otra php bd/instalar.php
// ¡Borra y recrea las tablas! Solo para desarrollo, pruebas o la primera instalación en el hosting.
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Conexion.php';

/** Orden de ejecución: actividad 2 (esquema, datos), actividad 3 (04, 05), actividad 4 (06) y trabajo final (07, 08). */
const SCRIPTS_SQL = ['esquema.sql', 'datos.sql', '04-usuarios.sql', '05-movimientos.sql', '06-tokens.sql', '07-tfi.sql', '08-demo.sql'];

/** Crea/recrea la base indicada en la configuración y ejecuta los scripts SQL. Devuelve el nombre de la base. */
function instalar_bd(bool $conDatos = true): string
{
    $bd = configuracion_bd();
    if (preg_match('/^[A-Za-z0-9_]+$/', $bd['nombre']) !== 1) {     // un nombre de base no se puede pasar como parámetro
        throw new InvalidArgumentException('Nombre de base de datos inválido.');
    }
    $servidor = Conexion::crear($bd, conBase: false);
    $servidor->exec("CREATE DATABASE IF NOT EXISTS `{$bd['nombre']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = Conexion::crear($bd);
    foreach ($conDatos ? SCRIPTS_SQL : ['esquema.sql'] as $script) {
        foreach (dividir_sql((string) file_get_contents(__DIR__ . "/$script")) as $sentencia) {
            $pdo->exec($sentencia);
        }
    }
    return $bd['nombre'];
}

/** Copia las 14 fotos de demostración (bd/fotos-demo) a la carpeta de fotos, sin pisar las que ya existen. */
function copiar_fotos_demo(string $destino = CARPETA_FOTOS): int
{
    if (!is_dir($destino)) {
        mkdir($destino, 0775, true);
    }
    $copiadas = 0;
    foreach (glob(__DIR__ . '/fotos-demo/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $foto) {
        if (!is_file($destino . '/' . basename($foto)) && copy($foto, $destino . '/' . basename($foto))) {
            $copiadas++;
        }
    }
    return $copiadas;
}

/** Separa un script en sentencias (por ';' al final de línea), ignorando los comentarios "-- ". @return list<string> */
function dividir_sql(string $sql): array
{
    $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sinComentarios)), fn(string $s): bool => $s !== ''));
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $nombre = instalar_bd();
    $fotos = copiar_fotos_demo();
    echo "Base de datos «{$nombre}» instalada con los datos de demostración ({$fotos} fotos copiadas)." . PHP_EOL;
}
