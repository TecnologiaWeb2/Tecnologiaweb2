<?php
// src/funciones.php — Funciones de ayuda para las vistas: escapar HTML, dinero, mensajes y token CSRF.
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

/** Redirige con 303 (patrón PRG: después de un POST, el navegador hace un GET). */
function redirigir(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

// ---------- Protección CSRF ----------

function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
    }
}

function csrf_token(): string
{
    iniciar_sesion();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Si el token del formulario no coincide con el de la sesión, responde 403 y termina. */
function exigir_csrf(array $entrada): void
{
    iniciar_sesion();
    $enviado = $entrada['csrf'] ?? '';
    if (!is_string($enviado) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        http_response_code(403);
        exit('Solicitud rechazada: el formulario expiró o no proviene de este sitio (CSRF).');
    }
}

/** Solo permite los métodos indicados; si no, responde 405 con la cabecera Allow. */
function exigir_metodo(string ...$permitidos): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $permitidos, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $permitidos));
        exit('Método no permitido');
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
    $html = '<nav class="paginacion" aria-label="Paginación"><ul>';
    $html .= $p->hayAnterior() ? '<li><a href="' . $url($p->pagina - 1) . '" rel="prev">← Anterior</a></li>' : '';
    for ($n = 1; $n <= $p->paginas(); $n++) {
        $html .= $n === $p->pagina
            ? "<li><span aria-current=\"page\">$n</span></li>"
            : '<li><a href="' . $url($n) . '">' . $n . '</a></li>';
    }
    $html .= $p->haySiguiente() ? '<li><a href="' . $url($p->pagina + 1) . '" rel="next">Siguiente →</a></li>' : '';
    return $html . '</ul></nav>';
}
