<?php
// config/config.ejemplo.php — Plantilla de configuración. Copiar como config/config.php y completar.
// config/config.php NO se sube al repositorio (.gitignore) y vive fuera de public/: nadie puede pedirlo por URL.
// Cada valor puede reemplazarse con una variable de entorno (útil en el hosting y en las pruebas).
declare(strict_types=1);

return [
    'bd' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'nombre' => 'manos_del_oriente',
        'usuario' => 'root',
        'clave' => '',
    ],
];
