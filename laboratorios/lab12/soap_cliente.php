<?php
// soap_cliente.php — Cliente SOAP de ejemplo: php soap_cliente.php MIE-500   (con el servidor en 127.0.0.1:8000)
// Muestra la respuesta y el sobre XML que viajó, para compararlo con /datos/productos/MIE-500 (JSON).
declare(strict_types=1);

$codigo = $argv[1] ?? 'MIE-500';
$cliente = new SoapClient(null, [
    'location' => getenv('SOAP_URL') ?: 'http://127.0.0.1:8000/soap',
    'uri' => 'urn:apicultores-precios',
    'trace' => true,                       // guarda la petición y la respuesta para mostrarlas
    'exceptions' => true,
]);
try {
    $r = $cliente->consultarProducto($codigo);
    print_r($r);
} catch (SoapFault $f) {
    echo "SoapFault ({$f->faultcode}): {$f->getMessage()}\n";
}
echo "\n--- Petición SOAP (" . strlen($cliente->__getLastRequest()) . " bytes) ---\n", $cliente->__getLastRequest(), "\n";
echo "--- Respuesta SOAP (" . strlen((string) $cliente->__getLastResponse()) . " bytes) ---\n", $cliente->__getLastResponse(), "\n";
