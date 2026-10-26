<?php
// src/Api.php — Actividad 4 (Laboratorio 13): API REST /api/v1. Contrato completo en docs/openapi.yaml.
//   · Siempre responde JSON; los errores, en application/problem+json (RFC 9457): type, title, status, detail.
//   · Lectura pública del catálogo; escribir exige "Authorization: Bearer mdo_…" (token con hash SHA-256 en la base).
//   · Sin cookies: por eso la API no usa token CSRF (un sitio ajeno no puede adjuntar el Bearer).
//   · Una API NO corrige en silencio un parámetro inválido: responde 422 y explica qué campo falló.
declare(strict_types=1);

/** Error HTTP de la API. $errores: detalle por campo (422); $cabeceras: Allow, WWW-Authenticate… */
final class ErrorApi extends RuntimeException
{
    public const TITULOS = [400 => 'Petición mal formada', 401 => 'No autenticado', 403 => 'Prohibido', 404 => 'No encontrado',
        405 => 'Método no permitido', 409 => 'Conflicto', 415 => 'Tipo de contenido no soportado', 422 => 'Datos inválidos',
        500 => 'Error interno', 503 => 'Servicio no disponible'];

    /** @param array<string, string> $errores @param array<string, string> $cabeceras */
    public function __construct(public readonly int $estado, string $detalle, public readonly array $errores = [], public readonly array $cabeceras = [])
    {
        parent::__construct($detalle);
    }
}

final class Api
{
    public const BASE = '/api/v1';
    private const TIPOS = 'https://github.com/TecnologiaWeb2/Tecnologiaweb2/blob/main/docs/actividad-04/endpoints.md#';

    private ?UsuarioSesion $usuario = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Atiende la petición completa y envía la respuesta. */
    public function atender(string $metodo, string $uri): void
    {
        $ruta = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        header('Access-Control-Allow-Origin: *');          // cualquier origen puede LEER; escribir exige token
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-None-Match');
        header('Access-Control-Expose-Headers: Location, ETag');
        header('Access-Control-Max-Age: 600');
        header('Cache-Control: no-store');
        if ($metodo === 'OPTIONS') {                       // "preflight" del navegador antes de un POST/PUT/DELETE
            http_response_code(204);
            return;
        }
        try {
            $this->usuario = $this->autenticar();
            [$estado, $datos, $cabeceras] = $this->despachar($metodo, $ruta);
            $this->enviar($estado, $datos, $cabeceras, 'application/json');
        } catch (ErrorApi $e) {
            $this->problema($e->estado, $e->getMessage(), $ruta, $e->errores, $e->cabeceras);
        } catch (JsonException $e) {
            $this->problema(400, 'El cuerpo no es JSON válido: ' . $e->getMessage() . '.', $ruta);
        } catch (Throwable $e) {
            $referencia = bin2hex(random_bytes(4));
            error_log("API ref=$referencia " . $e);
            $this->problema($e instanceof PDOException ? 503 : 500, "Ocurrió un error inesperado. Referencia: $referencia.", $ruta);
        }
    }

    /** Las rutas se prueban en ORDEN: /productos/sugerencias va ANTES de /productos/{codigo}. */
    private function despachar(string $metodo, string $ruta): array
    {
        $rutas = [
            ['GET', '#^/api/v1/productos$#', $this->listar(...)],
            ['POST', '#^/api/v1/productos$#', $this->crear(...)],
            ['GET', '#^/api/v1/productos/sugerencias$#', $this->sugerencias(...)],
            ['GET', '#^/api/v1/productos/([A-Za-z0-9-]{1,12})$#', $this->ver(...)],
            ['PUT', '#^/api/v1/productos/([A-Za-z0-9-]{1,12})$#', $this->reemplazar(...)],
            ['DELETE', '#^/api/v1/productos/([A-Za-z0-9-]{1,12})$#', $this->eliminar(...)],
            ['GET', '#^/api/v1/socios/(\d{1,9})/liquidacion$#', $this->liquidacion(...)],
            ['GET', '#^/api/v1/pedidos$#', $this->listarPedidos(...)],
            ['POST', '#^/api/v1/pedidos$#', $this->crearPedido(...)],
            ['GET', '#^/api/v1/pedidos/(\d{1,9})$#', $this->verPedido(...)],
            ['GET', '#^/api/v1/openapi\.yaml$#', $this->openapi(...)],
        ];
        $permitidos = [];
        foreach ($rutas as [$verbo, $patron, $accion]) {
            if (preg_match($patron, $ruta, $m) === 1) {
                if ($verbo === $metodo || ($verbo === 'GET' && $metodo === 'HEAD')) {
                    return $accion(...array_slice($m, 1));
                }
                $permitidos[] = $verbo;
            }
        }
        if ($permitidos !== []) {
            $permitidos[] = 'OPTIONS';
            throw new ErrorApi(405, "El método $metodo no está permitido en $ruta.", cabeceras: ['Allow' => implode(', ', array_unique($permitidos))]);
        }
        throw new ErrorApi(404, "No existe el recurso $ruta.");
    }

    // ---------- Autenticación y autorización ----------

    private function autenticar(): ?UsuarioSesion
    {
        // Algunos hostings (Apache con CGI) no pasan Authorization a PHP: .htaccess la copia a HTTP_AUTHORIZATION
        $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($cabecera === '' && function_exists('getallheaders')) {
            $cabecera = (string) (array_change_key_case(getallheaders())['authorization'] ?? '');
        }
        if ($cabecera === '') {
            return null;                                   // lectura pública
        }
        $invalido = ['WWW-Authenticate' => 'Bearer realm="manos-del-oriente", error="invalid_token"'];
        if (preg_match('/^Bearer\s+(\S+)$/', $cabecera, $m) !== 1) {
            throw new ErrorApi(401, 'Formato esperado: "Authorization: Bearer mdo_…".', cabeceras: $invalido);
        }
        return usuario_por_token($this->pdo, $m[1])
            ?? throw new ErrorApi(401, 'El token no es válido, venció o fue revocado.', cabeceras: $invalido);
    }

    private function autorizar(string $permiso): UsuarioSesion
    {
        if ($this->usuario === null) {
            throw new ErrorApi(401, 'Esta operación requiere un token: "Authorization: Bearer mdo_…".',
                cabeceras: ['WWW-Authenticate' => 'Bearer realm="manos-del-oriente"']);
        }
        if (!$this->usuario->puede($permiso)) {
            throw new ErrorApi(403, 'El rol ' . $this->usuario->etiqueta() . ' no permite esta operación.');
        }
        return $this->usuario;
    }

    /** El cuerpo debe declararse como JSON (415) y ser un OBJETO JSON (400 si no se puede leer). */
    private function cuerpo(): array
    {
        $tipo = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''))[0]));
        if ($tipo !== 'application/json') {
            throw new ErrorApi(415, 'Envía el cuerpo con "Content-Type: application/json".');
        }
        $datos = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($datos) || array_is_list($datos)) {
            throw new ErrorApi(400, 'Se esperaba un objeto JSON: {"codigo": …, "nombre": …}.');
        }
        return $datos;
    }

    // ---------- /productos ----------

    /** GET /api/v1/productos?pagina=1&por_pagina=10&q=jean&categoria=poleras&talla=M&disponibles=1&orden=precio-asc */
    private function listar(): array
    {
        $errores = [];
        $pagina = filter_var($_GET['pagina'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $porPagina = filter_var($_GET['por_pagina'] ?? '10', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $q = is_string($_GET['q'] ?? '') ? trim((string) ($_GET['q'] ?? '')) : null;
        $categoria = is_string($_GET['categoria'] ?? '') ? (string) ($_GET['categoria'] ?? '') : null;
        $tallaTexto = is_string($_GET['talla'] ?? '') ? (string) ($_GET['talla'] ?? '') : null;
        $orden = is_string($_GET['orden'] ?? 'nombre') ? (string) ($_GET['orden'] ?? 'nombre') : null;
        $disponibles = $_GET['disponibles'] ?? '0';
        $categorias = (new CategoriaRepositorio($this->pdo))->todas();
        if ($pagina === false) {
            $errores['pagina'] = 'Debe ser un entero mayor o igual a 1.';
        }
        if ($porPagina === false) {
            $errores['por_pagina'] = 'Debe ser un entero entre 1 y 50.';
        }
        if ($q === null || mb_strlen($q) > 60) {
            $errores['q'] = 'Texto de hasta 60 caracteres.';
        }
        if ($categoria === null || ($categoria !== '' && !isset($categorias[$categoria]))) {
            $errores['categoria'] = 'Valores permitidos: ' . implode(', ', array_keys($categorias)) . '.';
        }
        $talla = $tallaTexto === null ? null : Talla::tryFrom($tallaTexto);
        if ($tallaTexto === null || ($tallaTexto !== '' && $talla === null)) {
            $errores['talla'] = 'Valores permitidos: ' . implode(', ', array_map(fn(Talla $t): string => $t->value, Talla::cases())) . '.';
        }
        if ($orden === null || !array_key_exists($orden, ProductoRepositorio::ORDENES)) {
            $errores['orden'] = 'Valores permitidos: ' . implode(', ', array_keys(ProductoRepositorio::ORDENES)) . '.';
        }
        if (!in_array($disponibles, ['0', '1'], true)) {
            $errores['disponibles'] = 'Usa 1 (solo con stock) o 0.';
        }
        if ($errores !== []) {
            throw new ErrorApi(422, 'Revisa los parámetros indicados en "errores".', $errores);
        }
        $resultado = (new ProductoRepositorio($this->pdo))->paginar(pagina: $pagina, porPagina: $porPagina, texto: $q,
            categoria: $categoria, talla: $talla, soloDisponibles: $disponibles === '1', orden: $orden);
        if ($pagina > $resultado->paginas()) {
            throw new ErrorApi(422, 'Revisa los parámetros indicados en "errores".', ['pagina' => "Solo hay {$resultado->paginas()} páginas."]);
        }
        $filtros = array_filter(['q' => $q, 'categoria' => $categoria, 'talla' => $talla?->value,
            'disponibles' => $disponibles === '1' ? '1' : null, 'orden' => $orden === 'nombre' ? null : $orden]);
        $enlace = fn(int $n): string => self::BASE . '/productos?' . http_build_query($filtros + ['pagina' => $n, 'por_pagina' => $porPagina]);
        return [200, [
            'datos' => array_map($this->representar(...), $resultado->elementos),
            'meta' => ['total' => $resultado->total, 'pagina' => $resultado->pagina, 'por_pagina' => $porPagina, 'paginas' => $resultado->paginas()],
            'enlaces' => array_filter([
                'self' => $enlace($resultado->pagina),
                'primera' => $enlace(1),
                'anterior' => $resultado->hayAnterior() ? $enlace($resultado->pagina - 1) : null,
                'siguiente' => $resultado->haySiguiente() ? $enlace($resultado->pagina + 1) : null,
                'ultima' => $enlace($resultado->paginas()),
            ]),
        ], []];
    }

    /** GET /api/v1/productos/sugerencias?q=ja — hasta 8 coincidencias para el autocompletar. */
    private function sugerencias(): array
    {
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        if (mb_strlen($q) < 2 || mb_strlen($q) > 40) {
            throw new ErrorApi(422, 'Revisa los parámetros indicados en "errores".', ['q' => 'Escribe entre 2 y 40 caracteres.']);
        }
        $resultado = (new ProductoRepositorio($this->pdo))->paginar(porPagina: 8, texto: $q);
        return [200, ['datos' => array_map(fn(Producto $p): array => ['codigo' => $p->codigo, 'nombre' => $p->nombre,
            'precio' => number_format($p->precio, 2, '.', '')], $resultado->elementos)], ['Cache-Control' => 'public, max-age=60']];
    }

    /** GET /api/v1/productos/{codigo} — con ETag: si el cliente ya tiene esta versión, 304 sin cuerpo. */
    private function ver(string $codigo): array
    {
        $datos = $this->representar($this->buscar($codigo));
        $etag = '"' . substr(hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR)), 0, 20) . '"';
        $cabeceras = ['ETag' => $etag, 'Cache-Control' => 'private, max-age=0, must-revalidate'];
        $recibidos = array_map('trim', explode(',', (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')));
        if (in_array($etag, $recibidos, true)) {
            return [304, null, $cabeceras];
        }
        return [200, $datos, $cabeceras];
    }

    /** POST /api/v1/productos → 201 Created + Location */
    private function crear(): array
    {
        $this->autorizar('productos.crear');
        $producto = $this->validar($this->cuerpo(), null);
        $repositorio = new ProductoRepositorio($this->pdo);
        try {
            $repositorio->crear($producto);
        } catch (DomainException $e) {
            throw new ErrorApi(409, $e->getMessage());
        }
        $ruta = self::BASE . '/productos/' . rawurlencode($producto->codigo);
        return [201, $this->representar($this->buscar($producto->codigo)), ['Location' => $ruta]];
    }

    /** PUT /api/v1/productos/{codigo} → reemplaza la prenda completa (el código de la URL manda). */
    private function reemplazar(string $codigo): array
    {
        $this->autorizar('productos.editar');
        $actual = $this->buscar($codigo);
        $producto = $this->validar($this->cuerpo(), $actual)->con(foto: $actual->foto);
        if ($producto->stock < $actual->reservado) {
            throw new ErrorApi(422, 'Revisa los campos indicados en "errores".', ['stock' => "Hay {$actual->reservado} unidades reservadas por pedidos: el stock no puede ser menor."]);
        }
        (new ProductoRepositorio($this->pdo))->actualizar($producto);
        return [200, $this->representar($this->buscar($actual->codigo)), []];
    }

    /** DELETE /api/v1/productos/{codigo} → 204 sin cuerpo (409 si tiene ventas, acopios o pedidos). */
    private function eliminar(string $codigo): array
    {
        $this->autorizar('productos.eliminar');
        $producto = $this->buscar($codigo);
        try {
            (new ProductoRepositorio($this->pdo))->eliminar($producto->codigo);
        } catch (DomainException $e) {
            throw new ErrorApi(409, $e->getMessage());
        }
        borrar_foto($producto->foto);
        return [204, null, []];
    }

    /** Reutiliza la MISMA validación que el formulario web (src/validacion.php). */
    private function validar(array $cuerpo, ?Producto $actual): Producto
    {
        $texto = static fn(mixed $v): string => is_string($v) || is_int($v) || is_float($v) ? (string) $v : '';
        $entrada = [
            'codigo' => $texto($cuerpo['codigo'] ?? ''), 'nombre' => $texto($cuerpo['nombre'] ?? ''),
            'categoria' => $texto($cuerpo['categoria'] ?? ''), 'socio' => $texto($cuerpo['socio_id'] ?? ''),
            'precio' => $texto($cuerpo['precio'] ?? ''), 'stock' => $texto($cuerpo['stock'] ?? '0'),
            'tallas' => is_array($cuerpo['tallas'] ?? null) ? array_map($texto, $cuerpo['tallas']) : ['U'],
            'color' => $texto($cuerpo['color'] ?? ''), 'descripcion' => $texto($cuerpo['descripcion'] ?? ''),
        ];
        $socios = (new SocioRepositorio($this->pdo))->activos() + ($actual === null ? [] : [$actual->socio->id => $actual->socio]);
        $resultado = validar_producto($entrada, new ProductoRepositorio($this->pdo), (new CategoriaRepositorio($this->pdo))->todas(),
            $socios, codigoFijo: $actual?->codigo);
        if ($resultado['errores'] !== []) {
            $errores = $resultado['errores'];
            if (isset($errores['socio'])) {
                $errores['socio_id'] = $errores['socio'];
                unset($errores['socio']);
            }
            if (($errores['codigo'] ?? '') !== '' && str_starts_with($errores['codigo'], 'Ya existe')) {
                throw new ErrorApi(409, $errores['codigo']);
            }
            throw new ErrorApi(422, 'Revisa los campos indicados en "errores".', $errores);
        }
        return $resultado['producto'];
    }

    private function buscar(string $codigo): Producto
    {
        return (new ProductoRepositorio($this->pdo))->buscarPorCodigo(strtoupper($codigo))
            ?? throw new ErrorApi(404, 'No existe la prenda ' . strtoupper($codigo) . '.');
    }

    /** Representación pública: precios como texto con 2 decimales y sin el id interno de MySQL. */
    private function representar(Producto $p): array
    {
        return [
            'codigo' => $p->codigo, 'nombre' => $p->nombre,
            'categoria' => ['id' => $p->categoria->id, 'nombre' => $p->categoria->nombre],
            'precio' => number_format($p->precio, 2, '.', ''), 'stock' => $p->stock, 'reservado' => $p->reservado,
            'disponible' => $p->libre(), 'tallas' => array_map(fn(Talla $t): string => $t->value, $p->tallas),
            'color' => $p->color, 'descripcion' => $p->descripcion,
            'foto' => $p->foto === null ? null : '/foto.php?f=' . rawurlencode($p->foto),
            'socio' => ['id' => $p->socio->id, 'nombre' => $p->socio->nombre, 'comunidad' => $p->socio->comunidad],
            'enlaces' => ['self' => self::BASE . '/productos/' . rawurlencode($p->codigo), 'web' => '/producto.php?codigo=' . rawurlencode($p->codigo)],
        ];
    }

    // ---------- Módulos del trabajo final ----------

    /** GET /api/v1/socios/{id}/liquidacion — la socia solo puede pedir la SUYA; la directiva, cualquiera. */
    private function liquidacion(string $id): array
    {
        $usuario = $this->autorizar('liquidaciones.ver');
        $socioId = (int) $id;
        if ($usuario->rol === 'socio' && $usuario->socioId !== $socioId) {
            throw new ErrorApi(403, 'Solo puedes consultar tu propia liquidación.');
        }
        $filas = liquidaciones($this->pdo, $socioId);
        if ($filas === []) {
            throw new ErrorApi(404, "No existe la socia o socio $socioId.");
        }
        $f = $filas[0];
        $detalle = detalle_liquidacion($this->pdo, $socioId);
        return [200, [
            'socio' => ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'comunidad' => $f['comunidad']],
            'entregadas' => (int) $f['entregadas'], 'aprobadas' => (int) $f['aprobadas'],
            'liquidado' => number_format((float) $f['liquidado'], 2, '.', ''), 'pagado' => number_format((float) $f['pagado'], 2, '.', ''),
            'saldo' => $f['saldo'],
            'acopios' => array_map(fn(array $a): array => ['fecha' => (new DateTimeImmutable($a['fecha']))->format(DATE_ATOM), 'codigo' => $a['codigo'],
                'entregadas' => (int) $a['entregadas'], 'aprobadas' => (int) $a['aprobadas'], 'precio_unitario' => $a['precio_unitario'],
                'liquidado' => number_format((float) $a['liquidado'], 2, '.', ''), 'observacion' => $a['observacion']], $detalle['acopios']),
            'pagos' => array_map(fn(array $p): array => ['fecha' => (new DateTimeImmutable($p['fecha']))->format(DATE_ATOM),
                'importe' => $p['importe'], 'referencia' => $p['referencia']], $detalle['pagos']),
        ], []];
    }

    /** GET /api/v1/pedidos?estado=pendiente */
    private function listarPedidos(): array
    {
        $this->autorizar('pedidos.gestionar');
        $estado = is_string($_GET['estado'] ?? '') ? (string) ($_GET['estado'] ?? '') : '-';
        if ($estado !== '' && !isset(ESTADOS_PEDIDO[$estado])) {
            throw new ErrorApi(422, 'Revisa los parámetros indicados en "errores".', ['estado' => 'Valores permitidos: ' . implode(', ', array_keys(ESTADOS_PEDIDO)) . '.']);
        }
        return [200, ['datos' => array_map($this->representarPedido(...), listar_pedidos($this->pdo, $estado))], []];
    }

    /** POST /api/v1/pedidos {"numero": "…", "cliente": "…", "fecha": "AAAA-MM-DD", "lineas": [{"codigo": …, "cantidad": …}]} → 201 */
    private function crearPedido(): array
    {
        $usuario = $this->autorizar('pedidos.gestionar');
        $cuerpo = $this->cuerpo();
        try {
            $pedido = leer_pedido_json(json_encode($cuerpo, JSON_THROW_ON_ERROR));
            $id = crear_pedido($this->pdo, $pedido['cliente'], $pedido['lineas'], $usuario->id, 'api', $pedido['numero']);
        } catch (InvalidArgumentException $e) {
            throw new ErrorApi(str_starts_with($e->getMessage(), 'Ya se registró') ? 409 : 422, $e->getMessage());
        }
        return [201, $this->representarPedido(buscar_pedido($this->pdo, $id)), ['Location' => self::BASE . "/pedidos/$id"]];
    }

    private function verPedido(string $id): array
    {
        $this->autorizar('pedidos.gestionar');
        $pedido = buscar_pedido($this->pdo, (int) $id) ?? throw new ErrorApi(404, "No existe el pedido $id.");
        return [200, $this->representarPedido($pedido), []];
    }

    private function representarPedido(array $p): array
    {
        $datos = ['id' => (int) $p['id'], 'numero' => $p['numero'], 'cliente' => $p['cliente'], 'origen' => $p['origen'],
            'estado' => $p['estado'], 'total' => $p['total'], 'creado' => (new DateTimeImmutable($p['creado_en']))->format(DATE_ATOM),
            'venta_id' => $p['venta_id'] === null ? null : (int) $p['venta_id']];
        if (isset($p['lineas'])) {
            $datos['lineas'] = array_map(fn(array $l): array => ['codigo' => $l['codigo'], 'nombre' => $l['nombre'],
                'cantidad' => (int) $l['cantidad'], 'precio_unitario' => $l['precio_unitario']], $p['lineas']);
        } else {
            $datos['detalle'] = $p['detalle'];
        }
        $datos['enlaces'] = ['self' => self::BASE . '/pedidos/' . (int) $p['id']];
        return $datos;
    }

    /** GET /api/v1/openapi.yaml — el contrato de la API (docs/openapi.yaml). */
    private function openapi(): never
    {
        header('Content-Type: application/yaml; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        readfile(__DIR__ . '/../docs/openapi.yaml');
        exit;
    }

    // ---------- Respuesta ----------

    private function enviar(int $estado, ?array $datos, array $cabeceras, string $tipo): void
    {
        http_response_code($estado);
        foreach ($cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        if ($estado === 204 || $estado === 304) {
            header('Content-Type:');                   // sin cuerpo: PHP no debe anunciar text/html
            header_remove('Content-Type');
        }
        if ($datos !== null && $estado !== 204 && $estado !== 304) {
            header("Content-Type: $tipo; charset=UTF-8");
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
                echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }
        }
    }

    private function problema(int $estado, string $detalle, string $instancia, array $errores = [], array $cabeceras = []): void
    {
        $titulo = ErrorApi::TITULOS[$estado] ?? 'Error';
        $tipo = strtolower(str_replace(' ', '-', strtr($titulo, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u'])));
        $cuerpo = ['type' => self::TIPOS . $tipo, 'title' => $titulo, 'status' => $estado, 'detail' => $detalle, 'instance' => $instancia];
        if ($errores !== []) {
            $cuerpo['errores'] = $errores;                 // extensión permitida por el RFC 9457
        }
        $this->enviar($estado, $cuerpo, $cabeceras, 'application/problem+json');
    }
}
