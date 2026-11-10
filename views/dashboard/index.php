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
 * @var float $ventas_dia
 * @var float $valor_inventario
 * @var float|null $tasa_bcv
 * @var array $ultimas_facturas
 * @var array $tabla_criticos
 * @var array $tabla_compras
 * @var string $csrf
 * @var array $css_extra
 * @var array $js_extra
 */
$agruparActividadPorFecha = static function (array $items): array {
    $hoy = new DateTimeImmutable('today');
    $inicio_semana = $hoy->modify('monday this week');
    $grupos = [
        'hoy' => ['titulo' => 'HOY', 'items' => []],
        'semana' => ['titulo' => 'ESTA SEMANA', 'items' => []],
        'anteriores' => ['titulo' => 'ANTERIORES', 'items' => []],
    ];

    foreach ($items as $item) {
        $fecha_item = DateTimeImmutable::createFromFormat('!d/m/Y', (string)($item['fecha'] ?? ''));
        $grupo = 'anteriores';
        if ($fecha_item !== false) {
            if ($fecha_item->format('Y-m-d') === $hoy->format('Y-m-d')) {
                $grupo = 'hoy';
            } elseif ($fecha_item >= $inicio_semana && $fecha_item < $hoy) {
                $grupo = 'semana';
            }
        }
        $grupos[$grupo]['items'][] = $item;
    }

    return array_filter($grupos, static fn(array $grupo): bool => $grupo['items'] !== []);
};

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
            <?php include __DIR__ . '/_campana_alertas.php'; ?>
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
                <div class="kpi-icon kpi-icon-verde"><i class="bi bi-cash-coin"></i></div>
                <div class="kpi-label">Última Venta</div>
                <div class="kpi-value" id="kpi-ventas-dia"><?php echo format_bs((float)$ventas_dia, $tasa_bcv ?? null); ?></div>
            </div>
            <div class="kpi-card kpi-card-teal">
                <div class="kpi-icon kpi-icon-teal"><i class="bi bi-box-seam"></i></div>
                <div class="kpi-label">Valor del Inventario</div>
                <div class="kpi-value" id="kpi-valor-inv"><?php echo format_bs((float)$valor_inventario, $tasa_bcv ?? null); ?></div>
            </div>
        </div>
    </section>

    <!-- ACTIVIDAD RECIENTE -->
    <section class="dash-section">
        <h2 class="sec-title"><i class="bi bi-clock-history"></i> Actividad Reciente</h2>
        <div class="row g-4 bg-transparent">
            <?php if ($esAdmin || $esOpVentas): ?>
                <div class="col-12 col-xl-4">
                    <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-ventas">
                        <div class="card-header dashboard-activity-header">
                            <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>Últimas Notas de Entrega</h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list" id="lista-facturas">
                                <?php if (!empty($ultimas_facturas)): ?>
                                    <?php foreach ($agruparActividadPorFecha($ultimas_facturas) as $grupo): ?>
                                        <section class="activity-date-group">
                                            <h4 class="activity-date-heading"><?php echo $grupo['titulo']; ?></h4>
                                            <?php foreach ($grupo['items'] as $f): ?>
                                                <div class="activity-item activity-financial-item">
                                                    <span class="activity-status-dot activity-status-dot-venta" aria-hidden="true"></span>
                                                    <div class="activity-item-copy">
                                                        <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$f['cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="activity-financial-date">Fecha: <?php echo htmlspecialchars((string)$f['fecha'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                    <div class="activity-total fs-5 fw-bold text-success"><?php echo htmlspecialchars(format_bs((float)$f['total'], $tasa_bcv ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </section>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="mb-0 text-secondary">Sin ventas registradas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endif; ?>

            <?php if ($esAdmin || $esOpCarga): ?>
                <div class="col-12 col-xl-4">
                    <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-criticos">
                        <div class="card-header dashboard-activity-header">
                            <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>Productos Críticos</h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list" id="lista-criticos">
                                <?php if (!empty($tabla_criticos)): ?>
                                    <?php foreach ($tabla_criticos as $c): ?>
                                        <?php $stock = max(0, (int)$c['stock']); ?>
                                        <div class="activity-item">
                                            <span class="activity-status-dot <?php echo $stock === 0 ? 'activity-status-dot-stock-vacio' : 'activity-status-dot-critico'; ?>" aria-hidden="true"></span>
                                            <div class="activity-item-copy">
                                                <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$c['producto'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                                    <span class="activity-stock-label">Stock actual: <strong class="<?php echo $stock === 0 ? 'text-danger' : 'text-secondary'; ?>"><?php echo $stock; ?></strong></span>
                                                    <span class="badge bg-danger"><?php echo $stock === 0 ? 'Agotado' : 'Crítico'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="mb-0 text-secondary">Sin productos críticos.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-xl-4">
                    <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-compras">
                        <div class="card-header dashboard-activity-header">
                            <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>Últimas Compras</h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list" id="lista-compras">
                                <?php if (!empty($tabla_compras)): ?>
                                    <?php foreach ($agruparActividadPorFecha($tabla_compras) as $grupo): ?>
                                        <section class="activity-date-group">
                                            <h4 class="activity-date-heading"><?php echo $grupo['titulo']; ?></h4>
                                            <?php foreach ($grupo['items'] as $co): ?>
                                                <div class="activity-item activity-financial-item">
                                                    <span class="activity-status-dot activity-status-dot-compra" aria-hidden="true"></span>
                                                    <div class="activity-item-copy">
                                                        <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$co['proveedor'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="activity-financial-date">Fecha: <?php echo htmlspecialchars((string)$co['fecha'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                    <div class="activity-total fs-5 fw-bold text-success"><?php echo htmlspecialchars(format_bs((float)$co['total'], $tasa_bcv ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </section>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="mb-0 text-secondary">Sin compras registradas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </section>