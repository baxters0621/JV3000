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
if (isset($_POST['accion_categoria'])) {
    $accion = $_POST['accion_categoria'];
    $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
    $codigo = trim($_POST['codigo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $status = $_POST['status'] ?? 'Activo';
    $clasificacion_abc = strtoupper(trim($_POST['clasificacion_abc'] ?? ''));
    if (!in_array($clasificacion_abc, ['A', 'B', 'C', ''])) $clasificacion_abc = '';
    $tipo_manejo = in_array($_POST['tipo_manejo'] ?? '', ['normal', 'inflamable', 'liquido', 'peligroso', 'voluminoso', 'aerosol']) ? $_POST['tipo_manejo'] : 'normal';
    $status = in_array($status, ['Activo', 'Inactivo']) ? $status : 'Activo';

    if (empty($nombre)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL NOMBRE DE LA CATEGORÍA ES OBLIGATORIO.'];
        header("Location: categorias.php");
        exit();
    }

    if ($accion == "registrar") {
        $dup = $db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?)", [$nombre]);
        if ($dup) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];
            header("Location: categorias.php");
            exit();
        }

        $db->begin();
        try {
            $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
            if (!$cnt) {
                $db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('CAT', 0)");
                $prox = 1;
            } else {
                $prox = intval($cnt['ultimo_numero']) + 1;
            }
            $codigo = 'CAT-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
            $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CAT'", [$prox]);
            $db->insert('categorias', [
                'nombre'          => $nombre,
                'codigo'          => $codigo,
                'descripcion'     => $descripcion,
                'clasificacion_abc' => $clasificacion_abc,
                'tipo_manejo'     => $tipo_manejo,
                'status'          => $status,
            ]);
            $db->commit();
            registrarAuditoria('crear', 'Categoría creada');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CATEGORÍA REGISTRADA CON ÉXITO.'];
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR EN LA BASE DE DATOS.'];
        }
        header("Location: categorias.php");
        exit();
    }

    if ($accion == "editar") {
        $id_cat = intval($_POST['id_categoria'] ?? 0);

        $dup = $db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id_categoria != ?", [$nombre, $id_cat]);
        if ($dup) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];
            header("Location: categorias.php");
            exit();
        }

        $existente = $db->fetchOne("SELECT codigo FROM categorias WHERE id_categoria = ?", [$id_cat]);
        $codigo_final = $existente['codigo'] ?? $codigo;
        $db->execute("UPDATE categorias SET nombre=?, codigo=?, descripcion=?, clasificacion_abc=?, tipo_manejo=?, status=? WHERE id_categoria=?", 
            [$nombre, $codigo_final, $descripcion, $clasificacion_abc, $tipo_manejo, $status, $id_cat]);
        registrarAuditoria('editar', 'Categoría modificada');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CATEGORÍA ACTUALIZADA CORRECTAMENTE.'];
        header("Location: categorias.php");
        exit();
    }
}

// Cambiar estado
if (isset($_POST['toggle_status'])) {
    $id_target = intval($_POST['toggle_status']);
    $row_target = $db->fetchOne("SELECT status FROM categorias WHERE id_categoria = ?", [$id_target]);
    if ($row_target) {
        $nuevo_status = ($row_target['status'] == 'Activo') ? 'Inactivo' : 'Activo';
        $db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$nuevo_status, $id_target]);
        registrarAuditoria('toggle_status', 'Cambio de estado');
    }
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'ESTADO DE LA CATEGORÍA CAMBIADO.'];
    header("Location: categorias.php");
    exit();
}

// ==========================================
// OBTENER DATOS
// ==========================================
$categorias = $db->fetchAll("SELECT * FROM categorias ORDER BY nombre ASC");

// Corregir códigos nulos
$nulls = $db->fetchAll("SELECT id_categoria FROM categorias WHERE codigo IS NULL OR codigo = '' ORDER BY id_categoria");
foreach ($nulls as $n) {
    $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
    if (!$cnt) {
        $db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('CAT', 0)");
        $prox = 1;
    } else {
        $prox = intval($cnt['ultimo_numero']) + 1;
    }
    $ncod = 'CAT-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
    $db->execute("UPDATE categorias SET codigo=? WHERE id_categoria=?", [$ncod, (int)$n['id_categoria']]);
    $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CAT'", [$prox]);
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>Categorías | JV3000 C.A.</title>
    <!-- ESTILOS -->
        <link rel="stylesheet" href="../assets/modules/categorias/categorias.css?v=6">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- ENCABEZADO -->
            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding: 18px 24px; border-left: 4px solid #2563eb;">
                <div class="d-flex align-items-center gap-3">
                    <div class="cat-header-icon"><i class="bi bi-tags"></i></div>
                    <div>
                        <h1 class="module-title">CATEGORÍAS</h1>
                        <p class="module-subtitle">Organización de Catálogo</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-jv-primary pulse-jv module-action-btn" onclick="nuevaCat()">
                        <i class="bi bi-plus-lg me-1"></i>CREAR
                    </button>
                </div>
            </div>

            <!-- MENSAJES FLASH -->
            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert-jv alert-jv-<?php echo $_SESSION['flash_msg']['tipo']; ?> mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $_SESSION['flash_msg']['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['flash_msg']['texto']); ?>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <!-- TABLA PRINCIPAL -->
            <div class="card-jv card-jv-table p-0">
                <div class="buscador-wrapper d-flex align-items-center px-3 py-2">
                    <i class="bi bi-search me-2" style="color: var(--jv-orange); font-size: 1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nombre, código, descripción, ABC, manejo..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; font-size: 0.85rem; padding: 8px 6px; max-width: 340px;">
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width: 28%;">NOMBRE</th>
                                <th style="width: 14%;">CÓDIGO</th>
                                <th style="width: 8%;" class="text-center">ABC</th>
                                <th style="width: 13%;" class="text-center">MANEJO</th>
                                <th style="width: 12%;" class="text-center">ESTADO</th>
                                <th style="width: 130px;" class="text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCategorias">
                            <?php if (!empty($categorias)): ?>
                                <?php foreach ($categorias as $row): ?>
                                    <tr data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre'])); ?>" data-codigo="<?php echo strtolower(htmlspecialchars($row['codigo'] ?? '')); ?>">
                                        <td>
                                            <i class="bi bi-folder2-open me-2" style="color: #2563eb; font-size: 1.1rem;"></i>
                                            <span class="cat-nombre text-uppercase" data-tooltip="<?php echo htmlspecialchars($row['nombre']); ?>"><?php echo htmlspecialchars($row['nombre']); ?></span>
                                            <?php if ($row['descripcion']): ?>
                                                <br><span class="cat-desc"><?php echo htmlspecialchars($row['descripcion']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="codigo-badge"><?php echo htmlspecialchars($row['codigo'] ?? '—'); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($row['clasificacion_abc']): ?>
                                                <span class="abc-badge abc-<?php echo strtolower($row['clasificacion_abc']); ?>"><?php echo $row['clasificacion_abc']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="manejo-badge manejo-<?php echo htmlspecialchars($row['tipo_manejo'] ?? 'normal'); ?>"><?php echo htmlspecialchars(ucfirst($row['tipo_manejo'] ?? 'Normal')); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>">
                                                <i class="bi bi-<?php echo ($row['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                                <?php echo strtoupper($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn-action btn-action-edit btn btn-sm border-0 me-1" onclick='editarCat(<?php echo json_encode($row); ?>)' title="Editar">
                                                <i class="bi bi-pencil-square" style="color: var(--jv-orange); font-size: 0.85rem;"></i>
                                            </button>
                                            <span class="actions-divider"></span>
                                            <?php if ($row['status'] == 'Activo'): ?>
                                                <button class="btn-action btn-action-toggle btn btn-sm border-0 ms-1" onclick="confirmarToggle(<?php echo $row['id_categoria']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>', 'desactivar')" title="Desactivar">
                                                    <i class="bi bi-eye-slash-fill" style="color: var(--jv-warning); font-size: 0.85rem;"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-action-reactivate btn btn-sm border-0 ms-1" onclick="confirmarToggle(<?php echo $row['id_categoria']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>', 'activar')" title="Activar">
                                                    <i class="bi bi-eye-fill" style="color: var(--jv-success); font-size: 0.85rem;"></i>
                                                </button>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="bi bi-tags d-block mb-3 mx-auto" style="font-size: 3rem; color: var(--jv-orange); opacity: 0.5;"></i>
                                        <span class="text-uppercase" style="color: var(--jv-text-primary); font-weight: 700; font-size: 0.95rem;">No hay categorías registradas</span>
                                        <p class="mt-2" style="color: var(--jv-text-muted); font-size: 0.85rem;">Crea una categoría usando el botón <strong style="color: var(--jv-orange);">CREAR</strong></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL DE CATEGORÍA -->
        <div class="modal fade" id="modalCat" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-jv">
                    <form method="POST" id="formCat">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="accion_categoria" id="cat_accion" value="registrar">
                        <input type="hidden" name="id_categoria" id="cat_id_edit">
                        <input type="hidden" name="status" id="cat_status" value="Activo">

                        <div class="px-4 py-3" style="border-bottom: 1px solid var(--jv-border);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0 font-brand" style="color: var(--jv-navy); font-size: 1.3rem;" id="modalTitle"><i class="bi bi-tag-fill me-2"></i>NUEVA CATEGORÍA</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                        </div>

                        <div class="p-4 d-flex flex-column gap-3">

                            <div class="section-bg">
                                <div class="mb-2" style="border-bottom: 1px solid var(--jv-border); padding-bottom: 6px;">
                                    <span class="fw-bold text-uppercase" style="font-size: .8rem; letter-spacing: 1px; color: var(--jv-navy);">General</span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <label for="cat_nombre" class="fw-bold mb-1" style="color: var(--jv-text-primary); font-size: .95rem;">NOMBRE</label>
                                        <input type="text" name="nombre" id="cat_nombre" class="input-jv" required maxlength="100" placeholder="Ej: Aceites, Lubricantes" oninput="this.value = this.value.toUpperCase()" style="padding: 12px 16px; font-size: 1rem;">
                                    </div>
                                    <div>
                                        <label for="cat_desc" class="fw-bold mb-1" style="color: var(--jv-text-secondary); font-size: .95rem;">DESCRIPCIÓN</label>
                                        <textarea name="descripcion" id="cat_desc" class="input-jv" rows="2" placeholder="Ej: Aceites de motor, lubricantes, grasas..." style="padding: 12px 16px; font-size: 1rem;"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="section-bg">
                                <div class="mb-2" style="border-bottom: 1px solid var(--jv-border); padding-bottom: 6px;">
                                    <span class="fw-bold text-uppercase" style="font-size: .8rem; letter-spacing: 1px; color: var(--jv-navy);">Parámetros</span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <label for="cat_abc" class="fw-bold mb-1" style="color: var(--jv-text-secondary); font-size: .95rem;">CLASIFICACIÓN ABC</label>
                                        <select name="clasificacion_abc" id="cat_abc" class="input-jv" style="padding: 12px 16px; font-size: 1rem;">
                                            <option value="">—</option>
                                            <option value="A">A — Alto valor</option>
                                            <option value="B">B — Medio valor</option>
                                            <option value="C">C — Bajo valor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="cat_manejo" class="fw-bold mb-1" style="color: var(--jv-text-secondary); font-size: .95rem;">TIPO DE MANEJO</label>
                                        <select name="tipo_manejo" id="cat_manejo" class="input-jv" style="padding: 12px 16px; font-size: 1rem;">
                                            <option value="normal">Normal</option>
                                            <option value="inflamable">Inflamable</option>
                                            <option value="liquido">Líquido</option>
                                            <option value="peligroso">Peligroso</option>
                                            <option value="voluminoso">Voluminoso</option>
                                            <option value="aerosol">Aerosol</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2">
                                <button type="button" class="btn-jv-secondary" style="padding: 12px 28px; font-size: 1rem;" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn-jv-primary module-action-btn" onclick="return validarCategoria(this)">
                                    <i class="bi bi-check-lg me-1"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- JAVASCRIPT -->
        <script src="../assets/js/bootstrap.bundle.min.js"></script>
        <script>
    window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>' };
</script>
    <script src="../assets/modules/categorias/categorias.js?v=3"></script>
</body>

</html>