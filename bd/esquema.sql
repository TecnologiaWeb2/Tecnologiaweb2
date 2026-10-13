-- bd/esquema.sql — Esquema de la tienda «Manos del Oriente» (MySQL 8 / MariaDB 10.4+).
-- Se ejecuta sobre la base de datos ya seleccionada: php bd/instalar.php
-- Borra y vuelve a crear las tablas (orden inverso a las claves foráneas).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS producto_tallas;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS tallas;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS socios;
SET FOREIGN_KEY_CHECKS = 1;

-- Socias y socios de la asociación: confeccionan las prendas
CREATE TABLE socios (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ci             VARCHAR(13)   NOT NULL COMMENT 'Cédula de identidad, con complemento opcional (1234567-1A)',
    nombre         VARCHAR(80)   NOT NULL,
    telefono       CHAR(8)       NOT NULL COMMENT 'Celular boliviano: 8 dígitos, empieza en 6 o 7',
    comunidad      VARCHAR(60)   NOT NULL,
    especialidad   ENUM('Costura', 'Bordado', 'Tejido', 'Estampado') NOT NULL,
    fecha_ingreso  DATE          NOT NULL,
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_socios_ci (ci),
    KEY ix_socios_nombre (nombre),
    CONSTRAINT ck_socios_telefono CHECK (telefono REGEXP '^[67][0-9]{7}$')
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE categorias (
    id          VARCHAR(30)  NOT NULL COMMENT 'Identificador legible (slug): poleras, vestidos…',
    nombre      VARCHAR(60)  NOT NULL,
    descripcion VARCHAR(200) NOT NULL DEFAULT '',
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE tallas (
    codigo   VARCHAR(3)  NOT NULL,
    etiqueta VARCHAR(20) NOT NULL,
    orden    TINYINT     NOT NULL,
    PRIMARY KEY (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE productos (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    codigo         VARCHAR(9)    NOT NULL,
    nombre         VARCHAR(80)   NOT NULL,
    categoria_id   VARCHAR(30)   NOT NULL,
    socio_id       INT UNSIGNED  NOT NULL COMMENT 'Quién confeccionó la prenda',
    precio         DECIMAL(10,2) NOT NULL,
    stock          INT UNSIGNED  NOT NULL DEFAULT 0,
    color          VARCHAR(30)   NOT NULL DEFAULT '',
    descripcion    VARCHAR(500)  NOT NULL DEFAULT '',
    foto           CHAR(29)      NULL COMMENT '24 hex aleatorios + extensión; el archivo vive fuera de public/',
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_codigo (codigo),
    KEY ix_productos_nombre (nombre),
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- RESTRICT: no se puede borrar a una socia que tiene prendas registradas
    CONSTRAINT fk_productos_socio FOREIGN KEY (socio_id) REFERENCES socios (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_productos_precio CHECK (precio > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Relación muchos a muchos: una prenda se ofrece en varias tallas
CREATE TABLE producto_tallas (
    producto_id INT UNSIGNED NOT NULL,
    talla       VARCHAR(3)   NOT NULL,
    PRIMARY KEY (producto_id, talla),
    CONSTRAINT fk_pt_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_talla FOREIGN KEY (talla) REFERENCES tallas (codigo) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
