# Manos del Oriente: sistema web de la asociación

Sistema web de la **Asociación de Confeccionistas Textiles «Manos del Oriente»** (caso verosímil, Chiquitanía,
Santa Cruz, Bolivia), desarrollado de forma incremental en **Tecnología Web II, UPDS** y llevado al **Trabajo Final
Integrador**: tienda pública, gestión interna con roles, ventas, reportes, intercambio de datos, API REST, interfaz
asíncrona y dos módulos nuevos para la asociación.

| Actividad | Tema | Estado | Documentación |
|---|---|---|---|
| 1 | Catálogo en PHP 8 con POO, formularios validados y JSON | ✅ etiqueta [`actividad-1`](../../tree/actividad-1) | [docs/actividad-01](docs/actividad-01) |
| 2 | De JSON a MySQL: PDO, repositorios, CRUD, paginación, inyección SQL | ✅ etiqueta [`actividad-2`](../../tree/actividad-2) | [docs/actividad-02](docs/actividad-02) |
| 3 | Usuarios, roles, CSRF, transacciones y reportes (labs 10–11) | ✅ | [docs/actividad-03](docs/actividad-03) |
| 4 | JSON/XML/RSS/SOAP, API REST y AJAX con fetch (labs 12–14) | ✅ | [docs/actividad-04](docs/actividad-04) · [endpoints](docs/actividad-04/endpoints.md) |
| Final | 2 módulos nuevos + publicación en InfinityFree | ✅ código · ⏳ publicar | [docs/proyecto-final](docs/proyecto-final) |

**Pruebas:** `php verificar.php` → **136 de 136** (MariaDB 10.11 y MySQL 8.4) · navegador 12/12 · auditoría 18/18.

## Los dos módulos del trabajo final

1. **Liquidación y pagos a socias con control de calidad** (`/liquidaciones.php`). Cada entrega registra prendas
   entregadas, aprobadas y el motivo del rechazo; se paga solo lo aprobado. Saldo = liquidado − pagado (calculado, nunca
   guardado). Un pago no puede superar el saldo. Cada socia entra con su usuario y ve **solo su** liquidación.
2. **Pedidos de compradores con reserva de stock** (`/pedidos.php`, `/importar.php`, `POST /api/v1/pedidos`).
   El pedido reserva las prendas (disponible = stock − reservado); al **entregarlo** se registra la venta en una sola
   transacción; al **cancelarlo** se libera la reserva. La base lo garantiza con `CHECK (reservado <= stock)`.

Problema, objetivos medibles y requerimientos: [BRIEF](docs/proyecto-final/BRIEF.md) ·
[REQUERIMIENTOS](docs/proyecto-final/REQUERIMIENTOS.md) · [MODELO_DATOS](docs/proyecto-final/MODELO_DATOS.md) ·
[PLAN](docs/proyecto-final/PLAN.md) · [CHECKLIST-TFI](docs/proyecto-final/CHECKLIST-TFI.md).

## Requisitos

- PHP **8.2 o superior** con `pdo_mysql`, `mbstring`, `fileinfo`, `dom`, `xmlwriter` (y `curl`, `soap` para pruebas y SOAP).
- MySQL 8 o MariaDB 10.4+.

## Ejecutar en local

```bash
cp config/config.ejemplo.php config/config.php     # completar usuario y clave de MySQL (no se sube a git)
php bd/instalar.php                                # crea la base con los datos de demostración y copia las fotos
php -S 127.0.0.1:8000 -t public public/enrutador.php
php verificar.php                                  # usa su propia base de pruebas y la borra al terminar
```

El enrutador hace con `php -S` lo mismo que `public/.htaccess` en Apache (rutas `/api/v1/…`, `/datos/…`, `/noticias.rss`).
Cada valor de la conexión se puede dar por variable de entorno: `TIENDA_BD_HOST`, `_PUERTO`, `_NOMBRE`, `_USUARIO`, `_CLAVE`.

### Con Docker

```bash
docker compose up --build          # http://127.0.0.1:8000 (Apache con los mismos .htaccess que InfinityFree)
docker compose down -v             # detener y borrar la base
```

### Usuarios de demostración

| Correo | Contraseña | Rol |
|---|---|---|
| admin@manosdeloriente.bo | `Manos-Admin-2026!` | Administración (todo, usuarios y tokens) |
| operador@manosdeloriente.bo | `Manos-Ventas-2026!` | Operador (prendas, socias, ventas, acopios, pedidos, pagos, reportes) |
| rosa@manosdeloriente.bo · elena@manosdeloriente.bo | `Manos-Socia-2026!` | Socia (solo su liquidación) |

Son públicas: **al publicar se generan contraseñas nuevas** (ver abajo).

## Publicar en InfinityFree

```powershell
php herramientas/empaquetar_infinityfree.php --host=sqlNNN.infinityfree.com --usuario=if0_XXXXXXXX --clave="…" --base=if0_XXXXXXXX_manos
```

Genera `dist/infinityfree/` (fuera de git): `htdocs/` para subir, el `.zip`, `manos_del_oriente.sql` para phpMyAdmin y
`CREDENCIALES.txt` con contraseñas aleatorias. Paso a paso, HTTPS, el antibot de InfinityFree y el respaldo:
[DESPLIEGUE_INFINITYFREE.md](docs/proyecto-final/DESPLIEGUE_INFINITYFREE.md).
Auditoría del sitio en marcha: `php herramientas/auditar.php https://tu-dominio --cookie="__test=…"`.

## API REST

Base `/api/v1` · lectura pública · escritura con `Authorization: Bearer mdo_…` (emitido en `/tokens.php`) · errores
`application/problem+json` · contrato [OpenAPI 3.1](docs/openapi.yaml) (`GET /api/v1/openapi.yaml`).

| Método | Ruta | Resultado |
|---|---|---|
| GET | `/productos?pagina&por_pagina&q&categoria&talla&disponibles&orden` | 200 datos + meta + enlaces · 422 |
| GET | `/productos/sugerencias?q=` | 200 · 422 |
| GET | `/productos/{codigo}` | 200 con ETag · 304 · 404 |
| POST | `/productos` | 201 + Location · 400 · 401 · 403 · 409 · 415 · 422 |
| PUT | `/productos/{codigo}` | 200 |
| DELETE | `/productos/{codigo}` | 204 (solo administración) · 409 si tiene ventas |
| OPTIONS | `/productos` | 204 preflight CORS |
| GET | `/socios/{id}/liquidacion` | 200 (la socia, solo la suya) |
| GET · POST | `/pedidos` · GET `/pedidos/{id}` | 200 · 201 (reserva stock) |

Intercambio (lab 12): `/datos/catalogo.json`, `/datos/catalogo.xml`, `/noticias.rss`, `/datos/productos/{codigo}`,
`/soap.php` (cliente: `php herramientas/soap_cliente.php`). Fichas completas: [endpoints.md](docs/actividad-04/endpoints.md).

## Seguridad

Consultas preparadas reales · contraseñas con `password_hash` · CSRF en todos los POST · sesión HttpOnly + SameSite
(+ Secure en HTTPS), regenerada al entrar y cerrada tras 30 min · bloqueo tras 5 intentos · roles con permiso por
página · CSP sin scripts externos ni en línea · `X-Frame-Options`, `nosniff` · `.htaccess` que solo deja ver `public/` ·
tokens guardados como SHA-256 · la socia solo ve lo suyo · sin `innerHTML` en el JavaScript.

## Estructura

```
├── public/                 ← lo único visible por URL
│   ├── index.php           vitrina (filtros, paginación; AJAX con js/catalogo.js)
│   ├── admin.php, nuevo.php, editar.php, eliminar.php, producto.php, foto.php     prendas
│   ├── socios.php, socio_form.php, socio_eliminar.php                             socias
│   ├── entrar.php, salir.php, cuenta.php, usuarios.php, tokens.php                actividad 3 / API
│   ├── ventas.php, reportes.php, reportes.csv.php                                 actividad 3
│   ├── pedidos.php, importar.php, liquidaciones.php                               trabajo final
│   ├── api.php, catalogo.json.php, catalogo.xml.php, noticias.rss.php, producto-api.php, soap.php   actividad 4
│   ├── .htaccess, enrutador.php        rutas para Apache / php -S
│   └── js/ (app.js, api.js, catalogo.js, validacion-producto.js) · css/ · vendor/ (Bootstrap local)
├── src/                    Seguridad, Ventas, Intercambio, Api, Pedidos, Liquidaciones, Modelo/, Repositorio/…
├── plantillas/             cabecera, pie, formularios
├── bd/                     esquema.sql … 08-demo.sql, instalar.php, esquemas/pedido.xsd, fotos-demo/
├── pruebas/                actividad3.php, actividad4.php, trabajo_final.php (los incluye verificar.php)
├── herramientas/           empaquetar_infinityfree.php, auditar.php, evidencias_api.php, soap_cliente.php, consumir_publico.php
├── laboratorios/           lab10 … lab14 resueltos (caso del docente)
├── docs/                   actividad-01 … 04, proyecto-final, openapi.yaml, BITACORA_IA.md
└── verificar.php, Verificador.php
```

## Integrantes

| Nombre | GitHub | Rol |
|---|---|---|
| Daniela Cayo Mamani | dah0404 | frontend |
| Juan Jose Vicente Cossio | JUANJOVICO2021 | backend |
| Ronald Salvatierra Flores | RonSalvet | |
| Yordy Tolavi Ruiz | tolavi | backend |
| Alejandro Cadario Lopez | leinn04 | |

Docente: M.Sc. Ing. Jimmy Nataniel Requena Llorentty · Bitácora de IA: [docs/BITACORA_IA.md](docs/BITACORA_IA.md)
