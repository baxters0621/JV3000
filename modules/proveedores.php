<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_proveedor'])) {

    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR DE SEGURIDAD. INTENTE DE NUEVO.'];
        header("Location: proveedores.php");
        exit();
    }

    $accion = $_POST['accion_proveedor'];

    // Toggle status
    if ($accion == "toggle_status") {
        Security::soloAdmin();
        $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
        $prov = $db->fetchOne("SELECT status FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);

        if ($prov) {
            $nuevo_status = $prov['status'] == 'Activo' ? 'Inactivo' : 'Activo';
            $db->execute("UPDATE proveedores SET status = ? WHERE id_proveedor = ?", [$nuevo_status, $id_proveedor]);
            $accion_aud = $nuevo_status == 'Activo' ? 'activar' : 'desactivar';
            registrarAuditoria($accion_aud, 'Proveedor ' . $accion_aud . 'do');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PROVEEDOR ' . strtoupper($accion_aud) . 'DO CON ÉXITO.'];
        }

        header("Location: proveedores.php");
        exit();
    }

    // Limpiar datos del formulario
    $rif = normalizarDocumento($_POST['rif'] ?? '');
    $nombre_empresa = mb_strtoupper(trim($_POST['nombre_empresa'] ?? ''));
    $telefono = trim($_POST['telefono_completo'] ?? '');
    $contacto = trim($_POST['contacto_nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $lead_time = !empty($_POST['lead_time']) ? min(365, max(0, intval($_POST['lead_time']))) : null;

    $limite_credito_raw = preg_replace('/[^0-9.]/', '', str_replace(',', '', trim($_POST['limite_credito'] ?? '')));
    $limite_credito = !empty($limite_credito_raw) ? min(999999999.99, max(0, floatval($limite_credito_raw))) : null;

    $dias_credito = !empty($_POST['dias_credito']) ? min(360, max(0, intval($_POST['dias_credito']))) : 0;
    $condiciones_pago = in_array($_POST['condiciones_pago'] ?? '', ['Contado', 'Credito']) ? $_POST['condiciones_pago'] : 'Contado';
    $moneda = in_array($_POST['moneda'] ?? '', ['USD', 'EUR', 'VES']) ? $_POST['moneda'] : 'USD';
    $status = in_array($_POST['status'] ?? '', ['Activo', 'Inactivo']) ? $_POST['status'] : 'Activo';

    // Validaciones básicas
    if (empty($nombre_empresa)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL NOMBRE DE LA EMPRESA ES OBLIGATORIO.'];
        header("Location: proveedores.php");
        exit();
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'FORMATO DE CORREO ELECTRÓNICO INVÁLIDO.'];
        header("Location: proveedores.php");
        exit();
    }

    // Crear proveedor
    if ($accion == "registrar") {
        // Validaciones específicas para registro
        if (!validarRIF($rif)) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0'];
            header("Location: proveedores.php");
            exit();
        }

        if (empty($telefono) || strlen(preg_replace('/[^0-9+]/', '', $telefono)) < 7) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'TELÉFONO INVÁLIDO. INGRESE UN NÚMERO VÁLIDO.'];
            header("Location: proveedores.php");
            exit();
        }

        // Verificar duplicados
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?)", [$rif])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.'];
            header("Location: proveedores.php");
            exit();
        }

        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?)", [$nombre_empresa])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.'];
            header("Location: proveedores.php");
            exit();
        }

        if (!empty($email) && $db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(email) = LOWER(?)", [$email])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL CORREO ELECTRÓNICO YA PERTENECE A OTRO PROVEEDOR.'];
            header("Location: proveedores.php");
            exit();
        }

        // INSERTAR USANDO EL MÉTODO insert() DE LA CLASE DATABASE
        $data = [
            'rif' => $rif,
            'nombre_empresa' => $nombre_empresa,
            'contacto' => $contacto,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'lead_time' => $lead_time,
            'limite_credito' => $limite_credito,
            'dias_credito' => $dias_credito,
            'condiciones_pago' => $condiciones_pago,
            'moneda' => $moneda,
            'status' => $status
        ];

        // Eliminar campos NULL para evitar problemas
        $data = array_filter($data, function ($value) {
            return $value !== null;
        });

        try {
            $id_insertado = $db->insert('proveedores', $data);
            registrarAuditoria('crear', 'Proveedor registrado: ' . $nombre_empresa);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PROVEEDOR REGISTRADO CON ÉXITO.'];
        } catch (Exception $e) {
            error_log("Error al registrar proveedor: " . $e->getMessage());
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL REGISTRAR: ' . $e->getMessage()];
        }

        header("Location: proveedores.php");
        exit();
    }

    // Editar proveedor
    if ($accion == "editar") {
        $id_proveedor = intval($_POST['id_proveedor'] ?? 0);

        // Validaciones
        if (!validarRIF($rif)) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0'];
            header("Location: proveedores.php");
            exit();
        }

        if (empty($telefono) || strlen(preg_replace('/[^0-9+]/', '', $telefono)) < 7) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'TELÉFONO INVÁLIDO.'];
            header("Location: proveedores.php");
            exit();
        }

        // Verificar duplicados
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?) AND id_proveedor != ?", [$rif, $id_proveedor])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.'];
            header("Location: proveedores.php");
            exit();
        }

        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?) AND id_proveedor != ?", [$nombre_empresa, $id_proveedor])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.'];
            header("Location: proveedores.php");
            exit();
        }

        if (!empty($email) && $db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(email) = LOWER(?) AND id_proveedor != ?", [$email, $id_proveedor])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL CORREO ELECTRÓNICO YA PERTENECE A OTRO PROVEEDOR.'];
            header("Location: proveedores.php");
            exit();
        }

        // ACTUALIZAR
        $data = [
            'rif' => $rif,
            'nombre_empresa' => $nombre_empresa,
            'contacto' => $contacto,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'lead_time' => $lead_time,
            'limite_credito' => $limite_credito,
            'dias_credito' => $dias_credito,
            'condiciones_pago' => $condiciones_pago,
            'moneda' => $moneda,
            'status' => $status
        ];

        // Eliminar campos NULL
        $data = array_filter($data, function ($value) {
            return $value !== null;
        });

        try {
            $db->update('proveedores', $data, 'id_proveedor = ?', [$id_proveedor]);
            registrarAuditoria('editar', 'Proveedor modificado: ' . $nombre_empresa);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'DATOS ACTUALIZADOS CORRECTAMENTE.'];
        } catch (Exception $e) {
            error_log("Error al editar proveedor: " . $e->getMessage());
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL ACTUALIZAR: ' . $e->getMessage()];
        }

        header("Location: proveedores.php");
        exit();
    }
}

// ==========================================
// OBTENER DATOS
// ==========================================
$proveedores = $db->fetchAll("SELECT * FROM proveedores ORDER BY nombre_empresa ASC");

$total_prov = count($proveedores);
$activos_prov = $db->fetchOne("SELECT COUNT(*) as t FROM proveedores WHERE status='Activo'")['t'];
$limite_credito_total = $db->fetchOne("SELECT COALESCE(SUM(limite_credito),0) as t FROM proveedores WHERE limite_credito > 0 AND status = 'Activo'")['t'];

$credito_usado = [];
$rows_used = $db->fetchAll("SELECT id_proveedor, COALESCE(SUM(total),0) as usado FROM compras WHERE status = 'Activa' AND condiciones_pago = 'Credito' AND id_proveedor IS NOT NULL GROUP BY id_proveedor");
foreach ($rows_used as $r) {
    $credito_usado[$r['id_proveedor']] = (float)$r['usado'];
}

// Migrar teléfonos de formato antiguo (04XX) 000-0000 a E.164
$pendientes = $db->fetchAll("SELECT id_proveedor, telefono FROM proveedores WHERE telefono LIKE '(0%'");
foreach ($pendientes as $p) {
    $limpio = preg_replace('/[^0-9]/', '', $p['telefono']);
    $limpio = ltrim($limpio, '0');
    $e164 = '+58' . $limpio;
    $db->execute("UPDATE proveedores SET telefono = ? WHERE id_proveedor = ?", [$e164, $p['id_proveedor']]);
}

// Manejo de mensajes flash
$flash = null;
if (isset($_GET['res'])) {
    $map = ['success' => 'PROVEEDOR REGISTRADO CON ÉXITO.', 'updated' => 'DATOS ACTUALIZADOS CORRECTAMENTE.'];
    $flash = ['tipo' => 'success', 'texto' => $map[$_GET['res']] ?? 'OPERACIÓN EXITOSA.'];
} elseif (isset($_GET['err'])) {
    $map = ['rif_exists' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.', 'csrf' => 'ERROR DE SEGURIDAD. INTENTE DE NUEVO.', 'rif_invalido' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0', 'tel_invalido' => 'TELÉFONO INVÁLIDO. INGRESE UN NÚMERO VÁLIDO CON CÓDIGO DE PAÍS.', 'db_error' => 'ERROR EN LA BASE DE DATOS.'];
    $flash = ['tipo' => 'danger', 'texto' => $map[$_GET['err']] ?? 'ERROR DESCONOCIDO.'];
}
$flash_s = $_SESSION['flash_msg'] ?? $flash;
if ($flash_s) $flash = $flash_s;
unset($_SESSION['flash_msg']);
?>
<!-- HEAD Y ESTILOS HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>Proveedores | JV3000 C.A.</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
        <link rel="stylesheet" href="../assets/modules/proveedores/proveedores.css?v=2">
</head>
<!-- BODY HTML -->

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="pagina-proveedores">
            <div class="container-fluid px-4 py-4">

                <!-- Encabezado -->
                <div class="d-flex align-items-center gap-4 mb-4">
                    <div class="prov-header-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <h1 class="font-brand mb-1" style="font-size:2rem;letter-spacing:-1px; color: var(--jv-text-primary);">PROVEEDORES</h1>
                        <p class="text-secondary fw-bold text-uppercase mb-0" style="font-size:.95rem;">Directorio de Alianzas Comerciales</p>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
                        <div class="prov-search">
                            <i class="bi bi-search"></i>
                            <input type="text" class="input-jv" id="buscarProv" placeholder="Buscar proveedor..." onkeyup="filtrarProvTexto()" style="padding:10px 16px 10px 38px;max-width:280px;font-size:1rem;">
                        </div>
                        <div class="filter-group">
                            <button class="btn-filter active" onclick="filtrarProv('todos')" id="f-todos">Todos</button>
                            <button class="btn-filter" onclick="filtrarProv('Activo')" id="f-Activo">Activos</button>
                            <button class="btn-filter" onclick="filtrarProv('Inactivo')" id="f-Inactivo">Inactivos</button>
                        </div>
                        <button class="btn btn-jv-primary" onclick="nuevoProveedor()" id="btnNuevoProv" style="padding:12px 32px;font-size:1rem;">
                            <i class="bi bi-plus-lg me-2"></i>NUEVO
                        </button>
                    </div>
                </div>

                <!-- Mensajes flash -->
                <?php if ($flash): ?>
                    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
                        <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
                    </div>
                <?php endif; ?>

                <!-- Widgets de estadísticas -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="widget-card">
                            <div class="widget-icon" style="background:rgba(37,99,235,0.12);color:var(--jv-info);">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="widget-label">Total Proveedores</div>
                                <div class="widget-value"><?php echo $total_prov; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="widget-card">
                            <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div>
                                <div class="widget-label">Proveedores Activos</div>
                                <div class="widget-value"><?php echo $activos_prov; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="widget-card">
                            <div class="widget-icon" style="background:rgba(217,119,6,0.12);color:var(--jv-warning);">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div>
                                <div class="widget-label">Límite Crédito Total</div>
                                <div class="widget-value">$<?php echo number_format($limite_credito_total, 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjetas de proveedores -->
                <div class="row g-3" id="provGrid">
                    <?php if ($total_prov > 0): ?>
                        <?php foreach ($proveedores as $row): ?>
                            <div class="col-md-6 col-lg-4 prov-card" data-status="<?php echo $row['status']; ?>">
                                <div class="prov-premium">
                                    <div class="prov-head">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-dot-jv <?php echo $row['status'] == 'Activo' ? 'active' : 'inactive'; ?>"></span>
                                            <span class="badge-jv <?php echo $row['status'] == 'Activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $row['status']; ?></span>
                                        </div>
                                        <button class="btn-action" onclick='editarProveedor(<?php echo json_encode($row); ?>)' title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if (Security::esAdmin()): ?>
                                            <button class="btn-action" onclick="toggleStatusProveedor(<?php echo $row['id_proveedor']; ?>,'<?php echo htmlspecialchars($row['nombre_empresa'], ENT_QUOTES); ?>','<?php echo $row['status']; ?>')" title="<?php echo $row['status'] == 'Activo' ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="bi <?php echo $row['status'] == 'Activo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>" style="color:<?php echo $row['status'] == 'Activo' ? 'var(--jv-danger)' : 'var(--jv-success)'; ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prov-body" onclick="toggleProv(this)">
                                        <div class="prov-name" data-tooltip="<?php echo htmlspecialchars($row['nombre_empresa']); ?>"><?php echo htmlspecialchars($row['nombre_empresa']); ?></div>
                                        <div class="prov-rif"><span class="codigo-badge"><?php echo htmlspecialchars($row['rif']); ?></span></div>
                                        <div class="prov-info"><i class="bi bi-telephone"></i><?php echo !empty($row['telefono']) ? htmlspecialchars(formatearTelefono($row['telefono'])) : ($row['contacto'] ?? 'Sin teléfono'); ?></div>
                                        <?php if (!empty($row['contacto'])): ?>
                                            <div class="prov-info"><i class="bi bi-person"></i><?php echo htmlspecialchars($row['contacto']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['email'])): ?>
                                            <div class="prov-info"><i class="bi bi-envelope"></i><?php echo htmlspecialchars($row['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prov-details">
                                        <div class="prov-detail-row">
                                            <span class="detail-label">Plazo Entrega</span>
                                            <span class="detail-value"><?php echo $row['lead_time'] ? $row['lead_time'] . ' días' : '-'; ?></span>
                                        </div>
                                        <div class="prov-detail-row">
                                            <span class="detail-label">Límite Crédito</span>
                                            <span class="detail-value" style="color:var(--jv-success);"><?php echo $row['limite_credito'] ? '$' . number_format($row['limite_credito'], 2) : '-'; ?></span>
                                        </div>
                                        <div class="prov-detail-row">
                                            <span class="detail-label">Plazo Pago</span>
                                            <span class="detail-value"><?php echo $row['dias_credito'] ? $row['dias_credito'] . ' días' : 'Contado'; ?></span>
                                        </div>
                                        <div class="prov-detail-row">
                                            <span class="detail-label">Moneda</span>
                                            <span class="detail-value"><?php echo $row['moneda'] ?? 'USD'; ?></span>
                                        </div>
                                        <div class="prov-detail-row">
                                            <span class="detail-label">Condición Pago</span>
                                            <span class="detail-value"><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span>
                                        </div>
                                        <?php
                                        $usado = $credito_usado[$row['id_proveedor']] ?? 0;
                                        $limite = (float)($row['limite_credito'] ?? 0);
                                        if ($limite > 0):
                                            $disponible = $limite - $usado;
                                            $pct = min(100, max(0, round(($usado / $limite) * 100)));
                                            $bar_color = $pct >= 90 ? '#DC2626' : ($pct >= 70 ? '#D97706' : '#16A34A');
                                        ?>
                                            <div class="prov-detail-row">
                                                <span class="detail-label">Crédito Usado</span>
                                                <span class="detail-value" style="color:<?php echo $bar_color; ?>;">$<?php echo number_format($usado, 2); ?></span>
                                            </div>
                                            <div class="prov-detail-row">
                                                <span class="detail-label">Disponible</span>
                                                <span class="detail-value" style="color:var(--jv-success);">$<?php echo number_format(max(0, $disponible), 2); ?></span>
                                            </div>
                                            <div style="height:4px;background:rgba(15,26,46,0.08);border-radius:2px;margin:4px 12px;">
                                                <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $bar_color; ?>;border-radius:2px;transition:width .3s;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prov-foot">
                                        <button class="btn btn-jv-primary w-100 py-2" onclick="verHistorial(<?php echo $row['id_proveedor']; ?>)">
                                            <i class="bi bi-clock-history me-2"></i>Ver Historial
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="estado-vacio">
                                <i class="bi bi-building"></i>
                                <span>No hay proveedores registrados</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Formulario de proveedor -->
    <div class="modal fade" id="modalProveedor" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:var(--jv-bg-secondary); border:1px solid var(--jv-border); border-radius:var(--jv-radius-xl);">
                <form action="" method="POST" id="formProveedor">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_proveedor" id="p_accion" value="registrar">
                    <input type="hidden" name="id_proveedor" id="p_id_edit">
                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bolder font-brand m-0" id="modalTitle" style="color:var(--jv-navy);font-size:1.3rem;letter-spacing:-.5px;">REGISTRAR PROVEEDOR</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-building"></i> Información Fiscal</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-4">
                                    <label for="p_rif" class="small fw-bold text-secondary mb-2">RIF</label>
                                    <input type="text" name="rif" id="p_rif" class="input-jv" required placeholder="Ej: J-12345678-0" maxlength="13">
                                </div>
                                <div class="col-md-8">
                                    <label for="p_empresa" class="small fw-bold text-secondary mb-2">NOMBRE EMPRESA</label>
                                    <input type="text" name="nombre_empresa" id="p_empresa" class="input-jv text-uppercase" required placeholder="Nombre legal de la empresa" oninput="this.value = this.value.toUpperCase()">
                                </div>
                            </div>
                            <div class="mt-3 mb-0">
                                <label for="p_direccion" class="small fw-bold text-secondary mb-2">DIRECCIÓN</label>
                                <textarea name="direccion" id="p_direccion" class="input-jv" rows="2" placeholder="Dirección fiscal"></textarea>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-person-lines-fill"></i> Contacto</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-4">
                                    <label for="p_tel" class="small fw-bold text-secondary mb-2">TELÉFONO</label>
                                    <input type="tel" name="telefono" id="p_tel" class="input-jv" required>
                                    <input type="hidden" name="telefono_completo" id="p_tel_full">
                                </div>
                                <div class="col-md-4">
                                    <label for="p_contacto_nombre" class="small fw-bold text-secondary mb-2">CONTACTO NOMBRE</label>
                                    <input type="text" name="contacto_nombre" id="p_contacto_nombre" class="input-jv" placeholder="Nombre del contacto">
                                </div>
                                <div class="col-md-4">
                                    <label for="p_email" class="small fw-bold text-secondary mb-2">EMAIL</label>
                                    <input type="email" name="email" id="p_email" class="input-jv" placeholder="correo@ejemplo.com">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg mb-4">
                            <div class="section-label"><i class="bi bi-gear"></i> Condiciones Comerciales</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-3">
                                    <label for="p_lead_time" class="small fw-bold text-secondary mb-2">PLAZO DE ENTREGA (DÍAS)</label>
                                    <input type="number" name="lead_time" id="p_lead_time" class="input-jv" placeholder="Días" min="0" max="365">
                                </div>
                                <div class="col-md-3">
                                    <label for="p_limite_credito" class="small fw-bold text-secondary mb-2">LÍMITE CRÉDITO ($)</label>
                                    <input type="text" name="limite_credito" id="p_limite_credito" class="input-jv" placeholder="0.00" maxlength="15" inputmode="decimal">
                                </div>
                                <div class="col-md-3">
                                    <label for="p_dias_credito" class="small fw-bold text-secondary mb-2">DÍAS DE CRÉDITO</label>
                                    <input type="number" name="dias_credito" id="p_dias_credito" class="input-jv" placeholder="Días" min="0" max="360" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label for="p_moneda" class="small fw-bold text-secondary mb-2">MONEDA</label>
                                    <select name="moneda" id="p_moneda" class="input-jv">
                                        <option value="USD">USD - Dólar</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="VES">VES - Bolívar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-2 mb-0">
                                <div class="col-md-4">
                                    <label for="p_condiciones_pago" class="small fw-bold text-secondary mb-2">CONDICIÓN PAGO</label>
                                    <select name="condiciones_pago" id="p_condiciones_pago" class="input-jv">
                                        <option value="Contado">Contado</option>
                                        <option value="Credito">Crédito</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="p_status" class="small fw-bold text-secondary mb-2">ESTADO</label>
                                    <select name="status" id="p_status" class="input-jv">
                                        <option value="Activo">Activo</option>
                                        <option value="Inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
                            <i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.all.min.js"></script>

    <script>
    window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>' };
</script>
    <script src="../assets/modules/proveedores/proveedores.js?v=2"></script>

</body>

</html>