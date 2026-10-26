<?php
// public/salir.php — Cerrar sesión: solo por POST con token CSRF (un enlace o una imagen ajena no pueden cerrarla).
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

exigir_metodo('POST');
exigir_csrf($_POST);
cerrar_sesion();
redirigir('/entrar.php');
