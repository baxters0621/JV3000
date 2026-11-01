<?php

/**
 * Verifica la validación de fechas de compra sin conectar a una base de datos.
 */
final class Database
{
    private static ?self $instancia = null;
    public int $consultas = 0;

    public static function getInstance(): self
    {
        return self::$instancia ??= new self();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->consultas++;
        return null;
    }
}

$root = dirname(__DIR__);
require_once $root . '/core/Model.php';
require_once $root . '/models/Compra.php';

$db = Database::getInstance();
$compra = new Compra();
$fecha_futura = date('Y-m-d', strtotime('+1 day'));
$fechas_invalidas = [
    '',
    'no-es-fecha',
    '2026-02-30',
    $fecha_futura,
    '1969-12-31',
    ['2026-10-30'],
];

foreach ($fechas_invalidas as $fecha) {
    $resultado = $compra->registrar([
        'id_proveedor' => 1,
        'fecha_compra' => $fecha,
    ], 1);
    if ($resultado['ok'] || !str_starts_with($resultado['mensaje'], 'FECHA DE COMPRA') && !str_starts_with($resultado['mensaje'], 'LA FECHA DE COMPRA')) {
        throw new RuntimeException('El modelo no rechazó una fecha de compra inválida o futura.');
    }
}

if ($db->consultas !== 0) {
    throw new RuntimeException('La validación de fecha consultó la base antes de rechazar el valor.');
}

echo "OK fechas de compra inválidas, anteriores al rango y futuras se rechazan sin BD\n";
