-- sql/04-usuarios.sql — Usuarios del sistema. Las contraseñas se guardan con password_hash() (bcrypt): NUNCA en texto.
-- Contraseñas de prueba (solo laboratorio): admin Panal-2026! · operador Colmena-2026! · elena Polen-2026!
-- El hash del operador usa cost 10 a propósito: al iniciar sesión se actualiza a cost 12 (password_needs_rehash).
SET NAMES utf8mb4;

DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correo            VARCHAR(120) NOT NULL,
    nombre            VARCHAR(80)  NOT NULL,
    clave_hash        VARCHAR(255) NOT NULL,             -- 255: los algoritmos futuros generan hashes más largos
    rol               ENUM('admin', 'operador', 'socio') NOT NULL,
    socio_id          INT UNSIGNED NULL,                 -- solo para el rol socio
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta   DATETIME NULL,
    creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_correo UNIQUE (correo),
    CONSTRAINT fk_usuarios_socio FOREIGN KEY (socio_id) REFERENCES socios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

INSERT INTO usuarios (correo, nombre, clave_hash, rol, socio_id) VALUES
    ('admin@florchiquitania.bo', 'Administración', '$2y$12$pPcTmVFOsiW4JHoTFr/Z8O4DWj4Nj3S9DisPdBl77Zpu7VMMM5nbO', 'admin', NULL),
    ('operador@florchiquitania.bo', 'Secretaría de ventas', '$2y$10$cbFyx7.nvsyW3F.HM4DZGeTD.LHWaUSvBxs09DASLRag2HaoRklVe', 'operador', NULL),
    ('elena@florchiquitania.bo', 'Elena Masabí', '$2y$12$JYcXQveVhBc7gTBkRNZXCOVsQDy0cbH4.nleMP7xKLcGXyDKD6oiS', 'socio', 3);
