<?php

// ==========================================
// MIGRACIÓN jv3000_db v3 → v4
// ==========================================
// Uso:  php db/migrar_v3_v4.php [nombre_bd]
// El nombre de BD es opcional (por defecto DB_NAME de config.php).
// Idempotente: se puede ejecutar varias veces sin efecto repetido.
// Requiere MySQL arriba (servicio) antes de ejecutar.

require_once __DIR__ . '/../includes/config.php';

$db_name = $argv[1] ?? DB_NAME;

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $db_name);
if (!$conn) {
    fwrite(STDERR, '[migrar_v3_v4] No se pudo conectar a la BD "' . $db_name . '": ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

$cur_db = $db_name;

$ok = 0;
$skip = 0;
$err = 0;

function mig_tabla_existe($conn, $cur_db, $tabla) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$cur_db' AND TABLE_NAME = '$tabla'");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return $row && (int)$row['c'] > 0;
}
function mig_col_existe($conn, $cur_db, $tabla, $col) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$cur_db' AND TABLE_NAME = '$tabla' AND COLUMN_NAME = '$col'");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return $row && (int)$row['c'] > 0;
}
function mig_idx_existe($conn, $cur_db, $tabla, $idx) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = '$cur_db' AND TABLE_NAME = '$tabla' AND INDEX_NAME = '$idx'");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return $row && (int)$row['c'] > 0;
}

function mig_run($conn, $sql, $ya_aplicado) {
    global $ok, $skip, $err;
    if ($ya_aplicado) {
        $skip++;
        echo "  [skip] ya aplicado\n";
        return;
    }
    if (mysqli_query($conn, $sql)) {
        $ok++;
        echo "  [ok]   $sql\n";
    } else {
        $err++;
        echo "  [FAIL] $sql\n        " . mysqli_error($conn) . "\n";
    }
}

echo "== Migración $db_name : v3 → v4 ==\n";

// 1. Tabla clientes
echo "\n[1] Tabla clientes\n";
if (mig_tabla_existe($conn, $cur_db, 'clientes')) {
    $skip++;
    echo "  [skip] clientes ya existe\n";
} else {
    $sql = "CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `documento` varchar(20) DEFAULT NULL COMMENT 'RIF o cédula',
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `status` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `idx_cli_documento` (`documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mig_run($conn, $sql, false);
}

// 2. compras: columnas de pago y recepción
echo "\n[2] compras: columnas de pago/recepción\n";
mig_run($conn, "ALTER TABLE `compras` ADD COLUMN `status_pago` enum('Pendiente','Pagada') DEFAULT 'Pendiente' AFTER `observaciones`", mig_col_existe($conn, $cur_db, 'compras', 'status_pago'));
mig_run($conn, "ALTER TABLE `compras` ADD COLUMN `monto_pago` decimal(12,2) DEFAULT 0.00 AFTER `status_pago`", mig_col_existe($conn, $cur_db, 'compras', 'monto_pago'));
mig_run($conn, "ALTER TABLE `compras` ADD COLUMN `fecha_pago` datetime DEFAULT NULL AFTER `monto_pago`", mig_col_existe($conn, $cur_db, 'compras', 'fecha_pago'));
mig_run($conn, "ALTER TABLE `compras` ADD COLUMN `metodo_pago` varchar(30) DEFAULT NULL AFTER `fecha_pago`", mig_col_existe($conn, $cur_db, 'compras', 'metodo_pago'));
mig_run($conn, "ALTER TABLE `compras` ADD COLUMN `estado_recepcion` enum('Pendiente','Parcial','Completa') DEFAULT 'Pendiente' AFTER `metodo_pago`", mig_col_existe($conn, $cur_db, 'compras', 'estado_recepcion'));

// 3. compras: índices factura/control
echo "\n[3] compras: índices factura/control\n";
mig_run($conn, "ALTER TABLE `compras` DROP INDEX `uq_nro_control`", !mig_idx_existe($conn, $cur_db, 'compras', 'uq_nro_control'));
mig_run($conn, "ALTER TABLE `compras` ADD UNIQUE KEY `uq_comp_prov_factura` (`id_proveedor`, `nro_factura`)", mig_idx_existe($conn, $cur_db, 'compras', 'uq_comp_prov_factura'));

// 4. detalle_compras: cantidad_recibida
echo "\n[4] detalle_compras: cantidad_recibida\n";
mig_run($conn, "ALTER TABLE `detalle_compras` ADD COLUMN `cantidad_recibida` int(11) NOT NULL DEFAULT 0 AFTER `precio_costo`", mig_col_existe($conn, $cur_db, 'detalle_compras', 'cantidad_recibida'));

// 5. salidas: id_cliente (FK -> clientes)
echo "\n[5] salidas: id_cliente\n";
mig_run($conn, "ALTER TABLE `salidas` ADD COLUMN `id_cliente` int(11) DEFAULT NULL AFTER `rif_cliente`", mig_col_existe($conn, $cur_db, 'salidas', 'id_cliente'));
mig_run($conn, "ALTER TABLE `salidas` ADD CONSTRAINT `fk_sal_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL", mig_idx_existe($conn, $cur_db, 'salidas', 'fk_sal_cliente'));

// 6. clientes: backfill desde historial de salidas (idempotente)
echo "\n[6] clientes: backfill desde historial de ventas\n";
mysqli_query($conn, "INSERT IGNORE INTO `clientes` (`nombre`, `documento`)
                     SELECT s.cliente, s.rif_cliente FROM salidas s
                     WHERE s.cliente IS NOT NULL AND s.cliente <> ''
                       AND s.rif_cliente IS NOT NULL AND s.rif_cliente <> ''
                     GROUP BY s.cliente, s.rif_cliente");
if (mysqli_errno($conn)) {
    $err++;
    echo "  [FAIL] backfill clientes: " . mysqli_error($conn) . "\n";
} else {
    $ok++;
    echo "  [ok]   clientes insertados desde historial (" . mysqli_affected_rows($conn) . " filas afectadas)\n";
}
mysqli_query($conn, "UPDATE salidas s
                     JOIN clientes c ON c.documento = s.rif_cliente AND s.rif_cliente IS NOT NULL AND s.rif_cliente <> ''
                     SET s.id_cliente = c.id_cliente
                     WHERE s.id_cliente IS NULL");
if (mysqli_errno($conn)) {
    $err++;
    echo "  [FAIL] enlazar salidas.id_cliente: " . mysqli_error($conn) . "\n";
} else {
    $ok++;
    echo "  [ok]   salidas vinculadas a clientes (" . mysqli_affected_rows($conn) . " filas afectadas)\n";
}

echo "\n== Resumen: $ok aplicados | $skip omitidos | $err errores ==\n";
mysqli_close($conn);
exit($err > 0 ? 1 : 0);
