<?php
// bd/migrar_json.php — Migra las prendas guardadas en JSON (Actividad 1) a MySQL (Actividad 2).
// Uso: php bd/migrar_json.php [archivo.json] [--socio=ID] [--simular]
//   archivo.json  por defecto datos/productos.json
//   --socio=ID    socio al que se atribuyen las prendas (el JSON de la actividad 1 no tenía socio); por defecto 1
//   --simular     muestra qué haría sin guardar nada
// Las prendas cuyo código ya existe en la base se omiten. Todo se hace en una transacción: o se migra todo o nada.
declare(strict_types=1);
require __DIR__ . '/../src/arranque.php';

$argumentos = array_slice($argv, 1);
$simular = in_array('--simular', $argumentos, true);
$idSocio = 1;
$archivo = __DIR__ . '/../datos/productos.json';
foreach ($argumentos as $a) {
    if (str_starts_with($a, '--socio=')) {
        $idSocio = (int) substr($a, 8);
    } elseif (!str_starts_with($a, '--')) {
        $archivo = $a;
    }
}
if (!is_file($archivo)) {
    fwrite(STDERR, "No existe el archivo $archivo" . PHP_EOL);
    exit(1);
}

$pdo = Conexion::obtener();
$productos = new ProductoRepositorio($pdo);
$categorias = (new CategoriaRepositorio($pdo))->todas();
$socio = (new SocioRepositorio($pdo))->buscarPorId($idSocio);
if ($socio === null) {
    fwrite(STDERR, "No existe el socio $idSocio" . PHP_EOL);
    exit(1);
}
$filas = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);

$migradas = $omitidas = 0;
$pdo->beginTransaction();
try {
    foreach ($filas as $fila) {
        $codigo = (string) ($fila['codigo'] ?? '');
        if ($productos->existeCodigo($codigo)) {
            echo "  = $codigo ya existe, se omite" . PHP_EOL;
            $omitidas++;
            continue;
        }
        $categoria = $categorias[(string) ($fila['categoria'] ?? '')] ?? throw new RuntimeException("$codigo: categoría desconocida");
        $producto = new Producto(
            codigo: $codigo,
            nombre: (string) $fila['nombre'],
            categoria: $categoria,
            socio: $socio,
            precio: (float) $fila['precio'],
            stock: (int) ($fila['stock'] ?? 0),
            tallas: Talla::lista((array) ($fila['tallas'] ?? ['U'])) ?: [Talla::UNICA],
            color: (string) ($fila['color'] ?? ''),
            descripcion: (string) ($fila['descripcion'] ?? ''),
            foto: isset($fila['foto']) ? (string) $fila['foto'] : null,
        );
        // crear() abre su propia transacción: aquí se insertan las filas directamente dentro de la transacción general
        $pdo->prepare('INSERT INTO productos (codigo, nombre, categoria_id, socio_id, precio, stock, color, descripcion, foto)
            VALUES (:codigo, :nombre, :categoria, :socio, :precio, :stock, :color, :descripcion, :foto)')->execute([
            'codigo' => $producto->codigo, 'nombre' => $producto->nombre, 'categoria' => $categoria->id, 'socio' => $socio->id,
            'precio' => $producto->precio, 'stock' => $producto->stock, 'color' => $producto->color,
            'descripcion' => $producto->descripcion, 'foto' => $producto->foto,
        ]);
        $id = (int) $pdo->lastInsertId();
        $talla = $pdo->prepare('INSERT INTO producto_tallas (producto_id, talla) VALUES (:id, :talla)');
        foreach ($producto->tallas as $t) {
            $talla->execute(['id' => $id, 'talla' => $t->value]);
        }
        echo "  + $codigo {$producto->nombre} → socio {$socio->nombre}" . PHP_EOL;
        $migradas++;
    }
    $simular ? $pdo->rollBack() : $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Migración cancelada, no se guardó nada: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo ($simular ? '[SIMULACIÓN] ' : '') . "Migradas: $migradas · omitidas: $omitidas" . PHP_EOL;
