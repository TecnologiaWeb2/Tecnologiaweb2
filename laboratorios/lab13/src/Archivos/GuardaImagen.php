<?php
// src/Archivos/GuardaImagen.php — Recibe, valida y guarda la imagen de un producto.
// Nunca se confía en lo que dice el navegador: el tipo se comprueba leyendo el CONTENIDO del archivo
// y el nombre final lo inventa el servidor (así no se pueden subir "foto.php" ni sobrescribir archivos).
declare(strict_types=1);

namespace Apicultores\Archivos;

final class GuardaImagen
{
    public const MAXIMO_BYTES = 2 * 1024 * 1024;     // 2 MB
    private const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly string $carpeta)
    {
    }

    /** ¿El formulario trajo un archivo? (un campo vacío llega con error UPLOAD_ERR_NO_FILE) */
    public static function hayArchivo(?array $archivo): bool
    {
        return $archivo !== null && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** @return string|null mensaje de error, o null si la imagen es válida */
    public function error(array $archivo): ?string
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return 'La imagen pesa más de 2 MB.';
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file((string) $archivo['tmp_name'])) {
            return 'No se pudo recibir la imagen. Inténtalo de nuevo.';
        }
        if ((int) $archivo['size'] > self::MAXIMO_BYTES) {
            return 'La imagen pesa más de 2 MB.';
        }
        $tipo = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $medidas = @getimagesize($archivo['tmp_name']);
        if (!isset(self::TIPOS[$tipo]) || $medidas === false) {
            return 'El archivo debe ser una imagen JPG, PNG o WebP.';
        }
        if ($medidas[0] > 6000 || $medidas[1] > 6000) {
            return 'La imagen es demasiado grande (máximo 6000 × 6000 píxeles).';
        }
        return null;
    }

    /** Guarda la imagen YA validada y devuelve el nombre generado (ej. "9f2c1ab04d7e3b58.jpg"). */
    public function guardar(array $archivo): string
    {
        $extension = self::TIPOS[(string) (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name'])];
        $nombre = bin2hex(random_bytes(8)) . '.' . $extension;
        if (!is_dir($this->carpeta) && !mkdir($this->carpeta, 0775, true) && !is_dir($this->carpeta)) {
            throw new \RuntimeException('No se pudo crear la carpeta de imágenes.');
        }
        if (!move_uploaded_file($archivo['tmp_name'], "{$this->carpeta}/$nombre")) {
            throw new \RuntimeException('No se pudo guardar la imagen.');
        }
        return $nombre;
    }

    /** Borra una imagen anterior (solo si el nombre tiene la forma que genera guardar()). */
    public function borrar(?string $nombre): void
    {
        if ($nombre !== null && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nombre) === 1) {
            @unlink("{$this->carpeta}/$nombre");
        }
    }

    /** Dirección pública para <img src>; si el producto no tiene foto, una imagen genérica. */
    public static function url(?string $nombre): string
    {
        return $nombre !== null && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nombre) === 1
            ? "/uploads/productos/$nombre" : '/img/sin-imagen.svg';
    }
}
