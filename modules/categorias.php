<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

if (isset($_POST['accion_categoria'])) {
    $accion = $_POST['accion_categoria'];
    $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''));
    $codigo = trim($_POST['codigo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $status = $_POST['status'] ?? 'Activo';
    $stock_minimo = max(0, min(500, intval($_POST['stock_minimo'] ?? 5)));
    $stock_maximo = max(0, min(500, intval($_POST['stock_maximo'] ?? 100)));
    $alerta_reorden = 1;
    $clasificacion_abc = strtoupper(trim($_POST['clasificacion_abc'] ?? ''));
    if (!in_array($clasificacion_abc, ['A', 'B', 'C', ''])) $clasificacion_abc = '';
    $tipo_manejo = in_array($_POST['tipo_manejo'] ?? '', ['normal', 'perecedero', 'congelado', 'peligroso', 'controlado', 'granel']) ? $_POST['tipo_manejo'] : 'normal';
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

        if ($stock_maximo <= $stock_minimo) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'STOCK MÁXIMO DEBE SER MAYOR QUE STOCK MÍNIMO.'];
            header("Location: categorias.php");
            exit();
        }

        $db->begin();
        try {
            $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
            $prox = ($cnt ? intval($cnt['ultimo_numero']) : 0) + 1;
            $codigo = 'CAT-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
            $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CAT'", [$prox]);
            $db->insert('categorias', [
                'nombre'          => $nombre,
                'codigo'          => $codigo,
                'descripcion'     => $descripcion,
                'stock_minimo'    => $stock_minimo,
                'stock_maximo'    => $stock_maximo,
                'alerta_reorden'  => $alerta_reorden,
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

        if ($stock_maximo <= $stock_minimo) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'STOCK MÁXIMO DEBE SER MAYOR QUE STOCK MÍNIMO.'];
            header("Location: categorias.php");
            exit();
        }

        $existente = $db->fetchOne("SELECT codigo FROM categorias WHERE id_categoria = ?", [$id_cat]);
        $codigo_final = $existente['codigo'] ?? $codigo;
        $db->execute("UPDATE categorias SET nombre=?, codigo=?, descripcion=?, stock_minimo=?, stock_maximo=?, alerta_reorden=?, clasificacion_abc=?, tipo_manejo=?, status=? WHERE id_categoria=?", 
            [$nombre, $codigo_final, $descripcion, $stock_minimo, $stock_maximo, $alerta_reorden, $clasificacion_abc, $tipo_manejo, $status, $id_cat]);
        registrarAuditoria('editar', 'Categoría modificada');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CATEGORÍA ACTUALIZADA CORRECTAMENTE.'];
        header("Location: categorias.php");
        exit();
    }
}

if (isset($_GET['toggle_status'])) {
    $id_target = intval($_GET['toggle_status']);
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

$categorias = $db->fetchAll("SELECT * FROM categorias ORDER BY nombre ASC");

$nulls = $db->fetchAll("SELECT id_categoria FROM categorias WHERE codigo IS NULL OR codigo = '' ORDER BY id_categoria");
foreach ($nulls as $n) {
    $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
    $prox = intval($cnt['ultimo_numero'] ?? 0) + 1;
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
    <style>
        .section-bg { background: rgba(6, 182, 212, 0.03); border-radius: var(--jv-radius); padding: 14px; }
        /* THEAD */
        .table-jv thead { background: #0e7490 !important; }
        .table-jv thead th { background: transparent !important; color: #ffffff !important; font-weight: 900 !important; letter-spacing: 1.2px !important; font-size: 0.8rem !important; padding: 14px 16px !important; border-bottom: 1px solid rgba(255,255,255,0.12) !important; }
        /* Filas */
        .table-jv tbody td { padding: 16px 16px !important; border-bottom: 1px dashed rgba(56, 189, 248, 0.15) !important; }
        .table-jv tbody tr:last-child td { border-bottom: none !important; }
        .table-jv tbody tr:hover { background: rgba(6, 182, 212, 0.12) !important; }
        .table-jv tbody tr:hover td:first-child { border-left-color: #22d3ee; }
        .table-jv tbody td:first-child { border-left: 3px solid transparent; transition: border-color 0.2s ease; }
        /* Nombre categoria */
        .cat-nombre { font-size: 1rem; font-weight: 800; color: #f1f5f9; }
        .cat-desc { font-size: 0.8rem; color: #94a3b8; }
        /* Botones accion grandes */
        .btn-action { transition: all 0.2s ease; border-radius: 10px; min-width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; }
        .btn-action i { font-size: 1.1rem !important; }
        .btn-action-edit { border: 1px solid rgba(6,182,212,0.4); background: rgba(6,182,212,0.12); }
        .btn-action-edit:hover { border-color: #22d3ee; background: rgba(6,182,212,0.3); transform: scale(1.12); }
        .btn-action-toggle { border: 1px solid rgba(245,158,11,0.4); background: rgba(245,158,11,0.12); }
        .btn-action-toggle:hover { border-color: #fbbf24; background: rgba(245,158,11,0.3); transform: scale(1.12); }
        .btn-action-reactivate { border: 1px solid rgba(34,197,94,0.4); background: rgba(34,197,94,0.12); }
        .btn-action-reactivate:hover { border-color: #4ade80; background: rgba(34,197,94,0.3); transform: scale(1.12); }
        /* Badges */
        .badge-jv { padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 0.75rem; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: rgba(34,197,94,0.18); color: #4ade80; border: 1px solid rgba(34,197,94,0.4); }
        .badge-danger { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.4); }
        /* ABC badge */
        .abc-badge { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-size: 0.85rem; font-weight: 900; border: 2px solid; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .abc-a { background: rgba(34,197,94,0.25); color: #4ade80; border-color: rgba(34,197,94,0.5); }
        .abc-b { background: rgba(245,158,11,0.25); color: #fbbf24; border-color: rgba(245,158,11,0.5); }
        .abc-c { background: rgba(239,68,68,0.25); color: #f87171; border-color: rgba(239,68,68,0.5); }
        /* Alerts */
        .alert-jv { border-left: 4px solid; border-radius: 8px; padding: 14px 20px !important; font-size: 0.9rem; }
        .alert-jv-success { border-left-color: #22c55e; background: rgba(34,197,94,0.1); }
        .alert-jv-danger { border-left-color: #ef4444; background: rgba(239,68,68,0.1); }
        /* Buscador */
        .buscador-wrapper { border-bottom: 1px solid rgba(56, 189, 248, 0.12); background: rgba(2, 6, 23, 0.5); }
        .buscador-wrapper input { font-size: 0.95rem !important; padding: 10px 8px !important; }
        .buscador-wrapper i { font-size: 1.15rem !important; }
        /* Card tabla */
        .card-jv-table { border-top: 4px solid #22d3ee; border-radius: var(--jv-radius) !important; overflow: hidden; }
        /* Separador */
        .actions-divider { width: 1px; height: 26px; background: rgba(56, 189, 248, 0.15); display: inline-block; vertical-align: middle; }
        /* Stock text */
        .stock-text { font-size: 0.9rem; color: #94a3b8; font-weight: 600; }
        /* Codigo badge */
        .codigo-badge { background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.25); border-radius: 6px; padding: 3px 10px; font-size: 0.8rem; font-weight: 700; color: #22d3ee; font-family: 'Courier New', monospace; display: inline-block; }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding: 18px 24px; border-left: 4px solid #22d3ee;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #0e7490, #155e75); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(6, 182, 212, 0.35);">
                        <i class="bi bi-tags text-white" style="font-size: 1.3rem;"></i>
                    </div>
                    <div>
                        <h1 class="font-brand fw-bold m-0 text-white" style="font-size: 1.4rem;">CATEGORÍAS</h1>
                        <p class="m-0 text-white opacity-75" style="font-size: 0.85rem;">Organización de Catálogo</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-jv-primary pulse-jv" onclick="nuevaCat()" style="padding: 10px 28px; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 0 24px rgba(6, 182, 212, 0.35), inset 0 1px 0 rgba(255,255,255,0.1);">
                        <i class="bi bi-plus-lg me-1"></i>CREAR
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert-jv alert-jv-<?php echo $_SESSION['flash_msg']['tipo']; ?> mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $_SESSION['flash_msg']['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['flash_msg']['texto']); ?>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <div class="card-jv card-jv-table p-0">
                <div class="buscador-wrapper d-flex align-items-center px-3 py-2">
                    <i class="bi bi-search me-2" style="color: #22d3ee; font-size: 1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar categorías..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; font-size: 0.85rem; padding: 8px 6px;">
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width: 25%;">NOMBRE</th>
                                <th style="width: 14%;">CÓDIGO</th>
                                <th style="width: 8%;" class="text-center">ABC</th>
                                <th style="width: 14%;" class="text-center">STOCK</th>
                                <th style="width: 12%;" class="text-center">ESTADO</th>
                                <th style="width: 22%;" class="text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCategorias">
                            <?php if (!empty($categorias)): ?>
                                <?php foreach ($categorias as $row): ?>
                                    <tr data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre'])); ?>" data-codigo="<?php echo strtolower(htmlspecialchars($row['codigo'] ?? '')); ?>">
                                        <td>
                                            <i class="bi bi-folder2-open me-2" style="color: #22d3ee; font-size: 1rem;"></i>
                                            <span class="cat-nombre text-uppercase"><?php echo htmlspecialchars($row['nombre']); ?></span>
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
                                            <span class="stock-text"><?php echo $row['stock_minimo']; ?> / <?php echo $row['stock_maximo']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>">
                                                <i class="bi bi-<?php echo ($row['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                                <?php echo strtoupper($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn-action btn-action-edit btn btn-sm border-0 me-1" onclick='editarCat(<?php echo json_encode($row); ?>)' title="Editar">
                                                <i class="bi bi-pencil-square" style="color: #22d3ee; font-size: 0.85rem;"></i>
                                            </button>
                                            <span class="actions-divider"></span>
                                            <?php if ($row['status'] == 'Activo'): ?>
                                                <button class="btn-action btn-action-toggle btn btn-sm border-0 ms-1" onclick="confirmarToggle(<?php echo $row['id_categoria']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>', 'desactivar')" title="Desactivar">
                                                    <i class="bi bi-eye-slash-fill" style="color: #fbbf24; font-size: 0.85rem;"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-action-reactivate btn btn-sm border-0 ms-1" onclick="confirmarToggle(<?php echo $row['id_categoria']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>', 'activar')" title="Activar">
                                                    <i class="bi bi-eye-fill" style="color: #4ade80; font-size: 0.85rem;"></i>
                                                </button>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-tags d-block mb-3 mx-auto" style="font-size: 3rem; color: rgba(6, 182, 212, 0.5);"></i>
                                        <span class="text-uppercase" style="color: #e2e8f0; font-weight: 700; font-size: 0.95rem;">No hay categorías registradas</span>
                                        <p class="mt-2" style="color: #94a3b8; font-size: 0.85rem;">Crea una categoría usando el botón <strong style="color: #22d3ee;">CREAR</strong></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalCat" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-jv">
                    <form method="POST" id="formCat">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="accion_categoria" id="cat_accion" value="registrar">
                        <input type="hidden" name="id_categoria" id="cat_id_edit">

                        <div class="px-4 py-3" style="border-bottom: 1px solid rgba(56, 189, 248, 0.15);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0" style="color: #06b6d4; font-size: 1rem;" id="modalTitle"><i class="bi bi-tag-fill me-2"></i>NUEVA CATEGORÍA</h5>
                                <button type="button" class="btn-close" style="filter: invert(0.7);" data-bs-dismiss="modal"></button>
                            </div>
                        </div>

                        <div class="p-4 d-flex flex-column gap-3">

                            <div class="section-bg">
                                <div class="mb-2" style="border-bottom: 1px solid rgba(56, 189, 248, 0.12); padding-bottom: 6px;">
                                    <span class="fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px; color: #22d3ee;">General</span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <label for="cat_nombre" class="fw-bold mb-1" style="color: #e2e8f0; font-size: 0.85rem;">NOMBRE</label>
                                        <input type="text" name="nombre" id="cat_nombre" class="input-jv" required placeholder="Ej: Aceites, Lubricantes" oninput="this.value = this.value.toUpperCase()" style="padding: 12px 16px; font-size: 0.95rem;">
                                    </div>
                                    <div>
                                        <label for="cat_desc" class="fw-bold mb-1" style="color: #94a3b8; font-size: 0.85rem;">DESCRIPCIÓN</label>
                                        <textarea name="descripcion" id="cat_desc" class="input-jv" rows="2" placeholder="Ej: Aceites de motor, lubricantes, grasas..." style="padding: 12px 16px; font-size: 0.95rem;"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="section-bg">
                                <div class="mb-2" style="border-bottom: 1px solid rgba(56, 189, 248, 0.12); padding-bottom: 6px;">
                                    <span class="fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px; color: #22d3ee;">Parámetros</span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <label for="cat_stock_min" class="fw-bold mb-1" style="color: #94a3b8; font-size: 0.85rem;">STOCK MÍNIMO</label>
                                        <input type="number" name="stock_minimo" id="cat_stock_min" class="input-jv" value="5" min="0" max="500" oninput="if(this.value>500)this.value=500;if(this.value<0)this.value=0" style="padding: 12px 16px; font-size: 0.95rem;">
                                    </div>
                                    <div>
                                        <label for="cat_stock_max" class="fw-bold mb-1" style="color: #94a3b8; font-size: 0.85rem;">STOCK MÁXIMO</label>
                                        <input type="number" name="stock_maximo" id="cat_stock_max" class="input-jv" value="100" min="0" max="500" oninput="if(this.value>500)this.value=500;if(this.value<0)this.value=0" style="padding: 12px 16px; font-size: 0.95rem;">
                                    </div>
                                    <div>
                                        <label for="cat_abc" class="fw-bold mb-1" style="color: #94a3b8; font-size: 0.85rem;">CLASIFICACIÓN ABC</label>
                                        <select name="clasificacion_abc" id="cat_abc" class="input-jv" style="padding: 12px 16px; font-size: 0.95rem;">
                                            <option value="">—</option>
                                            <option value="A">A — Alto valor</option>
                                            <option value="B">B — Medio valor</option>
                                            <option value="C">C — Bajo valor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="cat_manejo" class="fw-bold mb-1" style="color: #94a3b8; font-size: 0.85rem;">TIPO DE MANEJO</label>
                                        <select name="tipo_manejo" id="cat_manejo" class="input-jv" style="padding: 12px 16px; font-size: 0.95rem;">
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
                                <button type="button" class="btn-jv-primary" style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #f87171; padding: 10px 22px; font-size: 0.9rem;" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-jv-success" style="padding: 10px 22px; font-size: 0.9rem;">
                                    <i class="bi bi-check-lg me-1"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="../assets/js/bootstrap.bundle.min.js"></script>
        <script>
            const modalC = new bootstrap.Modal(document.getElementById('modalCat'));

            function nuevaCat() {
                document.getElementById('cat_accion').value = "registrar";
                document.getElementById('cat_id_edit').value = "";
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag-fill me-2"></i>NUEVA CATEGORÍA';
                document.getElementById('cat_nombre').value = "";
                document.getElementById('cat_desc').value = "";
                document.getElementById('cat_stock_min').value = "5";
                document.getElementById('cat_stock_max').value = "100";
                document.getElementById('cat_abc').value = "";
                document.getElementById('cat_manejo').value = "normal";
                document.getElementById('cat_nombre').focus();
                modalC.show();
            }

            function editarCat(data) {
                document.getElementById('cat_accion').value = "editar";
                document.getElementById('cat_id_edit').value = data.id_categoria;
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag-fill me-2"></i>EDITAR CATEGORÍA';
                document.getElementById('cat_nombre').value = data.nombre;
                document.getElementById('cat_desc').value = data.descripcion || '';
                document.getElementById('cat_stock_min').value = data.stock_minimo || 5;
                document.getElementById('cat_stock_max').value = data.stock_maximo || 100;
                document.getElementById('cat_abc').value = data.clasificacion_abc || '';
                document.getElementById('cat_manejo').value = data.tipo_manejo || 'normal';
                document.getElementById('cat_nombre').focus();
                modalC.show();
            }

            function confirmarToggle(id, nombre, accion) {
                const esDes = accion === 'desactivar';
                Swal.fire({
                    title: esDes ? '¿DESACTIVAR CATEGORÍA?' : '¿REACTIVAR CATEGORÍA?',
                    text: esDes ? `Se desactivará '${nombre}'` : `Se reactivará '${nombre}'`,
                    icon: esDes ? 'warning' : 'info',
                    showCancelButton: true,
                    confirmButtonColor: esDes ? '#ef4444' : '#22c55e',
                    cancelButtonColor: '#1e293b',
                    confirmButtonText: esDes ? 'SÍ, DESACTIVAR' : 'SÍ, ACTIVAR',
                    cancelButtonText: 'CANCELAR',
                    background: '#0f172a',
                    color: '#ffffff'
                }).then((result) => {
                    if (result.isConfirmed) window.location.href = `categorias.php?toggle_status=${id}`;
                });
            }

            function filtrar() {
                const input = document.getElementById('buscar');
                const filter = input.value.toLowerCase();
                const rows = document.getElementById('tablaCategorias').getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const nombre = rows[i].getAttribute('data-nombre') || '';
                    const codigo = rows[i].getAttribute('data-codigo') || '';
                    rows[i].style.display = (nombre.includes(filter) || codigo.includes(filter)) ? '' : 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const alerts = document.querySelectorAll('.alert-jv');
                alerts.forEach(function(a) {
                    setTimeout(function() {
                        a.style.transition = 'opacity 0.6s';
                        a.style.opacity = '0';
                        setTimeout(function() { a.remove(); }, 600);
                    }, 4000);
                });
            });
        </script>
</body>

</html>