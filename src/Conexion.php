<?php
// src/Conexion.php — Una sola conexión PDO por petición, configurada de forma segura.
declare(strict_types=1);

final class Conexion
{
    private static ?PDO $pdo = null;

    public static function obtener(): PDO
    {
        return self::$pdo ??= self::crear(configuracion_bd());
    }

    /**
     * @param array{host: string, puerto: int, nombre: string, usuario: string, clave: string} $bd
     * @param bool $conBase false para conectarse al servidor sin elegir base (lo usa el instalador)
     */
    public static function crear(array $bd, bool $conBase = true): PDO
    {
        $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};charset=utf8mb4" . ($conBase ? ";dbname={$bd['nombre']}" : '');
        $pdo = new PDO($dsn, $bd['usuario'], $bd['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // todo error SQL lanza PDOException
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Consultas preparadas REALES: el servidor recibe la consulta y los datos por separado,
            // así un dato nunca se interpreta como SQL (con emulación, PDO arma el texto en PHP)
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // NOW() y CURRENT_TIMESTAMP en la hora de Bolivia, aunque el servidor del hosting esté en otro huso
        $pdo->exec("SET time_zone = '-04:00'");
        return $pdo;
    }
}
