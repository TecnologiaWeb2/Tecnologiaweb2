# CHECKLIST del Trabajo Final Integrador

Cada indicador de la rúbrica (20 pts) con su evidencia concreta. ✅ hecho · ⏳ pendiente del equipo.

## Solución conectada a la base de datos (6 pts)

- [x] Módulos obligatorios: catálogo, prendas, socias, usuarios/roles, ventas, acopios, reportes, API, AJAX
- [x] **Módulo nuevo 1**: liquidación y pagos con control de calidad → `src/Liquidaciones.php`, `public/liquidaciones.php`
- [x] **Módulo nuevo 2**: pedidos con reserva de stock → `src/Pedidos.php`, `public/pedidos.php`, `public/importar.php`
- [x] Normalización: 13 tablas, 18 FK, tallas y detalles en tablas propias → [MODELO_DATOS.md](MODELO_DATOS.md)
- [x] Transacciones: venta, acopio, pedido, entrega, cancelación y pago (`en_transaccion()` + `FOR UPDATE`)
- [x] Restricciones en la base: `CHECK (reservado <= stock)`, `aprobadas <= entregadas`, `importe > 0`, `UNIQUE` en referencias
- [x] Pruebas propias de cada módulo → `pruebas/trabajo_final.php` (dentro de `php verificar.php`: 136/136)

## Seguridad y niveles de acceso (6 pts)

- [x] Auditoría local al 100 % → `php herramientas/auditar.php http://127.0.0.1:8000` (18/18)
- [ ] Auditoría de la URL publicada al 100 % → `INFORME_AUDITORIA_PUBLICADO.md` (⏳ al publicar)
- [x] Roles admin / operador / socia con matriz de permisos; cada página se protege sola
- [x] CSRF en todos los POST (comprobado formulario por formulario)
- [x] Consultas preparadas reales (`ATTR_EMULATE_PREPARES = false`); ningún SQL concatena datos del usuario
- [x] Credenciales fuera del código: `config/config.php` en `.gitignore`; contraseñas nuevas al publicar
- [x] Cabeceras: CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS en HTTPS; cookie HttpOnly + SameSite (+ Secure)
- [x] Privacidad: la socia ve solo su liquidación (web y API); el JSON público no muestra CI ni teléfono

## Información veraz, publicación y defensa (8 pts)

- [ ] Sistema publicado con HTTPS en InfinityFree (⏳ seguir [DESPLIEGUE_INFINITYFREE.md](DESPLIEGUE_INFINITYFREE.md))
- [x] Reportes verificados contra los datos de prueba (cifras a mano en [MODELO_DATOS.md](MODELO_DATOS.md) y en `verificar.php`)
- [x] API documentada → [endpoints.md](../actividad-04/endpoints.md) + [openapi.yaml](../openapi.yaml)
- [x] Manual de usuario → [Manual_Usuario_Manos_del_Oriente.pdf](Manual_Usuario_Manos_del_Oriente.pdf)
- [x] Informe técnico → [Informe_Tecnico_Proyecto_Final.pdf](Informe_Tecnico_Proyecto_Final.pdf)
- [x] Presentación `.pptx` → [Presentacion_Proyecto_Final.pptx](Presentacion_Proyecto_Final.pptx)
- [ ] Bitácora de IA con la **reflexión de cada integrante** (⏳ completar en `docs/BITACORA_IA.md`)
- [ ] Commits de todos los integrantes (⏳ cada uno sube al menos su parte)
- [ ] Ensayo de la defensa de 15 min (guion en [PLAN.md](PLAN.md))

## Nivel estratégico (100 %)

- [x] Optimización medida: SOAP vs REST (1.745 B vs 253 B), ETag/304, índices en fechas y estados
- [x] Auditoría y respaldo documentados (sección «Respaldo» del despliegue)
- [ ] Validación con la organización: caso verosímil declarado; si hay entrevista real, adjuntar acta (⏳ opcional)
