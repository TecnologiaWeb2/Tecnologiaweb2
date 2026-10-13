<?php
// src/Repositorio/SocioRepositorio.php — CRUD de socios con consultas preparadas.
// Regla: los datos del usuario SIEMPRE viajan como parámetros (:nombre), nunca concatenados al SQL.
declare(strict_types=1);

final class SocioRepositorio
{
    private const COLUMNAS = 's.id, s.ci, s.nombre, s.telefono, s.comunidad, s.especialidad, s.fecha_ingreso, s.activo';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscarPorId(int $id): ?Socio
    {
        $consulta = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ' FROM socios s WHERE s.id = :id');
        $consulta->execute(['id' => $id]);
        $fila = $consulta->fetch();
        return $fila === false ? null : self::hidratar($fila);
    }

    public function existeCi(string $ci, ?int $excepto = null): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM socios WHERE ci = :ci AND id <> :excepto');
        $consulta->execute(['ci' => $ci, 'excepto' => $excepto ?? 0]);
        return (int) $consulta->fetchColumn() > 0;
    }

    /** @return array<int, Socio> socios activos indexados por id (para el formulario de productos) */
    public function activos(): array
    {
        $socios = [];
        foreach ($this->pdo->query('SELECT ' . self::COLUMNAS . ' FROM socios s WHERE s.activo = 1 ORDER BY s.nombre') as $fila) {
            $socios[(int) $fila['id']] = self::hidratar($fila);
        }
        return $socios;
    }

    /**
     * Listado paginado con búsqueda por nombre, CI o comunidad y la cantidad de prendas de cada socio.
     * @return Pagina<array{socio: Socio, productos: int, unidades: int}>
     */
    public function paginar(int $pagina = 1, int $porPagina = 10, string $texto = '', ?bool $activos = null): Pagina
    {
        $condiciones = [];
        $parametros = [];
        $texto = trim($texto);
        if ($texto !== '') {
            $patron = '%' . addcslashes($texto, '%_\\') . '%';     // % y _ del usuario se buscan literalmente
            $condiciones[] = '(s.nombre LIKE :t1 OR s.ci LIKE :t2 OR s.comunidad LIKE :t3)';
            $parametros += ['t1' => $patron, 't2' => $patron, 't3' => $patron];
        }
        if ($activos !== null) {
            $condiciones[] = 's.activo = :activo';
            $parametros['activo'] = (int) $activos;
        }
        $donde = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);

        $contar = $this->pdo->prepare("SELECT COUNT(*) FROM socios s$donde");
        $contar->execute($parametros);
        $total = (int) $contar->fetchColumn();
        $pagina = Pagina::normalizar($pagina, $total, $porPagina);

        $consulta = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ', COUNT(p.id) AS productos, COALESCE(SUM(p.stock), 0) AS unidades
            FROM socios s LEFT JOIN productos p ON p.socio_id = s.id' . $donde . '
            GROUP BY s.id ORDER BY s.activo DESC, s.nombre LIMIT :limite OFFSET :desde');
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $consulta->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $consulta->bindValue('desde', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $consulta->execute();
        $elementos = array_map(fn(array $f): array => [
            'socio' => self::hidratar($f), 'productos' => (int) $f['productos'], 'unidades' => (int) $f['unidades'],
        ], $consulta->fetchAll());
        return new Pagina($elementos, $total, $pagina, $porPagina);
    }

    /** Inserta y devuelve el socio con su id nuevo. */
    public function crear(Socio $socio): Socio
    {
        $consulta = $this->pdo->prepare('INSERT INTO socios (ci, nombre, telefono, comunidad, especialidad, fecha_ingreso, activo)
            VALUES (:ci, :nombre, :telefono, :comunidad, :especialidad, :fecha, :activo)');
        $this->ejecutar($consulta, self::parametros($socio));
        return $socio->conId((int) $this->pdo->lastInsertId());
    }

    public function actualizar(Socio $socio): void
    {
        $consulta = $this->pdo->prepare('UPDATE socios SET ci = :ci, nombre = :nombre, telefono = :telefono, comunidad = :comunidad,
            especialidad = :especialidad, fecha_ingreso = :fecha, activo = :activo WHERE id = :id');
        $this->ejecutar($consulta, self::parametros($socio) + ['id' => $socio->id]);
    }

    /** Borra un socio. Si tiene prendas, la clave foránea (ON DELETE RESTRICT) lo impide y se informa. */
    public function eliminar(int $id): bool
    {
        try {
            $consulta = $this->pdo->prepare('DELETE FROM socios WHERE id = :id');
            $consulta->execute(['id' => $id]);
            return $consulta->rowCount() === 1;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1451) {          // ER_ROW_IS_REFERENCED: tiene productos
                throw new DomainException('No se puede eliminar: tiene prendas registradas. Puedes marcarlo como inactivo.');
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $fila */
    public static function hidratar(array $fila, string $prefijo = ''): Socio
    {
        return new Socio(
            ci: $fila[$prefijo . 'ci'],
            nombre: $fila[$prefijo . 'nombre'],
            telefono: $fila[$prefijo . 'telefono'],
            comunidad: $fila[$prefijo . 'comunidad'],
            especialidad: $fila[$prefijo . 'especialidad'],
            fechaIngreso: $fila[$prefijo . 'fecha_ingreso'],
            activo: (bool) $fila[$prefijo . 'activo'],
            id: (int) $fila[$prefijo . 'id'],
        );
    }

    /** @return array<string, mixed> */
    private static function parametros(Socio $s): array
    {
        return [
            'ci' => $s->ci, 'nombre' => $s->nombre, 'telefono' => $s->telefono, 'comunidad' => $s->comunidad,
            'especialidad' => $s->especialidad, 'fecha' => $s->fechaIngreso, 'activo' => (int) $s->activo,
        ];
    }

    private function ejecutar(PDOStatement $consulta, array $parametros): void
    {
        try {
            $consulta->execute($parametros);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {          // ER_DUP_ENTRY: el UNIQUE de la base es la última defensa
                throw new DomainException("Ya existe un socio con el CI {$parametros['ci']}.");
            }
            throw $e;
        }
    }
}
