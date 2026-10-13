# Bitácora de uso de IA: proyecto «Manos del Oriente»

**Integrantes:** _(completar)_  **Herramienta:** Claude Code (modelo Claude Opus 5.5)  **Repositorio:** https://github.com/TecnologiaWeb2/Tecnologiaweb2

> Esta bitácora registra, para TODO el proyecto, qué se pidió a la IA, qué entregó, cómo se verificó y qué decidimos
> nosotros. Complétenla con sus palabras: el docente evalúa su criterio, no solo el código.

## Actividad 1: catálogo en PHP 8 con POO y JSON (12/10/2026)

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 1 | Tienda de ropa virtual para el proyecto final, empezando por el Lab 5 (POO + JSON) | Estructura `public/ src/ plantillas/ datos/` siguiendo los labs 1–4; caso de la asociación «Manos del Oriente» | Comparé con la estructura de `lab04` | _(¿mantenemos el caso verosímil o usamos una asociación real entrevistada?)_ |
| 2 | Clases `Producto`, `Categoria`, `Catalogo` con promoción de propiedades | Clases `readonly`, enum `Talla`, método `con(...)` para editar sin mutar | Prueba con `ReflectionMethod::isPromoted()` en `verificar.php` | _(explicar por qué inmutable)_ |
| 3 | Validación y saneamiento del formulario | `validar_producto()` con coma decimal boliviana, `strip_tags`, tallas como arreglo | Pruebas de entradas inválidas (422) y de saneamiento | |
| 4 | Subida segura de la foto | Reutilizó la lógica de `lab04` (finfo, 2 MB, nombre aleatorio, fuera de `public/`) | Pruebas: PHP disfrazado de JPG, archivo de 2,2 MB, `../` | |
| 5 | Pruebas automáticas | `verificar.php` con 32 comprobaciones sobre copia temporal de datos | Ejecuté `php verificar.php` → 32/32 | |
| 6 | Error detectado | La IA calculó mal el valor esperado del inventario en 2 pruebas (10.983,50 y luego 14.163,50) | La prueba falló; recalculé a mano: 12.303,50 y 14.042,90 | Lección: los valores esperados de los reportes se verifican a mano |
| 7 | Informe APA 7 en Word y presentación | Borrador del informe (17 págs.) y presentación de 11 diapositivas con notas del orador | Revisé cada página/diapositiva renderizada | _(revisar redacción, completar nombres)_ |
| 8 | Revisión del estado del proyecto | Detectó que «1.250» se guardaba como Bs 1,25 (punto de miles) | Prueba nueva: «1.250» = 1250 | Se corrigió antes de subir al repositorio (33/33) |

## Actividad 2: de JSON a MySQL (12–13/10/2026)

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 9 | Modelo entidad-relación y script SQL | 5 tablas InnoDB (`socios`, `categorias`, `tallas`, `productos`, `producto_tallas`), 4 claves foráneas, UNIQUE y CHECK; datos de 6 socios y 14 prendas | `php bd/instalar.php`; consulté con `SUM(precio*stock)` y conteos por socio; recalculé a mano: Bs 19.538,50 y 157 unidades | _(¿por qué una tabla intermedia para tallas y no una columna «S,M,L»?)_ |
| 10 | Conexión PDO fuera del código público | `config/config.ejemplo.php` (+ `config.php` ignorado por git), `Conexion` con `ERRMODE_EXCEPTION` y `EMULATE_PREPARES = false`; acepta las variables `BD_*` del Docker del curso | Prueba: la configuración no se descarga por URL; prueba de atributos de PDO | |
| 11 | Repositorios con CRUD, validación y paginación | `ProductoRepositorio` (transacciones para prenda + tallas), `SocioRepositorio`, clase `Pagina`, `validar_socio()` | 22 pruebas de repositorios y CRUD web; paginación 8/10/5 por página | |
| 12 | Demostrar resistencia a inyección SQL | 13 pruebas de ataques (`' OR '1'='1`, `UNION`, `DROP TABLE`, `ORDER BY` malicioso, `%`) + revisión automática del código fuente + `docs/actividad-02/INYECCION_SQL.md` | Todas en verde; las tablas siguen intactas después de cada ataque | |
| 13 | Errores de la IA en sus propias pruebas | Esperaba 5 claves foráneas (son 4); suponía que el id de una prenda nueva sería 15 (un INSERT fallido por FK ya había consumido el autoincremento); enviaba «saó» sin codificar en la URL; esperaba 404 en `/../config/...` (el servidor embebido devuelve la página de inicio) | Investigué cada caso: ninguno era un error de la aplicación; se corrigieron las pruebas (la última ahora comprueba que **no se filtra el contenido**) | Lección: una prueba que falla puede estar mal escrita; hay que entender la causa antes de «arreglar» |
| 14 | Migración de los datos de la actividad 1 | `bd/migrar_json.php` con transacción y modo `--simular` | Simulación: 7 prendas omitidas (ya existían) y 1 nueva | |
| 15 | Ejecutar en el Docker del curso, organizar el trabajo en un tablero de sprint y documentar en APA 7 con el modelo del docente | Contenedores MySQL 8.4 + PHP 8.3; organización `TecnologiaWeb2` con el tablero «Manos del Oriente Board» (27 issues); capturas automáticas; informes de las actividades 1 y 2 con la maqueta del PDF modelo | En PHP 8.3 salió 56/57: `ATTR_EMULATE_PREPARES` devuelve `0` (en 8.4, `false`); se corrigió la prueba → 57/57. Revisé cada página de los informes exportados a PDF | _(completar nombres, carrera y docente en las portadas)_ |

## Reflexión (completar)

- ¿Qué parte entendimos mejor gracias a la IA?
- ¿Qué parte tuvimos que corregir o cuestionar?
- ¿Qué haríamos distinto sin la IA?
