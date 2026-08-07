<?php

// ==========================================
// MIGRACIÓN: NORMALIZAR DOCUMENTO FISCAL (CÉDULA/RIF)
// ==========================================
// Uso:  php db/migrar_documentos.php [nombre_bd]
// Idempotente: los valores ya válidos no se tocan.
// Hace un backup previo en backups/ antes de escribir.
// Reglas:
//   - salidas.rif_cliente : irrecuperable → NULL ; corregible → UPDATE
//   - clientes.documento  : irrecuperable → NULL ; corregible → UPDATE (bloqueado si colisiona)
//   - proveedores.rif     : irrecuperable → se deja + reporte ; corregible → UPDATE (bloqueado si colisiona)
//   - N/A / S/RIF / vacíos se ignoran (no son documentos reales)
//
// También exportable como función para auto-ejecución desde init.php:
//   require_once db/migrar_documentos.php; migrar_documentos($conn, DB_NAME);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!function_exists('mig_doc_es_placeholder')) {
    function mig_doc_es_placeholder(string $v): bool {
        $n = mb_strtoupper(trim($v));
        return $n === '' || $n === 'N/A' || $n === 'S/RIF' || $n === 'SIN IDENTIFICACION';
    }
}

if (!function_exists('migrar_documentos')) {
    function migrar_documentos($conn, string $db_name): void
    {
        echo "== Migración documentos fiscales: $db_name ==\n";

        // --- 0. Backup previo con mysqldump ---
        $mysqlDump = '';
        foreach (['C:\xampp', 'D:\xampp', 'C:\Program Files\XAMPP', 'C:\Program Files (x86)\XAMPP'] as $base) {
            if (is_file($base . '\\mysql\\bin\\mysqldump.exe')) { $mysqlDump = $base . '\\mysql\\bin\\mysqldump.exe'; break; }
        }
        $backupFile = __DIR__ . '/../backups/jv3000_db_antes_documentos_' . date('Y-m-d_His') . '.sql';
        if ($mysqlDump !== '' && is_file($mysqlDump)) {
            $cmd = '"' . $mysqlDump . '" -u' . DB_USER . (DB_PASS !== '' ? ' -p' . DB_PASS : '') . ' ' . escapeshellarg($db_name);
            $out = [];
            exec($cmd . ' 2>&1', $out, $code);
            if ($code === 0) {
                file_put_contents($backupFile, implode("\n", $out));
                echo "  [ok]   backup: " . basename($backupFile) . " (" . filesize($backupFile) . " bytes)\n";
            } else {
                echo "  [warn] no se pudo generar backup automático (mysqldump falló).\n";
            }
        } else {
            echo "  [warn] no se encontró mysqldump; NO se generó backup.\n";
        }

        // --- Reporte de inválidos/bloqueados ---
        $reporte = __DIR__ . '/../backups/reporte_documentos_invalidos_' . date('Y-m-d_His') . '.txt';
        $reportLines = [];
        $reportLines[] = "Reporte de documentos fiscales no migrados / bloqueados";
        $reportLines[] = "Fecha: " . date('Y-m-d H:i:s');
        $reportLines[] = str_repeat('-', 60);

        $stats = ['salidas' => ['ok'=>0,'correg'=>0,'borrado'=>0,'bloqueado'=>0,'dejado'=>0], 'clientes' => ['ok'=>0,'correg'=>0,'borrado'=>0,'bloqueado'=>0,'dejado'=>0], 'proveedores' => ['ok'=>0,'correg'=>0,'borrado'=>0,'bloqueado'=>0,'dejado'=>0]];

        // ==========================================
        // 1. salidas.rif_cliente
        // ==========================================
        echo "\n[1] salidas.rif_cliente\n";
        $t_salidas = mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas'");
        if ($t_salidas && mysqli_fetch_row($t_salidas)) {
        $rows = mysqli_query($conn, "SELECT id_salida, rif_cliente FROM salidas WHERE rif_cliente IS NOT NULL AND rif_cliente <> ''");
        if ($rows) {
            while ($r = mysqli_fetch_assoc($rows)) {
                $v = $r['rif_cliente'];
                $id = (int)$r['id_salida'];
                if (mig_doc_es_placeholder($v)) { $stats['salidas']['ok']++; continue; }
                if (validarDocumentoFiscal(normalizarDocumento($v))) { $stats['salidas']['ok']++; continue; }
                $nuevo = migrarFormatoDocumento($v);
                if ($nuevo === null) {
                    mysqli_query($conn, "UPDATE salidas SET rif_cliente = NULL WHERE id_salida = $id");
                    $stats['salidas']['borrado']++;
                    echo "  [borrar] id_salida=$id valor='$v'\n";
                } elseif ($nuevo !== normalizarDocumento($v)) {
                    $esc = mysqli_real_escape_string($conn, $nuevo);
                    mysqli_query($conn, "UPDATE salidas SET rif_cliente = '$esc' WHERE id_salida = $id");
                    $stats['salidas']['correg']++;
                    echo "  [ok]     id_salida=$id '$v' -> '$nuevo'\n";
                } else {
                    $stats['salidas']['ok']++;
                }
            }
        } else {
            echo "  (tabla vacía o sin filas)\n";
        }
        } else {
            echo "  (tabla 'salidas' no existe)\n";
        }

        // ==========================================
        // 2. clientes.documento
        // ==========================================
        echo "\n[2] clientes.documento\n";
        $t_clientes = mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes'");
        if ($t_clientes && mysqli_fetch_row($t_clientes)) {
        $rows = mysqli_query($conn, "SELECT id_cliente, nombre, documento FROM clientes WHERE documento IS NOT NULL AND documento <> ''");
        if ($rows) {
            while ($r = mysqli_fetch_assoc($rows)) {
                $v = $r['documento'];
                $id = (int)$r['id_cliente'];
                if (mig_doc_es_placeholder($v)) { $stats['clientes']['ok']++; continue; }
                if (validarDocumentoFiscal(normalizarDocumento($v))) { $stats['clientes']['ok']++; continue; }
                $nuevo = migrarFormatoDocumento($v);
                if ($nuevo === null) {
                    mysqli_query($conn, "UPDATE clientes SET documento = NULL WHERE id_cliente = $id");
                    $stats['clientes']['borrado']++;
                    echo "  [borrar] id_cliente=$id ('$v')\n";
                } else {
                    $esc = mysqli_real_escape_string($conn, $nuevo);
                    // Bloquear si el documento corregido ya existe en OTRO cliente
                    $dup = mysqli_query($conn, "SELECT id_cliente FROM clientes WHERE documento = '$esc' AND id_cliente <> $id LIMIT 1");
                    if ($dup && mysqli_fetch_assoc($dup)) {
                        $stats['clientes']['bloqueado']++;
                        echo "  [bloqueo] id_cliente=$id '$v' -> '$nuevo' (ya existe otro cliente con ese documento)\n";
                        $reportLines[] = "[clientes] id_cliente=$id valor='$v' -> corregido='$nuevo' (BLOQUEADO: duplicado)";
                    } else {
                        mysqli_query($conn, "UPDATE clientes SET documento = '$esc' WHERE id_cliente = $id");
                        $stats['clientes']['correg']++;
                        echo "  [ok]     id_cliente=$id '$v' -> '$nuevo'\n";
                    }
                }
            }
        } else {
            echo "  (tabla vacía o sin filas)\n";
        }
        } else {
            echo "  (tabla 'clientes' no existe)\n";
        }

        // ==========================================
        // 3. proveedores.rif  (NOT NULL — irrecuperable se deja + reporte)
        // ==========================================
        echo "\n[3] proveedores.rif\n";
        $t_prov = mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedores'");
        if ($t_prov && mysqli_fetch_row($t_prov)) {
        $rows = mysqli_query($conn, "SELECT id_proveedor, nombre_empresa, rif FROM proveedores");
        if ($rows) {
            while ($r = mysqli_fetch_assoc($rows)) {
                $v = $r['rif'];
                $id = (int)$r['id_proveedor'];
                if (validarRIF(normalizarDocumento($v))) { $stats['proveedores']['ok']++; continue; }
                $nuevo = migrarFormatoDocumento($v);
                if ($nuevo === null) {
                    $stats['proveedores']['dejado']++;
                    echo "  [dejar]  id_proveedor=$id ('$v') irrecuperable → reportado\n";
                    $reportLines[] = "[proveedores] id_proveedor=$id rif='$v' (DEJADO: no recuperable, campo NOT NULL)";
                } else {
                    $esc = mysqli_real_escape_string($conn, $nuevo);
                    $dup = mysqli_query($conn, "SELECT id_proveedor FROM proveedores WHERE rif = '$esc' AND id_proveedor <> $id LIMIT 1");
                    if ($dup && mysqli_fetch_assoc($dup)) {
                        $stats['proveedores']['bloqueado']++;
                        echo "  [bloqueo] id_proveedor=$id '$v' -> '$nuevo' (ya existe otro proveedor con ese RIF)\n";
                        $reportLines[] = "[proveedores] id_proveedor=$id rif='$v' -> corregido='$nuevo' (BLOQUEADO: duplicado)";
                    } else {
                        mysqli_query($conn, "UPDATE proveedores SET rif = '$esc' WHERE id_proveedor = $id");
                        $stats['proveedores']['correg']++;
                        echo "  [ok]     id_proveedor=$id '$v' -> '$nuevo'\n";
                    }
                }
            }
        } else {
            echo "  (tabla vacía o sin filas)\n";
        }
        } else {
            echo "  (tabla 'proveedores' no existe)\n";
        }

        // ==========================================
        // Reporte
        // ==========================================
        if (count($reportLines) > 3) {
            file_put_contents($reporte, implode("\n", $reportLines) . "\n");
            echo "\n[reporte] " . basename($reporte) . "\n";
        } else {
            echo "\n[reporte] sin casos bloqueados/inválidos → no se genera archivo\n";
        }

        echo "\n== Resumen ==\n";
        foreach (['salidas' => 'salidas.rif_cliente', 'clientes' => 'clientes.documento', 'proveedores' => 'proveedores.rif'] as $k => $label) {
            $s = $stats[$k];
            printf("  %-22s ok=%d corregidos=%d borrados=%d bloqueados=%d dejados=%d\n", $label, $s['ok'], $s['correg'], $s['borrado'], $s['bloqueado'], $s['dejado']);
        }

        echo "\nListo.\n";
    }
}

// Ejecución CLI directa
if (PHP_SAPI === 'cli') {
    $db_name = $argv[1] ?? DB_NAME;
    $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $db_name);
    if (!$conn) {
        fwrite(STDERR, '[migrar_documentos] No se pudo conectar a la BD "' . $db_name . '": ' . mysqli_connect_error() . PHP_EOL);
        exit(1);
    }
    mysqli_set_charset($conn, 'utf8mb4');
    migrar_documentos($conn, $db_name);
    mysqli_close($conn);
}
