<?php
// src/Modelo/Talla.php — Tallas válidas de una prenda (enum respaldado por cadena).
declare(strict_types=1);

enum Talla: string
{
    case XS = 'XS';
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
    case XXL = 'XXL';
    case UNICA = 'U';

    public function etiqueta(): string
    {
        return $this === self::UNICA ? 'Talla única' : $this->value;
    }

    /**
     * Convierte una lista de textos ('S', 'M'…) en tallas, sin repetir y en el orden del enum.
     * @param array<mixed> $valores
     * @return list<Talla>
     */
    public static function lista(array $valores): array
    {
        $elegidas = array_map(fn(mixed $v): string => (string) $v, $valores);
        return array_values(array_filter(self::cases(), fn(Talla $t): bool => in_array($t->value, $elegidas, true)));
    }
}
