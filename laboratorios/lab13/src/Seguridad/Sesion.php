<?php
// src/Seguridad/Sesion.php — Sesión endurecida: cookie HttpOnly + SameSite, modo estricto y nuevo ID al iniciar sesión.
declare(strict_types=1);

namespace Apicultores\Seguridad;

use Apicultores\Modelo\Rol;
use Apicultores\Modelo\Usuario;

final class Sesion
{
    public const NOMBRE = 'APISESION';
    public const INACTIVIDAD = 1800;      // 30 minutos sin actividad → se cierra

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = ($_SERVER['HTTPS'] ?? '') === 'on';
        ini_set('session.use_strict_mode', '1');      // rechaza IDs que el servidor no creó (fijación de sesión)
        ini_set('session.use_only_cookies', '1');     // el ID nunca viaja en la URL
        session_name(self::NOMBRE);                   // no revelar "PHPSESSID"
        session_set_cookie_params([
            'lifetime' => 0,                          // hasta cerrar el navegador
            'path' => '/',
            'secure' => $https,                       // en producción (HTTPS) solo viaja cifrada
            'httponly' => true,                       // JavaScript no puede leerla (mitiga robo por XSS)
            'samesite' => 'Lax',                      // no se envía en POST desde otros sitios (mitiga CSRF)
        ]);
        session_start();

        $ahora = time();
        if (isset($_SESSION['ultima_actividad']) && $ahora - $_SESSION['ultima_actividad'] > self::INACTIVIDAD) {
            self::cerrar();
            session_start();
        }
        $_SESSION['ultima_actividad'] = $ahora;
    }

    /** Al iniciar sesión: ID NUEVO (el anterior queda inválido) y solo datos mínimos del usuario. */
    public static function entrar(Usuario $u): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = ['id' => $u->id, 'correo' => $u->correo, 'nombre' => $u->nombre, 'rol' => $u->rol->value, 'socio_id' => $u->socioId];
        unset($_SESSION['csrf']);                     // token nuevo para la sesión nueva
    }

    public static function usuario(): ?Usuario
    {
        $d = $_SESSION['usuario'] ?? null;
        return $d === null ? null : new Usuario($d['id'], $d['correo'], $d['nombre'], Rol::from($d['rol']), $d['socio_id']);
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(self::NOMBRE, '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => true, 'samesite' => $p['samesite']]);
        session_destroy();
    }
}
