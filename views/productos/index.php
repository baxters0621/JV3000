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
/** @var array<int, array<string, mixed>> $categorias Árbol de categorías (raíces con 'hijos') */
/** @var int $id_categoria_filtro Categoría seleccionada en el árbol (0 = todas) */
/** @var array<string, mixed>|null $categoria_filtro Fila de la categoría del filtro */

// ==========================================
// VISTA: Inventario / Productos (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
// Diseño: el inventario ocupa todo el ancho y el árbol se abre en un modal.
// La escritura de categorías queda en manos del Administrador u Operador de Carga.
$puede_categorias = $esAdmin || (int)$_SESSION['id_rol'] === 2;

// Aplana el árbol (raíces + subcategorías) para el JS del formulario.
$categorias_plano = [];
foreach ($categorias as $raiz) {
    $categorias_plano[] = $raiz;
    foreach (($raiz['hijos'] ?? []) as $hija) {
        $categorias_plano[] = $hija;
    }
}
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
</div>

<!-- Mensajes flash (el origen le indica al JS qué formulario reabrir) -->
<?php if ($flash): ?>
    <?php $flash_origen = (string)($flash['origen'] ?? ''); ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3" id="flashMsg" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>"<?php echo $flash_origen !== '' ? ' data-origen="' . htmlspecialchars($flash_origen) . '"' : ''; ?> role="status" aria-live="polite">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Aviso de filtro por categoría activo (?cat=N) -->
<?php if ($categoria_filtro): ?>
<div class="alert-jv alert-jv-info mb-3" id="bannerFiltroCat" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <i class="bi bi-funnel-fill"></i>
    <span class="small">
        Mostrando la rama <strong class="text-uppercase"><?php echo htmlspecialchars($categoria_filtro['nombre']); ?></strong>
        &middot; <?php echo (int)$total_registros; ?> producto(s)
    </span>
    <a class="btn btn-sm btn-jv-ghost ms-auto" href="<?php echo APP_URL_BASE; ?>index.php?url=productos">
        <i class="bi bi-x-lg me-1"></i>VER TODO
    </a>
</div>
<?php endif; ?>

<!-- Acceso al gestor de categorías -->
<div class="d-flex align-items-center mb-3">
    <button type="button" class="btn btn-jv-primary" onclick="catAbrirGestor()" aria-controls="modalCategorias">
        <i class="bi bi-diagram-3-fill me-2"></i>CATEGOR&Iacute;AS
    </button>
</div>

<!-- Gestor de categorías (consulta para todos los roles; edición según permisos) -->
<div class="modal fade" id="modalCategorias" tabindex="-1" aria-labelledby="tituloModalCategorias" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable cat-manager-dialog">
        <div class="modal-content modal-content-jv cat-panel p-0">
            <div class="cat-panel-head">
                <div class="d-flex align-items-center gap-2">
                    <span class="cat-panel-icono"><i class="bi bi-diagram-3-fill"></i></span>
                    <div>
                        <h2 class="cat-panel-titulo" id="tituloModalCategorias">Categor&iacute;as</h2>
                        <small class="cat-panel-sub">Selecciona una categor&iacute;a para ver sus productos. Las subcategor&iacute;as aparecen debajo.</small>
                    </div>
                </div>
                <div class="cat-panel-tools">
                    <?php if ($puede_categorias): ?>
                        <button type="button" class="btn btn-jv-primary cat-btn-nueva" onclick="catAbrirForm('registrar', 0, 0)">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nueva categor&iacute;a</span>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="cat-cerrar" data-bs-dismiss="modal" aria-label="Cerrar categor&iacute;as">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="cat-panel-buscar">
                <i class="bi bi-search"></i>
                <input type="text" id="buscarCat" placeholder="Buscar categor&iacute;a..." oninput="catFiltrar()" aria-label="Buscar categor&iacute;a">
            </div>
            <div class="cat-arbol-cont">
                <?php if (!empty($categorias)): ?>
                    <ul class="cat-arbol" id="arbolCategorias">
                        <?php foreach ($categorias as $raiz): ?>
                            <?php
                            // Cada categoría principal y sus subcategorías se muestran
                            // juntas para que la jerarquía se entienda sin controles ocultos.
                            $raiz_id = (int)$raiz['id_categoria'];
                            $raiz_inactiva = $raiz['status'] === 'Inactivo';
                            $raiz_total = (int)($raiz['productos_total'] ?? 0);
                            $raiz_con_stock = (int)($raiz['productos_con_stock'] ?? 0);
                            $raiz_sel = $raiz_id === $id_categoria_filtro;
                            ?>
                            <li class="cat-nodo cat-raiz<?php echo $raiz_inactiva ? ' cat-inactiva' : ''; ?><?php echo $raiz_sel ? ' cat-seleccionada' : ''; ?>"
                                data-id="<?php echo $raiz_id; ?>" data-status="<?php echo $raiz['status']; ?>"
                                data-texto="<?php echo strtolower(htmlspecialchars($raiz['nombre'] . ' ' . ($raiz['codigo'] ?? ''))); ?>">
                                <div class="cat-fila">
                                    <a class="cat-enlace" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&cat=<?php echo $raiz_id; ?>" title="Filtrar por esta categor&iacute;a">
                                        <i class="bi bi-folder-fill cat-icono" aria-hidden="true"></i>
                                        <span class="cat-nivel cat-nivel-raiz">Categor&iacute;a principal</span>
                                        <span class="cat-nombre"><?php echo htmlspecialchars($raiz['nombre']); ?></span>
                                        <?php if (!empty($raiz['codigo'])): ?><span class="codigo-badge-cat cat-codigo"><?php echo htmlspecialchars($raiz['codigo']); ?></span><?php endif; ?>
                                        <span class="cat-conteo" data-tooltip="<?php echo $raiz_con_stock; ?> con stock de <?php echo $raiz_total; ?> en la rama"><?php echo $raiz_total > 0 ? $raiz_con_stock . '/' . $raiz_total : '0'; ?></span>
                                    </a>
                                    <?php if ($raiz_inactiva): ?>
                                        <span class="cat-chip cat-chip-<?php echo $raiz_total > 0 ? 'sinstock' : 'oculta'; ?>"><?php echo $raiz_total > 0 ? 'SIN STOCK' : 'OCULTA'; ?></span>
                                    <?php endif; ?>
                                    <?php if ($puede_categorias): ?>
                                        <div class="cat-acciones">
                                            <?php if (!$raiz_inactiva): ?>
                                                <button type="button" class="btn-action btn-action-label btn-action-add" onclick="catAbrirForm('registrar', 0, <?php echo $raiz_id; ?>)">
                                                    <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Agregar subcategor&iacute;a</span>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn-action btn-action-label" onclick="catAbrirForm('editar', <?php echo $raiz_id; ?>)">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i><span>Editar</span>
                                            </button>
                                            <?php if ($raiz_total === 0): ?>
                                                <button type="button" class="btn-action btn-action-label btn-action-state" onclick="catToggleStatus(<?php echo $raiz_id; ?>, <?php echo htmlspecialchars(json_encode($raiz['nombre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $raiz['status']; ?>')">
                                                    <i class="bi <?php echo $raiz_inactiva ? 'bi-eye-fill' : 'bi-eye-slash-fill'; ?>" aria-hidden="true"></i>
                                                    <span><?php echo $raiz_inactiva ? 'Mostrar' : 'Ocultar'; ?></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($raiz['hijos'])): ?>
                                    <ul class="cat-hijos">
                                        <?php foreach ($raiz['hijos'] as $hija): ?>
                                            <?php
                                            // Subcategoría: igual que la raíz, sin expansor
                                            // ni botón de agregar (el árbol llega a 2 niveles).
                                            $hija_id = (int)$hija['id_categoria'];
                                            $hija_inactiva = $hija['status'] === 'Inactivo';
                                            $hija_total = (int)($hija['productos_total'] ?? 0);
                                            $hija_con_stock = (int)($hija['productos_con_stock'] ?? 0);
                                            $hija_sel = $hija_id === $id_categoria_filtro;
                                            ?>
                                            <li class="cat-nodo cat-hija<?php echo $hija_inactiva ? ' cat-inactiva' : ''; ?><?php echo $hija_sel ? ' cat-seleccionada' : ''; ?>"
                                                data-id="<?php echo $hija_id; ?>" data-status="<?php echo $hija['status']; ?>"
                                                data-texto="<?php echo strtolower(htmlspecialchars($hija['nombre'] . ' ' . ($hija['codigo'] ?? ''))); ?>">
                                                <div class="cat-fila">
                                                    <a class="cat-enlace" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&cat=<?php echo $hija_id; ?>" title="Filtrar por esta categor&iacute;a">
                                                        <i class="bi bi-folder-fill cat-icono" aria-hidden="true"></i>
                                                        <span class="cat-nivel cat-nivel-hija">Subcategor&iacute;a</span>
                                                        <span class="cat-nombre"><?php echo htmlspecialchars($hija['nombre']); ?></span>
                                                        <?php if (!empty($hija['codigo'])): ?><span class="codigo-badge-cat cat-codigo"><?php echo htmlspecialchars($hija['codigo']); ?></span><?php endif; ?>
                                                        <span class="cat-conteo" data-tooltip="<?php echo $hija_con_stock; ?> con stock de <?php echo $hija_total; ?> en la rama"><?php echo $hija_total > 0 ? $hija_con_stock . '/' . $hija_total : '0'; ?></span>
                                                    </a>
                                                    <?php if ($hija_inactiva): ?>
                                                        <span class="cat-chip cat-chip-<?php echo $hija_total > 0 ? 'sinstock' : 'oculta'; ?>"><?php echo $hija_total > 0 ? 'SIN STOCK' : 'OCULTA'; ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($puede_categorias): ?>
                                                        <div class="cat-acciones">
                                                            <button type="button" class="btn-action btn-action-label" onclick="catAbrirForm('editar', <?php echo $hija_id; ?>)">
                                                                <i class="bi bi-pencil-square" aria-hidden="true"></i><span>Editar</span>
                                                            </button>
                                                            <?php if ($hija_total === 0): ?>
                                                                <button type="button" class="btn-action btn-action-label btn-action-state" onclick="catToggleStatus(<?php echo $hija_id; ?>, <?php echo htmlspecialchars(json_encode($hija['nombre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $hija['status']; ?>')">
                                                                    <i class="bi <?php echo $hija_inactiva ? 'bi-eye-fill' : 'bi-eye-slash-fill'; ?>" aria-hidden="true"></i>
                                                                    <span><?php echo $hija_inactiva ? 'Mostrar' : 'Ocultar'; ?></span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="cat-arbol-vacio" id="catSinResultados"<?php echo empty($categorias) ? '' : ' style="display:none;"'; ?>>
                    <i class="bi bi-inbox"></i>
                    <span><?php echo empty($categorias) ? 'Todav&iacute;a no hay categor&iacute;as' : 'Sin coincidencias'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

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
                                <span class="codigo-badge" data-tooltip="<?php echo htmlspecialchars($productRecord['sku'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($productRecord['sku']); ?></span>
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
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual - 1; ?><?php echo $id_categoria_filtro > 0 ? '&cat=' . $id_categoria_filtro : ''; ?>">Anterior</a>
                    </li>
                    <?php
                    $inicio_p = max(1, $pagina_actual - 2);
                    $fin_p = min($total_paginas, $pagina_actual + 2);
                    for ($pageNumber = $inicio_p; $pageNumber <= $fin_p; $pageNumber++):
                    ?>
                        <li class="page-item <?php echo ($pageNumber == $pagina_actual) ? 'active' : ''; ?>">
                            <a class="page-link" style="<?php echo ($pageNumber == $pagina_actual) ? 'background:var(--jv-orange); border-color:var(--jv-orange); color:#fff;' : 'background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);'; ?>" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pageNumber; ?><?php echo $id_categoria_filtro > 0 ? '&cat=' . $id_categoria_filtro : ''; ?>"><?php echo $pageNumber; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual + 1; ?><?php echo $id_categoria_filtro > 0 ? '&cat=' . $id_categoria_filtro : ''; ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

</div>

<?php if ($puede_categorias): ?>
<!-- Datos para el JS del formulario (raíces activas y datos de edición) -->
<script>
    window.JV_CATS = <?php echo json_encode(array_values($categorias_plano), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<!-- Formulario de categoría (crear / editar) -->
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
                            <label for="cat_padre" class="jv-modal-label">CATEGOR&Iacute;A PADRE</label>
                            <select name="id_padre" id="cat_padre" class="input-jv">
                                <option value="0">&mdash; Categor&iacute;a ra&iacute;z (nivel 1)</option>
                            </select>
                            <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>Elige una ra&iacute;z activa para crear una subcategor&iacute;a. M&aacute;ximo 2 niveles.</div>
                        </div>
                        <div class="mb-3">
                            <label for="cat_nombre" class="jv-modal-label">NOMBRE DE LA CATEGOR&Iacute;A <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="cat_nombre" class="input-jv" required maxlength="100" placeholder="Ej: Aceites y Lubricantes" oninput="this.value = this.value.toUpperCase()">
                            <div class="jv-modal-hint"><i class="bi bi-info-circle me-1"></i>Se guarda en MAY&Uacute;SCULAS autom&aacute;ticamente. No puede repetirse en su nivel.</div>
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