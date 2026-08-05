<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();

$esAdmin = Security::esAdmin();
$csrf_token = Security::generateToken();

// Auto-incremento FAC
$ultimo_fac = $db->fetchOne("SELECT nro_factura FROM compras WHERE nro_factura LIKE 'FAC-%' ORDER BY id_compra DESC LIMIT 1");
$proximo_num = 1;
if ($ultimo_fac) {
    $num = (int)preg_replace('/[^0-9]/', '', $ultimo_fac['nro_factura']);
    $proximo_num = $num + 1;
}
$fac_default = 'FAC-' . str_pad($proximo_num, 8, '0', STR_PAD_LEFT);

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================
// AJAX: Crear producto desde el modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_producto']) && $_POST['accion_producto'] === 'crear_ajax') {
    header('Content-Type: application/json');
    $nombre = mb_strtoupper(trim($_POST['nombre_producto'] ?? ''));
    $id_cat = intval($_POST['id_categoria'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $stock_maximo = intval($_POST['stock_maximo'] ?? 0);
    $status = $_POST['status'] ?? 'Activo';
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    if (empty($nombre) || $id_cat <= 0) {
        echo json_encode(['success' => false, 'error' => 'Nombre y categoría requeridos']);
        exit;
    }
    if ($stock_maximo < 0) {
        echo json_encode(['success' => false, 'error' => 'Capacidad máxima no puede ser negativa']);
        exit;
    }

    $dup = $db->fetchOne("SELECT id_producto FROM productos WHERE LOWER(nombre_producto) = LOWER(?)", [$nombre]);
    if ($dup) {
        echo json_encode(['success' => false, 'error' => 'Ya existe un producto con ese nombre']);
        exit;
    }

    $db->execute("INSERT IGNORE INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('PROD', 0)");

    $db->begin();
    try {
        $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='PROD' FOR UPDATE");
        $prox = intval($cnt['ultimo_numero'] ?? 0) + 1;
        $sku = 'PROD-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
        $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='PROD'", [$prox]);

        $id = $db->insert('productos', [
            'sku'               => $sku,
            'nombre_producto'   => $nombre,
            'precio_venta'      => 0,
            'precio_costo'      => 0,
            'stock_actual'      => 0,
            'stock_minimo'      => $stock_minimo,
            'stock_maximo'      => $stock_maximo,
            'status'            => $status,
            'id_categoria'      => $id_cat,
            'fecha_vencimiento' => $fecha_vencimiento,
        ]);

        $db->commit();

        echo json_encode(['success' => true, 'id' => $id, 'nombre' => $nombre, 'sku' => $sku]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
    }
    exit;
}

// Procesar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_compra'])) {
    $tipo_entrada = in_array(trim($_POST['tipo_entrada'] ?? ''), ['Compra a proveedor', 'Ajuste', 'Donación']) ? trim($_POST['tipo_entrada']) : 'Compra a proveedor';
    $es_proveedor = $tipo_entrada === 'Compra a proveedor';
    $es_ajuste = $tipo_entrada === 'Ajuste';
    $es_donacion = $tipo_entrada === 'Donación';

    $mapa_direccion = [
        // Ajuste — Sumar stock
        'Sobrante físico'               => 1,
        'Devolución'                    => 1,
        'Error de conteo (+) Excedente' => 1,
        // Ajuste — Restar stock
        'Producto vencido'              => -1,
        'Dañado/Averiado'               => -1,
        'Robo hormiga'                  => -1,
        'Merma operativa'               => -1,
        'Error de conteo (-) Faltante'  => -1,
        // Donación — Recibir
        'Regalo de proveedor'           => 1,
        'Muestra comercial'             => 1,
        'Promocional'                   => 1,
        // Donación — Realizar
        'Apoyo comunitario'             => -1,
        'Cortesía comercial'            => -1,
        'Regalo empleado'               => -1,
        'Lote promocional'              => -1,
    ];
    $es_movimiento = $es_ajuste || $es_donacion;
    $causa_ajuste = $es_movimiento ? trim($_POST['causa_ajuste'] ?? '') : '';
    $signo_stock = $es_movimiento && isset($mapa_direccion[$causa_ajuste]) ? $mapa_direccion[$causa_ajuste] : 1;

    $id_proveedor = $es_proveedor ? intval($_POST['id_proveedor'] ?? 0) : null;
    if ($es_proveedor && $id_proveedor <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SELECCIONE UN PROVEEDOR PARA COMPRA A PROVEEDOR.'];
        header('Location: compras.php');
        exit;
    }
    $nro_factura = trim($_POST['nro_factura'] ?? '');
    if ($es_proveedor && empty($nro_factura)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL NÚMERO DE FACTURA ES OBLIGATORIO.'];
        header('Location: compras.php');
        exit;
    }
    if ($es_proveedor && $db->fetchOne("SELECT id_compra FROM compras WHERE nro_factura = ? AND status = 'Activa'", [$nro_factura])) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL NÚMERO DE FACTURA YA EXISTE EN EL SISTEMA.'];
        header('Location: compras.php');
        exit;
    }
    $nro_control = $es_proveedor ? trim($_POST['nro_control'] ?? '') : null;
    if ($es_proveedor && !preg_match('/^\d{2}-\d{8}$/', $nro_control)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'NRO. CONTROL INVÁLIDO. Formato: 00-00000000'];
        header('Location: compras.php');
        exit;
    }
    $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
    if ($es_movimiento && empty($causa_ajuste)) {
        $tipo_label = $es_donacion ? 'DONACIÓN' : 'AJUSTE';
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => "SELECCIONE UNA CAUSA PARA $tipo_label."];
        header('Location: compras.php');
        exit;
    }
    if ($es_movimiento && !isset($mapa_direccion[$causa_ajuste])) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CAUSA INVÁLIDA.'];
        header('Location: compras.php');
        exit;
    }
    $motivo = $es_movimiento ? trim($_POST['motivo'] ?? '') : '';
    $tipo_prefix = $es_donacion ? 'Donación' : 'Ajuste';
    $label_dir = $signo_stock > 0 ? '(+)' : '(-)';
    $observaciones = $es_movimiento ? "Causa: $causa_ajuste $label_dir" . ($motivo ? " | Motivo: $motivo" : '') : ($_POST['observaciones'] ?? '');

    $condiciones_pago = 'Contado';
    $dias_credito = 0;
    $fecha_vencimiento = null;

    if ($es_proveedor && $id_proveedor > 0) {
        $prov = $db->fetchOne("SELECT condiciones_pago, dias_credito, limite_credito FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);
        $condiciones_pago = $prov['condiciones_pago'] ?? 'Contado';
        $dias_credito = intval($prov['dias_credito'] ?? 0);
        if ($condiciones_pago === 'Credito' && $dias_credito > 0) {
            $fecha_vencimiento = date('Y-m-d', strtotime("+$dias_credito days", strtotime($fecha_compra)));
        }
    }

    $productos_raw = json_decode($_POST['productos_data'] ?? '[]', true);
    $productos = is_array($productos_raw) ? $productos_raw : [];
    if (count($productos) > 200) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'MÁXIMO 200 PRODUCTOS POR LOTE.'];
        header('Location: compras.php');
        exit;
    }

    // Validar límite de crédito
    if ($condiciones_pago === 'Credito' && $id_proveedor > 0) {
        $limite = (float)($prov['limite_credito'] ?? 0);
        if ($limite > 0) {
            $usado = (float)$db->fetchOne("SELECT COALESCE(SUM(total),0) as t FROM compras WHERE id_proveedor = ? AND status = 'Activa' AND condiciones_pago = 'Credito'", [$id_proveedor])['t'];
            $total_estimado = 0;
            foreach ($productos as $prod) {
                $total_estimado += intval($prod['cantidad'] ?? 0) * floatval($prod['precio'] ?? 0);
            }
            if (($usado + $total_estimado) > $limite) {
                $disponible = $limite - $usado;
                $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => "CRÉDITO INSUFICIENTE. Límite: \$" . number_format($limite, 2) . ", usado: \$" . number_format($usado, 2) . ", disponible: \$" . number_format(max(0, $disponible), 2) . "."];
                header('Location: compras.php');
                exit;
            }
        }
    }
    $exitos = 0;

    if (empty($productos)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'DEBE AGREGAR AL MENOS UN PRODUCTO A LA ENTRADA.'];
        header('Location: compras.php');
        exit;
    }

    $db->begin();
    try {
        $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);

        // 1. Insertar cabecera en compras
        $compra_id = $db->insert('compras', [
            'nro_factura'      => $nro_factura,
            'id_proveedor'     => $id_proveedor ?: null,
            'id_usuario'       => $id_usuario_sesion,
            'fecha_compra'     => $fecha_compra,
            'nro_control'      => $nro_control,
            'condiciones_pago' => $condiciones_pago,
            'dias_plazo'       => $dias_credito,
            'total'            => 0,
            'status'           => 'Activa',
            'tipo_entrada'     => $tipo_entrada,
            'observaciones'    => $observaciones,
        ]);

        $total_compra = 0;
        foreach ($productos as $prod) {
            $id_producto = intval($prod['id'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            $precio_costo = ($es_donacion || $signo_stock < 0) ? 0 : floatval($prod['precio'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0 || ($signo_stock > 0 && !$es_donacion && $precio_costo <= 0)) continue;

            $lote_venc = null;
            if (!empty($prod['fecha_vencimiento'])) {
                $fecha_v = trim($prod['fecha_vencimiento']);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_v)) $lote_venc = $fecha_v;
            }

            // 2. Insertar detalle
            $db->insert('detalle_compras', [
                'id_compra'       => $compra_id,
                'id_producto'     => $id_producto,
                'cantidad'        => $cantidad,
                'precio_costo'    => $precio_costo,
                'fecha_vencimiento' => $lote_venc,
                'observaciones'   => $observaciones,
            ]);

            // 3. Actualizar stock en productos
            $prod_row = $db->fetchOne("SELECT p.stock_actual, p.precio_costo, p.precio_venta, COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as capacidad FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.id_producto = ?", [$id_producto]);
            $old_stock = (int)($prod_row['stock_actual'] ?? 0);
            $old_cost = (float)($prod_row['precio_costo'] ?? 0);
            $old_pv = (float)($prod_row['precio_venta'] ?? 0);
            $new_stock = $old_stock + ($signo_stock * $cantidad);
            if ($new_stock < 0) throw new Exception("Stock insuficiente para {$prod['nombre']} (ID:$id_producto). Disponible: $old_stock, solicitado: $cantidad.");
            if ($signo_stock > 0) {
                $capacidad = (int)($prod_row['capacidad'] ?? 100);
                if ($new_stock > $capacidad)
                    throw new Exception("CAPACIDAD DE ALMACENAMIENTO SUPERADA para {$prod['nombre']} (ID:$id_producto). Capacidad: $capacidad, stock resultante: $new_stock.");
            }
            if (!$es_donacion && $signo_stock > 0) {
                $new_avg = $new_stock > 0 ? (($old_stock * $old_cost) + ($cantidad * $precio_costo)) / $new_stock : $precio_costo;
                $new_pv = $old_pv > 0 ? $old_pv : round($new_avg * 1.3, 2);
            } else {
                $new_avg = $old_cost;
                $new_pv = $old_pv;
            }
            $db->execute("UPDATE productos SET stock_actual = ?, precio_costo = ?, precio_venta = ? WHERE id_producto = ?", [$new_stock, $new_avg, $new_pv, $id_producto]);

            // 3b. Gestión de lotes
            if ($signo_stock > 0) {
                $db->insert('lotes', [
                    'id_producto'       => $id_producto,
                    'id_proveedor'      => $id_proveedor ?: null,
                    'id_compra'         => $compra_id,
                    'cantidad'          => $cantidad,
                    'cantidad_restante' => $cantidad,
                    'precio_costo'      => $precio_costo,
                    'fecha_vencimiento' => $lote_venc,
                ]);
            } else {
                $solo_vencidos = $es_ajuste && trim($causa_ajuste) === 'Producto vencido';
                $tiene_lotes = (int)$db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$id_producto])['n'];
                if ($tiene_lotes > 0) {
                    foreach (consumirLotes($db, $id_producto, $cantidad, $solo_vencidos) as $u) {
                        $db->execute("UPDATE lotes SET cantidad_restante = cantidad_restante - ? WHERE id_lote = ?", [$u['cantidad'], $u['id_lote']]);
                    }
                }
            }

            $subtotal_item = $cantidad * $precio_costo;
            $total_compra += $subtotal_item;
            $exitos++;
        }

        if ($exitos > 0) {
            // 4. Actualizar total en cabecera
            $db->execute("UPDATE compras SET subtotal = ?, total = ? WHERE id_compra = ?", [$total_compra, $total_compra, $compra_id]);

            // 5. Insertar movimiento de inventario
            $mov_id = $db->insert('movimientos', [
                'id_referencia'   => $compra_id,
                'tipo_referencia' => 'compra',
                'tipo'            => 'Entrada',
                'id_usuario'      => $id_usuario_sesion,
                'status'          => 'Activo',
            ]);

            // 6. Insertar detalle de movimiento (re-iterate productos)
            foreach ($productos as $prod) {
                $id_producto = intval($prod['id'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                $precio_costo = $es_donacion ? 0 : floatval($prod['precio'] ?? 0);
                if ($id_producto <= 0 || $cantidad <= 0) continue;
                $db->insert('detalle_movimientos', [
                    'id_movimiento'  => $mov_id,
                    'id_producto'    => $id_producto,
                    'cantidad'       => $cantidad,
                    'precio_unitario' => $precio_costo,
                ]);
            }

            $det_auditoria = $es_proveedor ? "Entrada registrada ($tipo_entrada, $exitos producto(s))" : "$tipo_prefix $label_dir: Causa: $causa_ajuste, $exitos producto(s)";
            registrarAuditoria('crear', $det_auditoria);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL PROCESAR LA ENTRADA. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
        header('Location: compras.php');
        exit;
    }
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => $exitos > 0 ? "ENTRADA REGISTRADA: $exitos producto(s)." : 'Error al guardar: verifica los datos e intenta de nuevo.'];
    header('Location: compras.php');
    exit;
}

// Eliminar / anular
if (isset($_POST['eliminar']) && $esAdmin) {
    $id_compra = intval($_POST['eliminar']);
    $compra = $db->fetchOne("SELECT tipo_entrada, observaciones FROM compras WHERE id_compra = ?", [$id_compra]);
    $obs = $compra['observaciones'] ?? '';
    $fue_resta = strpos($obs, '(-)') !== false;
    $detalles = $db->fetchAll("SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = ?", [$id_compra]);
    if (!empty($detalles)) {
        $db->begin();
        try {
            // Validar stock antes de revertir
            foreach ($detalles as $det) {
                if (!$fue_resta) {
                    $pi = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [(int)$det['id_producto']]);
                    if (!$pi || (int)$pi['stock_actual'] < (int)$det['cantidad'])
                        throw new Exception("STOCK INSUFICIENTE para el producto (ID:{$det['id_producto']}). Se vendió parte del lote, no se puede anular.");
                }
            }
            foreach ($detalles as $det) {
                $delta = $fue_resta ? (int)$det['cantidad'] : -(int)$det['cantidad'];
                $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [$delta, (int)$det['id_producto']]);
            }
            if (!$fue_resta) {
                $db->execute("UPDATE lotes SET cantidad_restante = cantidad WHERE id_compra = ?", [$id_compra]);
            }
            $db->execute("UPDATE compras SET status = 'Anulada' WHERE id_compra = ?", [$id_compra]);
            $db->execute("UPDATE movimientos SET status = 'Anulado' WHERE id_referencia = ? AND tipo_referencia = 'compra'", [$id_compra]);
            $db->commit();
            $label = $fue_resta ? 'RESTA (-) ANULADA' : 'SUMA (+) ANULADA';
            registrarAuditoria('anular', "Compra #$id_compra anulada ($label), " . count($detalles) . " producto(s)");
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => $fue_resta ? 'AJUSTE/DONACIÓN (-) ANULADO. STOCK RESTAURADO.' : 'ENTRADA ANULADA. STOCK RESTAURADO.'];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        }
    }
    header('Location: compras.php');
    exit;
}

// ==========================================
// OBTENER DATOS
// ==========================================
$filtro_proveedor = intval($_GET['filtro_proveedor'] ?? 0);
$sql_compras = "
    SELECT c.*,
           GROUP_CONCAT(DISTINCT p.nombre_producto SEPARATOR ', ') as productos_list,
           SUM(dc.cantidad) as total_cantidad,
           COUNT(dc.id_detalle) as num_productos,
           MIN(dc.observaciones) as observaciones,
           pr.nombre_empresa as proveedor
    FROM compras c
    LEFT JOIN detalle_compras dc ON c.id_compra = dc.id_compra
    LEFT JOIN productos p ON dc.id_producto = p.id_producto
    LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
    WHERE c.status = 'Activa'
";
$params = [];
if ($filtro_proveedor > 0) {
    $sql_compras .= " AND c.id_proveedor = ?";
    $params[] = $filtro_proveedor;
}
$sql_compras .= " GROUP BY c.id_compra ORDER BY c.fecha_compra DESC, c.id_compra DESC LIMIT 100";
$compras = $db->fetchAll($sql_compras, $params);

$productos = $db->fetchAll("SELECT id_producto, nombre_producto, stock_actual, precio_costo FROM productos WHERE status = 'Activo' ORDER BY nombre_producto");
$proveedores = $db->fetchAll("SELECT id_proveedor, nombre_empresa, rif, condiciones_pago, dias_credito, limite_credito FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa");
$categorias = $db->fetchAll("SELECT id_categoria, nombre FROM categorias WHERE status = 'Activo' ORDER BY nombre");

$credito_usado = [];
$rows_used = $db->fetchAll("SELECT id_proveedor, COALESCE(SUM(total),0) as usado FROM compras WHERE status = 'Activa' AND condiciones_pago = 'Credito' AND id_proveedor IS NOT NULL GROUP BY id_proveedor");
foreach ($rows_used as $r) {
    $credito_usado[(int)$r['id_proveedor']] = (float)$r['usado'];
}

$total_entradas = (int)$db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa'")['t'];
$entradas_hoy = (int)$db->fetchOne("SELECT COALESCE(SUM(dc.cantidad),0) as t FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra >= CURDATE() AND c.fecha_compra < CURDATE() + INTERVAL 1 DAY AND c.status = 'Activa'")['t'];
$inv_mes_row = $db->fetchOne("SELECT COALESCE(SUM(c.total),0) as t FROM compras c WHERE c.fecha_compra >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND c.fecha_compra < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01') AND c.status = 'Activa'");
$invertido_mes = $inv_mes_row['t'] ?? 0;

// Manejo de mensajes flash


$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!-- HEAD Y ESTILOS HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
        <link rel="stylesheet" href="../assets/modules/compras/compras.css">
</head>
<!-- BODY HTML -->

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 pagina-compras">

            <!-- Encabezado -->
            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="com-header-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <h1 class="font-brand fw-bold m-0" style="font-size:1.6rem;letter-spacing:-1px; color: var(--jv-text-primary);">COMPRAS</h1>
                        <p class="text-secondary small fw-bold text-uppercase m-0">Entradas de Inventario</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-jv-success pulse-jv" data-bs-toggle="modal" data-bs-target="#modalCompra" style="padding:10px 28px;font-size:.9rem;">
                        <i class="bi bi-plus-lg me-1"></i>NUEVA ENTRADA
                    </button>
                </div>
            </div>

            <!-- Mensajes flash -->
            <?php if ($flash): ?>
                <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($flash['texto']); ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas / Widgets -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="widget-card" style="border-left:4px solid var(--jv-success);">
                        <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <div class="widget-label">Total Entradas</div>
                            <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo $total_entradas; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="widget-card" style="border-left:4px solid var(--jv-warning);">
                        <div class="widget-icon" style="background:rgba(217,119,6,0.12);color:var(--jv-warning);">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div>
                            <div class="widget-label">Invertido (Mes)</div>
                            <div class="widget-value" style="color: var(--jv-text-primary);">$<?php echo number_format($invertido_mes, 0); ?></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Tabla de entradas -->
            <div class="card-jv card-jv-table p-0">
                <div class="d-flex align-items-center px-3 py-2 buscador-wrapper">
                    <i class="bi bi-search me-2" style="font-size:1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar entradas..." id="buscar" onkeyup="filtrar()" style="box-shadow:none;font-size:.85rem;padding:8px 6px;">
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="min-width:100px;">Factura</th>
                                <th style="min-width:120px;">Nro. Control</th>
                                <th>Proveedor</th>
                                <th>Productos</th>
                                <th style="width:110px;">Tipo</th>
                                <th class="text-center" style="width:70px;">Cant</th>
                                <th style="width:100px;">Total</th>
                                <th class="text-center">Condiciones</th>
                                <th style="width:90px;">Fecha</th>
                                <th class="text-center" style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tablaEntradas">
                            <?php if (count($compras) > 0): foreach ($compras as $row): ?>
                                    <tr>
                                        <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura'] ?: '-'); ?></span></td>
                                        <td style="color:var(--jv-text-muted);font-weight:600;"><?php echo htmlspecialchars($row['nro_control'] ?: '-'); ?></td>
                                        <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?></td>
                                        <td style="font-size:.75rem;color:var(--jv-text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($row['productos_list'] ?? ''); ?>"><?php echo htmlspecialchars($row['productos_list'] ?? '-'); ?></td>
                                        <td><?php
                                            $te = $row['tipo_entrada'] ?? 'Compra a proveedor';
                                            $obs = $row['observaciones'] ?? '';
                                            $causa = '';
                                            if (preg_match('/^Causa:\s*(.+?)(?:\s*\||$)/', $obs, $m)) $causa = trim($m[1]);
                                            if ($te === 'Compra a proveedor') echo '<span class="badge-jv badge-success" style="font-size:.7rem;"><i class="bi bi-cart-check me-1"></i>Compra</span>';
                                            else {
                                                $badge = $te === 'Ajuste' ? 'badge-warning' : 'badge-info';
                                                echo '<span class="badge-jv ' . $badge . '" style="font-size:.7rem;" title="' . htmlspecialchars($causa) . '"><i class="bi bi-arrow-up-circle me-1"></i>' . htmlspecialchars($te) . ($causa ? ': ' . htmlspecialchars($causa) : '') . '</span>';
                                            }
                                            ?></td>
                                        <td class="text-center"><span class="cant-badge">+<?php echo $row['total_cantidad']; ?></span></td>
                                        <td class="fw-bold text-success">$<?php echo number_format($row['total'], 2); ?></td>
                                        <td class="text-center"><span class="badge-jv <?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'badge-success' : 'badge-warning'; ?>"><i class="<?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'bi bi-cash-stack' : 'bi bi-calendar-check'; ?> me-1"></i><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span></td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                        <td class="text-center">
                                            <?php if ($esAdmin): ?>
                                                <button type="button" class="btn-action" onclick="confirmarEliminar(<?php echo $row['id_compra']; ?>)"><i class="bi bi-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="estado-vacio">
                                            <i class="bi bi-cart-x"></i>
                                            <span>No hay entradas registradas</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Registrar entrada -->
    <div class="modal fade" id="modalCompra" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formCompra">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_compra" value="registrar">
                    <input type="hidden" name="productos_data" id="productosData">

                    <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:1rem;letter-spacing:-.5px;"><i class="bi bi-cart-plus me-2"></i>REGISTRAR ENTRADA</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>

                    <div class="p-3" style="padding:16px 20px;">
                        <div class="comp-proveedor-section section-bg">
                            <div class="section-label"><i class="bi bi-building me-1"></i>Proveedor</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-1">PROVEEDOR *</label>
                                    <select name="id_proveedor" class="input-jv" id="selProveedor">
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($proveedores as $p): ?>
                                            <option value="<?php echo $p['id_proveedor']; ?>" data-condicion="<?php echo $p['condiciones_pago']; ?>" data-dias="<?php echo $p['dias_credito']; ?>" data-limite="<?php echo (float)($p['limite_credito'] ?? 0); ?>" data-usado="<?php echo $credito_usado[(int)$p['id_proveedor']] ?? 0; ?>">
                                                <?php echo htmlspecialchars($p['nombre_empresa']); ?> (<?php echo $p['rif']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">CONDICIÓN</label>
                                    <input type="text" class="input-jv" id="displayCondicion" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                 <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">DÍAS</label>
                                    <input type="text" class="input-jv" id="displayDias" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                            </div>
                            <div class="row g-2 mt-1" id="rowCredito" style="display:none;">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">LÍMITE CRÉDITO</label>
                                    <input type="text" class="input-jv" id="displayLimite" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">CRÉDITO USADO</label>
                                    <input type="text" class="input-jv" id="displayUsado" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">DISPONIBLE</label>
                                    <input type="text" class="input-jv" id="displayDisponible" value="-" readonly disabled style="color:var(--jv-text-muted);font-weight:700;">
                                </div>
                            </div>
                        </div>

                        <!-- MOTIVO (Ajuste / Donación) -->
                        <div class="comp-motivo-section section-bg" style="display:none;">
                            <div class="section-label"><i class="bi bi-chat-dots me-1"></i><span id="motivoLabel">Motivo</span></div>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="small fw-bold text-secondary mb-1">CAUSA *</label>
                                    <div class="d-flex gap-2 align-items-start">
                                        <select name="causa_ajuste" class="input-jv" style="flex:1;" onchange="actualizarDireccion()">
                                            <option value="">Seleccionar...</option>
                                        </select>
                                        <span id="direccionBadge" class="badge" style="display:none;padding:5px 12px;font-size:.7rem;border-radius:6px;font-weight:700;white-space:nowrap;margin-top:1px;"></span>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <label class="small fw-bold text-secondary mb-1">DESCRIPCIÓN <span class="fw-normal">(opcional)</span></label>
                                    <textarea name="motivo" class="input-jv" rows="2" placeholder="Detalle adicional..." style="resize:vertical;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-receipt me-1"></i>Comprobante</div>
                            <div class="row g-2">
                                <div class="comp-factura-section col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">NRO. FACTURA *</label>
                                    <input type="text" class="input-jv" value="<?php echo htmlspecialchars($fac_default); ?>" disabled style="color:var(--jv-text-muted);">
                                    <input type="hidden" name="nro_factura" value="<?php echo htmlspecialchars($fac_default); ?>">
                                </div>
                                <div class="comp-factura-section col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">NRO. CONTROL *</label>
                                    <input type="text" name="nro_control" class="input-jv" value="" placeholder="00-00000000" oninput="var v=this.value.replace(/[^0-9]/g,'');if(v.length>10)v=v.slice(0,10);if(v.length>2)v=v.slice(0,2)+'-'+v.slice(2);this.value=v" maxlength="11">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">TIPO</label>
                                    <select name="tipo_entrada" class="input-jv" onchange="toggleCamposCompras(this)">
                                        <option>Compra a proveedor</option>
                                        <option>Ajuste</option>
                                        <option>Donación</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">FECHA</label>
                                    <input type="date" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
                                    <input type="hidden" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-box-seam me-1"></i>Productos</div>

                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">Producto</label>
                                    <div class="d-flex gap-2">
                                        <select class="input-jv" id="selProducto" style="flex:1;min-width:0;">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($productos as $prod): ?>
                                                <option value="<?php echo $prod['id_producto']; ?>" data-precio="<?php echo $prod['precio_costo']; ?>">
                                                    <?php echo htmlspecialchars($prod['nombre_producto']); ?> (Stock: <?php echo $prod['stock_actual']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn-jv-primary pagina-compras" style="flex-shrink:0;padding:8px 14px;font-size:.75rem;white-space:nowrap;font-weight:700;" onclick="abrirNuevoProducto()" title="Crear producto nuevo">
                                            <i class="bi bi-lightning-fill me-1"></i>CREAR
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Cant</label>
                                    <input type="number" class="input-jv" id="inputCant" value="1" min="1" max="999999" oninput="if(this.value>999999)this.value=999999;if(this.value<1)this.value=1">
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                    <input type="text" inputmode="decimal" class="input-jv" id="inputPrecio" placeholder="0.00" readonly style="background:var(--jv-bg-primary);cursor:not-allowed;color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">Vence <span class="text-muted fw-normal">(opcional)</span></label>
                                    <input type="date" class="input-jv" id="inputVencimiento">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn-jv-primary w-100" style="padding:12px;" onclick="agregarProducto()">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;margin-top:10px;">
                                <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                    <thead>
                                        <tr style="background:var(--jv-navy);">
                                            <th style="padding:6px 8px;width:28px;text-align:center;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                            <th style="padding:6px 8px;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                            <th style="padding:6px 8px;width:55px;text-align:center;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                            <th style="padding:6px 8px;width:90px;text-align:right;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                            <th style="padding:6px 8px;width:110px;text-align:center;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Vence</th>
                                            <th style="padding:6px 8px;width:90px;text-align:right;color:#fff;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                            <th style="width:28px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productosBody">
                                        <tr id="filaVacia">
                                            <td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;border-bottom:1px solid var(--jv-border);">⬆ Use los controles de arriba para agregar productos</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;margin-top:8px;background:var(--jv-bg-card);border:1px solid var(--jv-border);border-radius:8px;">
                                <div>
                                    <span class="text-secondary" style="font-weight:600;font-size:.9rem;">Productos</span>
                                    <span class="fw-bold ms-2" id="totalItems" style="color:var(--jv-navy);font-size:1.1rem;">0</span>
                                </div>
                                <div>
                                    <span class="text-secondary" style="font-weight:600;font-size:.9rem;">Total Costo</span>
                                    <span class="fw-bold ms-2" id="totalCosto" style="color:var(--jv-warning);font-size:1.15rem;">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="section-bg" style="margin-bottom:0;">
                            <div class="section-label"><i class="bi bi-chat-text me-1"></i>Observaciones</div>
                            <input type="text" name="observaciones" class="input-jv" placeholder="Notas opcionales...">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                        <button type="button" class="btn btn-jv-danger" style="padding:10px 24px;font-size:.85rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-jv-success" id="btnGuardar" disabled style="padding:10px 24px;font-size:.85rem;" onclick="return validarFormulario(this)"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Crear producto -->
    <div class="modal fade" id="modalNuevoProducto" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:.95rem;letter-spacing:-.5px;"><i class="bi bi-box-seam-fill me-2"></i>CREAR PRODUCTO</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="p-3">
                    <input type="hidden" id="np_csrf" value="<?php echo $csrf_token; ?>">
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">NOMBRE *</label>
                        <input type="text" class="input-jv" id="np_nombre" required placeholder="Ej: ACEITE DE MOTOR 20W-50" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">CATEGORÍA *</label>
                        <select class="input-jv" id="np_categoria" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                            <input type="number" class="input-jv" id="np_stock_minimo" min="0" max="99999" value="5">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-secondary mb-1">CAPACIDAD MÁX. <span class="text-muted fw-normal">(0 = categoría)</span></label>
                            <input type="number" class="input-jv" id="np_stock_maximo" min="0" max="999999" value="0">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                            <select class="input-jv" id="np_status">
                                <option value="Activo" selected>Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">FECHA VENCIMIENTO <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="date" class="input-jv" id="np_fecha_vencimiento">
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-jv-danger" style="padding:10px 24px;font-size:.85rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" id="btnCrearProducto" style="padding:10px 24px;font-size:.85rem;" onclick="crearProducto()"><i class="bi bi-check-lg me-1"></i> Crear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>' };
</script>
    <script src="../assets/modules/compras/compras.js"></script>
    
</body>

</html>