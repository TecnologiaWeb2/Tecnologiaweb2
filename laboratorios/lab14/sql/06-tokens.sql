-- sql/06-tokens.sql — Tokens de la API (lab 13). Se guarda SOLO el hash SHA-256 del token, nunca el token.
-- Tokens de prueba (solo laboratorio; están en ejemplos/tokens.txt):
SET NAMES utf8mb4;

CREATE TABLE tokens_api (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nombre     VARCHAR(80) NOT NULL,                  -- para qué sistema se emitió
    hash       CHAR(64) NOT NULL,                     -- SHA-256 del token (el token completo no se guarda)
    expira     DATETIME NOT NULL,
    revocado   TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT uq_tokens_hash UNIQUE (hash),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

INSERT INTO tokens_api (usuario_id, nombre, hash, expira) VALUES
    (1, 'Integración tienda en línea', '9f60f5823cd055d655ebdfdc1be39c34ca507393c125dc9570fd0482fc4c8fce', NOW() + INTERVAL 1 YEAR),
    (2, 'App de la feria', 'bb3b445d17637b18cb71bf1cc2e67c482312ae126f0f1322d1219485fba63d75', NOW() + INTERVAL 1 YEAR),
    (3, 'Elena: consulta', '1fbcbda0bd8f55aa03797780abfc1e226549913b03a190ef0859ccc537aeb44e', NOW() + INTERVAL 1 YEAR);
