<?php

// ==========================================
// MODELO: Compra
// ==========================================
// Única capa que consulta la base de datos.
// Registro de facturas de proveedores, anulación,
// consultas del tablero y prefill de solicitudes.
// (El movimiento de stock/lotes NO ocurre aquí:
//  lo gestiona el módulo Recepción.)
class Compra extends Model
{
    // Facturas activas para el tablero (con filtros opcionales)
    public function obtenerCompras(int $filtro_proveedor = 0, string $filtro_pago = ''): array
    {
        $sql = "
            SELECT c.*,
                   GROUP_CONCAT(DISTINCT p.nombre_producto SEPARATOR ', ') as productos_list,
                   SUM(dc.cantidad) as total_cantidad,
                   COUNT(dc.id_detalle) as num_productos,
                   pr.nombre_empresa as proveedor
            FROM compras c
            LEFT JOIN detalle_compras dc ON c.id_compra = dc.id_compra
            LEFT JOIN productos p ON dc.id_producto = p.id_producto
            LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
            WHERE c.status = 'Activa'
        ";
        $params = [];
        if ($filtro_proveedor > 0) {
            $sql .= " AND c.id_proveedor = ?";
            $params[] = $filtro_proveedor;
        }
        if ($filtro_pago !== '') {
            $sql .= " AND c.status_pago = ?";
            $params[] = $filtro_pago;
        }
        $sql .= " GROUP BY c.id_compra ORDER BY c.fecha_compra DESC, c.id_compra DESC LIMIT 100";
        return $this->db->fetchAll($sql, $params);
    }

    // Proveedores activos para el formulario
    public function obtenerProveedores(): array
    {
        return $this->db->fetchAll(
            "SELECT id_proveedor, nombre_empresa, rif, condiciones_pago, dias_credito, limite_credito
             FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa"
        );
    }

    // Crédito consumido por proveedor (solo compras a crédito activas)
    public function creditoUsadoPorProveedor(): array
    {
        $usado = [];
        $rows = $this->db->fetchAll(
            "SELECT id_proveedor, COALESCE(SUM(total),0) as usado
             FROM compras WHERE status = 'Activa' AND condiciones_pago = 'Credito' AND id_proveedor IS NOT NULL
             GROUP BY id_proveedor"
        );
        foreach ($rows as $r) {
            $usado[(int)$r['id_proveedor']] = (float)$r['usado'];
        }
        return $usado;
    }

    // KPIs de la cabecera
    public function kpis(): array
    {
        $total = (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa'")['t'] ?? 0);
        $por_pagar = (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa' AND status_pago = 'Pendiente'")['t'] ?? 0);
        $mes = $this->db->fetchOne(
            "SELECT COALESCE(SUM(c.total),0) as t FROM compras c
             WHERE c.fecha_compra >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
               AND c.fecha_compra < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01')
               AND c.status = 'Activa'"
        );

        return [
            'total_compras' => $total,
            'por_pagar'     => $por_pagar,
            'invertido_mes' => (float)($mes['t'] ?? 0),
        ];
    }

    // Prefill de una solicitud de compra pendiente (para atenderla)
    // @return array|null ['id_solicitud', 'motivo', 'items']
    public function prefillSolicitud(int $id_solicitud): ?array
    {
        $sol = $this->db->fetchOne("SELECT id_solicitud, motivo, estado FROM solicitudes_compra WHERE id_solicitud = ?", [$id_solicitud]);
        if (!$sol || $sol['estado'] !== 'Pendiente') return null;

        $det = $this->db->fetchAll(
            "SELECT d.id_producto, d.cantidad_solicitada, p.sku, p.nombre_producto, p.precio_costo
             FROM detalle_solicitud_compra d
             JOIN productos p ON d.id_producto = p.id_producto
             WHERE d.id_solicitud = ?",
            [$id_solicitud]
        );
        if (empty($det)) return null;

        return [
            'id_solicitud' => (int)$sol['id_solicitud'],
            'motivo'       => $sol['motivo'],
            'items'        => array_map(function ($d) {
                return [
                    'id'       => (int)$d['id_producto'],
                    'nombre'   => $d['nombre_producto'],
                    'cantidad' => (int)$d['cantidad_solicitada'],
                    'precio'   => (float)$d['precio_costo'],
                ];
            }, $det),
        ];
    }

    // Registrar compra (factura del proveedor + comprobante de pago).
    // Respeta el flujo original: NO mueve stock ni crea lotes aquí
    // (eso lo hace Recepción). La mercancía se recibe después.
    // @return array ['ok'=>bool, 'mensaje'=>string]
    public function registrar(array $post, int $id_usuario): array
    {
        $id_proveedor = intval($post['id_proveedor'] ?? 0);
        if ($id_proveedor <= 0) {
            return ['ok' => false, 'mensaje' => 'SELECCIONE UN PROVEEDOR.'];
        }
        $id_solicitud = intval($post['id_solicitud'] ?? 0);

        // Número de factura: manual, única por proveedor
        $nro_factura = trim($post['nro_factura'] ?? '');
        if ($nro_factura === '') {
            return ['ok' => false, 'mensaje' => 'EL NÚMERO DE FACTURA ES OBLIGATORIO.'];
        }
        if ($this->db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = ? AND status = 'Activa'", [$id_proveedor, $nro_factura])) {
            return ['ok' => false, 'mensaje' => 'ESA FACTURA YA ESTÁ REGISTRADA PARA EL PROVEEDOR.'];
        }

        // Número de control: manual y opcional
        $nro_control = trim($post['nro_control'] ?? '');
        if ($nro_control !== '' && !preg_match('/^\d{2}-\d{8}$/', $nro_control)) {
            return ['ok' => false, 'mensaje' => 'NRO. CONTROL INVÁLIDO. Formato: 00-00000000'];
        }

        $observaciones = trim($post['observaciones'] ?? '');

        $prov = $this->db->fetchOne("SELECT condiciones_pago, dias_credito, limite_credito, rif FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);
        if (!$prov) {
            return ['ok' => false, 'mensaje' => 'PROVEEDOR INVÁLIDO.'];
        }
        if (!validarRIF(normalizarDocumento($prov['rif'] ?? ''))) {
            return ['ok' => false, 'mensaje' => 'EL PROVEEDOR SELECCIONADO NO TIENE UN RIF VÁLIDO. CORRÍJALO EN PROVEEDORES.'];
        }
        $condiciones_pago = $prov['condiciones_pago'] ?? 'Contado';
        $dias_credito = intval($prov['dias_credito'] ?? 0);

        // Ítems de la factura
        $productos_raw = json_decode($post['productos_data'] ?? '[]', true);
        $productos = is_array($productos_raw) ? $productos_raw : [];
        if (count($productos) > 200) {
            return ['ok' => false, 'mensaje' => 'MÁXIMO 200 PRODUCTOS POR FACTURA.'];
        }

        $iva_pct = (float)getConfig('iva_porcentaje', '16');
        $items_validos = [];
        $subtotal = 0;
        foreach ($productos as $prod) {
            $id_producto = intval($prod['id'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            $precio_costo = round((float)($prod['precio'] ?? 0), 2);
            if ($cantidad < 1 || $cantidad > 999999) {
                return ['ok' => false, 'mensaje' => 'CANTIDAD INVÁLIDA POR PRODUCTO. RANGO: 1 A 999,999.'];
            }
            if ($precio_costo < 0 || $precio_costo > 99999999.99) {
                return ['ok' => false, 'mensaje' => "PRECIO DE COSTO INVÁLIDO PARA PRODUCTO #$id_producto. RANGO: 0 A 99,999,999.99."];
            }
            if ($id_producto <= 0) continue;
            if (!$this->db->fetchOne("SELECT id_producto FROM productos WHERE id_producto = ?", [$id_producto])) continue;
            $lote_venc = null;
            if (!empty($prod['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($prod['fecha_vencimiento']))) {
                $lote_venc = trim($prod['fecha_vencimiento']);
            }
            $items_validos[] = ['id' => $id_producto, 'cantidad' => $cantidad, 'precio' => $precio_costo, 'fecha_vencimiento' => $lote_venc];
            $subtotal += $cantidad * $precio_costo;
        }
        if (empty($items_validos)) {
            return ['ok' => false, 'mensaje' => 'DEBE AGREGAR AL MENOS UN PRODUCTO VÁLIDO.'];
        }

        $subtotal = round($subtotal, 2);
        $iva = round($subtotal * $iva_pct / 100, 2);
        $total = round($subtotal + $iva, 2);

        // Validar límite de crédito
        if ($condiciones_pago === 'Credito') {
            $limite = (float)($prov['limite_credito'] ?? 0);
            if ($limite > 0) {
                $usado = (float)($this->db->fetchOne(
                    "SELECT COALESCE(SUM(total),0) as t FROM compras WHERE id_proveedor = ? AND status = 'Activa' AND condiciones_pago = 'Credito'",
                    [$id_proveedor]
                )['t'] ?? 0);
                if (($usado + $total) > $limite) {
                    $disponible = $limite - $usado;
                    return ['ok' => false, 'mensaje' => "CRÉDITO INSUFICIENTE. Límite: \$" . number_format($limite, 2) . ", usado: \$" . number_format($usado, 2) . ", disponible: \$" . number_format(max(0, $disponible), 2) . "."];
                }
            }
        }

        // Comprobante de pago
        $metodo_pago = in_array(trim($post['metodo_pago'] ?? ''), ['Efectivo', 'Transferencia', 'Cheque', 'Otro'], true) ? trim($post['metodo_pago']) : 'Efectivo';
        $monto_pago = round((float)($post['monto_pago'] ?? 0), 2);
        if ($monto_pago < 0) $monto_pago = 0;
        if ($monto_pago > 99999999.99) {
            return ['ok' => false, 'mensaje' => 'MONTO DE PAGO INVÁLIDO. MÁXIMO 99,999,999.99.'];
        }
        $status_pago = $monto_pago >= $total ? 'Pagada' : 'Pendiente';
        $fecha_compra = date('Y-m-d H:i:s');
        $fecha_pago = date('Y-m-d H:i:s');

        $this->db->begin();
        try {
            $compra_id = $this->db->insert('compras', [
                'nro_factura'      => $nro_factura,
                'id_proveedor'     => $id_proveedor,
                'id_usuario'       => $id_usuario,
                'fecha_compra'     => $fecha_compra,
                'nro_control'      => $nro_control !== '' ? $nro_control : null,
                'condiciones_pago' => $condiciones_pago,
                'dias_plazo'       => $dias_credito,
                'subtotal'         => $subtotal,
                'iva'              => $iva,
                'total'            => $total,
                'status'           => 'Activa',
                'tipo_entrada'     => 'Compra a proveedor',
                'observaciones'    => $observaciones,
                'status_pago'      => $status_pago,
                'monto_pago'       => $monto_pago,
                'fecha_pago'       => $fecha_pago,
                'metodo_pago'      => $metodo_pago,
                'estado_recepcion' => 'Pendiente',
            ]);

            foreach ($items_validos as $it) {
                $this->db->insert('detalle_compras', [
                    'id_compra'         => $compra_id,
                    'id_producto'       => $it['id'],
                    'cantidad'          => $it['cantidad'],
                    'precio_costo'      => $it['precio'],
                    'cantidad_recibida' => 0,
                    'fecha_vencimiento' => $it['fecha_vencimiento'],
                ]);
            }

            registrarAuditoria('crear', "Compra registrada (factura $nro_factura, " . count($items_validos) . " producto(s))");

            if ($id_solicitud > 0) {
                $this->db->execute(
                    "UPDATE solicitudes_compra SET estado = 'Atendida', id_compra = ?, fecha_atendida = NOW() WHERE id_solicitud = ? AND estado = 'Pendiente'",
                    [$compra_id, $id_solicitud]
                );
            }

            $this->db->commit();

            $msg = "COMPRA REGISTRADA: factura $nro_factura, " . count($items_validos) . " producto(s). ";
            $msg .= $status_pago === 'Pagada' ? 'COMPROBANTE DE PAGO REGISTRADO.' : 'PAGO PENDIENTE (' . number_format($monto_pago, 2) . ' DE ' . number_format($total, 2) . ').';
            return ['ok' => true, 'mensaje' => $msg];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR AL REGISTRAR LA COMPRA. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
        }
    }

    // Anular compra (solo si no hay mercancía recibida en inventario)
    // @return array ['ok'=>bool, 'mensaje'=>string]
    public function anular(int $id_compra): array
    {
        $compra = $this->db->fetchOne("SELECT nro_factura, status FROM compras WHERE id_compra = ?", [$id_compra]);
        if (!$compra || $compra['status'] === 'Anulada') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE O YA FUE ANULADA.'];
        }
        $recibida = (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM lotes WHERE id_compra = ?", [$id_compra])['n'] ?? 0);
        if ($recibida > 0) {
            return ['ok' => false, 'mensaje' => 'NO SE PUEDE ANULAR: la compra ya tiene mercancía recibida en inventario.'];
        }
        $this->db->execute("UPDATE compras SET status = 'Anulada' WHERE id_compra = ?", [$id_compra]);
        registrarAuditoria('anular', "Compra #$id_compra (factura {$compra['nro_factura']}) anulada");
        return ['ok' => true, 'mensaje' => 'COMPRA ANULADA.'];
    }

    // Marcar compra como pagada
    // @return array ['ok'=>bool, 'mensaje'=>string]
    public function pagar(int $id_compra): array
    {
        $compra = $this->db->fetchOne("SELECT status, status_pago, total FROM compras WHERE id_compra = ?", [$id_compra]);
        if (!$compra || $compra['status'] === 'Anulada') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE O ESTÁ ANULADA.'];
        }
        if ($compra['status_pago'] === 'Pagada') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA YA ESTÁ PAGADA.'];
        }
        $this->db->execute("UPDATE compras SET status_pago = 'Pagada', monto_pago = total, fecha_pago = NOW() WHERE id_compra = ?", [$id_compra]);
        registrarAuditoria('pagar', "Compra #$id_compra marcada como pagada");
        return ['ok' => true, 'mensaje' => 'COMPRA MARCADA COMO PAGADA.'];
    }
}
