<?php
// src/Seguridad.php — Actividad 3 (Laboratorio 10): sesión segura, usuarios, roles, permisos, CSRF y tokens de la API.
// Una sola sesión para todo el sitio (antes había dos con nombres distintos y el usuario «se perdía» entre páginas).
declare(strict_types=1);

/** Matriz de permisos: qué roles pueden hacer cada acción. Lo que no está aquí, no lo puede hacer nadie. */
const PERMISOS = [
    'productos.ver' => ['admin', 'operador'],
    'productos.crear' => ['admin', 'operador'],
    'productos.editar' => ['admin', 'operador'],
    'productos.eliminar' => ['admin'],
    'socios.ver' => ['admin', 'operador'],
    'socios.editar' => ['admin', 'operador'],
    'socios.eliminar' => ['admin'],
    'ventas.registrar' => ['admin', 'operador'],
    'acopios.registrar' => ['admin', 'operador'],
    'reportes.ver' => ['admin', 'operador'],
    'pedidos.gestionar' => ['admin', 'operador'],
    'liquidaciones.ver' => ['admin', 'operador', 'socio'],   // el socio solo ve la SUYA (filtro en la consulta)
    'liquidaciones.pagar' => ['admin', 'operador'],
    'usuarios.gestionar' => ['admin'],
    'tokens.gestionar' => ['admin'],
];

const ROLES = ['admin' => 'Administración', 'operador' => 'Operador', 'socio' => 'Socia o socio'];
const MINUTOS_INACTIVIDAD = 30;
const INTENTOS_MAXIMOS = 5;

final readonly class UsuarioSesion
{
    public function __construct(
        public int $id,
        public string $correo,
        public string $nombre,
        public string $rol,
        public ?int $socioId = null,
    ) {
    }

    public function puede(string $permiso): bool
    {
        return in_array($this->rol, PERMISOS[$permiso] ?? [], true);
    }

    public function etiqueta(): string
    {
        return ROLES[$this->rol] ?? $this->rol;
    }

    /** @param array<string, mixed> $fila fila de la tabla usuarios */
    public static function desdeFila(array $fila): self
    {
        return new self((int) $fila['id'], (string) $fila['correo'], (string) $fila['nombre'], (string) $fila['rol'],
            $fila['socio_id'] === null ? null : (int) $fila['socio_id']);
    }
}

// ---------- Sesión ----------

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Inicia la sesión con una cookie HttpOnly + SameSite (+ Secure en HTTPS). Cierra la sesión tras 30 min sin uso. */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    session_name('MANOS_SESION');
    session_start([
        'cookie_httponly' => true,          // JavaScript no puede leer la cookie (un XSS no la roba)
        'cookie_samesite' => 'Lax',         // otro sitio no puede enviar formularios con nuestra cookie
        'cookie_secure' => es_https(),
        'use_strict_mode' => true,          // no acepta identificadores de sesión inventados (fijación de sesión)
        'use_only_cookies' => true,
    ]);
    $ahora = time();
    if (isset($_SESSION['actividad']) && $ahora - (int) $_SESSION['actividad'] > MINUTOS_INACTIVIDAD * 60) {
        unset($_SESSION['usuario']);
        $_SESSION['expirada'] = true;
    }
    $_SESSION['actividad'] = $ahora;
}

function usuario_actual(): ?UsuarioSesion
{
    iniciar_sesion();
    $u = $_SESSION['usuario'] ?? null;
    return is_array($u) ? UsuarioSesion::desdeFila($u) : null;
}

/**
 * Exige una sesión iniciada (y, si se indica, un permiso). Sin sesión: redirige a /entrar.php (o 401 si la pidió un
 * modal o fetch). Sin permiso: 403. Así cada página protegida se defiende sola, aunque el menú no muestre el enlace.
 */
function exigir_usuario(string $permiso = ''): UsuarioSesion
{
    $u = usuario_actual();
    if ($u === null) {
        if (es_parcial() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            http_response_code(401);
            exit('Tu sesión terminó. Vuelve a iniciar sesión.');
        }
        redirigir('/entrar.php?volver=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/admin.php')));
    }
    if ($permiso !== '' && !$u->puede($permiso)) {
        responder_error(403, 'Sin permiso', 'Tu rol (' . $u->etiqueta() . ') no permite esta acción.');
    }
    return $u;
}

/** Página de error con el diseño del sitio (403, 404, 405…) y fin de la petición. */
function responder_error(int $estado, string $titulo, string $mensaje): never
{
    http_response_code($estado);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    require __DIR__ . '/../plantillas/cabecera.php';
    echo '<div class="text-center py-5"><i class="bi bi-shield-lock fs-1 text-secondary"></i><h1 class="h4 mt-2">' . e($titulo)
        . '</h1><p class="text-secondary">' . e($mensaje) . '</p><a class="btn btn-outline-secondary" href="/">Volver a la tienda</a></div>';
    require __DIR__ . '/../plantillas/pie.php';
    exit;
}

/** Ruta interna segura para volver después del login (evita redirecciones abiertas a otro sitio: //malo.com). */
function ruta_interna(string $ruta, string $porDefecto = '/admin.php'): string
{
    return preg_match('#^/(?![/\\\\])[\w\-/.?=&%]*$#', $ruta) === 1 ? $ruta : $porDefecto;
}

/** Página de inicio según el rol: el socio va directo a su liquidación. */
function inicio_de(UsuarioSesion $u): string
{
    return $u->rol === 'socio' ? '/liquidaciones.php' : '/admin.php';
}

// ---------- CSRF ----------

function csrf_token(): string
{
    iniciar_sesion();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Si el token del formulario no coincide con el de la sesión, responde 403 y termina. */
function exigir_csrf(array $entrada): void
{
    iniciar_sesion();
    $enviado = $entrada['csrf'] ?? '';
    if (!is_string($enviado) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        responder_error(403, 'Solicitud rechazada', 'El formulario expiró o no proviene de este sitio (CSRF). Recarga la página e intenta de nuevo.');
    }
}

// ---------- Autenticación ----------

/**
 * Comprueba correo y contraseña. Tras 5 fallos seguidos la cuenta se bloquea 15 minutos.
 * El mensaje de error es siempre el mismo (no revela si el correo existe) y el tiempo de respuesta también:
 * si el correo no existe se verifica contra un hash falso.
 * @return array{usuario: ?UsuarioSesion, error: ?string}
 */
function autenticar_usuario(PDO $pdo, string $correo, string $clave): array
{
    $generico = 'Correo o contraseña incorrectos.';
    $consulta = $pdo->prepare('SELECT id, correo, nombre, clave_hash, rol, socio_id, intentos_fallidos, bloqueado_hasta, activo
        FROM usuarios WHERE correo = :correo');
    $consulta->execute(['correo' => mb_strtolower(trim($correo))]);
    $fila = $consulta->fetch();
    if ($fila === false) {
        password_verify($clave, '$2y$12$NWmBeIPTIRwrqzHjjz3sIO0fMTZMiic8rggmWe7Jv9ykA61H5JJI2');
        return ['usuario' => null, 'error' => $generico];
    }
    if ($fila['bloqueado_hasta'] !== null && strtotime((string) $fila['bloqueado_hasta']) > time()) {
        return ['usuario' => null, 'error' => 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.'];
    }
    if (!password_verify($clave, (string) $fila['clave_hash']) || !(bool) $fila['activo']) {
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = IF(intentos_fallidos >= :maximo, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL) WHERE id = :id')
            ->execute(['id' => $fila['id'], 'maximo' => INTENTOS_MAXIMOS]);
        return ['usuario' => null, 'error' => $generico];
    }
    $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id')->execute(['id' => $fila['id']]);
    if (password_needs_rehash((string) $fila['clave_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE usuarios SET clave_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($clave, PASSWORD_DEFAULT), 'id' => $fila['id']]);
    }
    iniciar_sesion();
    session_regenerate_id(true);                      // identificador nuevo al iniciar sesión (fijación de sesión)
    unset($_SESSION['csrf'], $_SESSION['expirada']);
    $usuario = UsuarioSesion::desdeFila($fila);
    $_SESSION['usuario'] = ['id' => $usuario->id, 'correo' => $usuario->correo, 'nombre' => $usuario->nombre,
        'rol' => $usuario->rol, 'socio_id' => $usuario->socioId];
    return ['usuario' => $usuario, 'error' => null];
}

function cerrar_sesion(): void
{
    iniciar_sesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/** Regla de contraseñas: 10 caracteres o más, con letras y números. @return ?string mensaje de error */
function problema_clave(string $clave): ?string
{
    if (mb_strlen($clave) < 10 || preg_match('/\p{L}/u', $clave) !== 1 || preg_match('/\d/', $clave) !== 1) {
        return 'La contraseña debe tener al menos 10 caracteres, con letras y números.';
    }
    return null;
}

function cambiar_clave(PDO $pdo, int $usuarioId, string $actual, string $nueva): void
{
    $consulta = $pdo->prepare('SELECT clave_hash FROM usuarios WHERE id = :id');
    $consulta->execute(['id' => $usuarioId]);
    if (!password_verify($actual, (string) $consulta->fetchColumn())) {
        throw new InvalidArgumentException('La contraseña actual no es correcta.');
    }
    if (($problema = problema_clave($nueva)) !== null) {
        throw new InvalidArgumentException($problema);
    }
    $pdo->prepare('UPDATE usuarios SET clave_hash = :hash WHERE id = :id')
        ->execute(['hash' => password_hash($nueva, PASSWORD_DEFAULT), 'id' => $usuarioId]);
}

// ---------- Administración de usuarios ----------

function crear_usuario(PDO $pdo, string $correo, string $nombre, string $clave, string $rol, ?int $socioId): int
{
    $correo = mb_strtolower(trim($correo));
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
    if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false || mb_strlen($correo) > 120) {
        throw new InvalidArgumentException('Escribe un correo válido.');
    }
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 80) {
        throw new InvalidArgumentException('El nombre debe tener entre 3 y 80 caracteres.');
    }
    if (($problema = problema_clave($clave)) !== null) {
        throw new InvalidArgumentException($problema);
    }
    validar_rol($pdo, $rol, $socioId);
    try {
        $pdo->prepare('INSERT INTO usuarios (correo, nombre, clave_hash, rol, socio_id) VALUES (:correo, :nombre, :hash, :rol, :socio)')
            ->execute(['correo' => $correo, 'nombre' => $nombre, 'hash' => password_hash($clave, PASSWORD_DEFAULT),
                'rol' => $rol, 'socio' => $rol === 'socio' ? $socioId : null]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException("Ya existe un usuario con el correo $correo.");
        }
        throw $e;
    }
    return (int) $pdo->lastInsertId();
}

function cambiar_rol_usuario(PDO $pdo, int $usuarioId, string $rol, ?int $socioId, int $administradorId): void
{
    if ($usuarioId === $administradorId) {
        throw new InvalidArgumentException('No puedes cambiar tu propio rol (evita quedarte sin administración).');
    }
    $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id');
    $consulta->execute(['id' => $usuarioId]);
    if ($consulta->fetchColumn() === false) {
        throw new InvalidArgumentException('El usuario no existe.');
    }
    validar_rol($pdo, $rol, $socioId);
    $pdo->prepare('UPDATE usuarios SET rol = :rol, socio_id = :socio WHERE id = :id')
        ->execute(['rol' => $rol, 'socio' => $rol === 'socio' ? $socioId : null, 'id' => $usuarioId]);
}

function validar_rol(PDO $pdo, string $rol, ?int $socioId): void
{
    if (!isset(ROLES[$rol])) {
        throw new InvalidArgumentException('El rol seleccionado no es válido.');
    }
    if ($rol === 'socio') {
        if ($socioId === null) {
            throw new InvalidArgumentException('El rol socio requiere vincular su ficha de socia o socio.');
        }
        $socio = $pdo->prepare('SELECT id FROM socios WHERE id = :id');
        $socio->execute(['id' => $socioId]);
        if ($socio->fetchColumn() === false) {
            throw new InvalidArgumentException('La socia o socio seleccionado no existe.');
        }
    }
}

// ---------- Tokens de la API (Laboratorio 13) ----------

/** Genera un token aleatorio, guarda SOLO su hash SHA-256 y devuelve el token (se muestra una única vez). */
function crear_token_api(PDO $pdo, int $usuarioId, string $nombre, int $dias = 90): string
{
    $nombre = trim($nombre);
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 80) {
        throw new InvalidArgumentException('Describe para qué es el token (3 a 80 caracteres).');
    }
    if ($dias < 1 || $dias > 365) {
        throw new InvalidArgumentException('La vigencia debe ser de 1 a 365 días.');
    }
    $token = 'mdo_' . bin2hex(random_bytes(20));
    $pdo->prepare('INSERT INTO tokens_api (usuario_id, nombre, hash, expira) VALUES (:usuario, :nombre, :hash, DATE_ADD(NOW(), INTERVAL :dias DAY))')
        ->execute(['usuario' => $usuarioId, 'nombre' => $nombre, 'hash' => hash('sha256', $token), 'dias' => $dias]);
    return $token;
}

/** Usuario dueño de un token vigente, o null si no existe, venció o fue revocado. */
function usuario_por_token(PDO $pdo, string $token): ?UsuarioSesion
{
    if (preg_match('/^mdo_[a-f0-9]{40}$/', $token) !== 1) {
        return null;
    }
    $consulta = $pdo->prepare('SELECT u.id, u.correo, u.nombre, u.rol, u.socio_id FROM tokens_api t JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.hash = :hash AND t.revocado = 0 AND t.expira > NOW() AND u.activo = 1');
    $consulta->execute(['hash' => hash('sha256', $token)]);
    $fila = $consulta->fetch();
    return $fila === false ? null : UsuarioSesion::desdeFila($fila);
}
