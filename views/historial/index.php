<?php

/** @var array<string, string>|null $flash */
/** @var int $total_registros */
/** @var string $filtro_usuario */
/** @var string[] $acciones_disponibles */
/** @var string $filtro_accion */
/** @var string $filtro_desde */
/** @var string $filtro_hasta */
/** @var string $filtro_detalle */
/** @var array<string, string> $accion_nombres */
/** @var array<int, array<string, mixed>> $registros */
/** @var int $total_paginas */
/** @var string $query_string */
/** @var int $page */

// ==========================================
// VISTA: Historial de Auditoría (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
?>
<!-- MENSAJES FLASH -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3">
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- ENCABEZADO -->
<div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding:18px 24px;border-left:4px solid var(--jv-orange);">
    <div class="d-flex align-items-center gap-3">
        <div class="aud-header-icon"><i class="bi bi-shield-check"></i></div>
        <div>
            <h1 class="module-title">HISTORIAL</h1>
            <p class="module-subtitle">Registro de Actividades del Sistema</p>
        </div>
    </div>
    <span class="text-jv-muted fw-bold" style="font-size:.95rem;"><?php echo $total_registros; ?> registro(s)</span>
</div>

<!-- FORMULARIO DE FILTROS -->
<form class="filtro-box" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="small fw-bold text-secondary mb-1">USUARIO</label>
            <input type="text" name="usuario" class="input-jv" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtro_usuario); ?>">
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-secondary mb-1">ACCIÓN</label>
            <select name="accion" class="input-jv">
                <option value="">Todas</option>
                <?php foreach ($acciones_disponibles as $actionKey): ?>
                    <option value="<?php echo htmlspecialchars($actionKey); ?>" <?php echo $filtro_accion === $actionKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($accion_nombres[$actionKey] ?? $actionKey); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-secondary mb-1">DESDE</label>
            <input type="date" name="desde" class="input-jv" value="<?php echo htmlspecialchars($filtro_desde); ?>">
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-secondary mb-1">HASTA</label>
            <input type="date" name="hasta" class="input-jv" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
        </div>
        <div class="col-md-2">
            <label class="small fw-bold text-secondary mb-1">DETALLE</label>
            <input type="text" name="detalle" class="input-jv" placeholder="Buscar en detalle..." value="<?php echo htmlspecialchars($filtro_detalle); ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn-jv-primary w-100" style="padding:10px;font-size:.75rem;"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>

<!-- TABLA PRINCIPAL -->
<div class="card-jv card-jv-table p-0">
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:70px;">N°</th>
                    <th style="width:12%;">USUARIO</th>
                    <th style="width:11%;">ACCIÓN</th>
                    <th>DETALLE</th>
                    <th style="width:13%;">FECHA / HORA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($registros)): ?>
                    <?php foreach ($registros as $auditRecord):
                        $badge_class = 'b-default';
                        if ($auditRecord['accion'] === 'crear') $badge_class = 'b-crear';
                        elseif ($auditRecord['accion'] === 'editar') $badge_class = 'b-editar';
                        elseif ($auditRecord['accion'] === 'eliminar' || $auditRecord['accion'] === 'anular') $badge_class = 'b-eliminar';
                        elseif (in_array($auditRecord['accion'], ['toggle_status', 'desactivar', 'activar'])) $badge_class = 'b-toggle';
                        elseif ($auditRecord['accion'] === 'login') $badge_class = 'b-login';
                        elseif ($auditRecord['accion'] === 'logout') $badge_class = 'b-logout';
                    ?>
                        <tr>
                            <td class="fw-bold text-jv-muted">#<?php echo $auditRecord['id_auditoria']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($auditRecord['usuario_nombre'] ?? '?'); ?></td>
                            <td><span class="badge-accion <?php echo $badge_class; ?>"><?php echo htmlspecialchars($accion_nombres[$auditRecord['accion']] ?? $auditRecord['accion']); ?></span></td>
                            <td class="td-detalle" data-tooltip="<?php echo htmlspecialchars($auditRecord['detalle'] ?? ''); ?>"><?php echo htmlspecialchars($auditRecord['detalle'] ?? ''); ?></td>
                            <td class="td-fecha"><?php echo date('d/m/Y H:i', strtotime($auditRecord['fecha_hora'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-jv-muted">No hay registros de auditoría</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PAGINACIÓN -->
<?php if ($total_paginas > 1): ?>
    <div class="pagination-jv">
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=1<?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo;</a>
        <?php for ($pageNumber = max(1, $page - 3); $pageNumber <= min($total_paginas, $page + 3); $pageNumber++): ?>
            <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=<?php echo $pageNumber; ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $pageNumber === $page ? 'active' : ''; ?>"><?php echo $pageNumber; ?></a>
        <?php endfor; ?>
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=<?php echo $total_paginas; ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $page >= $total_paginas ? 'disabled' : ''; ?>">&raquo;</a>
    </div>
<?php endif; ?>