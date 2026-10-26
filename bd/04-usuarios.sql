-- bd/04-usuarios.sql — Actividad 3 (Laboratorio 10): usuarios con contraseña cifrada (password_hash) y rol.
-- Usuarios de DEMOSTRACIÓN (cambiar las contraseñas al publicar: ver docs/proyecto-final/DESPLIEGUE_INFINITYFREE.md):
--   admin@manosdeloriente.bo     Manos-Admin-2026!   administración (todo)
--   operador@manosdeloriente.bo  Manos-Ventas-2026!  operador (prendas, ventas, acopios, pedidos, pagos)
--   rosa@manosdeloriente.bo      Manos-Socia-2026!   socia (solo su propia liquidación)
--   elena@manosdeloriente.bo     Manos-Socia-2026!   socia (solo su propia liquidación)

SET NAMES utf8mb4;

CREATE TABLE usuarios (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    correo            VARCHAR(120)  NOT NULL,
    nombre            VARCHAR(80)   NOT NULL,
    clave_hash        VARCHAR(255)  NOT NULL COMMENT 'password_hash(): bcrypt, nunca la contraseña en texto',
    rol               ENUM('admin', 'operador', 'socio') NOT NULL,
    socio_id          INT UNSIGNED  NULL COMMENT 'Solo para el rol socio: qué socia es',
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta   DATETIME      NULL COMMENT '5 intentos fallidos → bloqueo de 15 minutos',
    activo            TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_correo (correo),
    CONSTRAINT fk_usuarios_socio FOREIGN KEY (socio_id) REFERENCES socios (id) ON DELETE RESTRICT,
    -- Un socio SIEMPRE está vinculado a su ficha; el personal no
    CONSTRAINT ck_usuarios_socio CHECK ((rol = 'socio' AND socio_id IS NOT NULL) OR (rol <> 'socio' AND socio_id IS NULL))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO usuarios (id, correo, nombre, clave_hash, rol, socio_id) VALUES
    (1, 'admin@manosdeloriente.bo',    'Administración',        '$2y$12$NWmBeIPTIRwrqzHjjz3sIO0fMTZMiic8rggmWe7Jv9ykA61H5JJI2', 'admin',    NULL),
    (2, 'operador@manosdeloriente.bo', 'Secretaría de ventas',  '$2y$12$loJWAf3cW2ZkQEvD3ljHYeL69apFBNh63QFrtN40MToEPRXRwXPbK', 'operador', NULL),
    (3, 'rosa@manosdeloriente.bo',     'Rosa Chuvé Supepí',     '$2y$12$M/9i5YIatfKRw6jbLvPb5uq1zj0RJ/x7TmjAxa06ItA5hLX0wtQMu', 'socio',    1),
    (4, 'elena@manosdeloriente.bo',    'Elena Masabí Tomichá',  '$2y$12$M/9i5YIatfKRw6jbLvPb5uq1zj0RJ/x7TmjAxa06ItA5hLX0wtQMu', 'socio',    2);

ALTER TABLE usuarios AUTO_INCREMENT = 5;
