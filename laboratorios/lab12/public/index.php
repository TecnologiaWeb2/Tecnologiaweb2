<?php
// public/index.php — Front controller (lab 12): lo del lab 11 + intercambio de datos (JSON, XML, RSS, SOAP).
//   php -S 127.0.0.1:8000 -t public public/index.php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

use Apicultores\Archivos\GuardaImagen;
use Apicultores\BaseDatos\Conexion;
use Apicultores\Controladores\IntercambioControlador;
use Apicultores\Controladores\MovimientosControlador;
use Apicultores\Controladores\ProductosControlador;
use Apicultores\Controladores\SesionControlador;
use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\DemasiadosIntentos;
use Apicultores\Excepciones\ErrorAplicacion;
use Apicultores\Excepciones\MetodoNoPermitido;
use Apicultores\Excepciones\NoAutenticado;
use Apicultores\Http\Router;
use Apicultores\Intercambio\ServicioPrecios;
use Apicultores\Repositorios\ProductoRepositorioMysql;
use Apicultores\Repositorios\SocioRepositorio;
use Apicultores\Reportes\ReporteVentas;
use Apicultores\Repositorios\UsuarioRepositorio;
use Apicultores\Seguridad\Autenticador;
use Apicultores\Seguridad\Csrf;
use Apicultores\Seguridad\Sesion;
use Apicultores\Validacion\ValidadorProducto;
use Apicultores\Ventas\RegistroAcopio;
use Apicultores\Ventas\RegistroVenta;
use Apicultores\Vista\Plantilla;

ini_set('display_errors', '0');
const REGISTRO_ERRORES = __DIR__ . '/../datos/errores.log';
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

// 1) Cabeceras de seguridad en TODAS las respuestas (relación con Tecnología Web I, clase 5)
header("Content-Security-Policy: default-src 'self'; img-src 'self' blob:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');                              // navegadores antiguos (CSP frame-ancestors es lo actual)
header_remove('X-Powered-By');                                // no anunciar la versión de PHP

$vista = new Plantilla(__DIR__ . '/../plantillas');
$ruta = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// SOAP: servicio entre máquinas (sin sesión, sin cookies, sin CSRF). Responde XML (sobre SOAP) y termina.
if ($ruta === '/soap') {
    try {
        $servidor = new SoapServer(null, ['uri' => 'urn:apicultores-precios', 'encoding' => 'UTF-8']);
        $servidor->setObject(new ServicioPrecios(new ProductoRepositorioMysql(Conexion::crear(require __DIR__ . '/../config/bd.php'))));
        $servidor->handle();
    } catch (Throwable $e) {
        error_log(sprintf("[%s] soap %s: %s
", date('c'), $e::class, $e->getMessage()), 3, REGISTRO_ERRORES);
        http_response_code(500);
    }
    exit;
}

try {
    // 2) Sesión endurecida y datos compartidos para todas las plantillas
    Sesion::iniciar();
    $usuario = Sesion::usuario();
    $vista->compartir([
        'csrf' => Csrf::token(),
        'usuarioNombre' => $usuario?->nombre ?? '',
        'usuarioRol' => $usuario?->rol->etiqueta() ?? '',
        'verSocios' => $usuario?->puede('socios.ver') ?? false,
        'verVentas' => $usuario?->puede('ventas.registrar') ?? false,
        'verReportes' => $usuario?->puede('reportes.ver') ?? false,
    ]);

    // 3) CSRF: TODO POST debe traer el token de la sesión (incluido el de iniciar sesión)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::validar($_POST[Csrf::CAMPO] ?? null);
    }

    $bd = Conexion::crear(require __DIR__ . '/../config/bd.php');
    $sesiones = new SesionControlador(new Autenticador(new UsuarioRepositorio($bd)), $vista);

    $router = new Router();
    $router->get('/', fn() => header('Location: /productos', true, 302));
    $router->get('/entrar', fn() => $sesiones->formulario());
    $router->post('/entrar', $sesiones->entrar(...));
    $router->post('/salir', $sesiones->salir(...));

    // Datos abiertos (solo lectura, sin sesión): otros sistemas consumen el catálogo
    $i = new IntercambioControlador($bd, $vista, $usuario);
    $router->get('/datos/catalogo.json', $i->catalogoJson(...));
    $router->get('/datos/catalogo.xml', $i->catalogoXml(...));
    $router->get('/datos/productos/{codigo}', $i->productoJson(...));
    $router->get('/noticias.rss', $i->rss(...));

    // 4) Autenticación: las rutas de la aplicación exigen sesión iniciada
    if ($usuario !== null) {
        $productos = new ProductoRepositorioMysql($bd);
        $socios = new SocioRepositorio($bd);
        $c = new ProductosControlador($productos, $socios, new ValidadorProducto($productos, $socios), $vista, $usuario,
            new GuardaImagen(__DIR__ . '/uploads/productos'));
        $router->get('/productos', $c->listar(...));
        $router->get('/productos/nuevo', $c->nuevo(...));
        $router->post('/productos', $c->crear(...));
        $router->get('/productos/{codigo}/editar', $c->editar(...));
        $router->post('/productos/{codigo}', $c->actualizar(...));
        $router->post('/productos/{codigo}/eliminar', $c->eliminar(...));
        $router->get('/socios', $c->socios(...));
        $router->get('/socios/{id}', $c->socio(...));
        $m = new MovimientosControlador(new RegistroVenta($bd), new RegistroAcopio($bd), new ReporteVentas($bd), $vista, $usuario);
        $router->get('/ventas/nueva', fn() => $m->formularioVenta());
        $router->post('/ventas', $m->registrarVenta(...));
        $router->post('/acopios', $m->registrarAcopio(...));
        $router->get('/reportes', $m->reportes(...));
        $router->get('/reportes/ventas.csv', $m->exportarCsv(...));
        $router->get('/pedidos/importar', fn() => $i->formularioImportar());
        $router->post('/pedidos/importar', $i->importar(...));
    } elseif (preg_match('#^/(productos|socios|ventas|acopios|reportes|pedidos)#', $ruta)) {
        throw new NoAutenticado('Inicia sesión para continuar.');
    }

    echo $router->despachar($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (NoAutenticado) {
    // Navegador: al formulario de ingreso, recordando adónde quería ir
    $volver = $_SERVER['REQUEST_METHOD'] === 'GET' ? '?volver=' . rawurlencode($_SERVER['REQUEST_URI']) : '';
    header("Location: /entrar$volver", true, 302);
} catch (DemasiadosIntentos $e) {
    http_response_code(429);
    header('Retry-After: ' . $e->segundos());
    echo $sesiones->formulario($e->getMessage(), (string) ($_POST['correo'] ?? ''));
} catch (DatosInvalidos $e) {
    http_response_code(422);
    if ($ruta === '/pedidos/importar') {
        echo $i->formularioImportar($e->errores());
        exit;
    }
    if ($ruta === '/ventas' || $ruta === '/acopios') {
        echo $m->formularioVenta($e->errores());
        exit;
    }
    if ($ruta !== '/productos' && !str_starts_with($ruta, '/productos/')) {
        echo $vista->pagina('error', ['codigo' => 422, 'titulo' => 'Datos inválidos', 'mensaje' => implode(' ', $e->errores())], 'Datos inválidos');
        exit;
    }
    $nuevo = $ruta === '/productos';
    echo $c->formulario($ruta, $_POST + ['codigo' => $nuevo ? '' : rawurldecode(basename($ruta))], $e->errores(), $nuevo);
} catch (ErrorAplicacion $e) {
    http_response_code($e->codigoHttp());
    if ($e instanceof MetodoNoPermitido) {
        header('Allow: ' . implode(', ', $e->permitidos()));
    }
    echo $vista->pagina('error', ['codigo' => $e->codigoHttp(), 'titulo' => $e->titulo(), 'mensaje' => $e->getMessage()], $e->titulo());
} catch (Throwable $e) {
    $referencia = bin2hex(random_bytes(4));
    error_log(sprintf("[%s] ref=%s %s: %s en %s:%d\n", date('c'), $referencia, $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
        3, REGISTRO_ERRORES);
    http_response_code(500);
    echo $vista->pagina('error', ['codigo' => 500, 'titulo' => 'Algo salió mal',
        'mensaje' => "Ya registramos el problema. Si llamas a soporte, menciona la referencia $referencia."], 'Error');
}
