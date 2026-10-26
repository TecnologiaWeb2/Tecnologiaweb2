<?php
declare(strict_types=1);

namespace Apicultores\Excepciones;

/** 415: el cuerpo no viene con el Content-Type que la API acepta (application/json). */
final class TipoNoSoportado extends ErrorAplicacion
{
    public function codigoHttp(): int { return 415; }
    public function titulo(): string { return 'Tipo de contenido no soportado'; }
}
