<?php
/**
 * Panel compartido de alertas críticas (campana del Panel de Inicio).
 *
 * Se incluye desde el dashboard raíz y desde la vista enrutada para evitar
 * duplicar el marcado. Tolera arreglos parciales de alertas (por ejemplo,
 * solo `total`) usando valores por defecto.
 *
 * @var array $alertas
 * @var string|null $csrf
 * @var bool|null $esAdmin
 * @var bool|null $esOpCarga
 */

$alertas = $alertas ?? [];
$count_vencidos = (int)($alertas['counts']['vencidos'] ?? 0);
$count_bajos = (int)($alertas['counts']['bajos'] ?? 0);
$total = (int)($alertas['total'] ?? ($count_vencidos + $count_bajos));
$vencidos = $alertas['vencidos'] ?? [];
$bajos = $alertas['bajos'] ?? [];
$puede_baja = (bool)($alertas['puede_baja'] ?? ($esAdmin ?? false));
$puede_reponer = (bool)($alertas['puede_reponer'] ?? (($esAdmin ?? false) || ($esOpCarga ?? false)));
$csrf_panel = $csrf ?? (class_exists('Security') ? Security::generateToken() : '');
$base = defined('BASE_PATH') ? BASE_PATH : '/';
?>
<div class="dash-bell-panel" id="dashBellPanel" data-csrf="<?php echo htmlspecialchars((string)$csrf_panel); ?>" data-puede-baja="<?php echo $puede_baja ? '1' : '0'; ?>" data-puede-reponer="<?php echo $puede_reponer ? '1' : '0'; ?>" aria-label="Alertas críticas">
    <div class="dash-bell-head">ALERTAS CRÍTICAS</div>
    <div class="dash-bell-body">
        <section class="dash-bell-sec dash-bell-ven">
            <div class="dash-bell-sec-titulo">
                <span><i class="bi bi-calendar-x-fill" aria-hidden="true"></i> VENCIDOS (<?php echo $count_vencidos; ?>)</span>
                <?php if ($count_vencidos > 0): ?>
                    <a href="<?php echo $base; ?>index.php?url=productos&alerta=vencidos" class="dash-bell-ver">Ver todos</a>
                <?php endif; ?>
            </div>
            <?php if ($count_vencidos === 0): ?>
                <p class="dash-bell-sec-vacio">No hay productos vencidos</p>
            <?php else: ?>
                <?php foreach ($vencidos as $it): ?>
                    <div class="dash-bell-item">
                        <i class="bi bi-calendar-x-fill" aria-hidden="true"></i>
                        <div class="dash-bell-item-copy">
                            <a class="dash-bell-item-nombre" href="<?php echo $base; ?>index.php?url=productos&producto=<?php echo (int)$it['id']; ?>"><?php echo htmlspecialchars((string)$it['nombre']); ?></a>
                            <span class="dash-bell-item-meta dash-bell-meta-venc">Vence: <?php echo htmlspecialchars((string)$it['fecha']); ?></span>
                        </div>
                        <?php if ($puede_baja): ?>
                            <button type="button" class="dash-bell-accion dash-bell-accion-danger" data-baja-id="<?php echo (int)$it['id']; ?>" data-baja-nombre="<?php echo htmlspecialchars((string)$it['nombre']); ?>">Dar de baja</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="dash-bell-sec dash-bell-bajo">
            <div class="dash-bell-sec-titulo">
                <span><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> STOCK BAJO (<?php echo $count_bajos; ?>)</span>
                <?php if ($count_bajos > 0): ?>
                    <a href="<?php echo $base; ?>index.php?url=productos&alerta=bajos" class="dash-bell-ver">Ver todos</a>
                <?php endif; ?>
            </div>
            <?php if ($count_bajos === 0): ?>
                <p class="dash-bell-sec-vacio">No hay productos con stock bajo</p>
            <?php else: ?>
                <?php foreach ($bajos as $it): ?>
                    <?php
                    $en_camino = (int)($it['en_camino'] ?? 0);
                    $id_compra_pendiente = (int)($it['id_compra'] ?? 0);
                    ?>
                    <div class="dash-bell-item">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div class="dash-bell-item-copy">
                            <a class="dash-bell-item-nombre" href="<?php echo $base; ?>index.php?url=productos&producto=<?php echo (int)$it['id']; ?>"><?php echo htmlspecialchars((string)$it['nombre']); ?></a>
                            <span class="dash-bell-item-meta dash-bell-meta-bajo">Quedan <?php echo (int)$it['stock']; ?> <span class="dash-bell-min">(mín <?php echo (int)$it['minimo']; ?>)</span><?php if ($en_camino > 0): ?> · <span class="dash-bell-camino">En camino <?php echo $en_camino; ?></span><?php endif; ?></span>
                        </div>
                        <?php if ($puede_reponer): ?>
                            <?php
                            $reponer_url = $base . 'index.php?url=compras';
                            if ($id_compra_pendiente > 0) {
                                $reponer_url .= '&recepcion_compra=' . $id_compra_pendiente;
                            } elseif ((int)($it['id_proveedor'] ?? 0) > 0) {
                                $reponer_url .= '&reponer_producto=' . (int)$it['id'] . '&reponer_proveedor=' . (int)$it['id_proveedor'];
                            }
                            ?>
                            <a class="dash-bell-accion dash-bell-accion-warn" href="<?php echo $reponer_url; ?>"><?php echo $en_camino > 0 ? 'Recibir' : 'Reponer'; ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
