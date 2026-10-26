-- bd/06-tokens.sql — Actividad 4 (Laboratorio 13): tokens de la API REST.
-- Se guarda SOLO el hash SHA-256 del token: si alguien copia la tabla, no obtiene tokens utilizables.
-- No hay tokens de ejemplo: se generan desde /tokens.php (administración) y se muestran UNA sola vez.

SET NAMES utf8mb4;

CREATE TABLE tokens_api (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'El token actúa con el rol de este usuario',
    nombre     VARCHAR(80)  NOT NULL COMMENT 'Para qué sistema se emitió',
    hash       CHAR(64)     NOT NULL,
    creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira     DATETIME     NOT NULL,
    revocado   TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_hash (hash),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
