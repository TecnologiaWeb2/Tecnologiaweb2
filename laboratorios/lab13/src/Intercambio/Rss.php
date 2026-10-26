<?php
// src/Intercambio/Rss.php — Canal RSS 2.0 con los últimos acopios: "llegó miel nueva de San Javier".
// Los clientes lo leen con un lector de noticias (y en el lab 14, con fetch desde la página).
declare(strict_types=1);

namespace Apicultores\Intercambio;

use DateTimeImmutable;
use PDO;
use XMLWriter;

final class Rss
{
    public function __construct(private readonly PDO $bd, private readonly string $urlBase)
    {
    }

    public function xml(int $cantidad = 10): string
    {
        $c = $this->bd->prepare(
            'SELECT a.id, a.fecha, a.cantidad, p.codigo, p.nombre, s.nombre AS socio, s.comunidad
             FROM acopios a JOIN productos p ON p.id = a.producto_id JOIN socios s ON s.id = a.socio_id
             ORDER BY a.fecha DESC, a.id DESC LIMIT :n');
        $c->bindValue('n', $cantidad, PDO::PARAM_INT);
        $c->execute();

        $x = new XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('rss');
        $x->writeAttribute('version', '2.0');
        $x->startElement('channel');
        $x->writeElement('title', 'Flor de la Chiquitanía — productos recién llegados');
        $x->writeElement('link', $this->urlBase . '/');
        $x->writeElement('description', 'Acopios recientes de los socios de la asociación');
        $x->writeElement('language', 'es-bo');
        foreach ($c->fetchAll() as $f) {
            $x->startElement('item');
            $x->writeElement('title', "{$f['cantidad']} × {$f['nombre']}");
            $x->writeElement('description', "Entregado por {$f['socio']} ({$f['comunidad']}).");
            $x->writeElement('link', $this->urlBase . '/catalogo#' . $f['codigo']);
            $x->startElement('guid');
            $x->writeAttribute('isPermaLink', 'false');
            $x->text('acopio-' . $f['id']);
            $x->endElement();
            $x->writeElement('pubDate', (new DateTimeImmutable($f['fecha']))->format(DATE_RSS));   // formato RFC 822
            $x->endElement();
        }
        $x->endElement();
        $x->endElement();
        $x->endDocument();
        return $x->outputMemory();
    }
}
