<?php
declare(strict_types=1);

namespace Apicultores\Excepciones;

/** 403: la sesión es válida, pero el rol no tiene permiso (o el token CSRF no coincide). */
final class Prohibido extends ErrorAplicacion
{
    public function codigoHttp(): int { return 403; }
    public function titulo(): string { return 'Sin permiso'; }
}
