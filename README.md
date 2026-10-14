# Manos del Oriente: sistema web de la tienda virtual de ropa

Sistema web de la **Asociación de Confeccionistas Textiles «Manos del Oriente»** (caso verosímil, Chiquitanía,
Santa Cruz, Bolivia), desarrollado de forma incremental en la asignatura **Tecnología Web II, UPDS**.

| Actividad | Tema | Estado | Versión |
|---|---|---|---|
| 1 | Catálogo en PHP 8 con POO, formularios validados y persistencia en JSON | ✅ Entregada | etiqueta [`actividad-1`](../../tree/actividad-1) |
| 2 | **De JSON a MySQL**: PDO, repositorios, CRUD de productos y socios, paginación | ✅ Esta versión | etiqueta `actividad-2` |
| 3 | Usuarios, roles, transacciones y reportes | ⏳ Pendiente | — |
| 4 | API REST y consumo asíncrono con `fetch` | ⏳ Pendiente | — |
| Final | Proyecto socioformativo publicado en hosting | ⏳ Pendiente | — |

## Requisitos

- PHP 8.3 o superior (probado con **PHP 8.4.13**) con `pdo_mysql`, `mbstring`, `fileinfo` y `curl` (este último solo para las pruebas).
- MySQL 8 o MariaDB 10.4+ (probado con **MariaDB 10.4.32** de XAMPP). Compatible con el Docker del curso (`entorno/`, variables `BD_HOST`, `BD_NOMBRE`…).

## Instalación y ejecución

```bash
cp config/config.ejemplo.php config/config.php   # completar usuario y clave de MySQL (no se sube a git)
php bd/instalar.php                              # crea la base manos_del_oriente con los datos de demostración
php -S 127.0.0.1:8000 -t public                  # abrir http://127.0.0.1:8000
php verificar.php                                # pruebas automáticas: 57 de 57
```

- Sin `config/config.php` se usa la plantilla (XAMPP: `root` sin clave en `127.0.0.1:3306`).
- `php bd/migrar_json.php [--simular]` migra las prendas del `datos/productos.json` de la actividad 1 a MySQL.
- Las pruebas usan su propia base (`manos_del_oriente_pruebas`, se borra al terminar) y una carpeta de fotos temporal.

## Estructura

```
├── public/                     ← única carpeta accesible por URL (docroot)
│   ├── index.php               vitrina: filtros GET + paginación (8 por página)
│   ├── producto.php            ficha de la prenda con la socia que la confeccionó
│   ├── admin.php               gestión de prendas: búsqueda, paginación (10), resumen del inventario
│   ├── nuevo.php / editar.php / eliminar.php        CRUD de prendas (POST + CSRF + PRG)
│   ├── socios.php / socio_form.php / socio_eliminar.php   CRUD de socios (paginación de 5)
│   └── foto.php                entrega fotos guardadas fuera de public/
├── config/config.ejemplo.php   plantilla de conexión (config.php real: fuera de public/ y de git)
├── bd/
│   ├── esquema.sql             5 tablas InnoDB, claves foráneas, UNIQUE y CHECK
│   ├── datos.sql               6 socios, 5 categorías, 7 tallas, 14 prendas
│   ├── instalar.php            crea la base y ejecuta los scripts
│   └── migrar_json.php         migración JSON → MySQL en una transacción
├── src/
│   ├── arranque.php            require_once de todo + manejador de errores sin filtrar detalles
│   ├── config.php / Conexion.php   configuración y PDO (excepciones, sin emulación de preparadas)
│   ├── Modelo/                 Producto, Categoria, Socio (readonly, promoción de propiedades), enum Talla
│   ├── Repositorio/            ProductoRepositorio, SocioRepositorio, CategoriaRepositorio, Pagina
│   ├── validacion.php          validar_producto(), validar_socio()
│   ├── fotos.php / funciones.php
├── plantillas/                 cabecera, pie, campos y formularios (require)
├── docs/
│   ├── BITACORA_IA.md          bitácora de IA de todo el proyecto
│   ├── actividad-01/           informe APA, presentación, video de 60 s y capturas
│   └── actividad-02/           MODELO_ER.md, INYECCION_SQL.md, informe y presentación
├── Verificador.php             motor de pruebas de la asignatura
└── verificar.php               57 comprobaciones (BD, repositorios, web, inyección SQL)
```

## Actividad 2: requisitos y dónde se cumplen

| Requisito | Dónde |
|---|---|
| Modelo entidad-relación | [`docs/actividad-02/MODELO_ER.md`](docs/actividad-02/MODELO_ER.md) (diagrama Mermaid) |
| Script SQL con tablas, claves foráneas y datos de ejemplo | [`bd/esquema.sql`](bd/esquema.sql), [`bd/datos.sql`](bd/datos.sql) |
| Conexión PDO configurada fuera del código público | `config/` (fuera de `public/`, `config.php` en `.gitignore`) + [`src/Conexion.php`](src/Conexion.php) |
| `ProductoRepositorio` y `SocioRepositorio` con CRUD en consultas preparadas | [`src/Repositorio/`](src/Repositorio/) (+ transacciones para prenda y tallas) |
| Validación | [`src/validacion.php`](src/validacion.php) + restricciones en la base (UNIQUE, CHECK, FK) |
| Paginación | `paginar()` con `LIMIT/OFFSET` enlazados como enteros; clase `Pagina`; componente `paginacion()` |
| Demostrar que resiste la inyección SQL | [`docs/actividad-02/INYECCION_SQL.md`](docs/actividad-02/INYECCION_SQL.md) + 13 pruebas automáticas |
| Migrar la aplicación de JSON a MySQL | Repositorios en lugar de `Catalogo`; [`bd/migrar_json.php`](bd/migrar_json.php) |
| Bitácora de IA | [`docs/BITACORA_IA.md`](docs/BITACORA_IA.md) |

## Datos de demostración (verificados a mano)

14 prendas · 157 unidades · valor del inventario **Bs 19.538,50** · 6 socios (5 activos). Detalle en
[`docs/actividad-02/MODELO_ER.md`](docs/actividad-02/MODELO_ER.md).

## Integrantes

| Nombre | Rol |
|Daniela Cayo| frontend|
|Juan Jose Vicente| backend|
|yordy tolavi ruiz| backend|
