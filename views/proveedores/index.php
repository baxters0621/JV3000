<?php

/** @var array<string, string>|null $flash */
/** @var int $total_prov */
/** @var int $activos_prov */
/** @var array<int, array<string, mixed>> $proveedores */
/** @var array<int, array<int, array<string, mixed>>> $catalogo */
/** @var array<int, array<string, mixed>> $productos_activos */
/** @var bool $esAdmin */
/** @var string $csrf */

// ==========================================
// VISTA: Proveedores (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
?>
<!-- Encabezado -->
<div class="d-flex align-items-center gap-4 mb-4 provider-header">
    <div class="prov-header-icon">
        <i class="bi bi-building"></i>
    </div>
    <div>
        <h1 class="module-title">PROVEEDORES</h1>
        <p class="module-subtitle">Directorio de Alianzas Comerciales</p>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
        <div class="prov-search">
            <i class="bi bi-search"></i>
            <input type="text" class="input-jv flex-grow-1" id="buscarProv" placeholder="Buscar proveedor..." onkeyup="filtrarProvTexto()" style="padding:10px 16px 10px 38px;max-width:none;font-size:1rem;">
        </div>
        <div class="filter-group">
            <button class="btn-filter active" onclick="filtrarProv('todos')" id="f-todos">Todos</button>
            <button class="btn-filter" onclick="filtrarProv('Activo')" id="f-Activo">Activos</button>
            <button class="btn-filter" onclick="filtrarProv('Inactivo')" id="f-Inactivo">Inactivos</button>
        </div>
        <button class="btn btn-jv-primary module-action-btn" onclick="nuevoProveedor()" id="btnNuevoProv">
            <i class="bi bi-plus-lg me-2"></i>NUEVO
        </button>
    </div>
</div>

<!-- Mensajes flash (data-texto permite al JS marcar el campo con error) -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4" id="flashMsg" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>">
        <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Widgets de estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="widget-card">
            <div class="widget-icon" style="background:rgba(219,39,119,0.12);color:var(--jv-prov,#DB2777);">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="widget-label">Total Proveedores</div>
                <div class="widget-value"><?php echo $total_prov; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
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
</div>

<!-- Tarjetas de proveedores -->
<div class="row g-3" id="provGrid">
    <?php if ($total_prov > 0): ?>
        <?php foreach ($proveedores as $proveedor): ?>
            <div class="col-md-6 col-lg-4 prov-card" data-status="<?php echo $proveedor['status']; ?>">
                <div class="prov-premium">
                    <div class="prov-head">
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-dot-jv <?php echo $proveedor['status'] == 'Activo' ? 'active' : 'inactive'; ?>"></span>
                            <span class="badge-jv <?php echo $proveedor['status'] == 'Activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $proveedor['status']; ?></span>
                        </div>
                        <!-- Acciones agrupadas a la derecha para que no queden dispersas en la cabecera -->
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn-action" onclick="editarProveedor(<?php echo htmlspecialchars(json_encode($proveedor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <?php if ($esAdmin): ?>
                                <button class="btn-action" onclick="toggleStatusProveedor(<?php echo (int)$proveedor['id_proveedor']; ?>, <?php echo htmlspecialchars(json_encode($proveedor['nombre_empresa'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($proveedor['status'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)" title="<?php echo $proveedor['status'] == 'Activo' ? 'Desactivar' : 'Activar'; ?>">
                                    <i class="bi <?php echo $proveedor['status'] == 'Activo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>" style="color:<?php echo $proveedor['status'] == 'Activo' ? 'var(--jv-danger)' : 'var(--jv-success)'; ?>"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="prov-body" onclick="toggleProv(this)">
                        <div class="prov-name" data-tooltip="<?php echo htmlspecialchars($proveedor['nombre_empresa']); ?>"><?php echo htmlspecialchars($proveedor['nombre_empresa']); ?></div>
                        <div class="prov-rif"><span class="codigo-badge"><?php echo htmlspecialchars($proveedor['rif']); ?></span></div>
                        <div class="prov-info"><i class="bi bi-telephone"></i><?php echo !empty($proveedor['telefono']) ? htmlspecialchars(formatearTelefono($proveedor['telefono'])) : ($proveedor['contacto'] ?? 'Sin teléfono'); ?></div>
                        <?php if (!empty($proveedor['contacto'])): ?>
                            <div class="prov-info"><i class="bi bi-person"></i><?php echo htmlspecialchars($proveedor['contacto']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($proveedor['email'])): ?>
                            <div class="prov-info"><i class="bi bi-envelope"></i><?php echo htmlspecialchars($proveedor['email']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="prov-details">
                        <div class="prov-detail-row">
                            <span class="detail-label"><i class="bi bi-truck me-1"></i>Plazo de Entrega</span>
                            <span class="detail-value"><?php echo $proveedor['lead_time'] ? $proveedor['lead_time'] . ' días' : 'A convenir'; ?></span>
                        </div>
                        <div class="prov-detail-row">
                            <span class="detail-label"><i class="bi bi-cash-coin me-1"></i>Moneda</span>
                            <span class="detail-value"><?php echo $proveedor['moneda'] ?? 'USD'; ?></span>
                        </div>

                        <?php
                        // Catálogo de costos del proveedor: productos que suministra,
                        // a qué costo y con qué código interno lo identifica él.
                        $entradas_catalogo = $catalogo[$proveedor['id_proveedor']] ?? [];
                        ?>
                        <div class="prov-catalogo-head">
                            <span><i class="bi bi-box-seam me-1"></i>PRODUCTOS QUE SUMINISTRA</span>
                            <button type="button" class="btn-cat-add" onclick="agregarProductoCatalogo(<?php echo (int)$proveedor['id_proveedor']; ?>, '<?php echo htmlspecialchars(addslashes($proveedor['nombre_empresa'])); ?>')">
                                <i class="bi bi-plus-lg"></i> Agregar
                            </button>
                        </div>
                        <?php if (!empty($entradas_catalogo)): ?>
                            <?php foreach ($entradas_catalogo as $entrada): ?>
                                <div class="prov-cat-item">
                                    <div class="cat-item-info">
                                        <span class="cat-item-nombre" data-tooltip="<?php echo htmlspecialchars($entrada['nombre_producto']); ?>"><?php echo htmlspecialchars($entrada['nombre_producto']); ?></span>
                                        <small class="cat-item-meta"><?php echo htmlspecialchars($entrada['sku']); ?><?php echo !empty($entrada['codigo_proveedor']) ? ' · Cód. prov: ' . htmlspecialchars($entrada['codigo_proveedor']) : ''; ?></small>
                                    </div>
                                    <span class="cat-item-costo">$<?php echo number_format((float)$entrada['costo'], 2); ?></span>
                                    <button type="button" class="btn-cat-icon" onclick="editarProductoCatalogo(<?php echo htmlspecialchars(json_encode($entrada, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars(addslashes($proveedor['nombre_empresa'])); ?>')" title="Editar costo">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn-cat-icon btn-cat-del" onclick="confirmarEliminarCatalogo(<?php echo (int)$entrada['id_catalogo']; ?>, '<?php echo htmlspecialchars(addslashes($entrada['nombre_producto'])); ?>')" title="Quitar del catálogo">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="prov-catalogo-vacio">
                                <i class="bi bi-inbox"></i>
                                Aún no tiene productos en su catálogo.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="prov-foot">
                        <button class="btn btn-jv-primary w-100 py-2" onclick="verHistorial(<?php echo $proveedor['id_proveedor']; ?>)">
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

<!-- Modal: Formulario de proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:var(--jv-bg-secondary); border:1px solid var(--jv-border); border-radius:var(--jv-radius-xl);">
            <form action="" method="POST" id="formProveedor">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
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
                                <input type="text" name="rif" id="p_rif" class="input-jv" required placeholder="Ej: J-12345678-0" maxlength="13" pattern="[VEJGPC]-\d{8}-\d" title="Formato: J-12345678-0">
                                <small style="color:var(--jv-text-secondary);font-size:.7rem;display:block;margin-top:4px;">Formato: J-12345678-0</small>
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
                            <div class="col-md-4">
                                <label for="p_lead_time" class="small fw-bold text-secondary mb-2">PLAZO DE ENTREGA (DÍAS)</label>
                                <input type="number" name="lead_time" id="p_lead_time" class="input-jv" placeholder="Días" min="0" max="365">
                            </div>
                            <div class="col-md-4">
                                <label for="p_moneda" class="small fw-bold text-secondary mb-2">MONEDA</label>
                                <select name="moneda" id="p_moneda" class="input-jv">
                                    <option value="USD">USD - Dólar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="VES">VES - Bolívar</option>
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

<!-- MODAL: CATÁLOGO DE COSTOS -->
<!-- Asocia un producto a este proveedor con su costo de compra y el código
     interno con el que él identifica el producto. Sirve para crear y editar. -->
<div class="modal fade" id="modalCatalogo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--jv-bg-secondary); border:1px solid var(--jv-border); border-radius:var(--jv-radius-xl);">
            <form action="" method="POST" id="formCatalogo">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_catalogo" id="cat_accion" value="registrar">
                <input type="hidden" name="id_catalogo" id="cat_id_edit">
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="fw-bolder font-brand m-0" style="color:var(--jv-navy);font-size:1.25rem;">
                            <i class="bi bi-box-seam me-2"></i><span id="catTitulo">AGREGAR PRODUCTO</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <p class="mb-4" style="color:var(--jv-text-muted);font-size:.85rem;" id="catSubtitulo"></p>

                    <div class="section-bg mb-4">
                        <div class="row g-3 mb-0">
                            <div class="col-12">
                                <label for="cat_proveedor_nombre" class="small fw-bold text-secondary mb-2">PROVEEDOR</label>
                                <input type="text" id="cat_proveedor_nombre" class="input-jv" readonly style="background:rgba(15,26,46,0.04);">
                                <input type="hidden" name="id_proveedor" id="cat_id_prov">
                            </div>
                            <div class="col-12">
                                <label for="cat_producto" class="small fw-bold text-secondary mb-2">PRODUCTO *</label>
                                <select name="id_producto" id="cat_producto" class="input-jv" required>
                                    <option value="">— Selecciona un producto —</option>
                                    <?php foreach ($productos_activos as $prod_activo): ?>
                                        <option value="<?php echo (int)$prod_activo['id_producto']; ?>">
                                            <?php echo htmlspecialchars($prod_activo['nombre_producto'] . ' (' . $prod_activo['sku'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cat_costo" class="small fw-bold text-secondary mb-2">COSTO DE COMPRA ($) *</label>
                                <input type="text" name="costo" id="cat_costo" class="input-jv" required placeholder="0.00" maxlength="12" inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <label for="cat_codigo_prov" class="small fw-bold text-secondary mb-2">CÓDIGO INTERNO DEL PROVEEDOR</label>
                                <input type="text" name="codigo_proveedor" id="cat_codigo_prov" class="input-jv" placeholder="Opcional" maxlength="50">
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btn-cat-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
                        <i class="bi bi-check-lg me-2"></i>GUARDAR EN CATÁLOGO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>