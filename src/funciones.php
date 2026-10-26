<?php
// src/funciones.php — Funciones de ayuda para las vistas: escapar HTML, dinero, mensajes y paginación.
declare(strict_types=1);

/** Escapa texto para insertarlo en HTML (evita XSS). */
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formato boliviano: bs(1234.5) → "Bs 1.234,50"; bs(10, moneda: 'USD', decimales: 0) → "USD 10". */
function bs(float $monto, string $moneda = 'Bs', int $decimales = 2): string
{
    return $moneda . ' ' . number_format($monto, $decimales, ',', '.');
}

/** Lee un texto de un arreglo de entrada ($_GET/$_POST) ya recortado; nunca devuelve arreglos. */
function texto(array $origen, string $clave, string $porDefecto = ''): string
{
    $valor = $origen[$clave] ?? $porDefecto;
    return is_string($valor) ? trim($valor) : $porDefecto;
}

/** Guarda un aviso que plantillas/pie.php muestra como toast en la esquina superior derecha. $tipo: 'exito' o 'error'. */
function avisar(string $mensaje, string $tipo = 'exito'): void
{
    $GLOBALS['avisos'][] = ['mensaje' => $mensaje, 'tipo' => $tipo];
}

/** true si la página se pidió desde un modal (public/js/app.js envía X-Parcial: 1): se devuelve solo el contenido. */
function es_parcial(): bool
{
    return ($_SERVER['HTTP_X_PARCIAL'] ?? '') === '1';
}

/** Redirige con 303 (patrón PRG: después de un POST, el navegador hace un GET). */
function redirigir(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

// La sesión y el token CSRF viven en src/Seguridad.php (actividad 3).

/** Solo permite los métodos indicados; si no, responde 405 con la cabecera Allow. */
function exigir_metodo(string ...$permitidos): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        responder_error(405, 'Método no permitido', 'Esta dirección solo acepta ' . implode(', ', $permitidos) . '.');
    }
}

// ---------- Paginación ----------

/**
 * Navegación «Anterior 1 2 3 Siguiente» que conserva los filtros actuales en la URL.
 * @param array<string, mixed> $parametros filtros GET a conservar (los vacíos se omiten)
 */
function paginacion(Pagina $p, string $ruta, array $parametros = []): string
{
    if ($p->paginas() <= 1) {
        return '';
    }
    $parametros = array_filter($parametros, fn(mixed $v): bool => $v !== '' && $v !== null && $v !== false);
    $url = fn(int $n): string => e($ruta . '?' . http_build_query($parametros + ['pagina' => $n]));
    $html = '<nav class="paginacion mt-4" aria-label="Paginación"><ul class="pagination justify-content-center">';
    $html .= $p->hayAnterior() ? '<li class="page-item"><a class="page-link" href="' . $url($p->pagina - 1) . '" rel="prev">← Anterior</a></li>' : '';
    for ($n = 1; $n <= $p->paginas(); $n++) {
        $html .= $n === $p->pagina
            ? "<li class=\"page-item active\"><span class=\"page-link\" aria-current=\"page\">$n</span></li>"
            : '<li class="page-item"><a class="page-link" href="' . $url($n) . '">' . $n . '</a></li>';
    }
    $html .= $p->haySiguiente() ? '<li class="page-item"><a class="page-link" href="' . $url($p->pagina + 1) . '" rel="next">Siguiente →</a></li>' : '';
    return $html . '</ul></nav>';
}

/** Fecha y hora de MySQL ("2026-07-05 10:30:00") en formato boliviano: "05/07/2026 10:30". */
function fecha_bo(string $fechaMysql, bool $conHora = true): string
{
    $fecha = new DateTimeImmutable($fechaMysql);
    return $fecha->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
}

/** Número con coma decimal boliviana ("45,50") o con punto ("45.50") → float; null si no es un número. */
function numero_bo(string $texto): ?float
{
    $texto = trim($texto);
    if (str_contains($texto, ',')) {
        $texto = str_replace(['.', ','], ['', '.'], $texto);
    }
    $valor = filter_var($texto, FILTER_VALIDATE_FLOAT);
    return $valor === false ? null : (float) $valor;
}

/** Líneas de un formulario con codigo[] y cantidad[] → ['POL-001' => 3, …] (las vacías se ignoran, los repetidos se suman). */
function lineas_formulario(array $entrada): array
{
    $lineas = [];
    $codigos = is_array($entrada['codigo'] ?? null) ? $entrada['codigo'] : [];
    $cantidades = is_array($entrada['cantidad'] ?? null) ? $entrada['cantidad'] : [];
    foreach ($codigos as $i => $codigo) {
        $codigo = is_string($codigo) ? strtoupper(trim($codigo)) : '';
        if ($codigo === '') {
            continue;
        }
        $cantidad = filter_var($cantidades[$i] ?? '', FILTER_VALIDATE_INT);
        $lineas[$codigo] = ($lineas[$codigo] ?? 0) + ($cantidad === false ? 0 : $cantidad);
    }
    return $lineas;
}
