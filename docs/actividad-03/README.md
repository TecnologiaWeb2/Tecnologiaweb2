# Actividad 3 · Usuarios, roles, transacciones y reportes

La actividad 3 se hizo primero con los **laboratorios 10 y 11** sobre el caso del docente (Apicultores Flor de la
Chiquitanía) — ese trabajo entregado está en [entregado/](entregado/) y en [`laboratorios/`](../../laboratorios/).
Después se tomó **como modelo** y se incorporó al sistema propio, Manos del Oriente.

## Del modelo a Manos del Oriente

| Lo que pide el laboratorio | En el modelo (lab 10/11) | En Manos del Oriente |
|---|---|---|
| Contraseñas con `password_hash` | `Seguridad/Autenticador.php` | `src/Seguridad.php` → `autenticar_usuario()` (rehash automático) |
| Sesión segura (HttpOnly, SameSite, regenerar id) | `Seguridad/Sesion.php` | `iniciar_sesion()`: cookie `MANOS_SESION`, `Secure` en HTTPS, 30 min de inactividad |
| Bloqueo por intentos | `DemasiadosIntentos` | 5 fallos → 15 minutos; mismo mensaje y mismo tiempo exista o no el correo |
| Roles y permisos | `Modelo/Rol.php` | Matriz `PERMISOS` (admin, operador, socia) + `exigir_usuario('permiso')` en cada página |
| CSRF en todo POST | `Seguridad/Csrf.php` | `csrf_campo()` / `exigir_csrf()` en todos los formularios POST (lo comprueba `verificar.php`) |
| XSS | `Vista/Plantilla.php` | `e()` en todas las vistas + CSP `script-src 'self'` |
| Venta en una transacción | `Ventas/RegistroVenta.php` | `src/Ventas.php` → `registrar_venta()` con `SELECT … FOR UPDATE` |
| Acopio | `Ventas/RegistroAcopio.php` | `registrar_acopio()` **con control de calidad** (entregadas / aprobadas / motivo) |
| Reportes y CSV | `Reportes/ReporteVentas.php`, `Csv.php` | `reporte_ventas()`: por mes, top 5, por socia · CSV con BOM y protección contra fórmulas |
| Usuarios | `UsuariosControlador.php` | `public/usuarios.php` (crear, cambiar rol, no cambiar el propio) y `cuenta.php` |

Además se corrigió lo que estaba a medio hacer en la carpeta (26/10): había **dos sesiones** con nombres distintos (al
entrar, la tienda «olvidaba» al usuario), el login tenía dos campos CSRF, Elena estaba vinculada a la ficha de Mario
y no se sabían las contraseñas de los usuarios de demostración.

## Usuarios de demostración

| Correo | Contraseña | Rol |
|---|---|---|
| admin@manosdeloriente.bo | `Manos-Admin-2026!` | Administración |
| operador@manosdeloriente.bo | `Manos-Ventas-2026!` | Operador |
| rosa@manosdeloriente.bo · elena@manosdeloriente.bo | `Manos-Socia-2026!` | Socia (solo su liquidación) |

Al publicar se reemplazan por contraseñas aleatorias (`herramientas/empaquetar_infinityfree.php`).

## Reportes verificados a mano (datos de `bd/08-demo.sql`)

| Mes | Ventas | Total |
|---|---|---|
| 2026-07 | 2 | Bs 965,00 (435,00 + 530,00) |
| 2026-08 | 2 | Bs 1.280,00 (360,00 + 920,00) |
| 2026-09 | 2 | Bs 1.042,50 (627,00 + 415,50) |
| **Total** | **6** | **Bs 3.287,50 · 27 prendas** |

Top 5 por unidades: ACC-300 (6), POL-001 (5), ACC-302 (4), POL-003 (3), ABR-201 (2, gana el desempate por importe).
Por socia: Mario 1.250,00 · Rosa 825,00 · Lidia 437,00 · Elena 415,50 · Juana 360,00.
Las mismas cifras las comprueba `verificar.php` (sección «Actividad 3 · Laboratorio 11»).

## Capturas

[Panel](capturas/01_panel_admin.png) · [Usuarios y roles](capturas/02_usuarios_roles.png) ·
[Ventas y acopios](capturas/03_ventas_acopios.png) · [Reportes](capturas/04_reportes.png) ·
[Operador sin permiso (403)](capturas/05_operador_sin_permiso.png)
