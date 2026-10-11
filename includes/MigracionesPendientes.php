<?php

/**
 * Conserva las migraciones históricas para una futura actualización manual autorizada.
 * No incluir ni ejecutar desde el arranque normal.
 *
 * @param Database $database Conexión de JV3000 al destino previamente respaldado.
 */
function jv_ejecutar_migraciones_pendientes(Database $database): void
{
// --- 7b. Normalización de documento fiscal (cédula/RIF) ---
// Se ejecuta una única vez (flag en configuracion). Idempotente.
$jv_db = $database;

// -- Compatibilidad de auditoría: columnas de trazabilidad del request
foreach (
    [
        ['auditoria', 'ip_origen', 'VARCHAR(45) NULL'],
        ['auditoria', 'ruta', 'VARCHAR(255) NULL'],
        ['auditoria', 'metodo', 'VARCHAR(10) NULL'],
        ['login_intentos', 'ultimo_intento', 'TIMESTAMP NOT NULL DEFAULT current_timestamp()'],
    ] as [$tabla, $columna, $tipo]
) {
    $exists = $jv_db->fetchOne(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [DB_NAME, $tabla, $columna]
    );
    if (!$exists || (int)($exists['n'] ?? 0) === 0) {
        $jv_db->execute("ALTER TABLE {$tabla} ADD COLUMN {$columna} {$tipo}");
    }
}

$loginUniqueIndex = $jv_db->fetchOne(
    "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'login_intentos' AND INDEX_NAME = 'idx_ip_unique'",
    [DB_NAME]
);
if (!$loginUniqueIndex || (int)($loginUniqueIndex['n'] ?? 0) === 0) {
    $jv_db->execute("DELETE FROM login_intentos WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM login_intentos GROUP BY ip_address) AS intentos_unicos)");
    $jv_db->execute("ALTER TABLE login_intentos ADD UNIQUE INDEX idx_ip_unique (ip_address)");
}

$normalizationFlag = $jv_db->fetchOne("SELECT valor FROM configuracion WHERE clave = 'documentos_normalizados'");
if (!$normalizationFlag) {
    $migrationFile = dirname(__DIR__) . '/db/migrar_documentos.php';
    if (is_file($migrationFile)) {
        require_once $migrationFile;
        if (function_exists('migrar_documentos')) {
            ob_start();
            migrar_documentos($jv_db->getConnection(), DB_NAME);
            ob_end_clean();
        }
    }
    @mysqli_query($jv_db->getConnection(), "INSERT INTO configuracion (clave, valor, descripcion, fecha_actualizado) VALUES ('documentos_normalizados', '1', 'Migración de formato de documento fiscal aplicada (v1)', NOW()) ON DUPLICATE KEY UPDATE valor = '1'");
    error_log("[JV3000] Migración de documentos fiscales ejecutada.");
}

// --- 7c. Esquema: solicitudes de compra + documento de recepción (idempotente) ---
$jv_db->execute("CREATE TABLE IF NOT EXISTS solicitudes_compra (
    id_solicitud int(11) NOT NULL AUTO_INCREMENT,
    id_usuario_solicitante int(11) NOT NULL,
    fecha_solicitud timestamp NOT NULL DEFAULT current_timestamp(),
    motivo varchar(150) DEFAULT NULL,
    estado enum('Pendiente','Atendida','Cancelada') NOT NULL DEFAULT 'Pendiente',
    id_compra int(11) DEFAULT NULL,
    fecha_atendida datetime DEFAULT NULL,
    PRIMARY KEY (id_solicitud),
    KEY fk_sol_user (id_usuario_solicitante),
    KEY fk_sol_compra (id_compra),
    KEY idx_sol_estado (estado),
    CONSTRAINT fk_sol_user FOREIGN KEY (id_usuario_solicitante) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_sol_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$jv_db->execute("CREATE TABLE IF NOT EXISTS detalle_solicitud_compra (
    id_detalle int(11) NOT NULL AUTO_INCREMENT,
    id_solicitud int(11) NOT NULL,
    id_producto int(11) NOT NULL,
    cantidad_solicitada int(11) NOT NULL,
    PRIMARY KEY (id_detalle),
    KEY fk_dsc_solicitud (id_solicitud),
    KEY fk_dsc_producto (id_producto),
    CONSTRAINT fk_dsc_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes_compra (id_solicitud) ON DELETE CASCADE,
    CONSTRAINT fk_dsc_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$receptionDocumentColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'documento_recepcion'");
if (!$receptionDocumentColumn || (int)$receptionDocumentColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE movimientos ADD COLUMN documento_recepcion VARCHAR(100) DEFAULT NULL AFTER status");
}
$receptionKeyColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'clave_idempotencia'");
if (!$receptionKeyColumn || (int)$receptionKeyColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE movimientos ADD COLUMN clave_idempotencia CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER documento_recepcion");
}
$receptionHashColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'huella_solicitud'");
if (!$receptionHashColumn || (int)$receptionHashColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE movimientos ADD COLUMN huella_solicitud CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER clave_idempotencia");
}
$receptionKeyIndex = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'movimientos' AND INDEX_NAME = 'uq_mov_clave_idempotencia'");
if (!$receptionKeyIndex || (int)$receptionKeyIndex['n'] === 0) {
    $jv_db->execute("ALTER TABLE movimientos ADD UNIQUE INDEX uq_mov_clave_idempotencia (clave_idempotencia)");
}
$pinEmergenciaColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'pin_emergencia'");
if (!$pinEmergenciaColumn || (int)$pinEmergenciaColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE usuarios ADD COLUMN pin_emergencia VARCHAR(60) DEFAULT NULL COMMENT 'bcrypt hash de PIN 6 digitos de emergencia' AFTER respuesta_seguridad");
}

// --- 7d. Login intentos: columna usuario (doble bloqueo IP + usuario) ---
$loginUsuarioColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'login_intentos' AND COLUMN_NAME = 'usuario'");
if (!$loginUsuarioColumn || (int)$loginUsuarioColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE login_intentos ADD COLUMN usuario VARCHAR(100) DEFAULT NULL AFTER ip_address");
    $jv_db->execute("ALTER TABLE login_intentos DROP INDEX idx_ip_unique");
    $jv_db->execute("ALTER TABLE login_intentos ADD UNIQUE INDEX idx_ip_user_unique (ip_address, usuario)");
    error_log("[JV3000] Migración login_intentos: columna usuario agregada.");
}

// --- 7e. Cuentas por pagar: tabla pagos_compra (historial de pagos parciales) ---
$pagosTable = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'pagos_compra'");
if (!$pagosTable || (int)$pagosTable['n'] === 0) {
    $jv_db->execute("CREATE TABLE IF NOT EXISTS pagos_compra (
        id_pago int(11) NOT NULL AUTO_INCREMENT,
        id_compra int(11) NOT NULL,
        id_usuario int(11) NOT NULL,
        monto decimal(10,2) NOT NULL,
        metodo_pago enum('Efectivo','Transferencia','Cheque','Otro') NOT NULL,
        detalle_pago json DEFAULT NULL,
        fecha_pago timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_pago),
        KEY fk_pago_compra (id_compra),
        KEY fk_pago_usuario (id_usuario),
        CONSTRAINT fk_pago_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra) ON DELETE CASCADE,
        CONSTRAINT fk_pago_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    error_log("[JV3000] Tabla pagos_compra creada.");
}

// --- 7e.1. Completar el historial con abonos iniciales previos al ledger ---
$ledgerBackfillFlag = $jv_db->fetchOne(
    "SELECT valor FROM configuracion WHERE clave = 'ledger_pagos_compra_v6'"
);
if (!$ledgerBackfillFlag) {
    $jv_db->begin();
    try {
        $legacyPayments = $jv_db->fetchAll(
            "SELECT c.id_compra, c.id_usuario, c.monto_pago, c.metodo_pago,
                    CASE
                        WHEN JSON_VALID(c.detalle_pago)
                            THEN JSON_SET(c.detalle_pago, '$._migracion_legacy_ledger', 'Abono reconstruido desde monto_pago')
                        WHEN c.detalle_pago IS NOT NULL
                            THEN JSON_OBJECT('_migracion_legacy_ledger', 'Abono reconstruido desde monto_pago', 'detalle_original', c.detalle_pago)
                        ELSE JSON_OBJECT('_migracion_legacy_ledger', 'Abono reconstruido desde monto_pago')
                    END AS detalle_pago,
                    CASE
                        WHEN COALESCE((
                            SELECT SUM(p.monto) FROM pagos_compra p WHERE p.id_compra = c.id_compra
                        ), 0) > 0 THEN c.fecha_compra
                        ELSE COALESCE(c.fecha_pago, c.fecha_compra, NOW())
                    END AS fecha_pago,
                    COALESCE((
                        SELECT SUM(p.monto) FROM pagos_compra p WHERE p.id_compra = c.id_compra
                    ), 0) AS total_ledger
             FROM compras c
             WHERE COALESCE(c.monto_pago, 0) > COALESCE((
                 SELECT SUM(p.monto) FROM pagos_compra p WHERE p.id_compra = c.id_compra
             ), 0)
             ORDER BY c.id_compra
             FOR UPDATE"
        );
        $metodosPagoValidos = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];
        foreach ($legacyPayments as $legacyPayment) {
            $metodoPago = in_array($legacyPayment['metodo_pago'], $metodosPagoValidos, true)
                ? $legacyPayment['metodo_pago']
                : 'Otro';
            $jv_db->insert('pagos_compra', [
                'id_compra'   => (int)$legacyPayment['id_compra'],
                'id_usuario'  => (int)$legacyPayment['id_usuario'],
                'monto'       => round((float)$legacyPayment['monto_pago'] - (float)$legacyPayment['total_ledger'], 2),
                'metodo_pago' => $metodoPago,
                'detalle_pago' => $legacyPayment['detalle_pago'],
                'fecha_pago'  => $legacyPayment['fecha_pago'],
            ]);
        }
        $jv_db->execute(
            "INSERT INTO configuracion (clave, valor, descripcion, fecha_actualizado)
             VALUES ('ledger_pagos_compra_v6', '1', 'Abonos históricos iniciales migrados al ledger', NOW())
             ON DUPLICATE KEY UPDATE valor = '1'"
        );
        $jv_db->commit();
        error_log("[JV3000] Historial de pagos históricos conciliado.");
    } catch (Throwable $e) {
        $jv_db->rollback();
        throw $e;
    }
}

// --- 7e.1. Estado de pago parcial (idempotente) ---
$migracion_estado_pago = $jv_db->fetchOne(
    "SELECT valor FROM configuracion WHERE clave = 'status_pago_parcial_v6'"
);
if (!$migracion_estado_pago) {
    $columna_estado_pago = $jv_db->fetchOne(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'status_pago'",
        [DB_NAME]
    );
    if ($columna_estado_pago && strpos($columna_estado_pago['COLUMN_TYPE'], "'Parcial'") === false) {
        $jv_db->execute(
            "ALTER TABLE compras MODIFY status_pago ENUM('Pendiente','Parcial','Pagada') NOT NULL DEFAULT 'Pendiente'"
        );
    }
    $jv_db->execute(
        "UPDATE compras
         SET status_pago = CASE
             WHEN COALESCE(monto_pago, 0) <= 0 THEN 'Pendiente'
             WHEN monto_pago >= total THEN 'Pagada'
             ELSE 'Parcial'
         END"
    );
    $jv_db->execute(
        "INSERT INTO configuracion (clave, valor, descripcion, fecha_actualizado)
         VALUES ('status_pago_parcial_v6', '1', 'Estado de pago parcial habilitado', NOW())
         ON DUPLICATE KEY UPDATE valor = '1'"
    );
    error_log("[JV3000] Migración de estados de pago parciales aplicada.");
}

// --- 7f. Optimistic locking: columna updated_at en tablas clave ---
foreach (['productos', 'compras', 'proveedores', 'clientes'] as $tablaLock) {
    $colCheck = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$tablaLock' AND COLUMN_NAME = 'updated_at'");
    if (!$colCheck || (int)$colCheck['n'] === 0) {
        $jv_db->execute("ALTER TABLE $tablaLock ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log("[JV3000] Migración optimistic locking: updated_at agregada a $tablaLock.");
    }
}


}
