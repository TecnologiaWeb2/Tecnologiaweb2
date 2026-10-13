<?php
// src/Repositorio/Pagina.php — Resultado paginado: los elementos de la página actual y los datos para navegar.
declare(strict_types=1);

/** @template T */
final readonly class Pagina
{
    /** @param list<T> $elementos */
    public function __construct(
        public array $elementos,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }

    public function hayAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function haySiguiente(): bool
    {
        return $this->pagina < $this->paginas();
    }

    /** Número del primer elemento mostrado (1-based), 0 si no hay resultados. */
    public function desde(): int
    {
        return $this->total === 0 ? 0 : ($this->pagina - 1) * $this->porPagina + 1;
    }

    public function hasta(): int
    {
        return min($this->total, $this->pagina * $this->porPagina);
    }

    /** Página pedida por el usuario llevada al rango válido: "abc", "-3" o "999" no rompen nada. */
    public static function normalizar(mixed $pedida, int $total, int $porPagina): int
    {
        $pagina = filter_var($pedida, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        return min($pagina, max(1, (int) ceil($total / $porPagina)));
    }
}
