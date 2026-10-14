<?php
// plantillas/campos.php — Funciones para dibujar campos de formulario (Bootstrap 5) con su error asociado (require_once).
declare(strict_types=1);

/** Atributos de accesibilidad de un campo con error. */
function atributos_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? " aria-invalid=\"true\" aria-describedby=\"f-$nombre-error\"" : '';
}

/** Clase de Bootstrap para el control: rojo si tiene error. */
function clase_control(string $nombre, array $errores, string $base = 'form-control'): string
{
    return $base . (isset($errores[$nombre]) ? ' is-invalid' : '');
}

function mensaje_error(string $nombre, array $errores): string
{
    return isset($errores[$nombre]) ? "<span class=\"error invalid-feedback d-block\" id=\"f-$nombre-error\">" . e($errores[$nombre]) . '</span>' : '';
}

/** Campo de texto con etiqueta, valor anterior (escapado) y error asociado. $columnas: ancho en la grilla (1 a 12). */
function campo(string $nombre, string $etiqueta, array $datos, array $errores, string $tipo = 'text', string $extra = '', int $columnas = 12): void
{
    echo "<div class=\"col-md-$columnas\"><label class=\"form-label\" for=\"f-$nombre\">$etiqueta</label>"
        . "<input id=\"f-$nombre\" class=\"" . clase_control($nombre, $errores) . "\" name=\"$nombre\" type=\"$tipo\" value=\"" . e((string) ($datos[$nombre] ?? '')) . "\" $extra"
        . atributos_error($nombre, $errores) . '>' . mensaje_error($nombre, $errores) . '</div>';
}

/** Resumen de errores arriba del formulario. */
function alerta_errores(array $errores): string
{
    if ($errores === []) {
        return '';
    }
    $texto = count($errores) === 1 ? 'el campo marcado' : 'los ' . count($errores) . ' campos marcados';
    return '<div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i>Revisa ' . $texto . '.</div>';
}
