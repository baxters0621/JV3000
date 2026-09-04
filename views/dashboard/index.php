<?php
/**
 * @var string $titulo
 * @var string $nombre_user
 * @var string $rol_user
 * @var bool $esAdmin
 * @var bool $esOpVentas
 * @var bool $esOpCarga
 * @var string $fecha_hoy
 * @var string $saludo
 * @var string $iniciales
 * @var array $alertas
 * @var int $rol_user_id
 * @var string $ventas_dia
 * @var string $valor_inventario
 * @var array $ultimas_facturas
 * @var array $tabla_criticos
 * @var array $tabla_compras
 * @var string $csrf
 * @var array $css_extra
 * @var array $js_extra
 */

// ==========================================
// VISTA: Dashboard (Panel de Inicio) - Partial
// ==========================================
// Solo contenido, sin estructura HTML completa.
// El layout principal (main.php) provee la estructura HTML.
?>

<!-- ENCABEZADO DE MARCA -->
<header class="dash-hero">
    <div class="dash-brand">
        <img class="dash-logo-badge" src="<?php echo BASE_ASSETS; ?>img/logo-mark.svg?v=1" alt="JV3000">
        <div class="dash-brand-meta">
            <div class="dash-brand-title">JV<span class="num">3000</span> <span class="dash-brand-ca">C.A.</span></div>
            <p class="dash-brand-tag">Centro de Gestión de Inventario, Compras y Ventas</p>
        </div>
    </div>
    <div class="dash-hero-info">
        <div class="dash-user">
            <div class="dash-user-avatar"><?php echo $iniciales; ?></div>
            <div>
                <div class="dash-user-greeting"><?php echo $saludo; ?></div>
                <div class="dash-user-name"><?php echo htmlspecialchars($nombre_user); ?></div>
                <div class="dash-user-role"><?php echo strtoupper($rol_user); ?></div>
            </div>
        </div>
        <div class="dash-date">
            <i class="bi bi-calendar3 me-2"></i><?php echo $fecha_hoy; ?>
            <span class="dash-date-sep">&middot;</span>
            <i class="bi bi-clock-fill me-1"></i><span id="dash-clock">--:--</span>
        </div>
        <div class="dash-bell-wrap">
            <button type="button" class="dash-bell" id="dashBellBtn" onclick="toggleAlertas(event)" title="Alertas críticas de stock">
                <i class="bi bi-bell"></i>
                <?php if ($alertas['total'] > 0): ?>
                    <span class="dash-bell-badge" id="dashBellBadge"><?php echo min($alertas['total'], 99); ?></span>
                <?php endif; ?>
            </button>
            <div class="dash-bell-panel" id="dashBellPanel">
                <div class="dash-bell-head">ALERTAS CRÍTICAS</div>
                <?php if ($alertas['total'] === 0): ?>
                    <div class="dash-bell-empty"><i class="bi bi-check-circle"></i> Sin alertas críticas</div>
                <?php else: ?>
                    <?php
                    $secciones = [
                        'vencidos' => ['titulo' => 'VENCIDOS', 'clase' => 'ven', 'alerta' => 'vencidos', 'count' => $alertas['counts']['vencidos'], 'items' => $alertas['vencidos']],
                        'proximos' => ['titulo' => 'PRÓXIMOS (1-7 DÍAS)', 'clase' => 'prox', 'alerta' => 'proximos', 'count' => $alertas['counts']['proximos'], 'items' => $alertas['proximos']],
                        'prontos' => ['titulo' => 'PRONTO (8-30 DÍAS)', 'clase' => 'pronto', 'alerta' => 'prontos', 'count' => $alertas['counts']['prontos'], 'items' => $alertas['prontos']],
                    ];
                    if ($rol_user_id !== 3) {
                        $secciones['bajos'] = ['titulo' => 'STOCK BAJO', 'clase' => 'bajo', 'alerta' => 'bajos', 'count' => $alertas['counts']['bajos'], 'items' => $alertas['bajos']];
                    }
                    foreach ($secciones as $clave => $sec):
                        if ($sec['count'] <= 0) {
                            continue;
                        }
                    ?>
                    <div class="dash-bell-sec dash-bell-<?php echo $sec['clase']; ?>">
                        <div class="dash-bell-sec-titulo">
                            <span><?php echo $sec['titulo']; ?> (<?php echo $sec['count']; ?>)</span>
                            <a href="<?php echo BASE_PATH; ?>index.php?url=productos&alerta=<?php echo $sec['alerta']; ?>" class="dash-bell-ver">Ver todos</a>
                        </div>
                        <?php foreach ($sec['items'] as $it): ?>
                            <a class="dash-bell-item" href="<?php echo BASE_PATH; ?>index.php?url=productos&producto=<?php echo (int)$it['id']; ?>">
                                <i class="bi bi-<?php echo $clave === 'bajos' ? 'exclamation-triangle' : ($clave === 'proximos' ? 'clock-history' : ($clave === 'prontos' ? 'calendar3' : 'x-octagon')); ?>"></i>
                                <span class="dash-bell-item-nombre"><?php echo htmlspecialchars($it['nombre']); ?></span>
                                <span class="dash-bell-item-meta">
                                    <?php if ($clave === 'bajos'): ?>
                                        <?php echo (int)$it['stock']; ?> / mín <?php echo (int)$it['minimo']; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($it['fecha']); ?>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <section class="dash-section">
        <div class="quick-grid">
            <?php if ($esAdmin || $esOpVentas): ?>
                <a href="<?php echo BASE_PATH; ?>index.php?url=salidas" class="quick-btn quick-venta">
                    <span class="quick-icon"><i class="bi bi-cart-fill"></i></span>
                    <span class="quick-text">
                        <span class="quick-label">Nueva Venta</span>
                        <span class="quick-sub">Registrar una nota de Entrega</span>
                    </span>
                    <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                </a>
            <?php endif; ?>
            <?php if ($esAdmin || $esOpCarga): ?>
                <a href="<?php echo BASE_PATH; ?>index.php?url=compras" class="quick-btn quick-entrada">
                    <span class="quick-icon"><i class="bi bi-box-arrow-in-down"></i></span>
                    <span class="quick-text">
                        <span class="quick-label">Nueva Entrada</span>
                        <span class="quick-sub">Registrar una compra</span>
                    </span>
                    <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- INDICADORES CLAVE -->
    <section class="dash-section">
        <h2 class="sec-title"><i class="bi bi-speedometer2"></i> Indicadores Clave</h2>
        <div class="kpi-grid">
            <div class="kpi-card kpi-card-verde">
                <div class="kpi-icon kpi-icon-verde"><i class="bi bi-currency-dollar"></i></div>
                <div class="kpi-label">Última Venta</div>
                <div class="kpi-value" id="kpi-ventas-dia">$<?php echo $ventas_dia; ?></div>
            </div>
            <div class="kpi-card kpi-card-teal">
                <div class="kpi-icon kpi-icon-teal"><i class="bi bi-box-seam"></i></div>
                <div class="kpi-label">Valor del Inventario</div>
                <div class="kpi-value" id="kpi-valor-inv">$<?php echo $valor_inventario; ?></div>
            </div>
        </div>
    </section>

    <!-- ACTIVIDAD RECIENTE -->
    <section class="dash-section">
        <h2 class="sec-title"><i class="bi bi-clock-history"></i> Actividad Reciente</h2>
        <div class="tables-grid">
            <?php if ($esAdmin || $esOpVentas): ?>
                <div class="table-card table-card-ventas">
                    <h3>Últimas Notas de Entrega</h3>
                    <p class="card-desc">Ventas registradas más recientes.</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-facturas">
                            <?php if (!empty($ultimas_facturas)): ?>
                                <?php foreach ($ultimas_facturas as $f): ?>
                                    <tr>
                                        <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($f['cliente'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($f['cliente']); ?></td>
                                        <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($f['fecha'], ENT_QUOTES); ?>"><?php echo $f['fecha']; ?></td>
                                        <td class="monto producto-tooltip" data-nombre="$<?php echo $f['total']; ?>">$<?php echo $f['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="vacio">Sin ventas registradas</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($esAdmin || $esOpCarga): ?>
                <div class="table-card table-card-criticos">
                    <h3>Productos Críticos</h3>
                    <p class="card-desc">Stock agotado o bajo el mínimo: reponer o reparar lo antes posible.</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-criticos">
                            <?php if (!empty($tabla_criticos)): ?>
                                <?php foreach ($tabla_criticos as $c): ?>
                                    <tr>
                                        <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($c['producto'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($c['producto']); ?></td>
                                        <td class="producto-tooltip" data-nombre="Stock: <?php echo $c['stock']; ?>"><?php echo $c['stock']; ?></td>
                                        <td class="producto-tooltip" data-nombre="Estado: <?php echo $c['estado'] === 'critico' ? 'Crítico' : 'Bajo'; ?>"><span class="stock-badge <?php echo $c['estado']; ?>"><?php echo $c['estado'] === 'critico' ? 'Crítico' : 'Bajo'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="vacio">Sin productos críticos</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-card table-card-compras">
                    <h3>Últimas Compras</h3>
                    <p class="card-desc">Total de factura, IVA incluido.</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-compras">
                            <?php if (!empty($tabla_compras)): ?>
                                <?php foreach ($tabla_compras as $co): ?>
                                    <tr>
                                        <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($co['proveedor'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($co['proveedor']); ?></td>
                                        <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($co['fecha'], ENT_QUOTES); ?>"><?php echo $co['fecha']; ?></td>
                                        <td class="monto producto-tooltip" data-nombre="$<?php echo $co['total']; ?>">$<?php echo $co['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="vacio">Sin compras registradas</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-card">
                    <h3>Clasificación ABC</h3>
                    <p class="card-desc">Distribución de productos activos por valor de rotación.</p>
                    <div class="d-flex gap-3 flex-wrap" style="padding: 8px 0;">
                        <?php
                        $abc_a = $abc_counts['A'] ?? 0;
                        $abc_b = $abc_counts['B'] ?? 0;
                        $abc_c = $abc_counts['C'] ?? 0;
                        $abc_sin = $abc_counts[''] ?? 0;
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="abc-badge abc-a" style="width:32px;height:32px;font-size:.95rem;">A</span>
                            <div>
                                <div style="font-size:1.3rem;font-weight:800;color:#065F46;"><?php echo $abc_a; ?></div>
                                <div style="font-size:.7rem;color:var(--jv-text-muted);text-transform:uppercase;letter-spacing:.5px;">Alto valor</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="abc-badge abc-b" style="width:32px;height:32px;font-size:.95rem;">B</span>
                            <div>
                                <div style="font-size:1.3rem;font-weight:800;color:#92400E;"><?php echo $abc_b; ?></div>
                                <div style="font-size:.7rem;color:var(--jv-text-muted);text-transform:uppercase;letter-spacing:.5px;">Valor medio</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="abc-badge abc-c" style="width:32px;height:32px;font-size:.95rem;">C</span>
                            <div>
                                <div style="font-size:1.3rem;font-weight:800;color:#DC2626;"><?php echo $abc_c; ?></div>
                                <div style="font-size:.7rem;color:var(--jv-text-muted);text-transform:uppercase;letter-spacing:.5px;">Valor bajo</div>
                            </div>
                        </div>
                        <?php if ($abc_sin > 0): ?>
                        <div class="d-flex align-items-center gap-2">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;font-size:.85rem;font-weight:900;border:2px solid #CBD5E1;color:#94A3B8;background:rgba(148,163,184,0.1);">&mdash;</span>
                            <div>
                                <div style="font-size:1.3rem;font-weight:800;color:var(--jv-text-muted);"><?php echo $abc_sin; ?></div>
                                <div style="font-size:.7rem;color:var(--jv-text-muted);text-transform:uppercase;letter-spacing:.5px;">Sin clasificar</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-card">
                    <h3>Tipos de Manejo</h3>
                    <p class="card-desc">Productos activos clasificados por tipo de almacenamiento.</p>
                    <div class="d-flex gap-3 flex-wrap" style="padding: 8px 0;">
                        <?php
                        $manejo_labels = [
                            'normal' => 'Normal',
                            'inflamable' => 'Inflamable',
                            'liquido' => 'Líquido',
                            'peligroso' => 'Peligroso',
                            'voluminoso' => 'Voluminoso',
                            'aerosol' => 'Aerosol',
                        ];
                        foreach ($manejo_labels as $tipo => $label):
                            $count = $manejo_counts[$tipo] ?? 0;
                            if ($count > 0):
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="manejo-badge manejo-<?php echo $tipo; ?>"><?php echo $label; ?></span>
                            <span style="font-size:1.2rem;font-weight:800;color:var(--jv-text-primary);"><?php echo $count; ?></span>
                        </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>