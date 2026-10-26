<?php
// herramientas/empaquetar_infinityfree.php — Arma TODO lo que se sube a InfinityFree (hosting gratuito con PHP y MySQL).
//
//   php herramientas/empaquetar_infinityfree.php --host=sql123.infinityfree.com --usuario=if0_12345678 \
//       --clave="la-del-panel" --base=if0_12345678_manos [--demo]
//
// Genera en dist/infinityfree/ (carpeta fuera de git):
//   htdocs/                       → se sube TAL CUAL a la carpeta htdocs del hosting (FTP o el administrador de archivos)
//   manos-del-oriente-htdocs.zip  → lo mismo comprimido (el administrador de archivos de InfinityFree puede extraerlo)
//   manos_del_oriente.sql         → se importa en phpMyAdmin (crea las 13 tablas con los datos de demostración)
//   CREDENCIALES.txt              → contraseñas NUEVAS de los 4 usuarios (las de demostración son públicas en GitHub)
//
// Sin --demo se generan contraseñas aleatorias (lo que pide la rúbrica: «contraseñas nuevas al publicar»).
// Los datos de conexión se escriben en htdocs/config/config.php, que .htaccess protege y que nunca entra en git.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por consola.');
}
$raiz = dirname(__DIR__);
$opciones = getopt('', ['host:', 'usuario:', 'clave:', 'base:', 'puerto::', 'demo', 'ayuda']);
if (isset($opciones['ayuda'])) {
    echo file_get_contents(__FILE__, length: 1200);
    exit(0);
}
$bd = [
    'host' => (string) ($opciones['host'] ?? 'sqlXXX.infinityfree.com'),
    'puerto' => (int) ($opciones['puerto'] ?? 3306),
    'nombre' => (string) ($opciones['base'] ?? 'if0_XXXXXXXX_manos'),
    'usuario' => (string) ($opciones['usuario'] ?? 'if0_XXXXXXXX'),
    'clave' => (string) ($opciones['clave'] ?? 'CLAVE_DEL_PANEL'),
];
$faltanDatos = !isset($opciones['host'], $opciones['usuario'], $opciones['clave'], $opciones['base']);

$salida = "$raiz/dist/infinityfree";
$htdocs = "$salida/htdocs";
borrar($salida);
mkdir($htdocs, 0775, true);

// 1) Código: la misma estructura del repositorio (public/ + carpetas privadas). La raíz .htaccess deja ver SOLO public/.
foreach (['public', 'src', 'plantillas'] as $carpeta) {
    copiar("$raiz/$carpeta", "$htdocs/$carpeta");
}
@unlink("$htdocs/public/enrutador.php");                       // solo sirve para php -S
mkdir("$htdocs/bd/esquemas", 0775, true);
copy("$raiz/bd/esquemas/pedido.xsd", "$htdocs/bd/esquemas/pedido.xsd");   // lo usa importar.php para validar el XML
mkdir("$htdocs/docs", 0775, true);
copy("$raiz/docs/openapi.yaml", "$htdocs/docs/openapi.yaml");             // GET /api/v1/openapi.yaml
mkdir("$htdocs/datos/fotos", 0775, true);
foreach (glob("$raiz/bd/fotos-demo/*.*") as $foto) {
    copy($foto, "$htdocs/datos/fotos/" . basename($foto));
}
copy("$raiz/herramientas/infinityfree/.htaccess", "$htdocs/.htaccess");
$denegar = "# Carpeta privada: nunca se sirve por URL\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
// (datos/ no: /datos/catalogo.json es una URL pública que .htaccess envía a public/; se protege datos/fotos)
foreach (['src', 'plantillas', 'bd', 'docs', 'datos/fotos', 'config'] as $carpeta) {
    @mkdir("$htdocs/$carpeta", 0775, true);
    file_put_contents("$htdocs/$carpeta/.htaccess", $denegar);   // segunda defensa si mod_rewrite fallara
}

// 2) Configuración de la base de datos del hosting (fuera de public/, protegida y fuera de git)
$exportar = fn(string $v): string => var_export($v, true);
file_put_contents("$htdocs/config/config.php", "<?php\n// config/config.php — Generado por herramientas/empaquetar_infinityfree.php el " . date('d/m/Y H:i') . ".\n"
    . "// NO subir a GitHub. Datos del panel de InfinityFree → MySQL Databases.\ndeclare(strict_types=1);\n\nreturn [\n    'bd' => [\n"
    . "        'host' => {$exportar($bd['host'])},\n        'puerto' => {$bd['puerto']},\n        'nombre' => {$exportar($bd['nombre'])},\n"
    . "        'usuario' => {$exportar($bd['usuario'])},\n        'clave' => {$exportar($bd['clave'])},\n    ],\n];\n");

// 3) Script SQL único para phpMyAdmin (InfinityFree no permite CREATE DATABASE: la base se crea en el panel)
require_once "$raiz/bd/instalar.php";
$sql = "-- manos_del_oriente.sql — Importar en phpMyAdmin de InfinityFree (elige primero la base {$bd['nombre']}).\n"
    . "-- Generado el " . date('d/m/Y H:i') . " a partir de bd/" . implode(', bd/', SCRIPTS_SQL) . ".\n\n";
$credenciales = [];
foreach (SCRIPTS_SQL as $script) {
    $contenido = (string) file_get_contents("$raiz/bd/$script");
    if ($script === '04-usuarios.sql' && !isset($opciones['demo'])) {
        foreach (['admin@manosdeloriente.bo', 'operador@manosdeloriente.bo', 'rosa@manosdeloriente.bo', 'elena@manosdeloriente.bo'] as $correo) {
            $clave = clave_aleatoria();
            $credenciales[$correo] = $clave;
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            // Reemplaza el hash de demostración de ESA fila. Con callback: el hash contiene «$2y$…», que en un
            // reemplazo normal se leería como una referencia a un grupo de la expresión regular
            $contenido = (string) preg_replace_callback("/('" . preg_quote($correo, '/') . "',\s*'[^']*',\s*)'\\$2y\\$[^']+'/u",
                fn(array $m): string => $m[1] . "'" . $hash . "'", $contenido, 1);
        }
        $contenido = (string) preg_replace('/^-- Usuarios de DEMOSTRACIÓN.*?(?=\n\nSET NAMES)/sm', '-- Contraseñas NUEVAS: ver CREDENCIALES.txt (no se publican).', $contenido);
    }
    $sql .= "-- ======================== bd/$script ========================\n" . $contenido . "\n";
}
file_put_contents("$salida/manos_del_oriente.sql", $sql);
$texto = "Manos del Oriente · credenciales de PRODUCCIÓN (" . date('d/m/Y H:i') . ")\nGuardar en un lugar seguro y BORRAR este archivo después. No subir a GitHub.\n\n";
if ($credenciales === []) {
    $texto .= "Se usaron las contraseñas de DEMOSTRACIÓN (--demo). Cámbialas en «Mi cuenta» después de publicar.\n";
}
foreach ($credenciales as $correo => $clave) {
    $texto .= str_pad($correo, 32) . $clave . "\n";
}
file_put_contents("$salida/CREDENCIALES.txt", $texto);

// 4) ZIP de htdocs (incluye los .htaccess)
if (class_exists(ZipArchive::class)) {
    $zip = new ZipArchive();
    $zip->open("$salida/manos-del-oriente-htdocs.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($htdocs, FilesystemIterator::SKIP_DOTS));
    foreach ($archivos as $archivo) {
        $zip->addFile($archivo->getPathname(), str_replace('\\', '/', substr($archivo->getPathname(), strlen($htdocs) + 1)));
    }
    $zip->close();
}

$total = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($htdocs, FilesystemIterator::SKIP_DOTS)));
echo "Paquete listo en dist/infinityfree/ ($total archivos en htdocs/).\n";
echo "  1. Panel de InfinityFree → MySQL Databases: crea la base y anota host, usuario y clave.\n";
echo "  2. phpMyAdmin → elige la base → Importar → manos_del_oriente.sql\n";
echo "  3. Sube el CONTENIDO de htdocs/ a la carpeta htdocs del hosting (o el .zip y extráelo allí).\n";
echo "  4. Abre https://tu-dominio/ e ingresa con CREDENCIALES.txt; emite tokens nuevos en /tokens.php.\n";
if ($faltanDatos) {
    echo "\n⚠ Faltan --host, --usuario, --clave y --base: config/config.php quedó con valores de ejemplo. Edítalo antes de subir.\n";
}

function clave_aleatoria(): string
{
    $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
    $clave = '';
    for ($i = 0; $i < 10; $i++) {
        $clave .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $clave . '-' . random_int(1000, 9999);
}

function copiar(string $origen, string $destino): void
{
    @mkdir($destino, 0775, true);
    foreach (new DirectoryIterator($origen) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $hacia = "$destino/" . $item->getFilename();
        $item->isDir() ? copiar($item->getPathname(), $hacia) : copy($item->getPathname(), $hacia);
    }
}

function borrar(string $ruta): void
{
    if (!file_exists($ruta)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($ruta);
}
