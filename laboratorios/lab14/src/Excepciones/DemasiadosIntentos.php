<?php
declare(strict_types=1);

namespace Apicultores\Excepciones;

/** 429: demasiados intentos fallidos de inicio de sesión. */
final class DemasiadosIntentos extends ErrorAplicacion
{
    public function __construct(private readonly int $segundos)
    {
        parent::__construct('Demasiados intentos fallidos. Vuelve a intentar en ' . (int) ceil($segundos / 60) . ' minutos.');
    }

    public function segundos(): int { return $this->segundos; }
    public function codigoHttp(): int { return 429; }
    public function titulo(): string { return 'Demasiados intentos'; }
}
