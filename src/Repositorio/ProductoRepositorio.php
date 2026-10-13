<?php
// src/Repositorio/ProductoRepositorio.php — CRUD de prendas con consultas preparadas y transacciones.
// Regla: los datos del usuario SIEMPRE viajan como parámetros; lo único que se arma con texto son
// fragmentos fijos elegidos de una lista blanca (columnas de orden, condiciones).
declare(strict_types=1);

final class ProductoRepositorio
{
    public const ORDENES = ['nombre' => 'Nombre (A-Z)', 'precio-asc' => 'Precio: menor a mayor', 'precio-desc' => 'Precio: mayor a menor'];
    private const SQL_ORDEN = ['nombre' => 'p.nombre, p.codigo', 'precio-asc' => 'p.precio, p.codigo', 'precio-desc' => 'p.precio DESC, p.codigo'];

    private const SELECT = 'SELECT p.id, p.codigo, p.nombre, p.precio, p.stock, p.color, p.descripcion, p.foto,
            c.id AS c_id, c.nombre AS c_nombre, c.descripcion AS c_descripcion,
            s.id AS s_id, s.ci AS s_ci, s.nombre AS s_nombre, s.telefono AS s_telefono, s.comunidad AS s_comunidad,
            s.especialidad AS s_especialidad, s.fecha_ingreso AS s_fecha_ingreso, s.activo AS s_activo,
            (SELECT GROUP_CONCAT(pt.talla ORDER BY t.orden SEPARATOR \',\')
               FROM producto_tallas pt JOIN tallas t ON t.codigo = pt.talla WHERE pt.producto_id = p.id) AS tallas
        FROM productos p
        JOIN categorias c ON c.id = p.categoria_id
        JOIN socios s ON s.id = p.socio_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscarPorCodigo(string $codigo): ?Producto
    {
        $consulta = $this->pdo->prepare(self::SELECT . ' WHERE p.codigo = :codigo');
        $consulta->execute(['codigo' => $codigo]);
        $fila = $consulta->fetch();
        return $fila === false ? null : self::hidratar($fila);
    }

    public function existeCodigo(string $codigo): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM productos WHERE codigo = :codigo');
        $consulta->execute(['codigo' => $codigo]);
        return (int) $consulta->fetchColumn() > 0;
    }

    /**
     * Búsqueda paginada con filtros opcionales (todos con valor por defecto: se usan con parámetros nombrados).
     * @return Pagina<Producto>
     */
    public function paginar(
        int $pagina = 1,
        int $porPagina = 10,
        string $texto = '',
        ?string $categoria = null,
        ?Talla $talla = null,
        bool $soloDisponibles = false,
        string $orden = 'nombre',
        ?int $socioId = null,
    ): Pagina {
        $condiciones = [];
        $parametros = [];
        $texto = trim($texto);
        if ($texto !== '') {
            $patron = '%' . addcslashes($texto, '%_\\') . '%';     // % y _ del usuario se buscan literalmente
            $condiciones[] = '(p.nombre LIKE :t1 OR p.codigo LIKE :t2 OR p.color LIKE :t3)';
            $parametros += ['t1' => $patron, 't2' => $patron, 't3' => $patron];
        }
        if ($categoria !== null && $categoria !== '') {
            $condiciones[] = 'p.categoria_id = :categoria';
            $parametros['categoria'] = $categoria;
        }
        if ($talla !== null) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM producto_tallas x WHERE x.producto_id = p.id AND x.talla = :talla)';
            $parametros['talla'] = $talla->value;
        }
        if ($soloDisponibles) {
            $condiciones[] = 'p.stock > 0';
        }
        if ($socioId !== null) {
            $condiciones[] = 'p.socio_id = :socio';
            $parametros['socio'] = $socioId;
        }
        $donde = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);
        // ORDER BY no admite parámetros: se elige de una lista blanca y cualquier otro valor usa el orden por nombre
        $ordenSql = self::SQL_ORDEN[$orden] ?? self::SQL_ORDEN['nombre'];

        $contar = $this->pdo->prepare("SELECT COUNT(*) FROM productos p$donde");
        $contar->execute($parametros);
        $total = (int) $contar->fetchColumn();
        $pagina = Pagina::normalizar($pagina, $total, $porPagina);

        $consulta = $this->pdo->prepare(self::SELECT . "$donde ORDER BY $ordenSql LIMIT :limite OFFSET :desde");
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $consulta->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $consulta->bindValue('desde', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $consulta->execute();
        return new Pagina(array_map(self::hidratar(...), $consulta->fetchAll()), $total, $pagina, $porPagina);
    }

    /** Inserta la prenda y sus tallas en una sola transacción; devuelve la prenda con su id. */
    public function crear(Producto $p): Producto
    {
        return $this->enTransaccion(function () use ($p): Producto {
            $consulta = $this->pdo->prepare('INSERT INTO productos (codigo, nombre, categoria_id, socio_id, precio, stock, color, descripcion, foto)
                VALUES (:codigo, :nombre, :categoria, :socio, :precio, :stock, :color, :descripcion, :foto)');
            $consulta->execute(self::parametros($p));
            $id = (int) $this->pdo->lastInsertId();
            $this->guardarTallas($id, $p->tallas);
            return $p->con(id: $id);
        });
    }

    /** Actualiza la prenda (identificada por su código) y reemplaza sus tallas, todo o nada. */
    public function actualizar(Producto $p): void
    {
        $this->enTransaccion(function () use ($p): void {
            $consulta = $this->pdo->prepare('UPDATE productos SET nombre = :nombre, categoria_id = :categoria, socio_id = :socio,
                precio = :precio, stock = :stock, color = :color, descripcion = :descripcion, foto = :foto WHERE codigo = :codigo');
            $consulta->execute(self::parametros($p));
            $id = $this->pdo->prepare('SELECT id FROM productos WHERE codigo = :codigo');
            $id->execute(['codigo' => $p->codigo]);
            $idProducto = (int) $id->fetchColumn();
            $this->pdo->prepare('DELETE FROM producto_tallas WHERE producto_id = :id')->execute(['id' => $idProducto]);
            $this->guardarTallas($idProducto, $p->tallas);
        });
    }

    /** Borra la prenda (sus tallas se borran en cascada) y la devuelve para que quien llama borre su foto. */
    public function eliminar(string $codigo): ?Producto
    {
        $producto = $this->buscarPorCodigo($codigo);
        if ($producto !== null) {
            $this->pdo->prepare('DELETE FROM productos WHERE id = :id')->execute(['id' => $producto->id]);
        }
        return $producto;
    }

    public function contar(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    }

    /** @return array{prendas: int, unidades: int, valor: float} resumen del inventario calculado por MySQL */
    public function resumenInventario(): array
    {
        $fila = $this->pdo->query('SELECT COUNT(*) AS prendas, COALESCE(SUM(stock), 0) AS unidades,
            COALESCE(SUM(precio * stock), 0) AS valor FROM productos')->fetch();
        return ['prendas' => (int) $fila['prendas'], 'unidades' => (int) $fila['unidades'], 'valor' => round((float) $fila['valor'], 2)];
    }

    /** @param list<Talla> $tallas */
    private function guardarTallas(int $productoId, array $tallas): void
    {
        $consulta = $this->pdo->prepare('INSERT INTO producto_tallas (producto_id, talla) VALUES (:producto, :talla)');
        foreach ($tallas as $talla) {
            $consulta->execute(['producto' => $productoId, 'talla' => $talla->value]);
        }
    }

    /**
     * Ejecuta $trabajo dentro de una transacción: si algo falla, se deshace todo (ROLLBACK).
     * @template T
     * @param callable(): T $trabajo
     * @return T
     */
    private function enTransaccion(callable $trabajo): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $trabajo();
            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Ya existe un producto con ese código.');
            }
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private static function parametros(Producto $p): array
    {
        return [
            'codigo' => $p->codigo, 'nombre' => $p->nombre, 'categoria' => $p->categoria->id, 'socio' => $p->socio->id,
            'precio' => number_format($p->precio, 2, '.', ''), 'stock' => $p->stock, 'color' => $p->color,
            'descripcion' => $p->descripcion, 'foto' => $p->foto,
        ];
    }

    /** @param array<string, mixed> $f fila con columnas de productos, categoría (c_) y socio (s_) */
    private static function hidratar(array $f): Producto
    {
        return new Producto(
            codigo: $f['codigo'],
            nombre: $f['nombre'],
            categoria: new Categoria(id: $f['c_id'], nombre: $f['c_nombre'], descripcion: $f['c_descripcion']),
            socio: SocioRepositorio::hidratar($f, 's_'),
            precio: (float) $f['precio'],
            stock: (int) $f['stock'],
            tallas: Talla::lista(explode(',', (string) $f['tallas'])),
            color: $f['color'],
            descripcion: $f['descripcion'],
            foto: $f['foto'],
            id: (int) $f['id'],
        );
    }
}
