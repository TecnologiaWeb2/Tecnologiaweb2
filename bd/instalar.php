<?php
// bd/instalar.php — Crea la base de datos (si no existe) y carga el esquema y los datos de demostración.
// Uso: php bd/instalar.php            → usa config/config.php (o la plantilla)
//      TIENDA_BD_NOMBRE=otra php bd/instalar.php
// ¡Borra y recrea las tablas! Solo para desarrollo, pruebas o la primera instalación en el hosting.
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Conexion.php';

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
    $scripts = $conDatos ? ['esquema.sql', 'datos.sql'] : ['esquema.sql'];
    foreach ($scripts as $script) {
        foreach (dividir_sql((string) file_get_contents(__DIR__ . "/$script")) as $sentencia) {
            $pdo->exec($sentencia);
        }
    }
    return $bd['nombre'];
}

/** Separa un script en sentencias (por ';' al final de línea), ignorando los comentarios "-- ". @return list<string> */
function dividir_sql(string $sql): array
{
    $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sinComentarios)), fn(string $s): bool => $s !== ''));
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $nombre = instalar_bd();
    echo "Base de datos «{$nombre}» instalada con los datos de demostración." . PHP_EOL;
}
