<?php
declare(strict_types=1);

namespace Apicultores\Excepciones;

/** 401: hace falta iniciar sesión. El front controller redirige al formulario de ingreso. */
final class NoAutenticado extends ErrorAplicacion
{
    public function codigoHttp(): int { return 401; }
    public function titulo(): string { return 'Inicia sesión'; }
}
