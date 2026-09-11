<?php
/** Ejecuta las migraciones de esquema fuera de las peticiones HTTP. Uso: php tools/migrar.php */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Este comando solo puede ejecutarse desde CLI.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
$db = Database::getInstance();
try {
    $db->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->execute("CREATE TABLE IF NOT EXISTS schema_migrations (version varchar(50) NOT NULL, applied_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $version = '001_legacy_schema_compatibility';
    $legacyApplied = (bool)$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$version]);
    if ($legacyApplied) { echo "[OK] Migracion ya aplicada: {$version}\n"; }
    $column = static function (string $table, string $name) use ($db): bool { $r=$db->fetchOne('SELECT COUNT(*) n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',[DB_NAME,$table,$name]); return (int)($r['n']??0)>0; };
    $table = static function (string $name) use ($db): bool { $r=$db->fetchOne('SELECT COUNT(*) n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',[DB_NAME,$name]); return (int)($r['n']??0)>0; };
    $index = static function (string $tableName, string $indexName) use ($db): bool { $r=$db->fetchOne('SELECT COUNT(*) n FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?',[DB_NAME,$tableName,$indexName]); return (int)($r['n']??0)>0; };
    foreach ([['auditoria','ip_origen','VARCHAR(45) NULL'],['auditoria','ruta','VARCHAR(255) NULL'],['auditoria','metodo','VARCHAR(10) NULL'],['login_intentos','ultimo_intento','TIMESTAMP NOT NULL DEFAULT current_timestamp()'],['movimientos','documento_recepcion','VARCHAR(100) DEFAULT NULL AFTER status'],['usuarios','pin_emergencia',"VARCHAR(60) DEFAULT NULL COMMENT 'bcrypt hash de PIN 6 digitos de emergencia' AFTER respuesta_seguridad"],['login_intentos','usuario','VARCHAR(100) DEFAULT NULL AFTER ip_address']] as [$t,$c,$definition]) { if (!$column($t,$c)) { $db->execute("ALTER TABLE {$t} ADD COLUMN {$c} {$definition}"); echo "[OK] Columna {$t}.{$c}\n"; } }
    if (!$index('login_intentos','idx_ip_user_unique')) { if ($index('login_intentos','idx_ip_unique')) { $db->execute('DELETE FROM login_intentos WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) id FROM login_intentos GROUP BY ip_address, usuario) x)'); $db->execute('ALTER TABLE login_intentos DROP INDEX idx_ip_unique'); } $db->execute('ALTER TABLE login_intentos ADD UNIQUE INDEX idx_ip_user_unique (ip_address, usuario)'); }
    $db->execute("CREATE TABLE IF NOT EXISTS solicitudes_compra (id_solicitud int(11) NOT NULL AUTO_INCREMENT,id_usuario_solicitante int(11) NOT NULL,fecha_solicitud timestamp NOT NULL DEFAULT current_timestamp(),motivo varchar(150) DEFAULT NULL,estado enum('Pendiente','Atendida','Cancelada') NOT NULL DEFAULT 'Pendiente',id_compra int(11) DEFAULT NULL,fecha_atendida datetime DEFAULT NULL,PRIMARY KEY(id_solicitud),KEY fk_sol_user(id_usuario_solicitante),KEY fk_sol_compra(id_compra),KEY idx_sol_estado(estado),CONSTRAINT fk_sol_user FOREIGN KEY(id_usuario_solicitante) REFERENCES usuarios(id_usuario),CONSTRAINT fk_sol_compra FOREIGN KEY(id_compra) REFERENCES compras(id_compra) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $db->execute("CREATE TABLE IF NOT EXISTS detalle_solicitud_compra (id_detalle int(11) NOT NULL AUTO_INCREMENT,id_solicitud int(11) NOT NULL,id_producto int(11) NOT NULL,cantidad_solicitada int(11) NOT NULL,PRIMARY KEY(id_detalle),KEY fk_dsc_solicitud(id_solicitud),KEY fk_dsc_producto(id_producto),CONSTRAINT fk_dsc_solicitud FOREIGN KEY(id_solicitud) REFERENCES solicitudes_compra(id_solicitud) ON DELETE CASCADE,CONSTRAINT fk_dsc_producto FOREIGN KEY(id_producto) REFERENCES productos(id_producto)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!$table('pagos_compra')) { $db->execute("CREATE TABLE pagos_compra (id_pago int(11) NOT NULL AUTO_INCREMENT,id_compra int(11) NOT NULL,id_usuario int(11) NOT NULL,monto decimal(10,2) NOT NULL,metodo_pago enum('Efectivo','Transferencia','Cheque','Otro') NOT NULL,detalle_pago json DEFAULT NULL,fecha_pago timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY(id_pago),KEY fk_pago_compra(id_compra),KEY fk_pago_usuario(id_usuario),CONSTRAINT fk_pago_compra FOREIGN KEY(id_compra) REFERENCES compras(id_compra) ON DELETE CASCADE,CONSTRAINT fk_pago_usuario FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"); }
    foreach (['productos','compras','proveedores','clientes'] as $t) { if (!$column($t,'updated_at')) { $db->execute("ALTER TABLE {$t} ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); echo "[OK] updated_at {$t}\n"; } }
    $normalization=$db->fetchOne("SELECT valor FROM configuracion WHERE clave='documentos_normalizados'"); if (!$normalization) { require_once __DIR__.'/../db/migrar_documentos.php'; migrar_documentos($db->getConnection(),DB_NAME); $db->execute("INSERT INTO configuracion (clave,valor,descripcion,fecha_actualizado) VALUES ('documentos_normalizados','1','Migracion de formato de documento fiscal aplicada (v1)',NOW()) ON DUPLICATE KEY UPDATE valor='1'"); }
    if (!$legacyApplied) {
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)',[$version]);
        echo "[OK] Migracion completada: {$version}\n";
    }
    $integrityVersion = '002_product_expiration_required';
    if (!$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$integrityVersion])) {
        $invalid = $db->fetchOne("SELECT COUNT(*) AS total FROM productos WHERE fecha_vencimiento IS NULL OR TRIM(sku) = '' OR TRIM(nombre_producto) = '' OR precio_venta <= 0 OR precio_costo < 0 OR id_categoria IS NULL");
        if ((int)($invalid['total'] ?? 0) > 0) {
            throw new RuntimeException('No se puede aplicar la migracion: existen productos con datos obligatorios incompletos.');
        }
        $db->execute('ALTER TABLE productos MODIFY fecha_vencimiento DATE NOT NULL');
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)', [$integrityVersion]);
        echo "[OK] Migracion completada: {$integrityVersion}\n";
    } else {
        echo "[OK] Migracion ya aplicada: {$integrityVersion}\n";
    }
    $constraintsVersion = '003_product_value_constraints';
    if (!$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$constraintsVersion])) {
        $invalidValues = $db->fetchOne("SELECT COUNT(*) AS total FROM productos WHERE precio_venta <= 0 OR precio_costo <= 0 OR stock_actual < 0 OR stock_minimo <= 0 OR stock_maximo < stock_minimo");
        if ((int)($invalidValues['total'] ?? 0) > 0) {
            throw new RuntimeException('No se puede aplicar la migracion: existen productos con precios o stocks invalidos.');
        }
        $db->execute('ALTER TABLE productos ADD CONSTRAINT chk_prod_precio_venta CHECK (precio_venta > 0)');
        $db->execute('ALTER TABLE productos ADD CONSTRAINT chk_prod_precio_costo CHECK (precio_costo > 0)');
        $db->execute('ALTER TABLE productos ADD CONSTRAINT chk_prod_stock CHECK (stock_actual >= 0 AND stock_minimo > 0 AND (stock_maximo = 0 OR stock_maximo >= stock_minimo))');
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)', [$constraintsVersion]);
        echo "[OK] Migracion completada: {$constraintsVersion}\n";
    } else {
        echo "[OK] Migracion ya aplicada: {$constraintsVersion}\n";
    }
    $controlVersion = '004_product_expiration_control';
    if (!$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$controlVersion])) {
        foreach ([
            ['fecha_vencimiento', "DATE NULL"],
            ['requiere_vencimiento', "TINYINT(1) NOT NULL DEFAULT 1 AFTER fecha_vencimiento"],
            ['tipo_control', "ENUM('FEFO','FIFO') NOT NULL DEFAULT 'FEFO' AFTER requiere_vencimiento"],
        ] as [$columnName, $definition]) {
            if ($columnName === 'fecha_vencimiento') {
                $db->execute("ALTER TABLE productos MODIFY fecha_vencimiento DATE NULL");
            } elseif (!$column('productos', $columnName)) {
                $db->execute("ALTER TABLE productos ADD COLUMN {$columnName} {$definition}");
            }
        }
        $db->execute("ALTER TABLE detalle_compras MODIFY fecha_vencimiento DATE NULL");
        $db->execute("UPDATE productos SET requiere_vencimiento = 1, tipo_control = 'FEFO' WHERE requiere_vencimiento IS NULL OR tipo_control IS NULL");
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)', [$controlVersion]);
        echo "[OK] Migracion completada: {$controlVersion}\n";
    } else {
        echo "[OK] Migracion ya aplicada: {$controlVersion}\n";
    }
    $capacityVersion = '005_product_capacity_inheritance';
    if (!$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$capacityVersion])) {
        $db->execute('ALTER TABLE productos DROP CONSTRAINT chk_prod_stock');
        $db->execute('ALTER TABLE productos ADD CONSTRAINT chk_prod_stock CHECK (stock_actual >= 0 AND stock_minimo > 0 AND (stock_maximo = 0 OR stock_maximo >= stock_minimo))');
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)', [$capacityVersion]);
        echo "[OK] Migracion completada: {$capacityVersion}\n";
    } else {
        echo "[OK] Migracion ya aplicada: {$capacityVersion}\n";
    }
    $expirationConstraintVersion = '006_product_expiration_consistency';
    if (!$db->fetchOne('SELECT version FROM schema_migrations WHERE version = ?', [$expirationConstraintVersion])) {
        $invalidExpiration = $db->fetchOne("SELECT COUNT(*) AS total FROM productos WHERE requiere_vencimiento = 1 AND fecha_vencimiento IS NULL");
        if ((int)($invalidExpiration['total'] ?? 0) > 0) {
            throw new RuntimeException('No se puede aplicar la migracion: existen productos FEFO sin fecha de vencimiento.');
        }
        $db->execute('ALTER TABLE productos ADD CONSTRAINT chk_prod_vencimiento CHECK (requiere_vencimiento = 0 OR fecha_vencimiento IS NOT NULL)');
        $db->execute('INSERT INTO schema_migrations(version) VALUES(?)', [$expirationConstraintVersion]);
        echo "[OK] Migracion completada: {$expirationConstraintVersion}\n";
    } else {
        echo "[OK] Migracion ya aplicada: {$expirationConstraintVersion}\n";
    }
} catch (Throwable $e) { fwrite(STDERR,"[ERROR] Migracion fallida: ".$e->getMessage()."\n"); exit(1); }