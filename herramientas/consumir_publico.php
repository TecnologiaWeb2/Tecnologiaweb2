<?php
// herramientas/consumir_publico.php — Laboratorio 12, paso 9: consumir un servicio público desde PHP con cURL.
// Uso: php herramientas/consumir_publico.php   (requiere Internet). Comprueba el código HTTP y decodifica el JSON.
declare(strict_types=1);

$url = $argv[1] ?? 'https://jsonplaceholder.typicode.com/todos/1';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ManosDelOriente/1.0 (Tecnologia Web II)'],
]);
$inicio = microtime(true);
$cuerpo = curl_exec($ch);
if ($cuerpo === false) {
    throw new RuntimeException('Sin respuesta: ' . curl_error($ch));
}
$estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
if ($estado !== 200) {
    throw new RuntimeException("El servicio respondió HTTP $estado");
}
if (!str_contains($tipo, 'json')) {
    throw new RuntimeException("Se esperaba JSON y llegó «{$tipo}»");
}
$datos = json_decode((string) $cuerpo, true, 512, JSON_THROW_ON_ERROR);
printf("GET %s → HTTP %d · %s · %d bytes · %.0f ms\n", $url, $estado, $tipo, strlen((string) $cuerpo), (microtime(true) - $inicio) * 1000);
print_r($datos);
