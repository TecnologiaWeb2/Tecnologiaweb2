<?php
// src/Modelo/Usuario.php — Usuario autenticado. NO contiene la contraseña ni su hash.
declare(strict_types=1);

namespace Apicultores\Modelo;

final readonly class Usuario
{
    public function __construct(
        public int $id,
        public string $correo,
        public string $nombre,
        public Rol $rol,
        public ?int $socioId = null,
    ) {
    }

    public function puede(string $accion): bool
    {
        return $this->rol->puede($accion);
    }
}
