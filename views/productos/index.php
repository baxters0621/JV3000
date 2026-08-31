<?php

/** @var array<string, mixed>|null $flash */
/** @var bool $esAdmin */
/** @var array<int, array<string, mixed>> $productos */
/** @var int $total_paginas */
/** @var int $offset */
/** @var int $registros_por_pagina */
/** @var int $total_registros */
/** @var int $pagina_actual */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $categorias_gestion Vacío si el rol no administra categorías */
/** @var bool $puede_categorias */

// ==========================================
// VISTA: Inventario / Productos (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
$puede_categorias = !empty($categorias_gestion) || $esAdmin || (int)$_SESSION['id_rol'] === 2;
?>
<!-- Encabezado -->
<div class="card-jv d-flex align-items-center gap-3 mb-3 flex-wrap" style="padding: 18px 24px; border-left: 4px solid var(--jv-orange);">
    <div class="module-header-icon" style="background: var(--jv-orange); box-shadow: 0 4px 16px rgba(234,88,12,0.25);">
        <i class="bi bi-box-seam text-white" style="font-size:1.5rem;"></i>
    </div>
    <div>
        <h1 class="module-title">INVENTARIO</h1>
        <p class="module-subtitle">Control Maestro de Existencias</p>
    </div>
    <?php if ($puede_categorias): ?>
        <button type="button" class="btn btn-jv-primary module-action-btn ms-auto" onclick="abrirGestorCat()" data-tooltip="Registrar, modificar o desactivar categorías">
            <i class="bi bi-folder2-open me-1"></i>CATEGOR&Iacute;AS
        </button>
    <?php endif; ?>
</div>

<!-- Mensajes flash (id/data-texto permiten al JS de categorías marcar el campo con error) -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3" id="flashMsg" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Tabla de productos -->
<div class="card-jv card-jv-table p-0">
    <div class="buscador-wrapper px-3 py-2">
        <!-- Fila 1: buscador -->
        <div class="buscador-fila d-flex align-items-center gap-2">
            <i class="bi bi-search me-1" style="color: var(--jv-orange);"></i>
            <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nombre, código, proveedor, categoría, estado..." id="buscar" onkeyup="filtrar()" style="box-shadow: none;">
        </div>
        <!-- Fila 2: filtros, cada unidad salta de linea completa si no cabe -->
        <div class="buscador-fila d-flex align-items-center flex-wrap gap-2 mt-2">
            <div class="filtro-unidad d-flex align-items-center gap-2">
                <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Estado:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn-filter-prod active" data-status="todas" onclick="filtrarStatus(this)">Todos</button>
                    <button type="button" class="btn-filter-prod" data-status="Activo" onclick="filtrarStatus(this)">Activos</button>
                    <button type="button" class="btn-filter-prod" data-status="Inactivo" onclick="filtrarStatus(this)">Inactivos</button>
                </div>
            </div>
            <span class="actions-divider mx-1"></span>
            <div class="filtro-unidad d-flex align-items-center gap-2">
                <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Vence:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-sm btn-filtro-venc active" data-venc="todas" onclick="filtrarVenc(this)" style="border-radius:6px 0 0 6px;background:rgba(234,88,12,0.15);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.3);">Todas</button>
                    <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vencido" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-danger);border:1px solid rgba(220,38,38,0.3);">Vencidos</button>
                    <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="proximo" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Próximo</button>
                    <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="pronto" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Pronto</button>
                    <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vigente" onclick="filtrarVenc(this)" style="border-radius:0 6px 6px 0;background:transparent;color:var(--jv-success);border:1px solid rgba(22,163,74,0.3);">Vigente</button>
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:10%;">CÓDIGO</th>
                    <th style="width:20%;">PRODUCTO</th>
                    <th style="width:11%;">CATEGORÍA</th>
                    <th style="width:12%;">PROVEEDOR</th>
                    <th class="text-center" style="width:10%;">STOCK</th>
                    <th style="width:8%;">PRECIO</th>
                    <th class="text-center" style="width:10%;">VENCE</th>
                    <th class="text-center" style="width:9%;">ESTADO</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-center" style="width:10%;">ACCIONES</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tablaProductos">
                <?php if (!empty($productos)): ?>
                    <?php foreach ($productos as $productRecord):
                        // Porcentaje visual del stock frente a su capacidad efectiva.
                        $stock_actual = intval($productRecord['stock_actual']);
                        $stock_minimo = intval($productRecord['stock_minimo']);
                        $capacidad = max(1, intval($productRecord['capacidad'] ?? 100));
                        if ($stock_actual == 0) {
                            $stock_clase = 'danger';
                            $stock_porcentaje = 0;
                        } elseif ($stock_actual <= $stock_minimo) {
                            $stock_clase = 'danger';
                            $stock_porcentaje = max(5, ($stock_actual / $capacidad) * 100);
                        } elseif ($stock_actual >= $capacidad) {
                            $stock_clase = 'info';
                            $stock_porcentaje = 100;
                        } else {
                            $stock_porcentaje = ($stock_actual / $capacidad) * 100;
                            $stock_clase = 'success';
                        }
                    ?>
                        <?php
                        // Estado del vencimiento: vencido / próximo (<=7 días) /
                        // pronto (<=30 días) / vigente. Si no tiene fecha, no aplica.
                        $expirationDate = $productRecord['fecha_vencimiento'] ?? '';
                        $venc_cls = '';
                        $venc_badge = 'badge-secondary';
                        $venc_icono = 'dash-circle';
                        $venc_fecha = '';
                        if ($expirationDate) {
                            $dias_vencer = floor((strtotime($expirationDate) - time()) / 86400);
                            $venc_fecha = date('d/m/Y', strtotime($expirationDate));
                            if ($dias_vencer < 0) {
                                $venc_cls = 'vencido';
                                $venc_badge = 'badge-danger';
                                $venc_icono = 'exclamation-triangle';
                            } elseif ($dias_vencer <= 7) {
                                $venc_cls = 'proximo';
                                $venc_badge = 'badge-danger';
                                $venc_icono = 'clock';
                            } elseif ($dias_vencer <= 30) {
                                $venc_cls = 'pronto';
                                $venc_badge = 'badge-warning';
                                $venc_icono = 'clock';
                            } else {
                                $venc_cls = 'vigente';
                                $venc_badge = 'badge-success';
                                $venc_icono = 'check-circle';
                            }
                        }
                        ?>
                        <tr data-id="<?php echo $productRecord['id_producto']; ?>" data-sku="<?php echo strtolower(htmlspecialchars($productRecord['sku'])); ?>" data-nombre="<?php echo strtolower(htmlspecialchars($productRecord['nombre_producto'])); ?>" data-prov="<?php echo strtolower(htmlspecialchars($productRecord['proveedores'] ?? '')); ?>" data-stock="<?php echo $productRecord['stock_actual']; ?>" data-minimo="<?php echo $productRecord['stock_minimo']; ?>" data-max="<?php echo $capacidad; ?>" data-maximo="<?php echo intval($productRecord['stock_maximo'] ?? 0); ?>" data-pvp="<?php echo $productRecord['precio_venta']; ?>" data-costo="<?php echo $productRecord['precio_costo']; ?>" data-status="<?php echo $productRecord['status']; ?>" data-venc="<?php echo $productRecord['fecha_vencimiento'] ?? ''; ?>" data-venc-cls="<?php echo $venc_cls; ?>">
                            <td class="td-prod-sku">
                                <span class="codigo-badge"><?php echo htmlspecialchars($productRecord['sku']); ?></span>
                            </td>
                            <td class="td-prod-nombre" data-tooltip="<?php echo htmlspecialchars($productRecord['nombre_producto']); ?>">
                                <span class="prod-nombre text-uppercase"><?php echo htmlspecialchars($productRecord['nombre_producto']); ?></span>
                            </td>
                            <td class="td-prod-cat" data-tooltip="<?php echo htmlspecialchars($productRecord['nombre_cat'] ?? 'Sin categoría'); ?>">
                                <span class="prod-cat"><?php echo htmlspecialchars($productRecord['nombre_cat'] ?? 'Sin categoría'); ?></span>
                            </td>
                            <td class="td-prod-prov" data-tooltip="<?php echo htmlspecialchars($productRecord['proveedores'] ?? 'Sin proveedor en catálogo'); ?>">
                                <span class="prod-prov"><?php echo htmlspecialchars($productRecord['proveedores'] ?? '—'); ?></span>
                            </td>
                            <td class="td-stock text-center">
                                <div class="stock-summary">
                                    <span class="stk-num"><?php echo $stock_actual; ?></span>
                                </div>
                                <div class="stock-meter" role="progressbar" aria-valuenow="<?php echo $stock_actual; ?>" aria-valuemin="0" aria-valuemax="<?php echo $capacidad; ?>" aria-label="Nivel de stock">
                                    <div class="stock-meter-fill stock-meter-<?php echo $stock_clase; ?>" style="width:<?php echo $stock_porcentaje; ?>%;"></div>
                                </div>
                                <div class="stk-meta">
                                    Mín <?php echo $stock_minimo; ?> · Máx <?php echo $capacidad; ?>
                                </div>
                            </td>
                            <td>
                                <span class="prod-precio">$<?php echo number_format($productRecord['precio_venta'], 2); ?></span>
                            </td>
                            <td class="text-center td-vencimiento">
                                <span class="badge-jv <?php echo $venc_badge; ?>" data-tooltip="<?php echo htmlspecialchars($venc_fecha ?: 'Sin fecha de vencimiento', ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="bi bi-<?php echo $venc_icono; ?>"></i>
                                    <span class="venc-fecha"><?php echo $venc_fecha ?: '—'; ?></span>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge-jv <?php echo ($productRecord['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>" style="font-size:.75rem;">
                                    <i class="bi bi-<?php echo ($productRecord['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                    <?php echo strtoupper($productRecord['status']); ?>
                                </span>
                            </td>
                            <?php if ($esAdmin): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm p-0" style="width:30px;height:30px;border-radius:8px;background:rgba(234,88,12,0.12);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="editarProducto(<?php echo $productRecord['id_producto']; ?>)" data-tooltip="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($productRecord['status'] === 'Activo'): ?>
                                            <button type="button" class="btn btn-sm p-0" style="width:30px;height:30px;border-radius:8px;background:rgba(220,38,38,0.12);color:var(--jv-danger);border:1px solid rgba(220,38,38,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo (int)$productRecord['id_producto']; ?>, <?php echo htmlspecialchars(json_encode($productRecord['nombre_producto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, 'desactivar')" data-tooltip="Desactivar">
                                                <i class="bi bi-power"></i>
                                            </button>
                                            <?php if ($venc_cls === 'vencido'): ?>
                                                <button type="button" class="btn btn-sm p-0 ms-1" style="width:30px;height:30px;border-radius:8px;background:rgba(100,116,139,0.12);color:var(--jv-text-muted);border:1px solid rgba(100,116,139,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="bajaVencido(<?php echo (int)$productRecord['id_producto']; ?>, <?php echo htmlspecialchars(json_encode($productRecord['nombre_producto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" data-tooltip="Dar de baja por vencimiento">
                                                    <i class="bi bi-archive"></i>
                                                </button>
<?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm p-0" style="width:30px;height:30px;border-radius:8px;background:rgba(22,163,74,0.12);color:var(--jv-success);border:1px solid rgba(22,163,74,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo (int)$productRecord['id_producto']; ?>, <?php echo htmlspecialchars(json_encode($productRecord['nombre_producto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, 'activar')" data-tooltip="Reactivar">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $esAdmin ? 9 : 8; ?>" class="text-center py-5">
                            <i class="bi bi-box-seam d-block mb-3 mx-auto" style="font-size: 3.5rem; color: var(--jv-text-muted);"></i>
                            <span class="text-uppercase" style="color: var(--jv-text-primary); font-weight: 700; font-size: 1.1rem;">Inventario vacío</span>
                            <p class="mt-2" style="color: var(--jv-text-muted); font-size: 1rem;">Registra entradas desde <strong style="color: var(--jv-orange);">Compras</strong> para ver productos aquí</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-4" style="border-top: 1px solid var(--jv-border);">
            <div class="small text-secondary">
                Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $registros_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> productos
            </div>
            <nav>
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual - 1; ?>">Anterior</a>
                    </li>
                    <?php
                    $inicio_p = max(1, $pagina_actual - 2);
                    $fin_p = min($total_paginas, $pagina_actual + 2);
                    for ($pageNumber = $inicio_p; $pageNumber <= $fin_p; $pageNumber++):
                    ?>
                        <li class="page-item <?php echo ($pageNumber == $pagina_actual) ? 'active' : ''; ?>">
                            <a class="page-link" style="<?php echo ($pageNumber == $pagina_actual) ? 'background:var(--jv-orange); border-color:var(--jv-orange); color:#fff;' : 'background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);'; ?>" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual + 1; ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php if ($puede_categorias): ?>
<!-- ============================================================
     GESTIÓN INTEGRADA DE CATEGORÍAS (pop-ups)
     Listado + formulario, colocados FUERA de la tabla de productos.
     ============================================================ -->
<script>
    window.JV_CATS = <?php echo json_encode(array_values($categorias_gestion), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<!-- POP-UP 1: Listado de categorías -->
<div class="modal fade" id="modalCategorias" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-jv">
            <div class="jv-modal-header">
                <div>
                    <h5 class="font-brand"><i class="bi bi-folder2-open me-2"></i>CATEGOR&Iacute;AS</h5>
                    <small>Organiza tu cat&aacute;logo para agrupar productos similares</small>
                </div>
                <button type="button" class="jv-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="cat-search">
                        <i class="bi bi-search"></i>
                        <input type="text" class="input-jv" id="buscarCat" placeholder="Buscar por nombre o c&oacute;digo..." oninput="catFiltrar()" aria-label="Buscar categor&iacute;a">
                    </div>
                    <div class="cat-filter-group" role="group" aria-label="Filtrar por estado">
                        <button type="button" class="btn-filter active" data-status="todos" onclick="catFiltrarEstado(this)">Todas</button>
                        <button type="button" class="btn-filter" data-status="Activo" onclick="catFiltrarEstado(this)">Activas</button>
                        <button type="button" class="btn-filter" data-status="Inactivo" onclick="catFiltrarEstado(this)">Inactivas</button>
                    </div>
                    <button type="button" class="btn btn-jv-primary module-action-btn ms-auto" onclick="nuevaCat()">
                        <i class="bi bi-plus-lg me-1"></i>NUEVA CATEGOR&Iacute;A
                    </button>
                </div>
                <?php if (!empty($categorias_gestion)): ?>
                    <div class="small fw-bold text-muted mb-2 cat-contador" id="catContador">
                        <?php echo count($categorias_gestion); ?> categor&iacute;a(s) &middot; Estado: Todas
                    </div>
                <?php endif; ?>
                <?php if (!empty($categorias_gestion)): ?>
                    <div class="cat-tabla-cont">
                        <table class="table-jv mb-0">
                            <thead>
                                <tr>
                                    <th style="width:28%;">Nombre</th>
                                    <th style="width:15%;">C&oacute;digo</th>
                                    <th class="text-center" style="width:9%;">ABC</th>
                                    <th class="text-center" style="width:19%;">Manejo</th>
                                    <th class="text-center" style="width:12%;">Estado</th>
                                    <th class="text-center" style="width:17%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaCategoriasPop">
                                <?php foreach ($categorias_gestion as $gestion_cat): ?>
                                    <?php $cat_json = htmlspecialchars(json_encode($gestion_cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>
                                    <tr class="cat-fila" data-status="<?php echo $gestion_cat['status']; ?>" data-texto="<?php echo strtolower(htmlspecialchars($gestion_cat['nombre'] . ' ' . ($gestion_cat['codigo'] ?? ''))); ?>">
                                        <td>
                                            <i class="bi bi-folder2-open me-2 cat-icono-archivo"></i>
                                            <span class="fw-bold text-uppercase" data-tooltip="<?php echo htmlspecialchars($gestion_cat['nombre']); ?>"><?php echo htmlspecialchars($gestion_cat['nombre']); ?></span>
                                        </td>
                                        <td><span class="codigo-badge-cat"><?php echo htmlspecialchars($gestion_cat['codigo'] ?? '&mdash;'); ?></span></td>
                                        <td class="text-center">
                                            <?php if (!empty($gestion_cat['clasificacion_abc'])): ?>
                                                <span class="abc-badge abc-<?php echo strtolower($gestion_cat['clasificacion_abc']); ?>" data-tooltip="<?php echo 'A' === $gestion_cat['clasificacion_abc'] ? 'Alto valor / rotaci&oacute;n' : ('B' === $gestion_cat['clasificacion_abc'] ? 'Valor medio' : 'Valor bajo'); ?>"><?php echo $gestion_cat['clasificacion_abc']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="manejo-badge manejo-<?php echo htmlspecialchars($gestion_cat['tipo_manejo'] ?? 'normal'); ?>"><?php echo htmlspecialchars(ucfirst($gestion_cat['tipo_manejo'] ?? 'Normal')); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo ($gestion_cat['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>"><?php echo strtoupper($gestion_cat['status']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn-action" onclick='editarCat(<?php echo $cat_json; ?>)' data-tooltip="Editar"><i class="bi bi-pencil-square"></i></button>
                                                <button type="button" class="btn-action" onclick="catToggleStatus(<?php echo (int)$gestion_cat['id_categoria']; ?>, '<?php echo htmlspecialchars(addslashes($gestion_cat['nombre'])); ?>', '<?php echo $gestion_cat['status']; ?>')" data-tooltip="<?php echo $gestion_cat['status'] == 'Activo' ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="bi <?php echo $gestion_cat['status'] == 'Activo' ? 'bi-eye-slash-fill' : 'bi-eye-fill'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="cat-fila-vacia" id="catSinResultados" style="display:none;">
                                    <td colspan="6">
                                        <div class="estado-vacio">
                                            <i class="bi bi-search"></i>
                                            <span>Sin resultados</span>
                                            <p class="mt-2 mb-0 cat-estado-guiador">Prueba con otro nombre o cambia el filtro de estado.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="cat-sin-datos">
                        <div class="cat-sin-datos-icono"><i class="bi bi-folder2-open"></i></div>
                        <div class="cat-sin-datos-titulo">Todav&iacute;a no hay categor&iacute;as</div>
                        <div class="cat-sin-datos-sub">Crea la primera para agrupar tus productos y mantener el inventario ordenado. Te tomar&aacute; menos de un minuto.</div>
                        <button type="button" class="btn btn-jv-primary module-action-btn" onclick="nuevaCat()">
                            <i class="bi bi-plus-lg me-1"></i>NUEVA CATEGOR&Iacute;A
                        </button>
                    </div>
                <?php endif; ?>
            </div>
    </div>
</div>

<!-- POP-UP 2: Formulario de categoría (crear / editar) -->
<div class="modal fade" id="modalCat" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="POST" id="formCat" autocomplete="off">
                <div class="jv-modal-header">
                    <div>
                        <h5 class="font-brand" id="modalTitleCat"><i class="bi bi-folder-plus-fill me-2"></i>NUEVA CATEGOR&Iacute;A</h5>
                        <small>Los campos con <span style="color:#FCA5A5;">*</span> son obligatorios</small>
                    </div>
                    <button type="button" class="jv-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_categoria" id="cat_accion" value="registrar">
                <input type="hidden" name="id_categoria" id="cat_id_edit">
                <input type="hidden" name="status" id="cat_status" value="Activo">
                <div class="modal-body p-4">
                    <div class="jv-modal-section" style="border-left:4px solid #2563EB;">
                        <div class="jv-modal-section-head">
                            <span class="jv-modal-chip" style="background:#2563EB;box-shadow:0 3px 10px rgba(37,99,235,0.3);"><i class="bi bi-card-heading"></i></span>
                            <div>
                                <div class="jv-modal-section-title">General</div>
                                <div class="jv-modal-section-sub">Identificaci&oacute;n b&aacute;sica de la categor&iacute;a</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="cat_nombre" class="jv-modal-label">NOMBRE DE LA CATEGOR&Iacute;A <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="cat_nombre" class="input-jv" required maxlength="100" placeholder="Ej: Aceites y Lubricantes" oninput="this.value = this.value.toUpperCase()">
                            <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>Se guarda en MAY&Uacute;SCULAS autom&aacute;ticamente. No puede repetirse.</div>
                        </div>
                        <div class="mb-0">
                            <label for="cat_desc" class="jv-modal-label">DESCRIPCI&Oacute;N <span class="text-secondary fw-normal">(OPCIONAL)</span></label>
                            <textarea name="descripcion" id="cat_desc" class="input-jv" rows="2" placeholder="Ej: Aceites de motor, lubricantes, grasas..."></textarea>
                            <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>Explica qu&eacute; productos agrupa, para que cualquier operador sepa c&oacute;mo usarla.</div>
                        </div>
                    </div>

                    <div class="jv-modal-section" style="border-left:4px solid #0D9488;">
                        <div class="jv-modal-section-head">
                            <span class="jv-modal-chip" style="background:#0D9488;box-shadow:0 3px 10px rgba(13,148,136,0.3);"><i class="bi bi-sliders"></i></span>
                            <div>
                                <div class="jv-modal-section-title">Par&aacute;metros</div>
                                <div class="jv-modal-section-sub">Clasificaci&oacute;n y manejo del inventario</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-6">
                                <label for="cat_abc" class="jv-modal-label">CLASIFICACI&Oacute;N ABC</label>
                                <select name="clasificacion_abc" id="cat_abc" class="input-jv">
                                    <option value="">&mdash; Sin clasificar</option>
                                    <option value="A">A &middot; Alto valor / rotaci&oacute;n</option>
                                    <option value="B">B &middot; Valor medio</option>
                                    <option value="C">C &middot; Valor bajo</option>
                                </select>
                                <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>Nivel de importancia de los productos de esta categor&iacute;a.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="cat_manejo" class="jv-modal-label">TIPO DE MANEJO</label>
                                <select name="tipo_manejo" id="cat_manejo" class="input-jv">
                                    <option value="normal">Normal &middot; Uso general</option>
                                    <option value="inflamable">Inflamable &middot; Riesgo de incendio</option>
                                    <option value="liquido">L&iacute;quido &middot; Contenedores</option>
                                    <option value="peligroso">Peligroso &middot; Riesgo para la salud</option>
                                    <option value="voluminoso">Voluminoso &middot; Ocupa espacio</option>
                                    <option value="aerosol">Aerosol &middot; Lata a presi&oacute;n</option>
                                </select>
                                <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>C&oacute;mo se almacena y manipula el producto.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="jv-modal-footer">
                    <button type="button" class="btn btn-jv-ghost" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>CANCELAR
                    </button>
                    <button type="submit" id="btn-cat-guardar" class="btn btn-jv-primary module-action-btn">
                        <i class="bi bi-check-lg me-2"></i>GUARDAR CATEGOR&Iacute;A
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($esAdmin): ?>
    <!-- Modal: Editar producto -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content modal-content-jv modal-producto-edit">
                <form method="POST" id="formEditar">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="accion" value="editar_producto">
                    <input type="hidden" name="id_producto" id="edit_id">
                    <div class="p-3 modal-producto-edit-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand">
                                <i class="bi bi-pencil-square me-2"></i>EDITAR PRODUCTO
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                    <div class="p-4 modal-producto-edit-body">
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">PRODUCTO</label>
                            <input type="text" class="input-jv" id="edit_nombre" readonly disabled style="color:var(--jv-text-muted);">
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">Código</label>
                            <input type="text" class="input-jv" id="edit_sku" readonly disabled style="color:var(--jv-text-muted);">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">STOCK ACTUAL</label>
                                <input type="text" class="input-jv" id="edit_stock" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                                <input type="number" class="input-jv" id="edit_minimo" name="stock_minimo" min="0" max="99999" required>
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">CAPACIDAD MÁX.</label>
                                <input type="number" class="input-jv" id="edit_maximo" name="stock_maximo" min="0" max="999999" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">PRECIO VENTA ($)</label>
                                <input type="text" class="input-jv precio-edicion" id="edit_pvp" inputmode="decimal" autocomplete="off" data-max="99999.99" required>
                                <input type="hidden" id="edit_pvp_valor" name="precio_venta">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">COSTO PROMEDIO ($)</label>
                                <input type="text" class="input-jv" id="edit_costo" readonly disabled style="color:var(--jv-text-muted);background:rgba(15,26,46,0.04);" data-tooltip="Se calcula automáticamente al recibir mercancía (costo promedio ponderado)">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                                <select class="input-jv" id="edit_status" name="status">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">VENCIMIENTO <span class="text-danger">*</span></label>
                                <input type="date" class="input-jv" id="edit_vencimiento" name="fecha_vencimiento" required>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                        <button type="button" class="btn btn-jv-danger" style="padding:8px 20px;font-size:.8rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" style="padding:8px 20px;font-size:.8rem;" onclick="return validarEditarProducto(this)"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>