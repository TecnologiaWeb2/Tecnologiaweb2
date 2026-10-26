<?php
// src/Modelo/Rol.php — Roles y su matriz de permisos. Un solo lugar decide quién puede qué.
declare(strict_types=1);

namespace Apicultores\Modelo;

enum Rol: string
{
    case Admin = 'admin';
    case Operador = 'operador';
    case Socio = 'socio';

    /** Matriz de permisos: acción → roles que la pueden hacer. */
    private const PERMISOS = [
        'productos.ver' => ['admin', 'operador', 'socio'],
        'productos.crear' => ['admin', 'operador'],
        'productos.editar' => ['admin', 'operador'],
        'productos.eliminar' => ['admin'],
        'socios.ver' => ['admin', 'operador'],
    ];

    public function puede(string $accion): bool
    {
        // TODO 5: busca la acción en self::PERMISOS y responde si este rol ($this->value) está en la lista.
        //         Una acción que NO está en la matriz debe responder false (negar por defecto).
        return in_array($this->value, self::PERMISOS[$accion] ?? [], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administración',
            self::Operador => 'Operador',
            self::Socio => 'Socio',
        };
    }
}
