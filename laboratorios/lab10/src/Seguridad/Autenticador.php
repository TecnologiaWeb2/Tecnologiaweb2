<?php
// src/Seguridad/Autenticador.php — Verifica correo y contraseña con password_verify y limita los intentos.
declare(strict_types=1);

namespace Apicultores\Seguridad;

use Apicultores\Excepciones\DemasiadosIntentos;
use Apicultores\Modelo\Rol;
use Apicultores\Modelo\Usuario;
use Apicultores\Repositorios\UsuarioRepositorio;

final class Autenticador
{
    public const MAXIMO_INTENTOS = 5;
    public const BLOQUEO_SEGUNDOS = 900;                       // 15 minutos
    public const OPCIONES_HASH = ['cost' => 12];
    // Hash de una contraseña cualquiera: se verifica cuando el correo NO existe, para tardar lo mismo
    private const HASH_FICTICIO = '$2y$12$pPcTmVFOsiW4JHoTFr/Z8O4DWj4Nj3S9DisPdBl77Zpu7VMMM5nbO';

    public function __construct(private readonly UsuarioRepositorio $usuarios)
    {
    }

    /**
     * @return Usuario|null null si el correo o la contraseña no coinciden (sin decir cuál de los dos)
     * @throws DemasiadosIntentos
     */
    public function intentar(string $correo, string $clave): ?Usuario
    {
        $fila = $this->usuarios->porCorreo($correo);
        if ($fila === null) {
            password_verify($clave, self::HASH_FICTICIO);      // mismo tiempo de respuesta: no revela qué correos existen
            return null;
        }
        // TODO 6: límite de intentos.
        //   a) si $fila['segundos_bloqueo'] es mayor que 0 → throw new DemasiadosIntentos((int) $fila['segundos_bloqueo']);
        //   b) si la contraseña falla → $this->usuarios->registrarFallo(id, self::MAXIMO_INTENTOS, self::BLOQUEO_SEGUNDOS)
        if ((int) $fila['segundos_bloqueo'] > 0) {
            throw new DemasiadosIntentos((int) $fila['segundos_bloqueo']);
        }
        if (!password_verify($clave, $fila['clave_hash'])) {
            $this->usuarios->registrarFallo((int) $fila['id'], self::MAXIMO_INTENTOS, self::BLOQUEO_SEGUNDOS);
            return null;
        }
        $this->usuarios->registrarExito((int) $fila['id']);
        // Si el hash se hizo con un costo o algoritmo anterior, se actualiza AHORA (es el único momento en que tenemos la clave)
        // TODO 1: si password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT, self::OPCIONES_HASH) es true,
        //         guarda un hash nuevo con $this->usuarios->actualizarHash(id, password_hash($clave, PASSWORD_DEFAULT, self::OPCIONES_HASH)).
        if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT, self::OPCIONES_HASH)) {
            $this->usuarios->actualizarHash((int) $fila['id'], password_hash($clave, PASSWORD_DEFAULT, self::OPCIONES_HASH));
        }
        return new Usuario((int) $fila['id'], $fila['correo'], $fila['nombre'], Rol::from($fila['rol']),
            $fila['socio_id'] === null ? null : (int) $fila['socio_id']);
    }
}
