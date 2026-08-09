<?php
// ==========================================
// VISTA: Nota de Entrega / Salida (imprimible)
// ==========================================
// HTML autónomo (sin layout): se renderiza con renderRaw().
// Recibe $data, $detalles y todos los valores derivados del modelo.
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Entrega #<?php echo $data['id_salida'] ?? 'PREVIEW'; ?> | <?php echo htmlspecialchars($empresa); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>assets/modules/preview_factura/preview_factura.css?v=4">
</head>

<body>
    <div class="page">
        <div class="doc-header">
            <div class="doc-left">
                <img class="doc-logo" src="<?php echo BASE_PATH; ?>assets/img/logo-mark.svg?v=1" alt="JV3000">
                <div class="doc-issuer">
                    <div class="issuer-name"><?php echo htmlspecialchars($empresa); ?></div>
                    <p>RIF: <?php echo htmlspecialchars($rif_emp); ?></p>
                    <p><?php echo htmlspecialchars($dir_emp ?: '&nbsp;'); ?></p>
                    <p>TLF: <?php echo htmlspecialchars($tel_emp ?: '&nbsp;'); ?></p>
                    <p>Correo: <?php echo htmlspecialchars($email_emp ?: '&nbsp;'); ?></p>
                </div>
            </div>
            <div class="doc-type">
                <h1><?php echo $es_merma ? 'NOTA DE SALIDA' : 'NOTA DE ENTREGA'; ?></h1>
                <div class="type-sub"><?php echo $es_merma ? 'Ajuste de inventario' : 'Oficial'; ?></div>
            </div>
        </div>

        <div class="num-row">
            <span class="num-item"><strong>N° N/ENTREGA:</strong> <?php echo htmlspecialchars($data['nro_factura_manual'] ?? 'PENDIENTE'); ?></span>
            <span class="num-item"><strong>N° CONTROL:</strong> <?php echo htmlspecialchars($data['nro_control'] ?? '—'); ?></span>
            <span class="num-item"><strong>FECHA:</strong> <?php echo date('d/m/Y', strtotime($data['fecha_salida'])); ?></span>
            <span class="num-item"><strong>HORA:</strong> <?php echo $hora_actual; ?></span>
        </div>

        <?php foreach ($alertas_venc as $av): ?>
            <div style="padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:.75rem;font-weight:600;text-align:center;<?php echo $av['tipo'] === 'vencido' ? 'background:rgba(220,38,38,0.1);color:#DC2626;border:1px solid rgba(220,38,38,0.3);' : 'background:rgba(217,119,6,0.1);color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);'; ?>">
                <?php echo $av['tipo'] === 'vencido' ? '⚠ VENCIDO' : '⚠ PRÓXIMO A VENCER'; ?>
                — <?php echo htmlspecialchars($av['producto']); ?> (<?php echo date('d/m/Y', strtotime($av['fecha'])); ?>)
            </div>
        <?php endforeach; ?>

        <?php if (!$es_merma): ?>
            <div class="info-grid">
                <div class="info-box">
                    <label>Cliente</label>
                    <div class="value"><?php echo htmlspecialchars($data['cliente'] ?? 'CONSUMIDOR FINAL'); ?></div>
                </div>
                <div class="info-box">
                    <label>RIF / Cédula</label>
                    <div class="value"><?php echo htmlspecialchars($data['rif_cliente'] ?? 'SIN IDENTIFICACIÓN'); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width:8%;">Cant.</th>
                    <th style="width:52%;">Descripción</th>
                    <th style="width:18%;">P. Unit.</th>
                    <th style="width:22%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $det): ?>
                    <?php $fila_total = $det['cantidad'] * $det['precio_venta']; ?>
                    <tr>
                        <td><?php echo $det['cantidad']; ?></td>
                        <td><strong class="pv-nombre" data-tooltip="<?php echo htmlspecialchars($det['nombre_producto'] ?? ''); ?>"><?php echo htmlspecialchars($det['nombre_producto'] ?? ''); ?></strong><br><span style="font-size:.85rem;color:#6C757D;">SKU: <?php echo htmlspecialchars($det['sku'] ?? ''); ?></span></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#6C757D;">$ <?php echo number_format($det['precio_original'], 2); ?></span> <span style="color:#198754;font-weight:700;">GRATIS</span><?php else: ?>$ <?php echo number_format($det['precio_venta'], 2); ?><?php endif; ?></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#6C757D;">$ <?php echo number_format($det['cantidad'] * $det['precio_original'], 2); ?></span> <span style="color:#198754;font-weight:700;">$ 0.00</span><?php else: ?>$ <?php echo number_format($fila_total, 2); ?><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$es_merma): ?>
            <div class="totals">
                <div class="row"><span class="label">Base Imponible</span><span>$ <?php echo number_format($subtotal, 2); ?></span></div>
                <?php if ($es_venta): ?>
                    <div class="row iva"><span class="label">I.V.A. (<?php echo $iva_pct; ?>%)</span><span>$ <?php echo number_format($iva, 2); ?></span></div>
                <?php else: ?>
                    <div class="row iva"><span class="label">I.V.A.</span><span>EXENTO</span></div>
                <?php endif; ?>
                <div class="row total"><span>MONTO TOTAL</span><span>$ <?php echo number_format($total_neto, 2); ?></span></div>
            </div>
        <?php else: ?>
            <div class="totals">
                <div class="row total" style="border:none;color:#6C757D;"><span>VALOR</span><span>$ 0.00</span></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($data['observaciones'])): ?>
            <div class="obs-box">
                <label>Observaciones</label>
                <p><?php echo nl2br(htmlspecialchars($data['observaciones'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$es_merma): ?>
            <div class="signatures">
                <div class="sig">
                    <p>Entregado por</p>
                    <div class="line">Firma del Vendedor</div>
                </div>
                <div class="sig">
                    <p>Recibido por</p>
                    <div class="line">Firma del Cliente</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="buttons">
            <button class="btn btn-outline" onclick="window.close()">← VOLVER</button>
            <?php if ($preview_token !== ''): ?>
                <form action="<?php echo BASE_PATH; ?>index.php?url=salidas/confirm&token=<?php echo urlencode($preview_token); ?>" method="POST" onsubmit="return confirmarRegistro(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button type="submit" class="btn btn-primary" id="btnConfirmar">✓ CONFIRMAR Y REGISTRAR</button>
                </form>
            <?php else: ?>
                <button class="btn btn-primary" onclick="window.print()">🖨 IMPRIMIR</button>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo BASE_PATH; ?>assets/modules/preview_factura/preview_factura.js?v=4"></script>
</body>

</html>
