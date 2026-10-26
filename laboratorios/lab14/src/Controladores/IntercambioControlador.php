<?php
// src/Controladores/IntercambioControlador.php — Datos abiertos (JSON, XML, RSS) e importación de pedidos.
declare(strict_types=1);

namespace Apicultores\Controladores;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\NoEncontrado;
use Apicultores\Excepciones\Prohibido;
use Apicultores\Intercambio\CatalogoJson;
use Apicultores\Intercambio\CatalogoXml;
use Apicultores\Intercambio\ImportadorPedidos;
use Apicultores\Intercambio\PedidoJson;
use Apicultores\Intercambio\PedidoXml;
use Apicultores\Intercambio\Rss;
use Apicultores\Modelo\Usuario;
use Apicultores\Repositorios\ProductoRepositorioMysql;
use Apicultores\Ventas\RegistroVenta;
use Apicultores\Vista\Plantilla;
use PDO;

final class IntercambioControlador
{
    private readonly ProductoRepositorioMysql $productos;

    public function __construct(private readonly PDO $bd, private readonly Plantilla $vista, private readonly ?Usuario $usuario)
    {
        $this->productos = new ProductoRepositorioMysql($bd);
    }

    public function catalogoJson(): never
    {
        $this->enviar('application/json; charset=utf-8', (new CatalogoJson($this->productos))->json());
    }

    public function catalogoXml(): never
    {
        $this->enviar('application/xml; charset=utf-8', (new CatalogoXml(new CatalogoJson($this->productos)))->xml());
    }

    /** "REST" mínimo para comparar con SOAP: la URL identifica el recurso, la respuesta es JSON. */
    public function productoJson(string $codigo): never
    {
        $p = $this->productos->porCodigo(strtoupper($codigo)) ?? throw new NoEncontrado("No existe el producto $codigo.");
        $this->enviar('application/json; charset=utf-8', json_encode(
            ['codigo' => $p->codigo, 'nombre' => $p->nombre, 'precio' => $p->precio, 'stock' => $p->stock],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function rss(): never
    {
        $base = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $this->enviar('application/rss+xml; charset=utf-8', (new Rss($this->bd, $base))->xml());
    }

    public function formularioImportar(array $errores = []): string
    {
        $this->autorizar();
        return $this->vista->pagina('importar', [
            'errores' => $errores,
            'contenido' => (string) ($_POST['contenido'] ?? ''),
            'mensaje' => isset($_GET['venta']) ? 'Pedido importado como venta N.º ' . (int) $_GET['venta'] . '.' : '',
        ], 'Importar pedido');
    }

    public function importar(): never
    {
        $this->autorizar();
        $contenido = (string) ($_POST['contenido'] ?? '');
        if (strlen($contenido) > 200_000) {
            throw new DatosInvalidos(['general' => 'El pedido es demasiado grande (máximo 200 KB).']);
        }
        $importador = new ImportadorPedidos(new PedidoXml(), new PedidoJson(), new RegistroVenta($this->bd));
        $id = $importador->importar($contenido, $this->usuario->id);
        header("Location: /pedidos/importar?venta=$id", true, 303);
        exit;
    }

    private function autorizar(): void
    {
        if ($this->usuario === null || !$this->usuario->puede('pedidos.importar')) {
            throw new Prohibido('Tu rol no permite importar pedidos.');
        }
    }

    private function enviar(string $tipo, string $cuerpo): never
    {
        header("Content-Type: $tipo");
        header('Access-Control-Allow-Origin: *');      // datos públicos: cualquier sitio puede leerlos (CORS, clase 14)
        header('Cache-Control: public, max-age=300');
        echo $cuerpo;
        exit;
    }
}
