# Laboratorio 10 — Hoja de trabajo (resuelta): usuarios, sesiones, roles y CSRF

Nombre: Yordy Tolavi   Fecha: 18/10/2026

> **Cómo se comprobó:** **[✔ comprobado]** = ejecutado con PHP 8.3 real. **[razonado]** = deducido del código. En el entorno de preparación no hubo MySQL ni Docker, así que `php verificar.php` (15 de 15) debes correrlo tú con `docker compose up -d`.

## Parte A — Contraseñas

**1. `SELECT correo, clave_hash FROM usuarios;`** [razonado]
No se puede saber la contraseña: el hash es de **un solo sentido**. En `$2y$12$…`: `$2y$` = algoritmo **bcrypt**; `12$` = **costo** (2¹² = 4096 rondas); los siguientes 22 caracteres son la **sal** aleatoria y los 31 restantes el **hash** de la contraseña.

**2. Dos veces `password_hash("Panal-2026!")`** [✔ comprobado]
Salen **distintos** (`$2y$10$9yIIWh9d…` y `$2y$10$BooC5pTU…`) porque cada llamada genera una **sal aleatoria nueva**. `password_verify` funciona igual porque la sal viaja **dentro del hash**: extrae sal y costo, recalcula y compara.

**3. ¿Por qué no `md5()`/`sha1()`? Tiempos con cost 10, 12 y 14** [✔ comprobado]
Son rápidos a propósito y sin sal: un atacante prueba miles de millones por segundo y usa tablas precalculadas (rainbow tables). bcrypt es **lento a propósito**. Medido: cost 10 ≈ **59 ms**, cost 12 ≈ **197 ms**, cost 14 ≈ **946 ms** (cada +1 de costo duplica el tiempo).cd

**4. TODO 1 — `password_needs_rehash`**
```php
if (password_needs_rehash($fila['clave_hash'], PASSWORD_DEFAULT, self::OPCIONES_HASH)) {
    $this->usuarios->actualizarHash((int) $fila['id'], password_hash($clave, PASSWORD_DEFAULT, self::OPCIONES_HASH));
}
```
Devuelve `true` si el hash fue hecho con un algoritmo o costo distinto del actual (el operador tiene cost 10, ahora se exige 12). Solo se puede actualizar **al iniciar sesión** porque es el único momento en que tenemos la contraseña en texto; el hash no se puede "convertir" sin ella.

## Parte B — Sesión (TODO 2 y 3)

**1. `Set-Cookie`** 
Antes del TODO 3 la cookie salía solo como `APISESION=…; path=/`. Después: `HttpOnly; SameSite=Lax` (y `Secure` solo si hay HTTPS).
- `HttpOnly`: JavaScript no puede leerla → un XSS no roba la sesión.
- `SameSite=Lax`: el navegador no la envía en POST/subrecursos iniciados desde otro sitio → frena CSRF.
- `Secure`: solo viaja por HTTPS → no se puede espiar en redes abiertas. En `localhost` con HTTP no aparece.

**2. TODO 2 — fijación de sesión**
```php
session_regenerate_id(true);
```
El atacante hace que la víctima use un ID **que él conoce**; cuando la víctima inicia sesión, ese ID pasaría a estar autenticado y el atacante entraría con él. Al **crear un ID nuevo y borrar el anterior** al iniciar sesión, el ID conocido queda inservible.

**3. `$_SESSION['usuario']`**
Guarda solo `id, correo, nombre, rol, socio_id`. El hash **no** se guarda: la sesión vive en disco del servidor y se usa en cada petición; si se filtra, no debe exponer credenciales, y no hace falta para nada tras el ingreso.

**4. 30 minutos sin actividad**
`Sesion::iniciar()` compara `ultima_actividad` con la hora actual; si pasaron más de `INACTIVIDAD` (1800 s) llama a `cerrar()` y abre una sesión vacía → el usuario debe volver a entrar.

## Parte C — CSRF (TODO 4)

**1. Ataque**
Estás conectado como admin y abres una página maliciosa con un `<form action="http://localhost:8000/productos/MIE-500/eliminar" method="post">` que se envía sola. El navegador adjunta **automáticamente la cookie `APISESION`** del sitio, y el servidor cree que fuiste tú. Sin token, el producto se elimina.

**2. `curl -X POST` sin token**
Antes del TODO 4 la ruta respondía **303** (borraba). Después responde **403** (`Prohibido`), porque `Csrf::validar` exige el token:
```php
$esperado = $_SESSION['csrf'] ?? '';
if ($recibido === null || $esperado === '' || !hash_equals($esperado, $recibido)) {
    throw new Prohibido('La sesión del formulario expiró o no es válida. …');
}
```
[✔ comprobado la lógica: `null`, token falso y vacío → `Prohibido`; el token real pasa.]

**3. `hash_equals` y no `===`**
`===` se detiene en el primer carácter distinto, y la diferencia de tiempo permite adivinar el token carácter a carácter (ataque de temporización). `hash_equals` tarda **lo mismo** acierte o falle.

**4. `SameSite=Lax` y el token**
`Lax` bloquea el envío de la cookie en POST de otros sitios, pero depende del navegador (antiguos no lo respetan), no protege dentro del mismo sitio (subdominios) y es una sola capa. El token es una segunda defensa independiente (**defensa en profundidad**).

## Parte D — Roles y permisos (TODO 5)

**1. Matriz**
```php
return in_array($this->value, self::PERMISOS[$accion] ?? [], true);
```
"Negar por defecto": si la acción no existe en la matriz (`?? []`) el resultado es `false`; lo que no se permitió explícitamente, está prohibido. [✔ comprobado: `Admin->puede('sistema.borrar_todo')` = false]

**2. Operador**
**No** puede eliminar, **no** ve el botón Eliminar (la plantilla lo oculta con `puede('productos.eliminar')`), y si envía el POST igual recibe **403** (el controlador vuelve a comprobar el permiso). Sí puede editar (303).

**3. Elena (socia)**
Ve **solo sus 4 productos** (`Mostrando 1–4 de 4`). El filtro se aplica **en la consulta** (`socio_id` del usuario), no en la plantilla; así nunca salen de la base los datos ajenos.

**4. Ocultar el botón no basta**
Es cosmética: un atacante puede enviar el POST a mano (curl). La **autorización real** debe estar en el servidor, en cada acción.

## Parte E — Fuerza bruta y redirecciones (TODO 6 y 7)

**1. Seis contraseñas incorrectas**
Antes del TODO 6: siempre **401**, intentos ilimitados. Después: los primeros 5 dan 401; el 6.º, **aunque la clave sea correcta**, da **429** con la cabecera **`Retry-After`** (≈ 900 s = 15 min).
```php
if ((int) $fila['segundos_bloqueo'] > 0) { throw new DemasiadosIntentos((int) $fila['segundos_bloqueo']); }
if (!password_verify($clave, $fila['clave_hash'])) {
    $this->usuarios->registrarFallo((int) $fila['id'], self::MAXIMO_INTENTOS, self::BLOQUEO_SEGUNDOS);
    return null;
}
```

**2. Mismo mensaje**
Para no revelar qué correos están registrados (**enumeración de usuarios**). Además se verifica un hash ficticio para que el **tiempo de respuesta** también sea parecido.

**3. TODO 7 — redirección abierta**
```php
return preg_match('#^/(?!/)[\w\-/?=&%.]*$#', $v) === 1 ? $v : '/productos';
```
Sin validar, `/entrar?volver=//sitio-malo.com` haría `Location: //sitio-malo.com` tras iniciar sesión: el navegador interpreta `//` como otro dominio y lleva a la víctima a un sitio falso (phishing) con la confianza de haber entrado a nuestro sistema. [✔ comprobado: `//sitio-malo.com` → rechazado, `/productos?pagina=2` → aceptado]

## Parte F — Verificación
```
docker compose down -v && docker compose up -d --build
docker compose exec php php verificar.php      # objetivo: 15 de 15
```
Los 7 TODO están completos; revisión de sintaxis sin errores. (La corrida completa requiere MySQL.)

## Reflexión — 5 ataques y su defensa

| Ataque | Defensa | Línea de código |
|---|---|---|
| Robo de contraseñas (filtración de la BD) | Hash lento con sal | `password_hash($clave, PASSWORD_DEFAULT, ['cost' => 12])` |
| Robo de cookie (XSS / red) | Cookie `HttpOnly`, `Secure` | `'httponly' => true, 'secure' => $https` |
| Fijación de sesión | ID nuevo al ingresar | `session_regenerate_id(true);` |
| CSRF | Token por sesión + `SameSite` | `hash_equals($esperado, $recibido)` · `'samesite' => 'Lax'` |
| Fuerza bruta | Bloqueo tras 5 fallos (429) | `throw new DemasiadosIntentos(...)` |
