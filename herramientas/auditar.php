<?php
// herramientas/auditar.php — Auditoría del SISTEMA EN MARCHA (local o publicado), parecida a la del docente (clase 15):
// cabeceras, cookie de sesión, rutas protegidas, CSRF, archivos internos y API. Escribe docs/proyecto-final/INFORME_AUDITORIA.md.
//
//   php herramientas/auditar.php http://127.0.0.1:8000
//   php herramientas/auditar.php https://manosdeloriente.infinityfreeapp.com --cookie="__test=…"
//
// InfinityFree pide a cada visitante un desafío JavaScript (cookie «__test») antes de mostrar el sitio: un cliente sin
// navegador (curl, este script, la auditoría del docente) recibe esa página en lugar de la nuestra. Para auditar la URL
// publicada, abre el sitio en el navegador, copia la cookie __test (F12 → Aplicación → Cookies) y pásala con --cookie.
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$cookie = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--cookie=')) {
        $cookie = substr($arg, 9);
    }
}

function pedir(string $url, string $metodo = 'GET', ?string $cuerpo = null, array $cabeceras = []): array
{
    global $cookie;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (auditoria Manos del Oriente)']);
    if ($cookie !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    }
    $r = (string) curl_exec($ch);
    $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return ['estado' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'cabeceras' => substr($r, 0, $tam), 'cuerpo' => substr($r, $tam)];
}

function cab(array $r, string $nombre): string
{
    return preg_match('/^' . preg_quote($nombre, '/') . ':\s*(.+?)\r?$/mi', $r['cabeceras'], $m) === 1 ? $m[1] : '';
}

$resultados = [];
function revisar(string $grupo, string $nombre, bool $ok, string $dondeCorregir): void
{
    global $resultados;
    $resultados[] = [$grupo, $nombre, $ok, $dondeCorregir];
    echo ($ok ? '✅' : '❌') . " [$grupo] $nombre" . ($ok ? '' : "  → $dondeCorregir") . PHP_EOL;
}

$inicio = pedir("$base/");
if (str_contains($inicio['cuerpo'], 'aes.js') || str_contains($inicio['cuerpo'], 'slowAES')) {
    fwrite(STDERR, "El hosting devolvió su desafío antibot (InfinityFree). Repite con --cookie=\"__test=…\" copiada del navegador.\n");
    exit(2);
}
$https = str_starts_with($base, 'https://');

revisar('Sistema', 'La tienda responde 200 con HTML', $inicio['estado'] === 200 && str_contains($inicio['cuerpo'], '<html'), 'public/index.php');
revisar('Seguridad', 'Content-Security-Policy sin scripts externos ni en línea', str_contains(cab($inicio, 'Content-Security-Policy'), "script-src 'self'"), 'src/arranque.php');
revisar('Seguridad', 'X-Frame-Options DENY y X-Content-Type-Options nosniff', cab($inicio, 'X-Frame-Options') === 'DENY' && cab($inicio, 'X-Content-Type-Options') === 'nosniff', 'src/arranque.php');
$login = pedir("$base/entrar.php");
$galleta = cab($login, 'Set-Cookie');
revisar('Seguridad', 'Cookie de sesión HttpOnly y SameSite' . ($https ? ' y Secure' : ''), stripos($galleta, 'HttpOnly') !== false
    && stripos($galleta, 'SameSite=Lax') !== false && (!$https || stripos($galleta, 'Secure') !== false), 'src/Seguridad.php → iniciar_sesion()');
revisar('Seguridad', 'El formulario de inicio de sesión lleva token CSRF', preg_match('/name="csrf" value="[a-f0-9]{64}"/', $login['cuerpo']) === 1, 'public/entrar.php');
revisar('Seguridad', 'Iniciar sesión sin token CSRF se rechaza (403)', pedir("$base/entrar.php", 'POST', 'correo=a%40b.bo&clave=x')['estado'] === 403, 'src/Seguridad.php → exigir_csrf()');
$protegidas = ['/admin.php', '/ventas.php', '/pedidos.php', '/liquidaciones.php', '/reportes.php', '/usuarios.php', '/tokens.php'];
$abiertas = array_filter($protegidas, fn(string $r): bool => pedir($base . $r)['estado'] !== 303);
revisar('Seguridad', 'Las páginas internas exigen sesión (303 a /entrar.php)', $abiertas === [], 'exigir_usuario() en ' . implode(', ', $abiertas));
$internos = ['/src/Seguridad.php', '/config/config.php', '/config/config.ejemplo.php', '/bd/esquema.sql', '/plantillas/cabecera.php', '/datos/fotos/', '/.htaccess', '/docs/openapi.yaml'];
$expuestos = array_filter($internos, function (string $r) use ($base): bool {
    $x = pedir($base . $r);
    return $x['estado'] === 200 && (str_contains($x['cuerpo'], '<?php') || str_contains($x['cuerpo'], 'CREATE TABLE') || str_contains($x['cuerpo'], "'clave'") || str_contains($x['cuerpo'], 'Index of'));
});
revisar('Seguridad', 'Código, configuración, SQL y fotos NO se descargan por URL', $expuestos === [], '.htaccess de la raíz: ' . implode(', ', $expuestos));
$error = pedir("$base/producto.php?codigo=" . urlencode("' OR '1'='1"));
revisar('Seguridad', 'Una inyección SQL en la URL no muestra errores de la base (404 limpio)', $error['estado'] === 404 && stripos($error['cuerpo'], 'SQLSTATE') === false, 'src/Repositorio/ProductoRepositorio.php');

$lista = pedir("$base/api/v1/productos?pagina=1&por_pagina=5", 'GET', null, ['Accept: application/json']);
$datos = json_decode($lista['cuerpo'], true);
revisar('API', 'GET /api/v1/productos: JSON paginado (datos, meta, enlaces)', $lista['estado'] === 200 && isset($datos['datos'], $datos['meta']['total'], $datos['enlaces']), 'src/Api.php → listar()');
$malo = pedir("$base/api/v1/productos?por_pagina=999");
revisar('API', 'Parámetro inválido → 422 application/problem+json', $malo['estado'] === 422 && str_starts_with(cab($malo, 'Content-Type'), 'application/problem+json'), 'src/Api.php');
$sin = pedir("$base/api/v1/productos", 'POST', '{}', ['Content-Type: application/json']);
revisar('API', 'Escribir sin token → 401 con WWW-Authenticate', $sin['estado'] === 401 && cab($sin, 'WWW-Authenticate') !== '', 'src/Api.php → autorizar()');
$preflight = pedir("$base/api/v1/productos", 'OPTIONS');
revisar('API', 'OPTIONS → 204 con cabeceras CORS', $preflight['estado'] === 204 && cab($preflight, 'Access-Control-Allow-Methods') !== '', 'src/Api.php → atender()');
$uno = pedir("$base/api/v1/productos/POL-001");
$cache = pedir("$base/api/v1/productos/POL-001", 'GET', null, ['If-None-Match: ' . cab($uno, 'ETag')]);
revisar('API', 'ETag + If-None-Match → 304 sin cuerpo', $uno['estado'] === 200 && $cache['estado'] === 304 && $cache['cuerpo'] === '', 'src/Api.php → ver()');
revisar('API', 'Contrato OpenAPI 3.1 publicado', str_starts_with(pedir("$base/api/v1/openapi.yaml")['cuerpo'], 'openapi: 3.1'), 'docs/openapi.yaml');
foreach (['/datos/catalogo.json' => 'application/json', '/datos/catalogo.xml' => 'application/xml', '/noticias.rss' => 'application/rss+xml'] as $ruta => $tipo) {
    $r = pedir($base . $ruta);
    revisar('Intercambio', "$ruta responde $tipo", $r['estado'] === 200 && str_starts_with(cab($r, 'Content-Type'), $tipo), 'public/' . basename($ruta) . '.php');
}

$bien = count(array_filter($resultados, fn(array $r): bool => $r[2]));
$total = count($resultados);
$porcentaje = (int) round(100 * $bien / $total);
echo PHP_EOL . "Resultado: $bien de $total ($porcentaje %)" . PHP_EOL;

$md = "# Informe de auditoría\n\n- **Sitio:** $base\n- **Fecha:** " . date('d/m/Y H:i') . "\n- **Resultado:** $bien de $total ($porcentaje %)\n\n| Grupo | Comprobación | Estado | Dónde corregir |\n|---|---|---|---|\n";
foreach ($resultados as [$grupo, $nombre, $ok, $donde]) {
    $md .= "| $grupo | $nombre | " . ($ok ? '✅' : '❌') . ' | ' . ($ok ? '' : "`$donde`") . " |\n";
}
@mkdir(__DIR__ . '/../docs/proyecto-final', 0775, true);
file_put_contents(__DIR__ . '/../docs/proyecto-final/INFORME_AUDITORIA' . ($https ? '_PUBLICADO' : '') . '.md', $md);
exit($bien === $total ? 0 : 1);
