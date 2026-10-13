<?php
// src/Modelo/Catalogo.php — Colección de productos y categorías con persistencia en JSON.
// Es el único lugar que lee/escribe los archivos: el día que llegue MySQL, solo cambia esta clase.
declare(strict_types=1);

final class Catalogo
{
    public const ORDENES = ['nombre' => 'Nombre (A-Z)', 'precio-asc' => 'Precio: menor a mayor', 'precio-desc' => 'Precio: mayor a menor'];

    /** @var array<string, Categoria> indexadas por id */
    private array $categorias = [];
    /** @var array<string, Producto> indexados por código */
    private array $productos = [];

    public function __construct(
        private readonly string $archivoProductos = ARCHIVO_PRODUCTOS,
        private readonly string $archivoCategorias = ARCHIVO_CATEGORIAS,
    ) {
        foreach (self::leerJson($this->archivoCategorias) as $fila) {
            $c = Categoria::desdeArreglo($fila);
            $this->categorias[$c->id] = $c;
        }
        foreach (self::leerJson($this->archivoProductos) as $fila) {
            $p = Producto::desdeArreglo($fila, $this->categorias);
            $this->productos[$p->codigo] = $p;
        }
    }

    // ---------- Categorías ----------

    /** @return array<string, Categoria> */
    public function categorias(): array
    {
        return $this->categorias;
    }

    public function categoria(string $id): ?Categoria
    {
        return $this->categorias[$id] ?? null;
    }

    /** @return array<string, int> id de categoría => cantidad de productos */
    public function conteoPorCategoria(): array
    {
        $conteo = array_fill_keys(array_keys($this->categorias), 0);
        foreach ($this->productos as $p) {
            $conteo[$p->categoria->id]++;
        }
        return $conteo;
    }

    // ---------- Productos ----------

    /** @return list<Producto> */
    public function todos(): array
    {
        return array_values($this->productos);
    }

    public function contar(): int
    {
        return count($this->productos);
    }

    public function existe(string $codigo): bool
    {
        return isset($this->productos[$codigo]);
    }

    public function buscar(string $codigo): ?Producto
    {
        return $this->productos[$codigo] ?? null;
    }

    public function agregar(Producto $producto): void
    {
        if ($this->existe($producto->codigo)) {
            throw new DomainException("Ya existe un producto con el código {$producto->codigo}.");
        }
        $this->exigirCategoria($producto);
        $this->productos[$producto->codigo] = $producto;
    }

    public function reemplazar(Producto $producto): void
    {
        if (!$this->existe($producto->codigo)) {
            throw new DomainException("No existe el producto {$producto->codigo}.");
        }
        $this->exigirCategoria($producto);
        $this->productos[$producto->codigo] = $producto;
    }

    /** Quita el producto y lo devuelve (para que quien llama borre su foto), o null si no existía. */
    public function eliminar(string $codigo): ?Producto
    {
        $producto = $this->productos[$codigo] ?? null;
        unset($this->productos[$codigo]);
        return $producto;
    }

    /**
     * Búsqueda con filtros opcionales (todos con valor por defecto: se pueden usar con parámetros nombrados).
     * @return list<Producto>
     */
    public function filtrar(
        string $texto = '',
        ?string $categoria = null,
        ?Talla $talla = null,
        bool $soloDisponibles = false,
        string $orden = 'nombre',
    ): array {
        $texto = trim($texto);
        $resultado = array_filter($this->productos, function (Producto $p) use ($texto, $categoria, $talla, $soloDisponibles): bool {
            if ($texto !== '' && mb_stripos("{$p->nombre} {$p->codigo} {$p->color}", $texto) === false) {
                return false;
            }
            if ($categoria !== null && $categoria !== '' && $p->categoria->id !== $categoria) {
                return false;
            }
            if ($talla !== null && !$p->tieneTalla($talla)) {
                return false;
            }
            return !$soloDisponibles || $p->disponible();
        });
        usort($resultado, match ($orden) {
            'precio-asc' => fn(Producto $a, Producto $b): int => $a->precio <=> $b->precio ?: strcmp($a->codigo, $b->codigo),
            'precio-desc' => fn(Producto $a, Producto $b): int => $b->precio <=> $a->precio ?: strcmp($a->codigo, $b->codigo),
            default => fn(Producto $a, Producto $b): int => strcmp(mb_strtolower($a->nombre), mb_strtolower($b->nombre)),
        });
        return $resultado;
    }

    /** Valor del inventario: suma de precio × stock. */
    public function valorInventario(): float
    {
        return round(array_sum(array_map(fn(Producto $p): float => $p->precio * $p->stock, $this->productos)), 2);
    }

    // ---------- Persistencia ----------

    /** Guarda los productos con bloqueo exclusivo (LOCK_EX); escribe a un temporal y lo renombra para no dejar el JSON a medias. */
    public function guardar(): void
    {
        $filas = array_map(fn(Producto $p): array => $p->aArreglo(), $this->todos());
        $json = json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $temporal = $this->archivoProductos . '.tmp';
        file_put_contents($temporal, $json . PHP_EOL, LOCK_EX);
        rename($temporal, $this->archivoProductos);
    }

    /** @return list<array<string, mixed>> */
    private static function leerJson(string $archivo): array
    {
        if (!is_file($archivo)) {
            return [];
        }
        $datos = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
        return is_array($datos) ? array_values($datos) : [];
    }

    private function exigirCategoria(Producto $producto): void
    {
        if (!isset($this->categorias[$producto->categoria->id])) {
            throw new DomainException("La categoría {$producto->categoria->id} no existe en el catálogo.");
        }
    }
}
