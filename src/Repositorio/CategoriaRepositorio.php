<?php
// src/Repositorio/CategoriaRepositorio.php — Lectura de categorías (catálogo fijo administrado por SQL).
declare(strict_types=1);

final class CategoriaRepositorio
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, Categoria> indexadas por id, en orden alfabético */
    public function todas(): array
    {
        $categorias = [];
        foreach ($this->pdo->query('SELECT id, nombre, descripcion FROM categorias ORDER BY nombre') as $fila) {
            $categorias[$fila['id']] = new Categoria(id: $fila['id'], nombre: $fila['nombre'], descripcion: $fila['descripcion']);
        }
        return $categorias;
    }

    /** @return array<string, int> id de categoría => cantidad de productos (LEFT JOIN: incluye las vacías) */
    public function conteoProductos(): array
    {
        $sql = 'SELECT c.id, COUNT(p.id) AS cantidad FROM categorias c LEFT JOIN productos p ON p.categoria_id = c.id GROUP BY c.id';
        return array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
