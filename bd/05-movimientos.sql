-- bd/05-movimientos.sql — Actividad 3 (Laboratorio 11): ventas con detalle y acopios (entrega de prendas de las socias).
-- Una venta descuenta stock y un acopio lo suma: ambos se registran en UNA transacción (src/Ventas.php).

SET NAMES utf8mb4;

CREATE TABLE ventas (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    fecha      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cliente    VARCHAR(80)   NOT NULL,
    usuario_id INT UNSIGNED  NOT NULL COMMENT 'Quién registró la venta',
    total      DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_ventas_fecha (fecha),
    CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT ck_ventas_total CHECK (total > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE venta_detalle (
    venta_id        INT UNSIGNED  NOT NULL,
    producto_id     INT UNSIGNED  NOT NULL,
    cantidad        INT UNSIGNED  NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL COMMENT 'Precio al momento de la venta (el del catálogo puede cambiar)',
    PRIMARY KEY (venta_id, producto_id),
    CONSTRAINT fk_vd_venta FOREIGN KEY (venta_id) REFERENCES ventas (id) ON DELETE CASCADE,
    CONSTRAINT fk_vd_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
    CONSTRAINT ck_vd_cantidad CHECK (cantidad > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Acopio con CONTROL DE CALIDAD (módulo 1 del trabajo final): la socia entrega N prendas, se aprueban M ≤ N
-- y se le liquida M × precio_unitario. Así se termina la discusión «me pagaron menos prendas de las que traje».
CREATE TABLE acopios (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    fecha           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    socio_id        INT UNSIGNED  NOT NULL,
    producto_id     INT UNSIGNED  NOT NULL,
    entregadas      INT UNSIGNED  NOT NULL,
    aprobadas       INT UNSIGNED  NOT NULL COMMENT 'Pasaron el control de calidad: solo estas suman stock y se pagan',
    precio_unitario DECIMAL(10,2) NOT NULL COMMENT 'Lo que se paga a la socia por cada prenda aprobada',
    observacion     VARCHAR(200)  NOT NULL DEFAULT '' COMMENT 'Motivo del rechazo (costura abierta, mancha…)',
    usuario_id      INT UNSIGNED  NOT NULL,
    PRIMARY KEY (id),
    KEY ix_acopios_fecha (fecha),
    CONSTRAINT fk_acopios_socio FOREIGN KEY (socio_id) REFERENCES socios (id),
    CONSTRAINT fk_acopios_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
    CONSTRAINT fk_acopios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT ck_acopios_cantidades CHECK (entregadas > 0 AND aprobadas <= entregadas),
    CONSTRAINT ck_acopios_precio CHECK (precio_unitario >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
