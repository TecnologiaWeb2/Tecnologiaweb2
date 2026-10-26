# Laboratorios 10 a 14 (actividades 3 y 4)

Laboratorios del curso resueltos por el equipo sobre el caso del docente (**Apicultores Flor de la Chiquitanía**).
Cada uno es independiente, con su propio `docker-compose.yml`, su verificador y su hoja de trabajo resuelta.
Lo aprendido en cada uno se llevó al sistema de **Manos del Oriente** (carpetas `src/` y `public/` de este repositorio).

| Lab | Actividad | Tema | Hoja resuelta | Dónde quedó en Manos del Oriente |
|---|---|---|---|---|
| [lab10](lab10/) | 3 | Usuarios, sesiones, roles y CSRF | [HOJA_TRABAJO_resuelta.md](lab10/HOJA_TRABAJO_resuelta.md) · [guía completada](lab10/documentacion/) | `src/Seguridad.php`, `public/entrar.php`, `usuarios.php`, `cuenta.php` |
| [lab11](lab11/) | 3 | Ventas, transacciones, reportes y CSV | [HOJA_TRABAJO_resuelta.md](lab11/HOJA_TRABAJO_resuelta.md) · [guía completada](lab11/documentacion/) | `src/Ventas.php`, `public/ventas.php`, `reportes.php`, `reportes.csv.php` |
| [lab12](lab12/) | 4 | JSON, XML, XSD, RSS y SOAP/REST | [HOJA_TRABAJO_resuelta.md](lab12/HOJA_TRABAJO_resuelta.md) | `src/Intercambio.php`, `src/Pedidos.php` (importación), `bd/esquemas/pedido.xsd` |
| [lab13](lab13/) | 4 | API REST con tokens, problem+json, ETag y OpenAPI | [HOJA_TRABAJO_resuelta.md](lab13/HOJA_TRABAJO_resuelta.md) | `src/Api.php`, `public/api.php`, `public/tokens.php`, `docs/openapi.yaml` |
| [lab14](lab14/) | 4 | AJAX con fetch, AbortController, autocompletar accesible | [HOJA_TRABAJO_resuelta.md](lab14/HOJA_TRABAJO_resuelta.md) | `public/js/api.js`, `catalogo.js`, `validacion-producto.js` |

## Ejecutar un laboratorio

```powershell
cd laboratorios\lab13
docker compose up -d --build        # MySQL en 127.0.0.1:3307 y PHP en 127.0.0.1:8000
composer install                    # solo si se ejecuta PHP fuera de Docker (vendor/ no se sube)
php verificar.php
```

Los labs usan los mismos puertos (8000 y 3307): detener uno (`docker compose down`) antes de iniciar otro.
`vendor/`, `node_modules/` y el archivo de tokens de prueba no se suben al repositorio (`.gitignore`).
Los labs 1 a 9 están en `DanielaTW2\Laboratorios` (actividades 1 y 2).
