<?php
// Verificador.php — Mini motor de comprobaciones para los laboratorios de Tecnología Web II.
// Levanta el servidor integrado de PHP (php -S), hace peticiones HTTP reales y muestra ✅/❌.
declare(strict_types=1);

final class Verificador
{
    private int $bien = 0;
    private int $total = 0;
    /** @var resource|null */
    private $proceso = null;
    private string $base = '';
    private string $galletas;

    public function __construct()
    {
        $this->galletas = tempnam(sys_get_temp_dir(), 'tw2');
    }

    /**
     * Inicia `php -S` sirviendo $docroot (opcionalmente con un router) en un puerto libre.
     * Si ya había un servidor, lo detiene. $entorno agrega variables de entorno al servidor (ej. un archivo de datos de prueba).
     */
    public function servidor(string $docroot, ?string $router = null, array $entorno = []): void
    {
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
        }
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
        fclose($sock);
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', $docroot];
        if ($router !== null) {
            $cmd[] = $router;
        }
        $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $this->proceso = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $pipes, null,
            $entorno === [] ? null : $entorno + getenv());
        $this->base = "http://127.0.0.1:$puerto";
        for ($i = 0; $i < 50; $i++) {                 // esperar hasta 5 s a que acepte conexiones
            if (@fsockopen('127.0.0.1', $puerto, $e, $m, 0.1)) {
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('El servidor integrado no arrancó');
    }

    /**
     * Petición HTTP real. Devuelve ['estado' => int, 'cabeceras' => string, 'cuerpo' => string].
     * Mantiene cookies entre peticiones (como un navegador).
     */
    public function pedir(string $ruta, string $metodo = 'GET', array|string|null $datos = null, array $cabeceras = []): array
    {
        $ch = curl_init($this->base . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_COOKIEJAR => $this->galletas, CURLOPT_COOKIEFILE => $this->galletas, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $cabeceras,
        ]);
        if ($datos !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $datos);
        }
        $r = (string) curl_exec($ch);
        $tam = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $estado = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return ['estado' => $estado, 'cabeceras' => substr($r, 0, $tam), 'cuerpo' => substr($r, $tam)];
    }

    /** URL completa del servidor de prueba (para clientes externos, por ejemplo SoapClient). */
    public function url(string $ruta = ''): string
    {
        return $this->base . $ruta;
    }

    /** Borra las cookies guardadas: como abrir una ventana privada nueva (otro usuario, sesión cerrada). */
    public function olvidarCookies(): void
    {
        file_put_contents($this->galletas, '');
    }

    public function revisar(string $descripcion, callable $prueba): void
    {
        $this->total++;
        try {
            $ok = (bool) $prueba();
        } catch (Throwable $e) {
            $ok = false;
            $descripcion .= '  (' . get_class($e) . ': ' . $e->getMessage() . ')';
        }
        if ($ok) {
            $this->bien++;
        }
        echo ($ok ? '✅ ' : '❌ ') . $descripcion . PHP_EOL;
    }

    public function fin(): int
    {
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
        }
        @unlink($this->galletas);
        echo "Resultado: {$this->bien} de {$this->total} comprobaciones" . PHP_EOL;
        return $this->bien === $this->total ? 0 : 1;
    }
}
