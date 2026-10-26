<?php
// src/Api/ProductosApi.php — Recurso /api/v1/productos. Devuelve [estado, cuerpo, cabeceras]; el núcleo lo envía.
declare(strict_types=1);

namespace Apicultores\Api;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\NoAutenticado;
use Apicultores\Excepciones\NoEncontrado;
use Apicultores\Excepciones\Prohibido;
use Apicultores\Modelo\Categoria;
use Apicultores\Archivos\GuardaImagen;
use Apicultores\Modelo\Producto;
use Apicultores\Modelo\Usuario;
use Apicultores\Repositorios\ProductoRepositorio;
use Apicultores\Validacion\ValidadorProducto;

final class ProductosApi
{
    public const RUTA = '/api/v1/productos';

    public function __construct(
        private readonly ProductoRepositorio $productos,
        private readonly ValidadorProducto $validador,
        private readonly ?Usuario $usuario,
        private readonly array $cuerpo,          // JSON ya decodificado (POST/PUT)
    ) {
    }

    /** GET /api/v1/productos?pagina=1&por_pagina=10&q=miel&categoria=Miel&orden=precio */
    public function listar(): array
    {
        $porPagina = filter_var($_GET['por_pagina'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $categoriaTexto = (string) ($_GET['categoria'] ?? '');
        $categoria = Categoria::tryFrom($categoriaTexto);
        $orden = (string) ($_GET['orden'] ?? 'nombre');
        $errores = [];
        if ($porPagina === false) {
            $errores['por_pagina'] = 'Debe ser un entero entre 1 y 50.';
        }
        if ($pagina === false) {
            $errores['pagina'] = 'Debe ser un entero mayor o igual a 1.';
        }
        if ($categoriaTexto !== '' && $categoria === null) {
            $errores['categoria'] = 'Valores permitidos: ' . implode(', ', array_map(fn($c) => $c->value, Categoria::cases())) . '.';
        }
        if (!array_key_exists($orden, ProductoRepositorio::ORDENES)) {
            $errores['orden'] = 'Valores permitidos: ' . implode(', ', array_keys(ProductoRepositorio::ORDENES)) . '.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);       // una API NO "corrige" en silencio: avisa (la web sí normalizaba)
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $r = $this->productos->buscar($q, $categoria, $orden, $pagina, $porPagina);
        $enlace = fn(int $n): string => self::RUTA . '?' . http_build_query(array_filter(
            ['q' => $q, 'categoria' => $categoria?->value, 'orden' => $orden === 'nombre' ? null : $orden]) + ['pagina' => $n, 'por_pagina' => $porPagina]);
        return [200, [
            'datos' => array_map(self::representar(...), $r->elementos),
            'meta' => ['total' => $r->total, 'pagina' => $r->numero, 'por_pagina' => $porPagina, 'paginas' => $r->totalPaginas()],
            'enlaces' => array_filter([
                'self' => $enlace($r->numero),
                'anterior' => $r->tieneAnterior() ? $enlace($r->numero - 1) : null,
                'siguiente' => $r->tieneSiguiente() ? $enlace($r->numero + 1) : null,
            ]),
        ], []];
    }

    /** GET /api/v1/productos/sugerencias?q=mi — hasta 8 coincidencias para autocompletar (respuesta mínima y rápida). */
    public function sugerencias(): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 40) {
            throw new DatosInvalidos(['q' => 'Escribe entre 2 y 40 caracteres.']);
        }
        $r = $this->productos->buscar($q, null, 'nombre', 1, 8);
        return [200, array_map(fn(Producto $p): array => ['codigo' => $p->codigo, 'nombre' => $p->nombre, 'precio' => $p->precio],
            $r->elementos), ['Cache-Control' => 'public, max-age=60']];
    }

    /** GET /api/v1/productos/{codigo} — con ETag: si el cliente ya tiene esta versión, 304 sin cuerpo. */
    public function ver(string $codigo): array
    {
        $datos = self::representar($this->buscar($codigo));
        $etag = '"' . substr(hash('sha256', json_encode($datos)), 0, 16) . '"';
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            return [304, null, ['ETag' => $etag]];
        }
        return [200, $datos, ['ETag' => $etag, 'Cache-Control' => 'private, max-age=0, must-revalidate']];
    }

    /** POST /api/v1/productos → 201 Created + Location */
    public function crear(): array
    {
        $this->autorizar('productos.crear');
        $datos = $this->validador->validar(self::comoTexto($this->cuerpo), nuevo: true);
        $this->productos->guardar($datos);
        $creado = self::representar($this->buscar($datos['codigo']));
        return [201, $creado, ['Location' => self::RUTA . '/' . rawurlencode($datos['codigo'])]];
    }

    /** PUT /api/v1/productos/{codigo} → reemplaza el recurso completo (el código de la URL manda). */
    public function reemplazar(string $codigo): array
    {
        $this->autorizar('productos.editar');
        $actual = $this->buscar($codigo);
        $datos = $this->validador->validar(['codigo' => $actual->codigo] + self::comoTexto($this->cuerpo), nuevo: false);
        $this->productos->guardar($datos);
        return [200, self::representar($this->buscar($codigo)), []];
    }

    /** DELETE /api/v1/productos/{codigo} → 204 No Content */
    public function eliminar(string $codigo): array
    {
        $this->autorizar('productos.eliminar');
        $this->productos->eliminar($this->buscar($codigo)->codigo);
        return [204, null, []];
    }

    private function buscar(string $codigo): Producto
    {
        return $this->productos->porCodigo(strtoupper($codigo)) ?? throw new NoEncontrado("No existe el producto $codigo.");
    }

    private function autorizar(string $accion): void
    {
        if ($this->usuario === null) {
            throw new NoAutenticado('Esta operación requiere un token: "Authorization: Bearer api_…".');
        }
        if (!$this->usuario->puede($accion)) {
            throw new Prohibido('El rol ' . $this->usuario->rol->etiqueta() . ' no permite esta operación.');
        }
    }

    /** El validador del lab 8 recibe textos (como $_POST); los valores JSON se convierten respetando su tipo. */
    private static function comoTexto(array $cuerpo): array
    {
        return array_map(fn($v) => is_scalar($v) ? (is_bool($v) ? '' : (string) $v) : '', $cuerpo);
    }

    /** Representación pública del recurso (no se expone el id interno de MySQL). */
    public static function representar(Producto $p): array
    {
        return [
            'codigo' => $p->codigo, 'nombre' => $p->nombre, 'categoria' => $p->categoria->value,
            'precio' => $p->precio, 'stock' => $p->stock, 'disponible' => $p->estaDisponible(),
            'imagen' => GuardaImagen::url($p->imagen),
            'socio' => ['id' => $p->socio->id, 'nombre' => $p->socio->nombre],
            'enlaces' => ['self' => self::RUTA . '/' . rawurlencode($p->codigo)],
        ];
    }
}
