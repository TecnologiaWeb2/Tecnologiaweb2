-- bd/datos.sql — Datos de demostración (6 socios, 5 categorías, 7 tallas, 14 prendas).
-- Valores verificados a mano en docs/: inventario = Bs 19.538,50 · 157 unidades.

SET NAMES utf8mb4;

INSERT INTO tallas (codigo, etiqueta, orden) VALUES
    ('XS', 'XS', 1), ('S', 'S', 2), ('M', 'M', 3), ('L', 'L', 4),
    ('XL', 'XL', 5), ('XXL', 'XXL', 6), ('U', 'Talla única', 7);

INSERT INTO categorias (id, nombre, descripcion) VALUES
    ('poleras',    'Poleras y blusas',    'Prendas superiores de algodón y tocuyo.'),
    ('pantalones', 'Pantalones y faldas', 'Jeans, pantalones de tela y faldas.'),
    ('vestidos',   'Vestidos',            'Vestidos casuales y de fiesta con bordado chiquitano.'),
    ('abrigos',    'Abrigos y chamarras', 'Chamarras, chalecos y ponchos para el surazo.'),
    ('accesorios', 'Accesorios',          'Bolsos, sombreros y fajas tejidas.');

INSERT INTO socios (id, ci, nombre, telefono, comunidad, especialidad, fecha_ingreso, activo) VALUES
    (1, '4589231',    'Rosa Chuvé Supepí',      '71234567', 'Concepción',             'Bordado',   '2019-03-15', 1),
    (2, '5123987',    'Elena Masabí Tomichá',   '72345678', 'San Javier',             'Costura',   '2020-06-01', 1),
    (3, '6234598-1A', 'Mario Surubí Peña',      '73456789', 'San Ignacio de Velasco', 'Tejido',    '2018-11-20', 1),
    (4, '7345612',    'Juana Pesoa Rojas',      '74567890', 'Concepción',             'Costura',   '2021-02-10', 1),
    (5, '8456723',    'Lidia Choré Vaca',       '65678901', 'San Rafael',             'Estampado', '2022-08-05', 1),
    (6, '3987456',    'Carmen Parapaino Masaí', '76789012', 'Santa Ana de Velasco',   'Bordado',   '2017-05-30', 0);

INSERT INTO productos (id, codigo, nombre, categoria_id, socio_id, precio, stock, color, descripcion) VALUES
    (1,  'POL-001', 'Polera de algodón con bordado de tajibo', 'poleras',    1,  95.00, 24, 'Blanco',     'Algodón peinado 100 %, bordado a mano por socias de Concepción.'),
    (2,  'POL-002', 'Blusa de tocuyo manga corta',             'poleras',    2, 120.00,  0, 'Crudo',      'Tela de tocuyo tradicional, fresca para el calor del oriente.'),
    (3,  'POL-003', 'Polera estampada Chiquitanía',            'poleras',    5,  85.00, 18, 'Negro',      'Estampado serigráfico con motivos de las misiones jesuíticas.'),
    (4,  'PAN-010', 'Jean clásico tiro alto',                  'pantalones', 4, 180.00, 15, 'Azul',       'Mezclilla de 12 onzas con costuras reforzadas.'),
    (5,  'PAN-011', 'Falda plisada de lino',                   'pantalones', 2, 140.50,  7, 'Terracota',  ''),
    (6,  'PAN-012', 'Pantalón de lino holgado',                'pantalones', 4, 165.00, 10, 'Beige',      'Corte recto, ideal para el clima cálido.'),
    (7,  'VES-100', 'Vestido tipoy bordado',                   'vestidos',   1, 350.00,  5, 'Blanco',     'Inspirado en el tipoy chiquitano, bordado en punto cruz.'),
    (8,  'VES-101', 'Vestido camisero de tocuyo',              'vestidos',   2, 275.00,  3, 'Crudo',      ''),
    (9,  'ABR-200', 'Chamarra de jean forrada',                'abrigos',    4, 260.00,  9, 'Azul',       'Forro de chiporro para los días de surazo.'),
    (10, 'ABR-201', 'Poncho tejido de lana',                   'abrigos',    3, 310.00,  4, 'Gris',       'Tejido en telar de cintura.'),
    (11, 'ABR-202', 'Chaleco bordado de bayeta',               'abrigos',    1, 195.00,  0, 'Rojo',       ''),
    (12, 'ACC-300', 'Bolso tejido de saó',                     'accesorios', 3,  75.00, 30, 'Natural',    'Tejido a mano con fibra de saó.'),
    (13, 'ACC-301', 'Sombrero de saó',                         'accesorios', 3,  90.00, 12, 'Natural',    ''),
    (14, 'ACC-302', 'Faja tejida tradicional',                 'accesorios', 5,  45.50, 20, 'Multicolor', 'Faja de algodón con diseños geométricos.');

INSERT INTO producto_tallas (producto_id, talla) VALUES
    (1, 'S'), (1, 'M'), (1, 'L'), (1, 'XL'),
    (2, 'XS'), (2, 'S'), (2, 'M'),
    (3, 'S'), (3, 'M'), (3, 'L'),
    (4, 'S'), (4, 'M'), (4, 'L'),
    (5, 'S'), (5, 'M'),
    (6, 'M'), (6, 'L'), (6, 'XL'),
    (7, 'S'), (7, 'M'), (7, 'L'),
    (8, 'S'), (8, 'M'), (8, 'L'),
    (9, 'M'), (9, 'L'), (9, 'XL'), (9, 'XXL'),
    (10, 'U'),
    (11, 'S'), (11, 'M'),
    (12, 'U'), (13, 'U'), (14, 'U');

ALTER TABLE socios AUTO_INCREMENT = 7;
ALTER TABLE productos AUTO_INCREMENT = 15;
