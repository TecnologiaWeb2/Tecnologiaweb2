<?php
// src/Api/NucleoApi.php — Atiende TODA petición a /api/v1/…: CORS, tipo de contenido, autenticación, rutas y errores.
// Siempre responde JSON (o problem+json): nunca HTML, nunca una redirección.
declare(strict_types=1);

namespace Apicultores\Api;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\TipoNoSoportado;
use Apicultores\Http\Router;
use Apicultores\Repositorios\ProductoRepositorioMysql;
use Apicultores\Repositorios\SocioRepositorio;
use Apicultores\Validacion\ValidadorProducto;
use PDO;
use Throwable;

final class NucleoApi
{
    public function __construct(private readonly PDO $bd, private readonly string $registro)
    {
    }

    public function atender(string $metodo, string $uri): void
    {
        $ruta = (string) parse_url($uri, PHP_URL_PATH);
        // CORS: cualquier origen puede LEER; para escribir hace falta un token (no hay cookies que robar)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-None-Match');
        header('Access-Control-Expose-Headers: Location, ETag');
        if ($metodo === 'OPTIONS') {                       // "preflight" del navegador antes de un POST/PUT/DELETE
            http_response_code(204);
            return;
        }
        try {
            $usuario = (new AutenticadorToken($this->bd))->usuario($_SERVER['HTTP_AUTHORIZATION'] ?? null);
            $cuerpo = in_array($metodo, ['POST', 'PUT'], true) ? $this->leerCuerpo() : [];

            $productos = new ProductoRepositorioMysql($this->bd);
            $api = new ProductosApi($productos, new ValidadorProducto($productos, new SocioRepositorio($this->bd)), $usuario, $cuerpo);
            $router = new Router();
            $router->get('/api/v1/productos', $api->listar(...));
            $router->post('/api/v1/productos', $api->crear(...));
            $router->get('/api/v1/productos/{codigo}', $api->ver(...));
            $router->put('/api/v1/productos/{codigo}', $api->reemplazar(...));
            $router->delete('/api/v1/productos/{codigo}', $api->eliminar(...));

            [$estado, $datos, $cabeceras] = $router->despachar($metodo, $uri);
            $this->enviar($estado, $datos, $cabeceras, 'application/json');
        } catch (Throwable $e) {
            [$estado, $datos, $cabeceras] = Problema::desde($e, $ruta, $this->registro);
            $this->enviar($estado, $datos, $cabeceras, 'application/problem+json');
        }
    }

    /** El cuerpo debe declararse como JSON (415 si no) y ser un OBJETO JSON (400 si no se puede leer). */
    private function leerCuerpo(): array
    {
        $tipo = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));   // sin "; charset=…"
        if ($tipo !== 'application/json') {
            throw new TipoNoSoportado('Envía el cuerpo con "Content-Type: application/json".');
        }
        $datos = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($datos) || array_is_list($datos)) {
            throw new DatosInvalidos(['cuerpo' => 'Se esperaba un objeto JSON: {"codigo": …, "nombre": …}.']);
        }
        return $datos;
    }

    private function enviar(int $estado, ?array $datos, array $cabeceras, string $tipo): void
    {
        http_response_code($estado);
        foreach ($cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        if ($datos !== null && $estado !== 204 && $estado !== 304) {
            header("Content-Type: $tipo; charset=utf-8");
            echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }
    }
}
