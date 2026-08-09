<?php
// ==========================================
// VISTA: Solicitudes de Reposición (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
?>
<!-- Encabezado -->
<div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
    <div class="d-flex align-items-center gap-3">
        <div class="module-header-icon">
            <i class="bi bi-cart-check"></i>
        </div>
        <div>
            <h1 class="module-title">Solicitudes de Reposición</h1>
            <p class="module-subtitle">Pedidos de reposición generados desde Ventas cuando no hay stock</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge-jv badge-primary" style="font-size:.75rem;padding:8px 16px;"><i class="bi bi-cart-check me-1"></i>SOLICITUDES DE REPOSICIÓN</span>
    </div>
</div>

<!-- Mensajes flash -->
<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="widget-card">
            <div class="widget-icon" style="background:rgba(99,102,241,0.12);color:#4F46E5;"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="widget-label">Pendientes</div>
                <div class="widget-value"><?php echo (int)$kpis['pendientes']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-card">
            <div class="widget-icon" style="background:rgba(59,130,246,0.12);color:#2563EB;"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="widget-label">Productos Solicitados</div>
                <div class="widget-value"><?php echo (int)$kpis['productos']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-card">
            <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:#0284C7;"><i class="bi bi-stack"></i></div>
            <div>
                <div class="widget-label">Unidades a Reponer</div>
                <div class="widget-value"><?php echo (int)$kpis['unidades']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-card">
            <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:#16A34A;"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="widget-label">Atendidas</div>
                <div class="widget-value"><?php echo (int)$kpis['atendidas']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Solicitudes pendientes -->
<div class="card-jv card-jv-table p-0 mb-4">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
        <i class="bi bi-hourglass-split me-1" style="font-size:1rem;"></i>
        <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Pendientes de Atención</span>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:7%;">#</th>
                    <th style="width:18%;">Motivo</th>
                    <th style="width:14%;">Solicitante</th>
                    <th class="text-center" style="width:10%;">Productos</th>
                    <th class="text-center" style="width:10%;">Unidades</th>
                    <th style="width:12%;">Fecha</th>
                    <th class="text-center" style="width:200px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($solicitudes) > 0): foreach ($solicitudes as $row): ?>
                        <tr>
                            <td><span class="codigo-badge">#<?php echo (int)$row['id_solicitud']; ?></span></td>
                            <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['motivo'] ?? 'Solicitud de reposición'); ?></td>
                            <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($row['solicitante']); ?></td>
                            <td class="text-center"><span class="cant-badge"><?php echo (int)$row['num_productos']; ?></span></td>
                            <td class="text-center fw-bold"><?php echo (int)$row['total_unidades']; ?></td>
                            <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($row['fecha_solicitud'])); ?></td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <a class="btn-atender" href="<?php echo APP_URL_BASE; ?>index.php?url=compras&atender_solicitud=<?php echo (int)$row['id_solicitud']; ?>">
                                        <i class="bi bi-check-lg me-1"></i>ATENDER
                                    </a>
                                    <button type="button" class="btn-cancelar" onclick="confirmarCancelar(<?php echo (int)$row['id_solicitud']; ?>)" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="estado-vacio">
                                <i class="bi bi-check2-circle"></i>
                                <span>No hay solicitudes pendientes</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Historial -->
<div class="card-jv card-jv-table p-0">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
        <i class="bi bi-clock-history me-1" style="font-size:1rem;"></i>
        <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Historial (Atendidas / Canceladas)</span>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:7%;">#</th>
                    <th style="width:16%;">Motivo</th>
                    <th style="width:12%;">Solicitante</th>
                    <th class="text-center" style="width:9%;">Productos</th>
                    <th class="text-center" style="width:9%;">Unidades</th>
                    <th style="width:14%;">Fecha</th>
                    <th style="width:12%;">Factura</th>
                    <th class="text-center" style="width:11%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($historial) > 0): foreach ($historial as $row): ?>
                        <tr>
                            <td><span class="codigo-badge">#<?php echo (int)$row['id_solicitud']; ?></span></td>
                            <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['motivo'] ?? 'Solicitud de reposición'); ?></td>
                            <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($row['solicitante']); ?></td>
                            <td class="text-center"><?php echo (int)$row['num_productos']; ?></td>
                            <td class="text-center"><?php echo (int)$row['total_unidades']; ?></td>
                            <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($row['fecha_solicitud'])); ?></td>
                            <td><?php echo htmlspecialchars($row['nro_factura'] ?: '-'); ?></td>
                            <td class="text-center">
                                <?php $est = $row['estado']; ?>
                                <span class="badge-jv <?php echo $est === 'Atendida' ? 'badge-success' : 'badge-danger'; ?>"><i class="bi <?php echo $est === 'Atendida' ? 'bi-check-circle' : 'bi-x-circle'; ?> me-1"></i><?php echo $est; ?></span>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="estado-vacio">
                                <i class="bi bi-inbox"></i>
                                <span>Aún no hay solicitudes procesadas</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
