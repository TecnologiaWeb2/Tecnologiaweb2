<?php
// plantillas/lineas.php — Filas «prenda + cantidad» para los formularios de venta y de pedido (require_once).
declare(strict_types=1);

/**
 * @param list<Producto> $productos
 * @param array<string, int> $elegidas líneas enviadas antes (para no perderlas si hubo un error)
 */
function filas_lineas(array $productos, array $elegidas = [], int $filas = 4): string
{
    $elegidas = array_slice($elegidas, 0, max($filas, count($elegidas)), true);
    $codigos = array_keys($elegidas);
    $html = '';
    $total = max($filas, count($elegidas));
    for ($i = 0; $i < $total; $i++) {
        $codigo = $codigos[$i] ?? '';
        $opciones = '<option value="">— prenda —</option>';
        foreach ($productos as $p) {
            $opciones .= '<option value="' . e($p->codigo) . '"' . ($p->codigo === $codigo ? ' selected' : '') . ($p->libre() === 0 ? ' disabled' : '') . '>'
                . e($p->codigo . ' · ' . $p->nombre . ' · ' . bs($p->precio) . ' · ' . $p->libre() . ' libres') . '</option>';
        }
        $n = $i + 1;
        $html .= '<div class="row g-2 mb-2"><div class="col-8 col-md-9"><label class="visually-hidden" for="l-codigo-' . $n . '">Prenda ' . $n . '</label>'
            . '<select class="form-select" id="l-codigo-' . $n . '" name="codigo[]">' . $opciones . '</select></div>'
            . '<div class="col-4 col-md-3"><label class="visually-hidden" for="l-cantidad-' . $n . '">Cantidad ' . $n . '</label>'
            . '<input class="form-control" id="l-cantidad-' . $n . '" name="cantidad[]" type="number" min="1" max="1000" placeholder="Cant." value="'
            . ($codigo === '' ? '' : (int) $elegidas[$codigo]) . '"></div></div>';
    }
    return $html;
}
