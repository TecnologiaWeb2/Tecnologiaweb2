<?php
// src/fotos.php — Subida SEGURA de la foto de la prenda.
// Regla: nada de lo que dice el navegador sobre el archivo (nombre, tipo) es confiable.
declare(strict_types=1);

/**
 * Valida y guarda la foto subida en el campo $campo de $archivos ($_FILES).
 * @return array{nombre: ?string, error: ?string} nombre del archivo guardado o mensaje de error (ambos null si no se subió nada)
 */
function guardar_foto(array $archivos, string $campo = 'foto', int $maximo = TAM_MAXIMO, string $carpeta = CARPETA_FOTOS): array
{
    $f = $archivos[$campo] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['nombre' => null, 'error' => null];                  // la foto es opcional
    }
    if (is_array($f['error'])) {                                      // foto[]=… : varios archivos en un solo campo
        return ['nombre' => null, 'error' => 'Envía una sola foto.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $motivo = match ($f['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La foto supera el tamaño permitido.',
            UPLOAD_ERR_PARTIAL => 'La foto llegó incompleta; vuelve a intentarlo.',
            default => 'No se pudo recibir la foto.',
        };
        return ['nombre' => null, 'error' => $motivo];
    }
    if ($f['size'] > $maximo) {
        return ['nombre' => null, 'error' => 'La foto supera los ' . intdiv($maximo, 1024 * 1024) . ' MB.'];
    }
    // El TIPO REAL se obtiene leyendo el contenido (finfo), no de $f['type'] ni de la extensión del nombre
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(TIPOS_PERMITIDOS[$tipo]) || @getimagesize($f['tmp_name']) === false) {
        return ['nombre' => null, 'error' => 'El archivo no es una imagen JPG, PNG o WebP.'];
    }
    // Nombre nuevo y aleatorio: el original podría ser "../../index.php" o repetirse
    $nombre = bin2hex(random_bytes(12)) . '.' . TIPOS_PERMITIDOS[$tipo];
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }
    if (!move_uploaded_file($f['tmp_name'], "$carpeta/$nombre")) {
        return ['nombre' => null, 'error' => 'No se pudo guardar la foto.'];
    }
    return ['nombre' => $nombre, 'error' => null];
}

/** Ruta segura de una foto guardada, o null si el nombre no tiene el formato que generamos (evita ../). */
function ruta_foto(string $nombre, string $carpeta = CARPETA_FOTOS): ?string
{
    if (preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $nombre) !== 1) {
        return null;
    }
    $ruta = "$carpeta/$nombre";
    return is_file($ruta) ? $ruta : null;
}

function borrar_foto(?string $nombre): void
{
    if ($nombre !== null && ($ruta = ruta_foto($nombre)) !== null) {
        unlink($ruta);
    }
}
