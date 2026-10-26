<?php
// src/Intercambio.php — Actividad 4 (Laboratorio 12): intercambio de datos con otros sistemas.
//   /datos/catalogo.json   JSON: fechas ISO 8601 y precios decimales como TEXTO ("95.00", sin errores de redondeo)
//   /datos/catalogo.xml    XML generado con XMLWriter (escapa &, <, > solo: nunca se concatena texto)
//   /noticias.rss          RSS 2.0 con title, description, guid y pubDate (DATE_RSS)
//   /soap.php              SOAP: el mismo producto que GET /datos/productos/{codigo}, para comparar con REST
declare(strict_types=1);

/** URL base del sitio (http o https según la petición). */
function url_base(): string
{
    return (es_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Representación pública de una prenda para JSON, XML y SOAP: sin ids internos ni datos personales de la socia. */
function prenda_publica(Producto $p): array
{
    return [
        'codigo' => $p->codigo,
        'nombre' => $p->nombre,
        'categoria' => $p->categoria->nombre,
        'precio' => number_format($p->precio, 2, '.', ''),
        'moneda' => 'BOB',
        'stock' => $p->libre(),
        'tallas' => array_map(fn(Talla $t): string => $t->value, $p->tallas),
        'color' => $p->color,
        'confecciono' => $p->socio->nombre,
        'comunidad' => $p->socio->comunidad,
    ];
}

/** @return list<Producto> todo el catálogo ordenado por código */
function catalogo_completo(PDO $pdo): array
{
    $productos = (new ProductoRepositorio($pdo))->paginar(porPagina: 1000)->elementos;
    usort($productos, fn(Producto $a, Producto $b): int => strcmp($a->codigo, $b->codigo));
    return $productos;
}

function catalogo_json(PDO $pdo): string
{
    $productos = catalogo_completo($pdo);
    $actualizado = (string) $pdo->query('SELECT MAX(actualizado_en) FROM productos')->fetchColumn();
    return json_encode([
        'asociacion' => NOMBRE_ASOCIACION,
        'generado' => (new DateTimeImmutable())->format(DATE_ATOM),                   // ISO 8601: 2026-10-26T14:30:00-04:00
        'actualizado' => $actualizado === '' ? null : (new DateTimeImmutable($actualizado))->format(DATE_ATOM),
        'total' => count($productos),
        'productos' => array_map(prenda_publica(...), $productos),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}

function catalogo_xml(PDO $pdo): string
{
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('catalogo');
    $xml->writeAttribute('asociacion', NOMBRE_ASOCIACION);
    $xml->writeAttribute('generado', (new DateTimeImmutable())->format(DATE_ATOM));
    foreach (catalogo_completo($pdo) as $p) {
        $datos = prenda_publica($p);
        $xml->startElement('producto');
        $xml->writeAttribute('codigo', $datos['codigo']);
        $xml->writeElement('nombre', $datos['nombre']);             // «Bolso & cartera» → «Bolso &amp; cartera»
        $xml->writeElement('categoria', $datos['categoria']);
        $xml->startElement('precio');
        $xml->writeAttribute('moneda', 'BOB');
        $xml->text($datos['precio']);
        $xml->endElement();
        $xml->writeElement('stock', (string) $datos['stock']);
        $xml->startElement('tallas');
        foreach ($datos['tallas'] as $talla) {
            $xml->writeElement('talla', $talla);
        }
        $xml->endElement();
        $xml->writeElement('confecciono', $datos['confecciono']);
        $xml->endElement();
    }
    $xml->endElement();
    $xml->endDocument();
    return $xml->outputMemory();
}

/** Canal RSS 2.0 con las 10 prendas más recientes (lo lee la vitrina con fetch + DOMParser en el Laboratorio 14). */
function noticias_rss(PDO $pdo): string
{
    $base = url_base();
    $filas = $pdo->query('SELECT p.codigo, p.nombre, p.precio, p.creado_en, c.nombre AS categoria
        FROM productos p JOIN categorias c ON c.id = p.categoria_id ORDER BY p.creado_en DESC, p.id DESC LIMIT 10')->fetchAll();
    $xml = new XMLWriter();
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('rss');
    $xml->writeAttribute('version', '2.0');
    $xml->startElement('channel');
    $xml->writeElement('title', NOMBRE_TIENDA . ' · Novedades');
    $xml->writeElement('link', $base . '/');
    $xml->writeElement('description', 'Prendas nuevas confeccionadas por las socias de ' . NOMBRE_ASOCIACION . '.');
    $xml->writeElement('language', 'es-BO');
    $xml->writeElement('lastBuildDate', date(DATE_RSS));
    foreach ($filas as $f) {
        $enlace = $base . '/producto.php?codigo=' . rawurlencode($f['codigo']);
        $xml->startElement('item');
        $xml->writeElement('title', 'Nueva prenda: ' . $f['nombre']);
        $xml->writeElement('link', $enlace);
        $xml->writeElement('description', $f['categoria'] . ' · ' . bs((float) $f['precio']));
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', 'false');
        $xml->text('manos-del-oriente:' . $f['codigo']);
        $xml->endElement();
        $xml->writeElement('pubDate', date(DATE_RSS, (int) strtotime((string) $f['creado_en'])));
        $xml->endElement();
    }
    $xml->endElement();
    $xml->endElement();
    $xml->endDocument();
    return $xml->outputMemory();
}

/** Servicio SOAP (sin WSDL): precio(codigo) devuelve la misma prenda que GET /datos/productos/{codigo}. */
final class ServicioPrecios
{
    public function precio(string $codigo): array
    {
        $p = (new ProductoRepositorio(Conexion::obtener()))->buscarPorCodigo(strtoupper(trim($codigo)));
        if ($p === null) {
            throw new SoapFault('Client', "No existe la prenda {$codigo}.");
        }
        return prenda_publica($p);
    }
}
