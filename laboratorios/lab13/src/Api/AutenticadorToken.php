<?php
// src/Api/AutenticadorToken.php — "Authorization: Bearer <token>". Sin cookies: por eso la API no necesita token CSRF.
declare(strict_types=1);

namespace Apicultores\Api;

use Apicultores\Excepciones\NoAutenticado;
use Apicultores\Modelo\Rol;
use Apicultores\Modelo\Usuario;
use PDO;

final class AutenticadorToken
{
    public function __construct(private readonly PDO $bd)
    {
    }

    /** null si no se envió token (lectura pública); Usuario si es válido; NoAutenticado si es inválido o venció. */
    public function usuario(?string $cabecera): ?Usuario
    {
        if ($cabecera === null || $cabecera === '') {
            return null;
        }
        if (!preg_match('/^Bearer\s+(api_[a-f0-9]{40})$/', $cabecera, $m)) {
            throw new NoAutenticado('Formato esperado: "Authorization: Bearer api_…".');
        }
        // Se busca por el HASH: si alguien roba la tabla, no obtiene tokens utilizables
        $c = $this->bd->prepare('SELECT u.id, u.correo, u.nombre, u.rol, u.socio_id
                                 FROM tokens_api t JOIN usuarios u ON u.id = t.usuario_id
                                 WHERE t.hash = ? AND t.revocado = 0 AND t.expira > NOW()');
        $c->execute([hash('sha256', $m[1])]);
        $f = $c->fetch() ?: throw new NoAutenticado('El token no es válido, fue revocado o venció.');
        return new Usuario((int) $f['id'], $f['correo'], $f['nombre'], Rol::from($f['rol']), $f['socio_id'] === null ? null : (int) $f['socio_id']);
    }
}
