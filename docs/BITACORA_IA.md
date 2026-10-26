# Bitácora de uso de IA: proyecto «Manos del Oriente»

**Integrantes:** Daniela Cayo Mamani (dah0404) · Juan Jose Vicente Cossio (JUANJOVICO2021) · Ronald Salvatierra Flores (RonSalvet) · Yordy Tolavi Ruiz (tolavi) · Alejandro Cadario Lopez (leinn04)
**Docente:** M.Sc. Ing. Jimmy Nataniel Requena Llorentty  **Herramienta:** Claude Code (modelo Claude Opus 5.5)  **Repositorio:** https://github.com/TecnologiaWeb2/Tecnologiaweb2

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
| 15 | Ejecutar en el Docker del curso, organizar el trabajo en un tablero de sprint y documentar en APA 7 con el modelo del docente | Contenedores MySQL 8.4 + PHP 8.3; organización `TecnologiaWeb2` con el tablero «Manos del Oriente Board» (27 issues); capturas automáticas; informes de las actividades 1 y 2 con la maqueta del PDF modelo | En PHP 8.3 salió 56/57: `ATTR_EMULATE_PREPARES` devuelve `0` (en 8.4, `false`); se corrigió la prueba → 57/57. Revisé cada página de los informes exportados a PDF | Portadas: carrera y docente completados; faltan los nombres de 2 integrantes (RonSalvet y leinn04) |
| 16 | Cierre de las actividades 1 y 2: repartir el trabajo pendiente entre los 5 integrantes, completar portadas y crear el video de 60 s de la Actividad 1 | Issues #28–#31 asignados (video, revisión del informe, guion de la defensa, demostración en vivo) y las tareas de las actividades 3–4 y del proyecto final repartidas; video de 60 s armado con capturas reales de la aplicación (registro con foto, filtros, validación 422 y las 33 pruebas) y exportado con PowerPoint | Con el proyecto movido a `DanielaTW2\Proyecto`, las pruebas siguieron en verde: 33/33 (act. 1) y 57/57 (act. 2); la duración del video se comprobó en las propiedades del archivo (1:00) | El video no tiene narración: si el grupo prefiere, se graba una versión con voz (issue #28) |
| 17 | Mejorar el diseño: Bootstrap 5, modales para ver, registrar y editar, confirmación y avisos en la esquina al eliminar, y una paleta pastel de tienda de ropa | Bootstrap 5.3.3 e íconos guardados en el proyecto (sin depender de internet); `public/js/app.js` carga las páginas reales dentro del modal con `X-Parcial: 1`; avisos con `avisar()`; paleta crema, rosa empolvado, lavanda y menta | Prueba de punta a punta con Edge automatizado: detalle, registro con errores dentro del modal, registro, edición y eliminación con aviso; `verificar.php` siguió en 57/57. **La prueba encontró un error previo:** la sesión con el token CSRF se iniciaba a mitad del HTML y su cookie no llegaba, así que eliminar desde la gestión daba «Solicitud rechazada (CSRF)» si era la primera página visitada. Se corrigió iniciando la sesión en `plantillas/cabecera.php` | Sin JavaScript todo sigue funcionando como páginas normales (mejora progresiva) |
| 18 | Rehacer el informe técnico y la presentación de la Actividad 2 con la nueva interfaz y eliminar las versiones anteriores | Informe APA 7 (37 págs., 11 tablas y 15 figuras) con la sección «Interfaz con Bootstrap 5», la prueba de punta a punta, el error de sesión y CSRF, y los requisitos RF-10 y RNF-11; presentación de 14 diapositivas con la paleta pastel; capturas de docs/actividad-02/capturas tomadas de nuevo | Revisé el índice, las referencias cruzadas a tablas y figuras y cada diapositiva exportada a PDF | Nombres de los 5 integrantes completos en informes y presentaciones |

## Actividad 3: usuarios, roles, ventas y reportes (labs 10–11) — integración del 26/10/2026

Los labs 10 y 11 se resolvieron antes sobre el caso del docente (`laboratorios/`, `docs/actividad-03/entregado/`).
El 26/10 se pidió a la IA **tomar esa actividad como modelo e incorporarla a Manos del Oriente**.

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 19 | Revisar el estado del proyecto (había cambios sin commitear del 26/10 de madrugada) | Diagnóstico: dos sesiones con nombres distintos (`PHPSESSID` y `MANOS_SESION`) que hacían «perder» al usuario según la primera página visitada; login con dos campos CSRF; `cambiar_estado_pedido()` usaba `$line` en lugar de `$linea` (entregar un pedido fallaba); el usuario de Elena apuntaba a la ficha de Mario; contraseñas de demostración desconocidas; reportes vacíos por falta de ventas de prueba | `php verificar.php` daba 34/57 antes de tocar nada | Se rehízo la seguridad en un solo archivo (`src/Seguridad.php`) en lugar de parchar |
| 20 | Usuarios, roles y CSRF siguiendo el lab 10 | Matriz `PERMISOS` con 3 roles, `exigir_usuario()` en cada página, bloqueo tras 5 intentos con el mismo mensaje exista o no el correo, regeneración del id de sesión, cambio de contraseña y alta de usuarios | 14 pruebas nuevas (cookie HttpOnly/SameSite, id que cambia, 12 rutas protegidas, cada formulario POST con token, redirección abierta `//sitio-malo.com` rechazada) | |
| 21 | Ventas, acopios y reportes siguiendo el lab 11 | Transacciones con `FOR UPDATE` en orden fijo (evita interbloqueos), totales en centavos, acopio con **control de calidad**, reporte por mes/top 5/por socia y CSV con BOM | Calculé a mano las 6 ventas de `bd/08-demo.sql` (Bs 3.287,50) y lo vendido por socia; una venta con la 2.ª línea sin stock no deja nada guardado | El desempate del top 5 (dos prendas con 2 unidades) se definió por importe |
| 22 | Error de la IA | Con consultas preparadas reales PDO no admite repetir `:desde` en la misma consulta; la IA lo había escrito así en el reporte | Lo detecté al leer el código antes de probar | Se separó en dos consultas |

## Actividad 4: intercambio de datos, API REST y AJAX (labs 12–14) — 26/10/2026

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 23 | Lab 12 con la guía integral | JSON con ISO 8601 y precios como texto, XML con XMLWriter, RSS 2.0, SOAP y REST de la misma prenda, pedidos JSON validados por tipos y XML con XSD (`LIBXML_NONET`) | Pruebas de escape (`Bolso & cartera <tejida>`), XML con entidad externa (XXE), fecha 30/02; medición propia SOAP 1.745 B vs REST 253 B | El JSON público no expone CI ni teléfono de las socias |
| 24 | Lab 13: API REST | `src/Api.php`: CRUD, Bearer con hash SHA-256, problem+json, ETag/304, OPTIONS, 405 con `Allow`, 409, 415, 422 sin «corregir en silencio»; `docs/openapi.yaml` | 20 pruebas con tokens emitidos en la prueba misma; las evidencias ocultan y revocan los tokens | Se reutiliza `validar_producto()` de la web: API y formulario nunca discrepan |
| 25 | Lab 14: fetch | Vitrina con `AbortController`, pausa de 250 ms, combobox WAI-ARIA, RSS con `DOMParser`, validación en vivo; `app.js` dejó de usar `innerHTML` (DOMParser para el HTML propio) | Edge automatizado: con red lenta se cancelaron 3 búsquedas y ganó «jean»; `aria-activedescendant` = opción seleccionada; 0 errores de consola o de CSP (12/12) | |
| 26 | Errores encontrados por las pruebas | Un `id="contenido"` repetido (el `<main>` y el textarea de importar); las fotos de demostración estaban **cruzadas** (la polera mostraba el vestido, y POL-003 una captura de terminal) | La prueba en navegador falló; comparé cada foto con `fotosdeaprendas/` por su hash MD5 | Se reasignaron las 14 fotos |

## Trabajo final: 2 módulos y publicación — 26/10/2026

| # | Lo que pedí | Lo que entregó la IA | Cómo lo verifiqué | Decisión / cambio propio |
|---|---|---|---|---|
| 27 | Dos módulos nuevos (clase 15) | Módulo 1: liquidación y pagos con control de calidad (saldo calculado, pago que no supera el saldo, referencia única); módulo 2: pedidos con reserva (`CHECK reservado <= stock`, entregar = venta en una transacción) | Cifras a mano en `docs/proyecto-final/MODELO_DATOS.md` (saldo total Bs 2.330,00); 18 pruebas; MariaDB 10.11 y MySQL 8.4 → 136/136 | _(¿el precio por prenda lo fija la directiva o se negocia con cada socia?)_ |
| 28 | Preparar la publicación en InfinityFree | `herramientas/empaquetar_infinityfree.php` (htdocs, .zip, .sql para phpMyAdmin, contraseñas nuevas), `.htaccess` que solo deja ver `public/`, `herramientas/auditar.php` | Copia local con Apache + `.htaccess` + la base importada del `.sql`: auditoría 18/18, login con las claves nuevas, Bearer que llega a PHP | **La IA cometió dos errores que la prueba detectó:** el hash nuevo se dañaba (`$2y` se leía como referencia en `preg_replace`) y la regla bloqueaba `/vendor/` (Bootstrap). El antibot de InfinityFree impide auditar con `curl` sin la cookie `__test` |

## Reflexión (completar)

- ¿Qué parte entendimos mejor gracias a la IA?
- ¿Qué parte tuvimos que corregir o cuestionar?
- ¿Qué haríamos distinto sin la IA?
