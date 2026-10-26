<?php
// src/Intercambio/CatalogoXml.php — El mismo catálogo en XML, generado con XMLWriter (escapa por nosotros).
declare(strict_types=1);

namespace Apicultores\Intercambio;

use XMLWriter;

final class CatalogoXml
{
    public function __construct(private readonly CatalogoJson $fuente)
    {
    }

    public function xml(): string
    {
        $d = $this->fuente->datos();
        $x = new XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('catalogo');
        $x->writeAttribute('version', (string) $d['version']);
        $x->writeAttribute('generado', $d['generado']);
        $x->writeAttribute('moneda', $d['moneda']);
        foreach ($d['productos'] as $p) {
            $x->startElement('producto');
            $x->writeAttribute('codigo', $p['codigo']);
            $x->writeAttribute('categoria', $p['categoria']);
            $x->writeElement('nombre', $p['nombre']);           // "Polen & propóleo" → "Polen &amp; propóleo"
            $x->writeElement('precio', $p['precio']);
            $x->writeElement('stock', (string) $p['stock']);
            $x->startElement('socio');
            $x->writeAttribute('comunidad', $p['socio']['comunidad']);
            $x->text($p['socio']['nombre']);
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();
        $x->endDocument();
        return $x->outputMemory();
    }
}
