<?php
// src/Controladores/SesionControlador.php — Iniciar y cerrar sesión.
declare(strict_types=1);

namespace Apicultores\Controladores;

use Apicultores\Seguridad\Autenticador;
use Apicultores\Seguridad\Sesion;
use Apicultores\Vista\Plantilla;

final class SesionControlador
{
    public function __construct(private readonly Autenticador $autenticador, private readonly Plantilla $vista)
    {
    }

    public function formulario(string $error = '', string $correo = ''): string
    {
        return $this->vista->pagina('entrar', ['error' => $error, 'correo' => $correo, 'volver' => self::volver()], 'Iniciar sesión');
    }

    public function entrar(): string
    {
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $usuario = $this->autenticador->intentar($correo, (string) ($_POST['clave'] ?? ''));
        if ($usuario === null) {
            http_response_code(401);
            // Mismo mensaje para correo inexistente y contraseña incorrecta: no se revela qué correos existen
            return $this->formulario('Correo o contraseña incorrectos.', $correo);
        }
        Sesion::entrar($usuario);
        header('Location: ' . self::volver(), true, 303);
        exit;
    }

    public function salir(): never
    {
        Sesion::cerrar();
        header('Location: /entrar', true, 303);
        exit;
    }

    /** Solo rutas internas ("/productos…"): evita redirecciones abiertas hacia otros sitios (//sitio-malo.com). */
    private static function volver(): string
    {
        $v = (string) ($_POST['volver'] ?? $_GET['volver'] ?? '/productos');
        return preg_match('#^/(?!/)[\w\-/?=&%.]*$#', $v) ? $v : '/productos';
    }
}
