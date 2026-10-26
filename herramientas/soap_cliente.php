<?php
// herramientas/soap_cliente.php — Laboratorio 12, paso 8: consulta la MISMA prenda por SOAP y por REST y compara
// método, formato, tamaño de la respuesta y tiempo, medidos en TU equipo (no copiar cifras de ejemplo).
// Uso: php herramientas/soap_cliente.php [POL-001] [http://127.0.0.1:8000]
declare(strict_types=1);

$codigo = $argv[1] ?? 'POL-001';
$base = rtrim($argv[2] ?? 'http://127.0.0.1:8000', '/');

$cliente = new SoapClient(null, ['location' => "$base/soap.php", 'uri' => 'urn:manos-del-oriente:precios', 'trace' => true, 'exceptions' => true]);
$t = microtime(true);
try {
    $soap = (array) $cliente->precio($codigo);
} catch (SoapFault $f) {
    $soap = ['error' => $f->getMessage()];
}
$msSoap = (microtime(true) - $t) * 1000;
$bytesSoap = strlen((string) $cliente->__getLastResponse());
$bytesPeticionSoap = strlen((string) $cliente->__getLastRequest());

$t = microtime(true);
$ch = curl_init("$base/datos/productos/" . rawurlencode($codigo));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
$cuerpo = (string) curl_exec($ch);
$estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$msRest = (microtime(true) - $t) * 1000;

printf("%-6s %-6s %-34s %-24s %10s %10s %8s\n", 'Estilo', 'Método', 'URL', 'Formato', 'Petición', 'Respuesta', 'Tiempo');
printf("%-6s %-6s %-34s %-24s %9dB %9dB %6.1fms\n", 'SOAP', 'POST', '/soap.php (precio)', 'XML (sobre SOAP 1.1)', $bytesPeticionSoap, $bytesSoap, $msSoap);
printf("%-6s %-6s %-34s %-24s %9dB %9dB %6.1fms\n", 'REST', 'GET', "/datos/productos/$codigo", "JSON (HTTP $estado)", 0, strlen($cuerpo), $msRest);
echo PHP_EOL . 'SOAP → ' . json_encode($soap, JSON_UNESCAPED_UNICODE) . PHP_EOL . 'REST → ' . $cuerpo . PHP_EOL;
