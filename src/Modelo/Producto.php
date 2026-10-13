<?php
// src/Modelo/Producto.php — Una prenda del catálogo. Promoción de propiedades en el constructor, inmutable (readonly):
// para "editar" se crea un Producto nuevo con con(...) y el repositorio lo guarda.
declare(strict_types=1);

final readonly class Producto
{
    public const PATRON_CODIGO = '/^[A-Z]{3}-[A-Z0-9]{2,5}$/';

    /** @param list<Talla> $tallas */
    public function __construct(
        public string $codigo,
        public string $nombre,
        public Categoria $categoria,
        public Socio $socio,
        public float $precio,
        public int $stock = 0,
        public array $tallas = [Talla::UNICA],
        public string $color = '',
        public string $descripcion = '',
        public ?string $foto = null,
        public ?int $id = null,
    ) {
        // Reglas que SIEMPRE deben cumplirse, venga de un formulario, de la base de datos o de una prueba
        if (preg_match(self::PATRON_CODIGO, $codigo) !== 1) {
            throw new InvalidArgumentException("Código inválido: «{$codigo}».");
        }
        if (mb_strlen(trim($nombre)) < 3) {
            throw new InvalidArgumentException('El nombre es demasiado corto.');
        }
        if ($precio <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }
        if ($stock < 0) {
            throw new InvalidArgumentException('El stock no puede ser negativo.');
        }
        if ($tallas === [] || array_filter($tallas, fn(mixed $t): bool => !$t instanceof Talla) !== []) {
            throw new InvalidArgumentException('Indica al menos una talla válida.');
        }
    }

    public function disponible(): bool
    {
        return $this->stock > 0;
    }

    public function tieneTalla(Talla $talla): bool
    {
        return in_array($talla, $this->tallas, true);
    }

    /** "S · M · L" */
    public function tallasTexto(string $separador = ' · '): string
    {
        return implode($separador, array_map(fn(Talla $t): string => $t->etiqueta(), $this->tallas));
    }

    /** Copia con algunos campos cambiados (parámetros nombrados): $p->con(foto: 'abc.png'). */
    public function con(
        ?string $nombre = null,
        ?Categoria $categoria = null,
        ?Socio $socio = null,
        ?float $precio = null,
        ?int $stock = null,
        ?array $tallas = null,
        ?string $color = null,
        ?string $descripcion = null,
        ?string $foto = null,
        bool $quitarFoto = false,
        ?int $id = null,
    ): self {
        return new self(
            codigo: $this->codigo,
            nombre: $nombre ?? $this->nombre,
            categoria: $categoria ?? $this->categoria,
            socio: $socio ?? $this->socio,
            precio: $precio ?? $this->precio,
            stock: $stock ?? $this->stock,
            tallas: $tallas ?? $this->tallas,
            color: $color ?? $this->color,
            descripcion: $descripcion ?? $this->descripcion,
            foto: $quitarFoto ? null : ($foto ?? $this->foto),
            id: $id ?? $this->id,
        );
    }

    /** @return array<string, mixed> representación simple (la usará la API REST de la actividad 4) */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria->id,
            'socio_id' => $this->socio->id,
            'precio' => $this->precio,
            'stock' => $this->stock,
            'tallas' => array_map(fn(Talla $t): string => $t->value, $this->tallas),
            'color' => $this->color,
            'descripcion' => $this->descripcion,
            'foto' => $this->foto,
        ];
    }
}
