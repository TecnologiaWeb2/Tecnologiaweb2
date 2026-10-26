-- bd/07-tfi.sql — Trabajo Final Integrador: los 2 módulos nuevos acordados con la asociación.
--   Módulo 1 · Liquidación y pagos a socias: saldo = Σ(aprobadas × precio_unitario de los acopios) − Σ(pagos).
--                El saldo se CALCULA (no se guarda): nunca queda desactualizado.
--   Módulo 2 · Pedidos de compradores con reserva de stock: al crear el pedido se reserva; al entregarlo se
--                convierte en venta en UNA transacción; al cancelarlo se libera la reserva.

SET NAMES utf8mb4;

-- Stock reservado por pedidos pendientes o confirmados. Disponible para vender = stock − reservado.
ALTER TABLE productos
    ADD COLUMN reservado INT UNSIGNED NOT NULL DEFAULT 0 AFTER stock,
    ADD CONSTRAINT ck_productos_reservado CHECK (reservado <= stock);

CREATE TABLE pedidos (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    numero     VARCHAR(30)   NOT NULL COMMENT 'Número del comprador (orden de compra) o generado: PED-000001',
    cliente    VARCHAR(80)   NOT NULL,
    origen     ENUM('web', 'json', 'xml', 'api') NOT NULL DEFAULT 'web',
    estado     ENUM('pendiente', 'confirmado', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    total      DECIMAL(12,2) NOT NULL,
    creado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT UNSIGNED  NOT NULL,
    venta_id   INT UNSIGNED  NULL COMMENT 'La venta que generó al entregarse',
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_numero (numero),
    KEY ix_pedidos_estado (estado),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_pedidos_venta FOREIGN KEY (venta_id) REFERENCES ventas (id),
    CONSTRAINT ck_pedidos_entregado CHECK ((estado = 'entregado') = (venta_id IS NOT NULL))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE pedido_detalle (
    pedido_id       INT UNSIGNED  NOT NULL,
    producto_id     INT UNSIGNED  NOT NULL,
    cantidad        INT UNSIGNED  NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (pedido_id, producto_id),
    CONSTRAINT fk_pd_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
    CONSTRAINT ck_pd_cantidad CHECK (cantidad > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE pagos_socios (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    socio_id   INT UNSIGNED  NOT NULL,
    fecha      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    importe    DECIMAL(12,2) NOT NULL,
    referencia VARCHAR(60)   NOT NULL COMMENT 'N.º de transferencia o recibo',
    usuario_id INT UNSIGNED  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pagos_referencia (referencia),
    KEY ix_pagos_socio (socio_id),
    CONSTRAINT fk_pagos_socio FOREIGN KEY (socio_id) REFERENCES socios (id),
    CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT ck_pagos_importe CHECK (importe > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
