# Requerimientos

## Actores

| Actor | Rol en el sistema | Puede |
|---|---|---|
| Visitante / comprador | (sin sesión) | Ver la tienda, buscar, filtrar, leer el RSS y los datos abiertos (JSON/XML/API de lectura) |
| Secretaría de ventas | `operador` | Prendas (sin eliminar), socias, ventas, acopios con control de calidad, pedidos, pagos, reportes |
| Directiva | `admin` | Todo lo anterior + eliminar, usuarios y roles, tokens de la API |
| Socia | `socio` | Solo su propia liquidación (entregas, rechazos, pagos y saldo) |
| Sistema externo (tienda en línea, app de feria) | token de la API | Lo que permita el rol del usuario dueño del token |

## Requerimientos funcionales

| Id | Requerimiento | Módulo | Prueba |
|---|---|---|---|
| RF01 | Catálogo público con búsqueda, filtros, orden y paginación sin recargar | Base / lab 14 | `verificar.php` + navegador |
| RF02 | CRUD de prendas con foto validada y de socias | Base (act. 1–2) | `verificar.php` |
| RF03 | Iniciar y cerrar sesión; bloqueo tras 5 intentos; cambio de contraseña | Act. 3 | «Laboratorio 10» |
| RF04 | Usuarios y roles; cada página exige su permiso | Act. 3 | «Laboratorio 10» |
| RF05 | Registrar ventas de varias prendas en una transacción | Act. 3 | «Laboratorio 11» |
| RF06 | Reportes por fechas (mes, top 5, por socia) y CSV | Act. 3 | «Laboratorio 11» |
| RF07 | Catálogo JSON/XML, RSS, SOAP y REST para otros sistemas | Act. 4 | «Laboratorio 12» |
| RF08 | API REST con CRUD, tokens, errores problem+json y OpenAPI | Act. 4 | «Laboratorio 13» |
| **RF09** | **Registrar acopios con control de calidad (entregadas, aprobadas, motivo)** | **Módulo 1** | «Módulo 1» |
| **RF10** | **Calcular liquidado, pagado y saldo de cada socia** | **Módulo 1** | «Módulo 1» |
| **RF11** | **Registrar pagos sin superar el saldo y con referencia única** | **Módulo 1** | «Módulo 1» |
| **RF12** | **La socia consulta solo su liquidación (web y API)** | **Módulo 1** | «Módulo 1» |
| **RF13** | **Registrar pedidos que reservan stock (web, JSON/XML o API)** | **Módulo 2** | «Módulo 2» |
| **RF14** | **Confirmar, entregar (venta en una transacción) o cancelar (libera reserva)** | **Módulo 2** | «Módulo 2» |
| **RF15** | **Impedir vender o editar el stock por debajo de lo reservado** | **Módulo 2** | «Módulo 2» |

## Requerimientos no funcionales

| Id | Requerimiento | Cómo se cumple |
|---|---|---|
| RNF01 | Seguridad | Consultas preparadas reales, `password_hash`, CSRF, cookie HttpOnly/SameSite/Secure, CSP, roles, archivos internos bloqueados |
| RNF02 | Integridad | InnoDB, 18 claves foráneas, `UNIQUE`, `CHECK` (reservado ≤ stock, aprobadas ≤ entregadas, importe > 0), transacciones con `FOR UPDATE` |
| RNF03 | Privacidad | La API y el JSON no exponen CI ni teléfono; la socia solo ve lo suyo (filtro en la consulta) |
| RNF04 | Publicación | PHP 8.2+ y MySQL 8 / MariaDB 10.4+; InfinityFree con `.htaccess` |
| RNF05 | Usabilidad | Bootstrap 5, modales, avisos, versión móvil, autocompletar accesible (WAI-ARIA) |
| RNF06 | Verificabilidad | 136 comprobaciones automáticas + 12 en navegador + auditoría 18/18 |

## Historias de usuario de los módulos nuevos

- *Como secretaría*, al recibir 20 poleras de Rosa y aprobar 18, quiero anotar el motivo de los 2 rechazos, para que
  ella vea por qué se le pagan 18. **Criterio:** stock +18, liquidado +18 × precio, motivo obligatorio si hay rechazos.
- *Como socia*, quiero entrar con mi correo y ver mis entregas, rechazos, pagos y saldo, sin ver los de las demás.
- *Como secretaría*, cuando una boutique encarga prendas, quiero reservarlas para no venderlas en la feria.
  **Criterio:** «disponible» = stock − reservado; una venta o un pedido que lo supere se rechaza sin guardar nada.
- *Como secretaría*, al entregar el pedido quiero que se registre la venta y baje el stock en un solo paso.
