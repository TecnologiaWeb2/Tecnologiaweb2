# Laboratorio 10 — Hoja de trabajo: usuarios, sesiones, roles y CSRF

Nombre: ______________________   Fecha: ___/___/2026

```
docker compose up -d && composer dump-autoload
php verificar.php                        # agrega la tabla usuarios → 5 de 15 al inicio
php -S 127.0.0.1:8000 -t public public/index.php
```
Usuarios de prueba (solo laboratorio): `admin@florchiquitania.bo` / `Panal-2026!` · `operador@florchiquitania.bo` /
`Colmena-2026!` · `elena@florchiquitania.bo` / `Polen-2026!` (socia).

## Parte A — Contraseñas
1. En la consola de MySQL: `SELECT correo, clave_hash FROM usuarios;`. ¿Se puede saber la contraseña? ¿Qué significan
   `$2y$`, `12$` y el resto?
2. Ejecuta dos veces `php -r 'echo password_hash("Panal-2026!", PASSWORD_DEFAULT), PHP_EOL;'`. ¿Salen iguales? ¿Por qué
   `password_verify` funciona igual?
3. ¿Por qué no usar `md5()` o `sha1()`? Mide cuánto tarda `password_hash` con `cost` 10, 12 y 14.
4. TODO 1: el operador tiene un hash con `cost` 10. ¿Qué hace `password_needs_rehash` y por qué solo se puede
   actualizar el hash al iniciar sesión?

## Parte B — Sesión (TODO 2 y 3)
1. Antes del TODO 3, mira la cabecera `Set-Cookie` de `/entrar` (DevTools > Red). Después, ¿qué atributos aparecen?
   Explica `HttpOnly`, `SameSite=Lax` y `Secure`.
2. TODO 2: ¿qué es la **fijación de sesión** y cómo la evita `session_regenerate_id(true)`?
3. ¿Qué guarda `$_SESSION['usuario']`? ¿Por qué NO se guarda el hash de la contraseña?
4. ¿Qué pasa tras 30 minutos sin actividad?

## Parte C — CSRF (TODO 4)
1. Explica el ataque con un ejemplo: estás conectado como administrador y abres otra página que envía un formulario
   oculto a `/productos/MIE-500/eliminar`. ¿Qué cookie enviaría el navegador?
2. Antes del TODO 4, con `curl -X POST` (sin token) contra una ruta protegida, ¿qué responde? ¿Y después?
3. ¿Por qué el token se compara con `hash_equals` y no con `===`?
4. ¿Qué aporta `SameSite=Lax` y por qué igual se usa el token?

## Parte D — Roles y permisos (TODO 5)
1. Completa la matriz en `Rol::puede()`. ¿Qué significa "negar por defecto"?
2. Entra como operador: ¿puede eliminar? ¿Ve el botón? ¿Qué pasa si envía el POST igual?
3. Entra como Elena: ¿qué productos ve? ¿Dónde se aplica ese filtro: en la plantilla o en la consulta?
4. ¿Por qué ocultar el botón **no** es suficiente?

## Parte E — Fuerza bruta y redirecciones (TODO 6 y 7)
1. Prueba 6 contraseñas incorrectas seguidas. ¿Qué responde antes y después del TODO 6? ¿Qué cabecera indica cuánto esperar?
2. ¿Por qué el mensaje es el mismo para un correo inexistente y para una contraseña incorrecta?
3. TODO 7: ¿qué pasaría con `/entrar?volver=//sitio-malo.com` sin la validación?

## Parte F — Verificación
`php verificar.php` → objetivo **15 de 15**.

## Reflexión
Arma una tabla con 5 ataques (robo de contraseñas, robo de cookie, fijación de sesión, CSRF, fuerza bruta) y la línea
de código que defiende de cada uno.
