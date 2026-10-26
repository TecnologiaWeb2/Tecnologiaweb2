<?php
// src/Seguridad/Csrf.php — Token anti-CSRF: un secreto por sesión que cada formulario POST debe devolver.
// Otro sitio puede hacer que el navegador ENVÍE un formulario a nuestro sistema, pero no puede LEER este token.
declare(strict_types=1);

namespace Apicultores\Seguridad;

use Apicultores\Excepciones\Prohibido;

final class Csrf
{
    public const CAMPO = '_csrf';

    public static function token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));   // 64 caracteres hexadecimales
    }

    /** @throws Prohibido si el token falta o no coincide */
    public static function validar(?string $recibido): void
    {
        // TODO 4: compara $recibido con $_SESSION['csrf'] usando hash_equals (tiempo constante).
        //         Si falta o no coincide → throw new Prohibido('La sesión del formulario expiró o no es válida. …');
        $esperado = $_SESSION['csrf'] ?? '';
        if ($recibido === null || $esperado === '' || !hash_equals($esperado, $recibido)) {
            throw new Prohibido('La sesión del formulario expiró o no es válida. Vuelve a cargar la página e inténtalo de nuevo.');
        }
    }
}
