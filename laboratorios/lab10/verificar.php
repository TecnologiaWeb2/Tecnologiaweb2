<?php
// verificar.php — Comprueba el Laboratorio 10 (password_hash, sesiones, roles, CSRF, intentos, cabeceras). Uso: php verificar.php
// Requiere MySQL en marcha (docker compose up -d). Restaura los datos al empezar y al terminar.
declare(strict_types=1);
require is_file(__DIR__ . '/Verificador.php') ? __DIR__ . '/Verificador.php' : __DIR__ . '/../../comun/Verificador.php';

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    exit("❌ Falta vendor/autoload.php. Primero ejecuta:  composer dump-autoload" . PHP_EOL);
}
require __DIR__ . '/vendor/autoload.php';

use Apicultores\BaseDatos\Conexion;
use Apicultores\Modelo\Rol;

try {
    $bd = Conexion::crear(require __DIR__ . '/config/bd.php');
} catch (PDOException $e) {
    exit("❌ No hay conexión con MySQL ({$e->getMessage()}).\n   Inicia la base de datos:  docker compose up -d" . PHP_EOL);
}
function restaurar(PDO $bd): void
{
    foreach (['01-esquema.sql', '02-datos.sql', '03-mas-datos.sql', '04-usuarios.sql'] as $archivo) {
        $bd->exec((string) file_get_contents(__DIR__ . "/sql/$archivo"));
    }
}
function hashDe(PDO $bd, string $correo): string
{
    $c = $bd->prepare('SELECT clave_hash FROM usuarios WHERE correo = ?');
    $c->execute([$correo]);
    return (string) $c->fetchColumn();
}
function token(string $html): ?string
{
    return preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : null;
}
function idSesion(string $cabeceras): ?string
{
    return preg_match('/Set-Cookie: APISESION=([^;]+)/i', $cabeceras, $m) && $m[1] !== 'deleted' ? $m[1] : null;
}
/** Abre /entrar (cookie y token) y envía el formulario. Devuelve [respuesta, idAntes]. */
function entrar(Verificador $v, string $correo, string $clave): array
{
    $v->olvidarCookies();
    $form = $v->pedir('/entrar');
    $r = $v->pedir('/entrar', 'POST', ['_csrf' => token($form['cuerpo']), 'correo' => $correo, 'clave' => $clave]);
    return [$r, idSesion($form['cabeceras'])];
}
/** Envía un POST con el token tomado de la página indicada */
function postConToken(Verificador $v, string $pagina, string $ruta, array $datos = []): array
{
    return $v->pedir($ruta, 'POST', ['_csrf' => token($v->pedir($pagina)['cuerpo'])] + $datos);
}
restaurar($bd);
$v = new Verificador();

$v->revisar('Contraseñas guardadas con password_hash (bcrypt), nunca en texto', fn() =>
    str_starts_with(hashDe($bd, 'admin@florchiquitania.bo'), '$2y$12$') && password_verify('Panal-2026!', hashDe($bd, 'admin@florchiquitania.bo'))
    && (int) $bd->query("SELECT COUNT(*) FROM usuarios WHERE clave_hash LIKE '%2026!%'")->fetchColumn() === 0);
$v->revisar('Matriz de permisos: operador edita pero no elimina; socio solo ve; acción desconocida → no', fn() =>
    Rol::Operador->puede('productos.editar') && !Rol::Operador->puede('productos.eliminar') && Rol::Admin->puede('productos.eliminar')
    && Rol::Socio->puede('productos.ver') && !Rol::Socio->puede('productos.crear') && !Rol::Admin->puede('sistema.borrar_todo'));

$v->servidor(__DIR__ . '/public', __DIR__ . '/public/index.php');
$anon = $v->pedir('/productos?pagina=2');
$v->revisar('Sin sesión: /productos → 302 a /entrar?volver=… (recuerda adónde ibas)', fn() =>
    $anon['estado'] === 302 && str_contains($anon['cabeceras'], 'Location: /entrar?volver=%2Fproductos%3Fpagina%3D2'));
$v->olvidarCookies();                               // sesión nueva: así llega Set-Cookie
$cab = $v->pedir('/entrar');
$v->revisar('Cabeceras de seguridad: CSP, nosniff, Referrer-Policy, sin X-Powered-By', fn() =>
    str_contains($cab['cabeceras'], "Content-Security-Policy: default-src 'self'; frame-ancestors 'none'")
    && stripos($cab['cabeceras'], 'X-Content-Type-Options: nosniff') !== false && str_contains($cab['cabeceras'], 'Referrer-Policy:')
    && stripos($cab['cabeceras'], 'X-Powered-By') === false);
$v->revisar('Cookie de sesión: nombre propio, HttpOnly y SameSite=Lax', fn() =>
    preg_match('/Set-Cookie: APISESION=[^\r\n]*HttpOnly[^\r\n]*SameSite=Lax/i', $cab['cabeceras']) === 1);

[$mal1] = entrar($v, 'admin@florchiquitania.bo', 'clave-equivocada');
[$mal2] = entrar($v, 'nadie@florchiquitania.bo', 'clave-equivocada');
$v->revisar('Contraseña errónea y correo inexistente → 401 con EL MISMO mensaje', fn() =>
    $mal1['estado'] === 401 && $mal2['estado'] === 401 && str_contains($mal1['cuerpo'], 'Correo o contraseña incorrectos.')
    && str_contains($mal2['cuerpo'], 'Correo o contraseña incorrectos.'));
$v->revisar('Iniciar sesión sin token CSRF → 403', function () use ($v) {
    $v->olvidarCookies();
    $v->pedir('/entrar');
    return $v->pedir('/entrar', 'POST', ['correo' => 'admin@florchiquitania.bo', 'clave' => 'Panal-2026!'])['estado'] === 403;
});
[$ok, $idAntes] = entrar($v, 'admin@florchiquitania.bo', 'Panal-2026!');
$v->revisar('Ingreso correcto → 303 y ID de sesión NUEVO (session_regenerate_id)', fn() =>
    $ok['estado'] === 303 && $idAntes !== null && idSesion($ok['cabeceras']) !== null && idSesion($ok['cabeceras']) !== $idAntes);
$v->revisar('Admin: elimina con token (303); sin token o con token falso → 403', function () use ($v, $bd) {
    $sin = $v->pedir('/productos/CAR-MIE/eliminar', 'POST', []);
    $falso = $v->pedir('/productos/CAR-MIE/eliminar', 'POST', ['_csrf' => str_repeat('a', 64)]);
    $con = postConToken($v, '/productos', '/productos/CAR-MIE/eliminar');
    return $sin['estado'] === 403 && $falso['estado'] === 403 && $con['estado'] === 303
        && (int) $bd->query("SELECT COUNT(*) FROM productos WHERE codigo = 'CAR-MIE'")->fetchColumn() === 0;
});
$v->revisar('Cerrar sesión (POST con token) → luego /productos vuelve a pedir ingreso', fn() =>
    postConToken($v, '/productos', '/salir')['estado'] === 303 && $v->pedir('/productos')['estado'] === 302);

$hashViejo = hashDe($bd, 'operador@florchiquitania.bo');
entrar($v, 'operador@florchiquitania.bo', 'Colmena-2026!');
$v->revisar('Operador: el hash cost 10 se actualiza a cost 12 al ingresar (password_needs_rehash)', fn() =>
    str_starts_with($hashViejo, '$2y$10$') && str_starts_with(hashDe($bd, 'operador@florchiquitania.bo'), '$2y$12$'));
$v->revisar('Operador: edita (303) pero eliminar → 403 y no ve el botón Eliminar', function () use ($v) {
    $lista = $v->pedir('/productos');
    $edita = postConToken($v, '/productos/MIE-500/editar', '/productos/MIE-500',
        ['nombre' => 'Miel multifloral 500 g', 'categoria' => 'Miel', 'precio' => '46', 'stock' => '32', 'socio_id' => '1']);
    return $edita['estado'] === 303 && postConToken($v, '/productos', '/productos/MIE-500/eliminar')['estado'] === 403
        && !str_contains($lista['cuerpo'], '>Eliminar<');
});

entrar($v, 'elena@florchiquitania.bo', 'Polen-2026!');
$soc = $v->pedir('/productos');
$v->revisar('Socio (Elena): ve SOLO sus 4 productos; registrar y ver socios → 403', fn() =>
    str_contains($soc['cuerpo'], 'Mostrando 1–4 de 4') && $v->pedir('/productos/nuevo')['estado'] === 403
    && $v->pedir('/socios')['estado'] === 403 && !str_contains($soc['cuerpo'], 'Registrar producto'));

$v->revisar('5 intentos fallidos bloquean la cuenta: el 6.º, aun correcto, → 429 con Retry-After', function () use ($v) {
    for ($i = 0; $i < 5; $i++) {
        entrar($v, 'elena@florchiquitania.bo', "mal-$i");
    }
    [$r] = entrar($v, 'elena@florchiquitania.bo', 'Polen-2026!');
    return $r['estado'] === 429 && preg_match('/Retry-After: (\d+)/', $r['cabeceras'], $m) === 1 && (int) $m[1] > 800
        && str_contains($r['cuerpo'], 'Demasiados intentos');
});
$v->revisar('Redirección abierta bloqueada: volver=//sitio-malo.com → /productos', function () use ($v) {
    $v->olvidarCookies();
    $form = $v->pedir('/entrar?volver=' . rawurlencode('//sitio-malo.com'));
    $r = $v->pedir('/entrar', 'POST', ['_csrf' => token($form['cuerpo']), 'correo' => 'admin@florchiquitania.bo',
        'clave' => 'Panal-2026!', 'volver' => '//sitio-malo.com']);
    return $r['estado'] === 303 && str_contains($r['cabeceras'], 'Location: /productos');
});
@unlink(__DIR__ . '/datos/errores.log');
restaurar($bd);
exit($v->fin());
