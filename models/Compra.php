<?php

// ==========================================
// MODELO: Compra
// ==========================================
// Única capa que consulta la base de datos.
// Registro de facturas de proveedores, anulación,
// consultas del tablero y prefill de solicitudes.
// (El movimiento de stock/lotes NO ocurre aquí:
//  lo gestiona el módulo Recepción.)

/**
 * Compra: modelo del módulo de compras (facturas de proveedores).
 *
 * Única capa autorizada para consultar la base de datos. Maneja el registro
 * de facturas, la anulación, el marcado como pagada, las consultas del
 * tablero (filtros y KPIs) y el prefill de solicitudes.
 * NOTA: el movimiento de stock/lotes NO ocurre aquí; lo gestiona Recepción.
 */
class Compra extends Model
{
    /**
     * Facturas activas para el tablero, con filtros opcionales.
     *
     * Agrupa cada compra con el resumen de sus productos (lista, cantidad,
     * número de ítems) y el proveedor. Aplica filtro por proveedor y por
     * estado de pago. Limitado a las 100 facturas más recientes.
     *
     * @param int    $filtro_proveedor Id del proveedor (0 = sin filtro).
     * @param string $filtro_pago      Estado de pago ('Pendiente'/'Pagada' o '').
     * @return array Facturas activas con resumen.
     */
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

    /**
     * Proveedores activos para el formulario de compra.
     *
     * Devuelve id, nombre y RIF de los proveedores activos, ordenados
     * alfabéticamente, para el selector del formulario.
     *
     * @return array Lista de proveedores activos.
     */
    public function obtenerProveedores(): array
    {
        return $this->db->fetchAll(
            "SELECT id_proveedor, nombre_empresa, rif
             FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa"
        );
    }

    /**
     * Mapa de costos del catálogo de proveedores [id_proveedor][id_producto].
     *
     * Lo usa la vista de compras para autocompletar el costo de cada línea
     * según el proveedor seleccionado. Si el proveedor no tiene el producto
     * en su catálogo, el costo se ingresa a mano.
     *
     * @return array Mapa anidado con el costo por combinación.
     */
    public function mapaCostosCatalogo(): array
    {
        $mapa = [];
        foreach ($this->db->fetchAll("SELECT id_proveedor, id_producto, costo FROM catalogo_costos") as $fila) {
            $mapa[(int)$fila['id_proveedor']][(int)$fila['id_producto']] = (float)$fila['costo'];
        }
        return $mapa;
    }

    /**
     * KPIs de la cabecera del tablero de compras.
     *
     * Calcula el total de compras activas, las pendientes de pago y el
     * monto invertido en el mes en curso.
     *
     * @return array ['total_compras'=>int, 'por_pagar'=>int, 'invertido_mes'=>float].
     */
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

    /**
     * Prefill de una solicitud de compra pendiente (para atenderla).
     *
     * Devuelve los datos de la solicitud (motivo e ítems con sku, nombre,
     * cantidad y precio de costo) si existe y está Pendiente. Retorna null
     * si no existe, no está pendiente o no tiene ítems.
     *
     * @param int $id_solicitud Identificador de la solicitud.
     * @return array|null ['id_solicitud'=>int, 'motivo'=>string, 'items'=>array].
     */
    public function prefillSolicitud(int $id_solicitud): ?array
    {
        $purchaseRequest = $this->db->fetchOne("SELECT id_solicitud, motivo, estado FROM solicitudes_compra WHERE id_solicitud = ?", [$id_solicitud]);
        if (!$purchaseRequest || $purchaseRequest['estado'] !== 'Pendiente') return null;

        $requestDetails = $this->db->fetchAll(
            "SELECT d.id_producto, d.cantidad_solicitada, p.sku, p.nombre_producto, p.precio_costo
             FROM detalle_solicitud_compra d
             JOIN productos p ON d.id_producto = p.id_producto
             WHERE d.id_solicitud = ?",
            [$id_solicitud]
        );
        if (empty($requestDetails)) return null;

        return [
            'id_solicitud' => (int)$purchaseRequest['id_solicitud'],
            'motivo'       => $purchaseRequest['motivo'],
            'items'        => array_map(function ($requestDetail) {
                return [
                    'id'       => (int)$requestDetail['id_producto'],
                    'nombre'   => $requestDetail['nombre_producto'],
                    'cantidad' => (int)$requestDetail['cantidad_solicitada'],
                    'precio'   => (float)$requestDetail['precio_costo'],
                ];
            }, $requestDetails),
        ];
    }

    /**
     * Registra una compra (factura del proveedor + comprobante de pago).
     *
     * Valida proveedor (activo, RIF válido), número de factura único por
     * proveedor, formato de nro. de control, ítems (cantidad, precio y
     * vencimiento) y monto de pago. Inserta la compra con sus detalles
     * dentro de una transacción y, si se atiende una solicitud, la marca
     * Atendida.
     * Respeta el flujo original: NO mueve stock ni crea lotes aquí (eso lo
     * hace Recepción).
     *
     * @param array $purchaseFormData Datos del formulario (proveedor, ítems, pagos...).
     * @param int   $id_usuario Usuario que registra la compra.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function registrar(array $purchaseFormData, int $id_usuario): array
    {
        $id_proveedor = intval($purchaseFormData['id_proveedor'] ?? 0);
        if ($id_proveedor <= 0) {
            return ['ok' => false, 'mensaje' => 'SELECCIONE UN PROVEEDOR.'];
        }
        $id_solicitud = intval($purchaseFormData['id_solicitud'] ?? 0);

        // Número de factura: manual, única por proveedor
        $nro_factura = trim($purchaseFormData['nro_factura'] ?? '');
        if ($nro_factura === '') {
            return ['ok' => false, 'mensaje' => 'EL NÚMERO DE FACTURA ES OBLIGATORIO.'];
        }
        if ($this->db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = ? AND status = 'Activa'", [$id_proveedor, $nro_factura])) {
            return ['ok' => false, 'mensaje' => 'ESA FACTURA YA ESTÁ REGISTRADA PARA EL PROVEEDOR.'];
        }

        // Número de control: obligatorio, formato 00-00000000
        $nro_control = trim($purchaseFormData['nro_control'] ?? '');
        if ($nro_control === '') {
            return ['ok' => false, 'mensaje' => 'EL NÚMERO DE CONTROL ES OBLIGATORIO.'];
        }
        if (!preg_match('/^\d{2}-\d{8}$/', $nro_control)) {
            return ['ok' => false, 'mensaje' => 'NRO. CONTROL INVÁLIDO. Formato: 00-00000000'];
        }

        $observaciones = trim($purchaseFormData['observaciones'] ?? '');

        $prov = $this->db->fetchOne("SELECT rif, status FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);
        if (!$prov) {
            return ['ok' => false, 'mensaje' => 'PROVEEDOR INVÁLIDO.'];
        }
        if ($prov['status'] !== 'Activo') {
            return ['ok' => false, 'mensaje' => 'EL PROVEEDOR ESTÁ INACTIVO. NO SE PUEDE REGISTRAR LA COMPRA.'];
        }
        if (!validarRIF(normalizarDocumento($prov['rif'] ?? ''))) {
            return ['ok' => false, 'mensaje' => 'EL PROVEEDOR SELECCIONADO NO TIENE UN RIF VÁLIDO. CORRÍJALO EN PROVEEDORES.'];
        }

        // Ítems de la factura
        $productos_raw = json_decode($purchaseFormData['productos_data'] ?? '[]', true);
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
            $prod_fila = $this->db->fetchOne("SELECT sku, nombre_producto FROM productos WHERE id_producto = ?", [$id_producto]);
            if (!$prod_fila) continue;
            // REGLA DE NEGOCIO: todo lote nace de una compra y TODO lote exige
            // fecha de vencimiento. Sin ella se rompe el control FEFO del inventario.
            $lote_venc = null;
            if (!empty($prod['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($prod['fecha_vencimiento']))) {
                $lote_venc = trim($prod['fecha_vencimiento']);
            }
            if (!$lote_venc) {
                return ['ok' => false, 'mensaje' => "FECHA DE VENCIMIENTO REQUERIDA PARA: {$prod_fila['nombre_producto']} ({$prod_fila['sku']})."];
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

        // Comprobante de pago
        $metodo_pago = in_array(trim($purchaseFormData['metodo_pago'] ?? ''), ['Efectivo', 'Transferencia', 'Cheque', 'Otro'], true) ? trim($purchaseFormData['metodo_pago']) : 'Efectivo';
        $monto_pago = round((float)($purchaseFormData['monto_pago'] ?? 0), 2);
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

    /**
     * Anula una compra (solo si no hay mercancía recibida en inventario).
     *
     * Verifica que la compra exista, no esté ya anulada y no tenga lotes
     * recibidos. Si cumple, la marca Anulada y registra la auditoría.
     *
     * @param int $id_compra Identificador de la compra.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
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

    /**
     * Marca una compra como pagada.
     *
     * Valida que la compra exista, no esté anulada ni ya pagada; luego
     * actualiza estado de pago, monto a total y fecha de pago, y registra
     * la auditoría.
     *
     * @param int $id_compra Identificador de la compra.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
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
