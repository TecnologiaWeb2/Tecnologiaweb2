<?php
// src/Api/Problema.php — Errores de la API en formato estándar "Problem Details" (RFC 9457, application/problem+json).
// Todos los errores tienen la MISMA forma: el cliente escribe un solo código para manejarlos.
declare(strict_types=1);

namespace Apicultores\Api;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\DemasiadosIntentos;
use Apicultores\Excepciones\ErrorAplicacion;
use Apicultores\Excepciones\MetodoNoPermitido;
use Apicultores\Excepciones\NoAutenticado;
use JsonException;
use Throwable;

final class Problema
{
    public const BASE_TIPOS = 'https://florchiquitania.bo/problemas/';

    /** Convierte cualquier excepción en [estado, cuerpo, cabeceras]. Los errores inesperados no revelan detalles. */
    public static function desde(Throwable $e, string $instancia, string $registro): array
    {
        $cabeceras = [];
        if ($e instanceof JsonException) {
            return [400, self::cuerpo('json-invalido', 'JSON inválido', 400, 'El cuerpo no es JSON válido: ' . $e->getMessage() . '.', $instancia), $cabeceras];
        }
        if ($e instanceof ErrorAplicacion) {
            $estado = $e->codigoHttp();
            $cuerpo = self::cuerpo(self::tipo($e), $e->titulo(), $estado, $e->getMessage(), $instancia);
            if ($e instanceof DatosInvalidos) {
                $cuerpo['detail'] = 'Revisa los campos indicados en "errores".';
                $cuerpo['errores'] = $e->errores();                 // extensión permitida por el RFC
            }
            if ($e instanceof MetodoNoPermitido) {
                $cabeceras['Allow'] = implode(', ', $e->permitidos());
            }
            if ($e instanceof NoAutenticado) {
                $cabeceras['WWW-Authenticate'] = 'Bearer realm="api", error="invalid_token"';
            }
            if ($e instanceof DemasiadosIntentos) {
                $cabeceras['Retry-After'] = (string) $e->segundos();
            }
            return [$estado, $cuerpo, $cabeceras];
        }
        $referencia = bin2hex(random_bytes(4));
        error_log(sprintf("[%s] api ref=%s %s: %s en %s:%d\n", date('c'), $referencia, $e::class, $e->getMessage(), $e->getFile(), $e->getLine()), 3, $registro);
        return [500, self::cuerpo('error-interno', 'Error interno', 500, "Ocurrió un error inesperado. Referencia: $referencia.", $instancia), $cabeceras];
    }

    public static function cuerpo(string $tipo, string $titulo, int $estado, string $detalle, string $instancia): array
    {
        return ['type' => self::BASE_TIPOS . $tipo, 'title' => $titulo, 'status' => $estado, 'detail' => $detalle, 'instance' => $instancia];
    }

    /** NoEncontrado → "no-encontrado", DatosInvalidos → "datos-invalidos" … */
    private static function tipo(ErrorAplicacion $e): string
    {
        $corto = substr(strrchr($e::class, '\\'), 1);
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $corto));
    }
}
