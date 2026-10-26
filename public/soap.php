<?php
// public/soap.php — Servicio SOAP (Laboratorio 12): operación precio(codigo). Cliente de ejemplo: herramientas/soap_cliente.php
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

if (!class_exists('SoapServer')) {
    http_response_code(501);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('La extensión SOAP no está habilitada en este servidor. Usa la alternativa REST: GET /datos/productos/{codigo}');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Servicio SOAP de precios (sin WSDL). uri: urn:manos-del-oriente:precios · operación: precio(codigo)\n");
}
$servidor = new SoapServer(null, ['uri' => 'urn:manos-del-oriente:precios', 'encoding' => 'UTF-8']);
$servidor->setClass(ServicioPrecios::class);
$servidor->handle();
