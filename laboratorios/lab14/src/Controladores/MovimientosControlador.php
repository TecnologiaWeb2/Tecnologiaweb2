<?php
// src/Controladores/MovimientosControlador.php — Ventas, acopios, reportes y exportación CSV.
declare(strict_types=1);

namespace Apicultores\Controladores;

use Apicultores\Excepciones\DatosInvalidos;
use Apicultores\Excepciones\Prohibido;
use Apicultores\Modelo\Usuario;
use Apicultores\Reportes\Csv;
use Apicultores\Reportes\ReporteVentas;
use Apicultores\Ventas\RegistroAcopio;
use Apicultores\Ventas\RegistroVenta;
use Apicultores\Vista\Plantilla;
use DateTimeImmutable;

final class MovimientosControlador
{
    public function __construct(
        private readonly RegistroVenta $ventas,
        private readonly RegistroAcopio $acopios,
        private readonly ReporteVentas $reportes,
        private readonly Plantilla $vista,
        private readonly Usuario $usuario,
    ) {
    }

    private function autorizar(string $accion): void
    {
        if (!$this->usuario->puede($accion)) {
            throw new Prohibido('Tu rol (' . $this->usuario->rol->etiqueta() . ') no permite esta acción.');
        }
    }

    public function formularioVenta(array $errores = []): string
    {
        $this->autorizar('ventas.registrar');
        return $this->vista->pagina('venta', [
            'errores' => $errores,
            'cliente' => (string) ($_POST['cliente'] ?? ''),
            'lineas' => array_map(fn(int $i): array => [
                'codigo' => (string) ($_POST['codigo'][$i] ?? ''), 'cantidad' => (string) ($_POST['cantidad'][$i] ?? '')], [0, 1, 2]),
            'mensaje' => isset($_GET['venta']) ? 'Venta N.º ' . (int) $_GET['venta'] . ' registrada.' : '',
        ], 'Nueva venta');
    }

    public function registrarVenta(): never
    {
        $this->autorizar('ventas.registrar');
        $lineas = [];
        foreach ((array) ($_POST['codigo'] ?? []) as $i => $codigo) {
            $codigo = strtoupper(trim((string) $codigo));
            if ($codigo === '') {
                continue;
            }
            $cantidad = filter_var($_POST['cantidad'][$i] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
            if ($cantidad === false) {
                throw new DatosInvalidos(['general' => "La cantidad de $codigo debe ser un entero entre 1 y 1000."]);
            }
            $lineas[$codigo] = ($lineas[$codigo] ?? 0) + $cantidad;      // el mismo código dos veces se suma
        }
        $id = $this->ventas->registrar($lineas, (string) ($_POST['cliente'] ?? ''), $this->usuario->id);
        header("Location: /ventas/nueva?venta=$id", true, 303);
        exit;
    }

    public function registrarAcopio(): never
    {
        $this->autorizar('acopios.registrar');
        $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT) ?: 0;
        $precio = str_replace(',', '.', (string) ($_POST['precio'] ?? '0'));
        $id = $this->acopios->registrar(strtoupper(trim((string) ($_POST['codigo'] ?? ''))), $cantidad,
            is_numeric($precio) ? number_format((float) $precio, 2, '.', '') : '0');
        header("Location: /ventas/nueva?acopio=$id", true, 303);
        exit;
    }

    public function reportes(): string
    {
        $this->autorizar('reportes.ver');
        [$desde, $hasta] = $this->periodo();
        $bs = fn(array $filas, string ...$campos): array => array_map(function (array $f) use ($campos): array {
            foreach ($campos as $c) {
                $f[$c] = bs((float) $f[$c]);
            }
            return $f;
        }, $filas);
        return $this->vista->pagina('reportes', [
            'desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'),
            'porMes' => $bs($this->reportes->porMes($desde, $hasta), 'total'),
            'top' => $bs($this->reportes->topProductos($desde, $hasta), 'importe'),
            'porSocio' => $bs($this->reportes->porSocio($desde, $hasta, 3000), 'importe'),
            'sinVentas' => $this->reportes->sinVentas($desde, $hasta),
            'csv' => '/reportes/ventas.csv?' . http_build_query(['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]),
        ], 'Reportes');
    }

    public function exportarCsv(): never
    {
        $this->autorizar('reportes.ver');
        [$desde, $hasta] = $this->periodo();
        $nombre = sprintf('ventas_%s_%s.csv', $desde->format('Ymd'), $hasta->format('Ymd'));
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$nombre\"");
        echo Csv::generar(['Venta', 'Fecha', 'Cliente', 'Código', 'Producto', 'Cantidad', 'Precio unitario', 'Subtotal'],
            $this->reportes->detalle($desde, $hasta));
        exit;
    }

    /** Fechas de la URL: formato estricto Y-m-d, desde <= hasta, máximo un año. Por defecto, el trimestre jul-sep 2026. */
    private function periodo(): array
    {
        $leer = function (string $campo, string $defecto): DateTimeImmutable {
            $texto = (string) ($_GET[$campo] ?? $defecto);
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
            // createFromFormat acepta "2026-02-31" (lo convierte en 3 de marzo): se exige que vuelva a dar el mismo texto
            if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
                throw new DatosInvalidos(['general' => "La fecha '$texto' no es válida (formato AAAA-MM-DD)."]);
            }
            return $fecha;
        };
        $desde = $leer('desde', '2026-07-01');
        $hasta = $leer('hasta', '2026-09-30');
        if ($desde > $hasta || $desde->diff($hasta)->days > 366) {
            throw new DatosInvalidos(['general' => 'El período debe ir de una fecha a otra posterior, como máximo un año.']);
        }
        return [$desde, $hasta];
    }
}
