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
        'ventas.registrar' => ['admin', 'operador'],
        'acopios.registrar' => ['admin', 'operador'],
        'reportes.ver' => ['admin'],
    ];

    public function puede(string $accion): bool
    {
        return in_array($this->value, self::PERMISOS[$accion] ?? [], true);   // acción desconocida → NO (negar por defecto)
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
