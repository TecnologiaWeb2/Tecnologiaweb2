<?php
// src/Modelo/Categoria.php — Categoría de prendas (poleras, pantalones…). Inmutable: promoción de propiedades + readonly.
declare(strict_types=1);

final readonly class Categoria
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $descripcion = '',
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{1,29}$/', $id) !== 1) {
            throw new InvalidArgumentException("Identificador de categoría inválido: «{$id}».");
        }
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('La categoría necesita un nombre.');
        }
    }

    /** @param array<string, mixed> $fila */
    public static function desdeArreglo(array $fila): self
    {
        return new self(
            id: (string) ($fila['id'] ?? ''),
            nombre: (string) ($fila['nombre'] ?? ''),
            descripcion: (string) ($fila['descripcion'] ?? ''),
        );
    }

    /** @return array{id: string, nombre: string, descripcion: string} */
    public function aArreglo(): array
    {
        return ['id' => $this->id, 'nombre' => $this->nombre, 'descripcion' => $this->descripcion];
    }
}
