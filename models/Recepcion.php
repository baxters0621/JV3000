<?php

// ==========================================
// MODELO: Recepcion
// ==========================================
// Única capa que consulta la base de datos.
// Incluye las reglas de negocio de recepción de
// mercancía: registrar recepción (lotes, stock,
// movimientos, auditoría) y datos del tablero.

/**
 * Recepcion: modelo del módulo de recepción de mercancía.
 *
 * Única capa autorizada para consultar la base de datos. Contiene las reglas
 * de negocio de recepción: registrar la recepción de una compra (crea lotes,
 * actualiza stock, registra movimientos y auditoría) y los datos del tablero.
 */
class Recepcion extends Model
{
    /**
     * Procesa la recepción de mercancía de una compra.
     *
     * Valida la compra, los ítems recibidos (cantidad vs. pendiente) y, en
     * una transacción, crea los lotes, actualiza cantidad_recibida y stock,
     * registra el movimiento y marca la compra como Completa o Parcial según
     * si quedó mercancía pendiente.
     *
     * @param array $receptionFormData Datos del formulario (id_compra, items_data, etc.).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function registrar(array $receptionFormData): array
    {
        $id_compra = intval($receptionFormData['id_compra'] ?? 0);
        $compra = $this->db->fetchOne(
            "SELECT id_compra, nro_factura, id_proveedor, status, estado_recepcion FROM compras WHERE id_compra = ? AND status = 'Activa'",
            [$id_compra]
        );
        if (!$compra || $compra['estado_recepcion'] === 'Completa') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE O YA FUE RECIBIDA POR COMPLETO.'];
        }

        $items_raw = json_decode($receptionFormData['items_data'] ?? '[]', true);
        $items = is_array($items_raw) ? $items_raw : [];
        if (empty($items)) {
            return ['ok' => false, 'mensaje' => 'DEBE INDICAR AL MENOS UN PRODUCTO PARA RECIBIR.'];
        }

        $solicitado = [];
        foreach ($items as $it) {
            $id_detalle = intval($it['id_detalle'] ?? 0);
            $cantidad = intval($it['cantidad'] ?? 0);
            if ($id_detalle <= 0 || $cantidad <= 0) continue;
            $venc = null;
            if (!empty($it['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($it['fecha_vencimiento']))) {
                $venc = trim($it['fecha_vencimiento']);
            }
            $solicitado[$id_detalle] = ['cantidad' => $cantidad, 'fecha_vencimiento' => $venc];
        }
        if (empty($solicitado)) {
            return ['ok' => false, 'mensaje' => 'DEBE INDICAR CANTIDADES VÁLIDAS PARA RECIBIR.'];
        }

        $ids = array_keys($solicitado);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->db->fetchAll(
            "SELECT d.id_detalle, d.id_producto, d.cantidad, d.cantidad_recibida, d.precio_costo, d.fecha_vencimiento, p.nombre_producto
             FROM detalle_compras d JOIN productos p ON d.id_producto = p.id_producto
             WHERE d.id_compra = ? AND d.id_detalle IN ($placeholders)",
            array_merge([$id_compra], $ids)
        );
        if (count($filas) !== count($ids)) {
            return ['ok' => false, 'mensaje' => 'ALGUNO DE LOS PRODUCTOS NO PERTENECE A LA COMPRA.'];
        }

        // REGLA DE NEGOCIO: todo lote exige fecha de vencimiento (la indicada en la
        // recepción o, en su defecto, la guardada al registrar la compra). Sin ella
        // el control FEFO del inventario se rompe, así que la recepción se rechaza.
        foreach ($filas as $f) {
            $venc_final = trim((string)($solicitado[(int)$f['id_detalle']]['fecha_vencimiento'] ?? ''));
            if ($venc_final === '') {
                $venc_final = trim((string)($f['fecha_vencimiento'] ?? ''));
            }
            if ($venc_final === '') {
                return ['ok' => false, 'mensaje' => "FECHA DE VENCIMIENTO REQUERIDA PARA: {$f['nombre_producto']}."];
            }
        }

        foreach ($filas as $f) {
            $restante = (int)$f['cantidad'] - (int)$f['cantidad_recibida'];
            if ($solicitado[(int)$f['id_detalle']]['cantidad'] > $restante) {
                return ['ok' => false, 'mensaje' => "CANTIDAD EXCEDE LO PENDIENTE PARA: {$f['nombre_producto']} (pendiente $restante)."];
            }
        }

        $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);
        $id_proveedor = $compra['id_proveedor'] ? intval($compra['id_proveedor']) : null;
        $documento_recepcion = trim(substr((string)($receptionFormData['documento_recepcion'] ?? ''), 0, 100));

        $this->db->begin();
        try {
            $mov_id = $this->db->insert('movimientos', [
                'id_referencia'      => $id_compra,
                'tipo_referencia'    => 'compra',
                'tipo'               => 'Entrada',
                'id_usuario'         => $id_usuario_sesion,
                'status'             => 'Activo',
                'documento_recepcion' => $documento_recepcion !== '' ? $documento_recepcion : null,
            ]);

            $total_productos = 0;
            $total_unidades = 0;
            $faltante = false;

            foreach ($filas as $f) {
                $id_detalle = (int)$f['id_detalle'];
                $recibir = $solicitado[$id_detalle]['cantidad'];
                $venc = $solicitado[$id_detalle]['fecha_vencimiento'] ?? $f['fecha_vencimiento'];

                $this->db->insert('lotes', [
                    'id_producto'       => (int)$f['id_producto'],
                    'id_proveedor'      => $id_proveedor,
                    'id_compra'         => $id_compra,
                    'cantidad'          => $recibir,
                    'cantidad_restante' => $recibir,
                    'precio_costo'      => (float)$f['precio_costo'],
                    'fecha_vencimiento' => $venc,
                ]);

                $this->db->execute("UPDATE detalle_compras SET cantidad_recibida = cantidad_recibida + ? WHERE id_detalle = ?", [$recibir, $id_detalle]);
                $this->db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [$recibir, (int)$f['id_producto']]);
                if ($id_proveedor) {
                    $this->db->execute("UPDATE productos SET id_proveedor = ? WHERE id_producto = ? AND (id_proveedor IS NULL OR id_proveedor = 0)", [$id_proveedor, (int)$f['id_producto']]);
                }

                $this->db->insert('detalle_movimientos', [
                    'id_movimiento'   => $mov_id,
                    'id_producto'     => (int)$f['id_producto'],
                    'cantidad'        => $recibir,
                    'precio_unitario' => (float)$f['precio_costo'],
                ]);

                if (((int)$f['cantidad_recibida'] + $recibir) < (int)$f['cantidad']) {
                    $faltante = true;
                }
                $total_productos++;
                $total_unidades += $recibir;
            }

            $this->db->execute("UPDATE compras SET estado_recepcion = ? WHERE id_compra = ?", [$faltante ? 'Parcial' : 'Completa', $id_compra]);
            registrarAuditoria('crear', "Recepción de mercancía (factura {$compra['nro_factura']}, $total_productos producto(s), $total_unidades und(s))");
            $this->db->commit();

            $msg = "RECEPCIÓN REGISTRADA: factura {$compra['nro_factura']}, $total_productos producto(s), $total_unidades und(s). ";
            $msg .= $faltante ? 'RECEPCIÓN PARCIAL.' : 'MERCADERÍA RECIBIDA POR COMPLETO.';
            return ['ok' => true, 'mensaje' => $msg];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR AL REGISTRAR LA RECEPCIÓN. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
        }
    }

    /**
     * Compras pendientes de recepción (Pendiente o Parcial).
     *
     * Devuelve las compras activas no recibidas por completo, con resumen de
     * proveedor, número de ítems y unidades pendientes por recibir.
     *
     * @return array Compras pendientes de recepción.
     */
    public function comprasPendientes(): array
    {
        return $this->db->fetchAll("
            SELECT c.id_compra, c.nro_factura, c.nro_control, c.fecha_compra, c.condiciones_pago, c.estado_recepcion, c.total,
                   pr.nombre_empresa AS proveedor,
                   COUNT(dc.id_detalle) AS num_items,
                   SUM(dc.cantidad - dc.cantidad_recibida) AS unidades_pend,
                   SUM(CASE WHEN (dc.cantidad - dc.cantidad_recibida) > 0 THEN 1 ELSE 0 END) AS items_pend
            FROM compras c
            LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
            LEFT JOIN detalle_compras dc ON c.id_compra = dc.id_compra
            WHERE c.status = 'Activa' AND c.estado_recepcion != 'Completa'
            GROUP BY c.id_compra
            ORDER BY c.fecha_compra ASC, c.id_compra ASC
        ");
    }

    /**
     * Ítems pendientes por compra (para el modal de recepción).
     *
     * Devuelve las líneas de detalle con saldo pendiente por recibir, con
     * sku, nombre, cantidades, precio de costo y fecha de vencimiento.
     *
     * @return array Ítems pendientes de recepción.
     */
    public function itemsPendientes(): array
    {
        return $this->db->fetchAll("
            SELECT dc.id_compra, dc.id_detalle, dc.id_producto, dc.cantidad, dc.cantidad_recibida,
                   dc.precio_costo, dc.fecha_vencimiento, p.sku, p.nombre_producto
            FROM detalle_compras dc
            JOIN productos p ON dc.id_producto = p.id_producto
            JOIN compras c ON dc.id_compra = c.id_compra
            WHERE c.status = 'Activa' AND c.estado_recepcion != 'Completa' AND (dc.cantidad - dc.cantidad_recibida) > 0
            ORDER BY c.id_compra, dc.id_detalle
        ");
    }

    /**
     * Datos del modal de recepción agrupados por compra.
     *
     * Combina comprasPendientes() e itemsPendientes() en un mapa por id de
     * compra con su factura, proveedor y la lista de ítems con saldos.
     *
     * @return array Mapa [id_compra => ['nro_factura', 'proveedor', 'items']].
     */
    public function datosRecepcion(): array
    {
        $datos = [];
        foreach ($this->comprasPendientes() as $cp) {
            $datos[$cp['id_compra']] = [
                'nro_factura' => $cp['nro_factura'],
                'proveedor'   => $cp['proveedor'] ?? 'S/P',
                'condiciones' => $cp['condiciones_pago'] ?? 'Contado',
                'items'       => [],
            ];
        }
        foreach ($this->itemsPendientes() as $it) {
            $cid = (int)$it['id_compra'];
            if (!isset($datos[$cid])) continue;
            $datos[$cid]['items'][] = [
                'id_detalle' => (int)$it['id_detalle'],
                'id_producto' => (int)$it['id_producto'],
                'nombre'     => $it['nombre_producto'],
                'sku'        => $it['sku'],
                'cantidad'   => (int)$it['cantidad'],
                'recibida'   => (int)$it['cantidad_recibida'],
                'restante'   => (int)$it['cantidad'] - (int)$it['cantidad_recibida'],
                'precio'     => (float)$it['precio_costo'],
                'vence'      => $it['fecha_vencimiento'],
            ];
        }
        return $datos;
    }

    /**
     * Últimas recepciones registradas (hasta 20).
     *
     * Devuelve los movimientos de entrada de compra más recientes con
     * operador, factura, proveedor y resumen de ítems/unidades.
     *
     * @return array Recepciones recientes.
     */
    public function recepcionesRecientes(): array
    {
        return $this->db->fetchAll("
            SELECT m.id_movimiento, m.fecha_movimiento, m.documento_recepcion, u.usuario AS operador,
                   c.nro_factura, pr.nombre_empresa AS proveedor,
                   (SELECT COUNT(*) FROM detalle_movimientos dm WHERE dm.id_movimiento = m.id_movimiento) AS num_items,
                   (SELECT COALESCE(SUM(dm.cantidad),0) FROM detalle_movimientos dm WHERE dm.id_movimiento = m.id_movimiento) AS unidades
            FROM movimientos m
            JOIN usuarios u ON m.id_usuario = u.id_usuario
            LEFT JOIN compras c ON m.tipo_referencia = 'compra' AND m.id_referencia = c.id_compra
            LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
            WHERE m.tipo_referencia = 'compra' AND m.tipo = 'Entrada' AND m.status = 'Activo'
            ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC
            LIMIT 20
        ");
    }

    /**
     * Cantidad de recepciones registradas hoy.
     *
     * @return int Número de movimientos de entrada de compra del día.
     */
    public function recepcionesHoy(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) AS n FROM movimientos WHERE tipo_referencia = 'compra' AND tipo = 'Entrada' AND status = 'Activo' AND fecha_movimiento >= CURDATE()")['n'] ?? 0);
    }

    /**
     * Datos completos del tablero de recepción para la vista.
     *
     * Agrupa compras pendientes, totales por recibir, datos del modal,
     * recepciones recientes y las de hoy en un solo arreglo.
     *
     * @return array Datos del dashboard de recepción.
     */
    public function dashboard(): array
    {
        $compras = $this->comprasPendientes();
        $unidades = 0;
        foreach ($compras as $cp) {
            $unidades += (int)$cp['unidades_pend'];
        }
        return [
            'compras_pendientes'  => $compras,
            'total_por_recibir'   => count($compras),
            'unidades_por_recibir' => $unidades,
            'datos_recepcion'     => $this->datosRecepcion(),
            'recepciones'         => $this->recepcionesRecientes(),
            'recepciones_hoy'     => $this->recepcionesHoy(),
        ];
    }
}
