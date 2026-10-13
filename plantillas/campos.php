<?php
// plantillas/campos.php — Funciones para dibujar campos de formulario con su error asociado (se incluye con require_once).
declare(strict_types=1);

/** Atributos de accesibilidad de un campo con error. */
function atributos_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? " aria-invalid=\"true\" aria-describedby=\"f-$nombre-error\"" : '';
}

function mensaje_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? "<span class=\"error\" id=\"f-$nombre-error\">" . e($errores[$nombre]) . '</span>' : '';
}

/** Campo de texto con etiqueta, valor anterior (escapado) y error asociado. */
function campo(string $nombre, string $etiqueta, array $datos, array $errores, string $tipo = 'text', string $extra = ''): void
{
    echo "<p><label for=\"f-$nombre\">$etiqueta</label>"
        . "<input id=\"f-$nombre\" name=\"$nombre\" type=\"$tipo\" value=\"" . e((string) ($datos[$nombre] ?? '')) . "\" $extra"
        . atributos_error($nombre, $errores) . '>' . mensaje_error($nombre, $errores) . '</p>';
}
