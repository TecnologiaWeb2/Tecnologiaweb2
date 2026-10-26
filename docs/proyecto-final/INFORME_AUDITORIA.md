# Informe de auditoría

- **Sitio:** http://127.0.0.1:8000 (servidor local de desarrollo)
- **Fecha:** 26/10/2026 19:03
- **Resultado:** 18 de 18 (100 %)

| Grupo | Comprobación | Estado | Dónde corregir |
|---|---|---|---|
| Sistema | La tienda responde 200 con HTML | ✅ |  |
| Seguridad | Content-Security-Policy sin scripts externos ni en línea | ✅ |  |
| Seguridad | X-Frame-Options DENY y X-Content-Type-Options nosniff | ✅ |  |
| Seguridad | Cookie de sesión HttpOnly y SameSite | ✅ |  |
| Seguridad | El formulario de inicio de sesión lleva token CSRF | ✅ |  |
| Seguridad | Iniciar sesión sin token CSRF se rechaza (403) | ✅ |  |
| Seguridad | Las páginas internas exigen sesión (303 a /entrar.php) | ✅ |  |
| Seguridad | Código, configuración, SQL y fotos NO se descargan por URL | ✅ |  |
| Seguridad | Una inyección SQL en la URL no muestra errores de la base (404 limpio) | ✅ |  |
| API | GET /api/v1/productos: JSON paginado (datos, meta, enlaces) | ✅ |  |
| API | Parámetro inválido → 422 application/problem+json | ✅ |  |
| API | Escribir sin token → 401 con WWW-Authenticate | ✅ |  |
| API | OPTIONS → 204 con cabeceras CORS | ✅ |  |
| API | ETag + If-None-Match → 304 sin cuerpo | ✅ |  |
| API | Contrato OpenAPI 3.1 publicado | ✅ |  |
| Intercambio | /datos/catalogo.json responde application/json | ✅ |  |
| Intercambio | /datos/catalogo.xml responde application/xml | ✅ |  |
| Intercambio | /noticias.rss responde application/rss+xml | ✅ |  |
