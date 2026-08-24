<?php

/** @var array<string, string>|null $flash */
/** @var int $total_prov */
/** @var int $activos_prov */
/** @var float $limite_credito_total */
/** @var array<int, array<string, mixed>> $proveedores */
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
                            <span class="detail-label">Plazo Entrega</span>
                            <span class="detail-value"><?php echo $proveedor['lead_time'] ? $proveedor['lead_time'] . ' días' : '-'; ?></span>
                        </div>
                        <div class="prov-detail-row">
                            <span class="detail-label">Límite Crédito</span>
                            <span class="detail-value" style="color:var(--jv-success);"><?php echo $proveedor['limite_credito'] ? '$' . number_format($proveedor['limite_credito'], 2) : '-'; ?></span>
                        </div>
                        <div class="prov-detail-row">
                            <span class="detail-label">Plazo Pago</span>
                            <span class="detail-value"><?php echo $proveedor['dias_credito'] ? $proveedor['dias_credito'] . ' días' : 'Contado'; ?></span>
                        </div>
                        <div class="prov-detail-row">
                            <span class="detail-label">Moneda</span>
                            <span class="detail-value"><?php echo $proveedor['moneda'] ?? 'USD'; ?></span>
                        </div>
                        <div class="prov-detail-row">
                            <span class="detail-label">Condición Pago</span>
                            <span class="detail-value"><?php echo $proveedor['condiciones_pago'] ?? 'Contado'; ?></span>
                        </div>
                        <?php
                        // Indicadores de crédito del proveedor: monto usado, límite y % consumido
                        $monto_credito_usado = $credito_usado[$proveedor['id_proveedor']] ?? 0;
                        $limite_credito = (float)($proveedor['limite_credito'] ?? 0);
                        if ($limite_credito > 0):
                            $credito_disponible = $limite_credito - $monto_credito_usado;
                            $porcentaje_credito = min(100, max(0, round(($monto_credito_usado / $limite_credito) * 100)));
                            $color_barra_credito = $porcentaje_credito >= 90 ? '#DC2626' : ($porcentaje_credito >= 70 ? '#D97706' : '#16A34A');
                        ?>
                            <div class="prov-detail-row">
                                <span class="detail-label">Crédito Usado</span>
                                <span class="detail-value" style="color:<?php echo $color_barra_credito; ?>;">$<?php echo number_format($monto_credito_usado, 2); ?></span>
                            </div>
                            <div class="prov-detail-row">
                                <span class="detail-label">Disponible</span>
                                <span class="detail-value" style="color:var(--jv-success);">$<?php echo number_format(max(0, $credito_disponible), 2); ?></span>
                            </div>
                            <div style="height:4px;background:rgba(15,26,46,0.08);border-radius:2px;margin:4px 12px;">
                                <div style="height:100%;width:<?php echo $porcentaje_credito; ?>%;background:<?php echo $color_barra_credito; ?>;border-radius:2px;transition:width .3s;"></div>
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