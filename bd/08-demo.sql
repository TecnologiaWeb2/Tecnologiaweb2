-- bd/08-demo.sql — Movimientos de DEMOSTRACIÓN (julio–septiembre 2026) para verificar los reportes a mano.
-- Las cifras esperadas están calculadas en docs/proyecto-final/MODELO_DATOS.md y las comprueba verificar.php.
-- El stock de bd/datos.sql ya es el stock ACTUAL (después de estas ventas y acopios): aquí no se modifica.

SET NAMES utf8mb4;

-- 6 ventas · Bs 3.287,50 · julio Bs 965,00 · agosto Bs 1.280,00 · septiembre Bs 1.042,50
INSERT INTO ventas (id, fecha, cliente, usuario_id, total) VALUES
    (1, '2026-07-05 10:30:00', 'Feria de Concepción',  2,  435.00),
    (2, '2026-07-19 16:00:00', 'Boutique Chiquitana',  2,  530.00),
    (3, '2026-08-02 11:15:00', 'Cliente de mostrador', 2,  360.00),
    (4, '2026-08-23 09:45:00', 'Hotel Misiones',       2,  920.00),
    (5, '2026-09-10 15:20:00', 'Feria de San Javier',  2,  627.00),
    (6, '2026-09-28 17:40:00', 'Boutique Chiquitana',  2,  415.50);

INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario) VALUES
    (1, 1, 3, 95.00), (1, 12, 2, 75.00),
    (2, 7, 1, 350.00), (2, 13, 2, 90.00),
    (3, 4, 2, 180.00),
    (4, 10, 2, 310.00), (4, 12, 4, 75.00),
    (5, 1, 2, 95.00), (5, 3, 3, 85.00), (5, 14, 4, 45.50),
    (6, 8, 1, 275.00), (6, 5, 1, 140.50);

-- 7 acopios con control de calidad · liquidado total Bs 5.635,00
INSERT INTO acopios (id, fecha, socio_id, producto_id, entregadas, aprobadas, precio_unitario, observacion, usuario_id) VALUES
    (1, '2026-07-01 09:00:00', 1, 1,  20, 18,  50.00, '2 con el bordado incompleto', 2),
    (2, '2026-07-10 09:30:00', 2, 2,  10,  9,  65.00, '1 con mancha en la manga', 2),
    (3, '2026-07-20 10:00:00', 4, 4,  12, 12, 100.00, '', 2),
    (4, '2026-08-01 09:00:00', 1, 7,   4,  4, 200.00, '', 2),
    (5, '2026-08-15 11:00:00', 3, 12, 30, 28,  40.00, '2 con el tejido flojo', 2),
    (6, '2026-09-01 08:30:00', 2, 8,   3,  3, 160.00, '', 2),
    (7, '2026-09-05 10:15:00', 5, 14, 25, 22,  25.00, '3 con el estampado corrido', 2);

-- 4 pagos · Bs 3.305,00 → saldo pendiente total Bs 2.330,00
INSERT INTO pagos_socios (id, socio_id, fecha, importe, referencia, usuario_id) VALUES
    (1, 4, '2026-08-01 12:00:00',  600.00, 'TRF-0801-03', 2),
    (2, 1, '2026-08-05 12:00:00', 1000.00, 'TRF-0805-01', 2),
    (3, 2, '2026-08-10 12:00:00',  585.00, 'REC-0810-02', 2),
    (4, 3, '2026-09-01 12:00:00', 1120.00, 'TRF-0901-02', 2);

-- 1 pedido pendiente (reserva 4 POL-003 y 2 ACC-301) y 1 cancelado (no reserva nada)
INSERT INTO pedidos (id, numero, cliente, origen, estado, total, creado_en, usuario_id, venta_id) VALUES
    (1, 'OC-ART-0101', 'Tienda Artesanal Santa Cruz', 'xml', 'pendiente', 520.00, '2026-10-20 10:00:00', 2, NULL),
    (2, 'PED-000002',  'Hotel Misiones',              'web', 'cancelado', 260.00, '2026-10-21 15:00:00', 2, NULL);

INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario) VALUES
    (1, 3, 4, 85.00), (1, 13, 2, 90.00),
    (2, 9, 1, 260.00);

UPDATE productos SET reservado = CASE id WHEN 3 THEN 4 WHEN 13 THEN 2 ELSE 0 END;

ALTER TABLE ventas AUTO_INCREMENT = 7;
ALTER TABLE acopios AUTO_INCREMENT = 8;
ALTER TABLE pagos_socios AUTO_INCREMENT = 5;
ALTER TABLE pedidos AUTO_INCREMENT = 3;
