<?php
// src/Repositorios/UsuarioRepositorio.php — Usuarios y su control de intentos fallidos.
declare(strict_types=1);

namespace Apicultores\Repositorios;

use PDO;

final class UsuarioRepositorio
{
    public function __construct(private readonly PDO $bd)
    {
    }

    /** Fila completa (incluye clave_hash): SOLO la usa el Autenticador. */
    public function porCorreo(string $correo): ?array
    {
        $c = $this->bd->prepare('SELECT id, correo, nombre, clave_hash, rol, socio_id, intentos_fallidos,
                                        TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) AS segundos_bloqueo
                                 FROM usuarios WHERE correo = ?');
        $c->execute([mb_strtolower(trim($correo))]);
        return $c->fetch() ?: null;
    }

    public function registrarFallo(int $id, int $maximo, int $bloqueoSegundos): void
    {
        $this->bd->prepare('UPDATE usuarios
                            SET intentos_fallidos = intentos_fallidos + 1,
                                bloqueado_hasta = IF(intentos_fallidos >= :maximo, NOW() + INTERVAL :segundos SECOND, bloqueado_hasta)
                            WHERE id = :id')
            ->execute(['maximo' => $maximo, 'segundos' => $bloqueoSegundos, 'id' => $id]);
    }

    public function registrarExito(int $id): void
    {
        $this->bd->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$id]);
    }

    public function actualizarHash(int $id, string $hash): void
    {
        $this->bd->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }
}
