# Publicar en InfinityFree (producción)

InfinityFree da gratis PHP 8, MySQL/MariaDB, phpMyAdmin, `.htaccess` y certificado HTTPS. No tiene consola ni
Composer, por eso el proyecto no depende de nada que haya que instalar en el servidor.

> Se probó el paquete en una copia local que imita al hosting (Apache con `.htaccess` + la base importada desde el
> `.sql`, como hace phpMyAdmin): la tienda, la API con token Bearer, el login con las contraseñas nuevas y los bloqueos
> de archivos internos funcionaron, y la auditoría dio **18 de 18**.

## 1. Crear la cuenta y la base

1. <https://www.infinityfree.com> → **Register** → **Create Account** → elegir un subdominio gratuito, por ejemplo
   `manosdeloriente.infinityfreeapp.com` (o `.rf.gd`, `.42web.io`…).
2. Panel de la cuenta (**Control Panel / VistaPanel**) → **MySQL Databases** → crear la base `manos`.
   Anotar los 4 datos que muestra el panel: **MySQL Hostname** (`sqlNNN.infinityfree.com`), **MySQL Username**
   (`if0_XXXXXXXX`), **MySQL Password** (la de la cuenta: *Account → Show password*) y el nombre completo de la base
   (`if0_XXXXXXXX_manos`).
3. Panel → **PHP Config** → elegir **PHP 8.2 o superior** (el sistema usa clases `readonly`).

## 2. Generar el paquete (en tu PC)

```powershell
cd C:\Users\danie\OneDrive\Documentos\DanielaTW2\Proyecto\Actividad2
php herramientas/empaquetar_infinityfree.php --host=sqlNNN.infinityfree.com --usuario=if0_XXXXXXXX `
    --clave="CLAVE_DEL_PANEL" --base=if0_XXXXXXXX_manos
```

Se crea `dist/infinityfree/` (está en `.gitignore`: **nunca** se sube a GitHub):

| Archivo | Para qué |
|---|---|
| `htdocs/` | Todo lo que va a la carpeta `htdocs` del hosting (código, `.htaccess`, `config/config.php` con tus datos, fotos de demostración) |
| `manos-del-oriente-htdocs.zip` | Lo mismo comprimido |
| `manos_del_oriente.sql` | Las 13 tablas con los datos de demostración, para phpMyAdmin |
| `CREDENCIALES.txt` | Contraseñas **nuevas y aleatorias** de los 4 usuarios (las de demostración son públicas en GitHub) |

## 3. Importar la base

Panel → **MySQL Databases** → **Admin** (phpMyAdmin) de la base `if0_XXXXXXXX_manos` → pestaña **Importar** →
elegir `manos_del_oriente.sql` → **Importar**. Deben aparecer 13 tablas.

## 4. Subir los archivos

Opción A — **Administrador de archivos** (Panel → *Online File Manager*): entrar a `htdocs/`, borrar el `index2.html`
de bienvenida, subir `manos-del-oriente-htdocs.zip` y **extraerlo ahí mismo** (deben quedar `public/`, `src/`, `.htaccess`… dentro de `htdocs/`).

Opción B — **FileZilla** con los datos de *FTP Details* del panel (servidor `ftpupload.net`, puerto 21): arrastrar el
**contenido** de `dist/infinityfree/htdocs/` a `/htdocs/`. Activar «mostrar archivos ocultos» para que suban los `.htaccess`.

## 5. HTTPS

Panel → **Free SSL Certificates** → emitir el certificado para el subdominio (tarda unos minutos). Después, en
`htdocs/.htaccess`, quitar el `#` de las 3 líneas de «Forzar HTTPS». La cookie de sesión pasa a ser `Secure` sola
(`src/Seguridad.php` detecta HTTPS).

## 6. Comprobar

1. Abrir `https://tu-subdominio/` → la tienda con las 14 prendas y sus fotos.
2. `/entrar.php` con `admin@manosdeloriente.bo` y la contraseña de `CREDENCIALES.txt`. Después, **cada usuario cambia
   su contraseña** en *Mi cuenta* y se borra `CREDENCIALES.txt`.
3. `/tokens.php` → emitir un token nuevo para las pruebas de la API (los tokens locales no existen en producción).
4. Deben dar **403**: `/src/Seguridad.php`, `/config/config.php`, `/bd/esquema.sql`, `/datos/fotos/`.
5. Auditoría de la URL publicada (ver la nota del antibot):
   ```powershell
   php herramientas/auditar.php https://tu-subdominio --cookie="__test=VALOR_COPIADO_DEL_NAVEGADOR"
   ```
   Escribe `docs/proyecto-final/INFORME_AUDITORIA_PUBLICADO.md`.

## Limitaciones de InfinityFree (importante para la defensa)

- **Antibot:** la primera visita de cada navegador pasa por un desafío JavaScript que crea la cookie `__test`. Un
  navegador lo resuelve solo; **`curl`, Postman o un script reciben esa página en lugar del JSON**. Por eso la API se
  demuestra desde el navegador (la vitrina y `/api/v1/productos` funcionan) o pasando la cookie copiada
  (F12 → Aplicación → Cookies → `__test`): `curl.exe -b "__test=…" https://…/api/v1/productos`. Si el docente audita
  con su script, conviene avisarle y darle la cookie, o mostrar la auditoría local (18/18) y la de la copia.
- Sin correo saliente, sin tareas programadas y con límite de procesos: el sistema no necesita ninguno.
- La extensión SOAP puede no estar disponible: `/soap.php` responde 501 con un mensaje y la alternativa REST
  `/datos/productos/{codigo}` sigue funcionando.
- La base de datos **no es accesible desde fuera** del hosting: los scripts `php bd/instalar.php` y `verificar.php`
  se ejecutan en local; en producción se usa phpMyAdmin.

## Respaldo

phpMyAdmin → **Exportar** (SQL) una vez por semana y guardar el archivo fuera del hosting. Las fotos subidas están en
`htdocs/datos/fotos/` (descargarlas por FTP).
