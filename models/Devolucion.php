<?php

// ==========================================
// MODELO: Devolución (devolución de ventas)
// ==========================================
// Procesa devoluciones de clientes: seleccionar
// venta original, productos, cantidades, lotes
// FEFO y restaurar stock.

/**
 * Devolucion: modelo del módulo de devoluciones.
 *
 * Permite registrar devoluciones de ventas (entradas por devolución),
 * seleccionando lotes específicos FEFO y restaurando stock.
 */
class Devolucion extends Model
{
    /**
     * Detalle de una venta para la devolución.
     *
     * Devuelve la venta activa con todos sus productos, cantidades y lotes.
     *
     * @param int $idSalida Identificador de la salida/venta.
     * @return array|null Datos de la venta o null si no existe.
     */
    public function detalleVenta(int $idSalida): ?array
    {
        $salida = $this->db->fetchOne(
            "SELECT s.*, tm.nombre as tipo_nombre
             FROM salidas s
             JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
             WHERE s.id_salida = ? AND s.status = 'Activa'",
            [$idSalida]
        );
        if (!$salida) return null;

        $salida['detalles'] = $this->db->fetchAll(
            "SELECT ds.*, p.nombre_producto, p.sku,
                    p.stock_actual, p.stock_maximo,
                    COALESCE(lotes_info.total_lote, 0) as stock_lote,
                    COALESCE(lotes_info.proximo_vencimiento, NULL) as proximo_vencimiento
             FROM detalle_salidas ds
             JOIN productos p ON ds.id_producto = p.id_producto
             LEFT JOIN (
                 SELECT id_producto,
                        SUM(cantidad_restante) as total_lote,
                        MIN(CASE WHEN fecha_vencimiento > CURDATE() THEN fecha_vencimiento END) as proximo_vencimiento
                 FROM lotes WHERE cantidad_restante > 0
                 GROUP BY id_producto
             ) lotes_info ON p.id_producto = lotes_info.id_producto
             WHERE ds.id_salida = ?
             ORDER BY ds.id_detalle",
            [$idSalida]
        );

        return $salida;
    }

    /**
     * Lotes disponibles para un producto (FEFO, vigentes).
     *
     * @param int     $idProducto Identificador del producto.
     * @param int     $cantidadMax Cantidad máxima a devolver (para filtrar lotes con stock suficiente).
     * @return array  Lotes disponibles ordenados FEFO.
     */
    public function lotesDisponibles(int $idProducto, int $cantidadMax = 0): array
    {
        return $this->db->fetchAll(
            "SELECT l.*, p.nombre_producto
             FROM lotes l
             JOIN productos p ON l.id_producto = p.id_producto
             WHERE l.id_producto = ? AND l.cantidad_restante > 0
               AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento > CURDATE())
             ORDER BY (l.fecha_vencimiento IS NULL) ASC, l.fecha_vencimiento ASC, l.id_lote ASC",
            [$idProducto]
        );
    }

    /**
     * Busca ventas activas para seleccionar cuál devolver.
     *
     * @param string $busqueda Texto de búsqueda (nro factura, cliente, RIF).
     * @return array Ventas encontradas.
     */
    public function buscarVentas(string $busqueda = ''): array
    {
        $where = "s.status = 'Activa'";
        $params = [];

        if ($busqueda !== '') {
            $where .= " AND (s.nro_factura_manual LIKE ? OR s.cliente LIKE ? OR s.rif_cliente LIKE ?)";
            $like = '%' . $busqueda . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return $this->db->fetchAll(
            "SELECT s.id_salida, s.nro_factura_manual, s.nro_control, s.cliente, s.rif_cliente,
                    s.fecha_salida, tm.nombre as tipo_nombre,
                    SUM(ds.cantidad) as total_cantidad,
                    SUM(ds.cantidad * ds.precio_venta) as total_monto
             FROM salidas s
             JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
             JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
             WHERE $where
             GROUP BY s.id_salida
             ORDER BY s.fecha_salida DESC
             LIMIT 20",
            $params
        );
    }

    /**
     * Registra una devolución completa (transacción FEFO).
     *
     * Flujo:
     * 1. Valida la venta original y los datos de devolución
     * 2. Por cada producto: crea lote devuelto o resta al existente
     * 3. Incrementa stock_actual del producto
     * 4. Registra movimiento de entrada
     * 5. Registra detalle de movimiento
     *
     * @param array  $data       Datos del formulario (productos, lotes, motivo).
     * @param int    $idUsuario  ID del usuario que registra.
     * @return array ['ok'=>bool, 'id_devolucion'?, 'error'?].
     */
    public function registrar(array $data, int $idUsuario): array
    {
        $idSalida = (int)($data['id_salida'] ?? 0);
        $motivo = trim($data['motivo'] ?? '');
        $productos = json_decode($data['productos_data'] ?? '[]', true) ?: [];

        if ($idSalida <= 0) {
            return ['ok' => false, 'error' => 'DEBE SELECCIONAR UNA VENTA.'];
        }
        if (empty($motivo)) {
            return ['ok' => false, 'error' => 'EL MOTIVO ES OBLIGATORIO.'];
        }
        if (empty($productos)) {
            return ['ok' => false, 'error' => 'DEBE SELECCIONAR AL MENOS UN PRODUCTO.'];
        }

        // Validar que la venta existe y está activa
        $salida = $this->db->fetchOne(
            "SELECT id_salida, cliente FROM salidas WHERE id_salida = ? AND status = 'Activa'",
            [$idSalida]
        );
        if (!$salida) {
            return ['ok' => false, 'error' => 'LA VENTA NO EXISTE O YA FUE ANULADA.'];
        }

        $this->db->begin();
        try {
            $totalDevuelto = 0;

            foreach ($productos as $prod) {
                $idProducto = (int)($prod['id_producto'] ?? 0);
                $cantidad = (int)($prod['cantidad'] ?? 0);
                $idLote = (int)($prod['id_lote'] ?? 0);
                $precioVenta = (float)($prod['precio_venta'] ?? 0);

                if ($idProducto <= 0 || $cantidad <= 0) continue;

                // Validar que el lote existe y tiene stock
                if ($idLote > 0) {
                    $lote = $this->db->fetchOne(
                        "SELECT id_lote, cantidad_restante, fecha_vencimiento FROM lotes WHERE id_lote = ? AND id_producto = ?",
                        [$idLote, $idProducto]
                    );
                    if (!$lote) {
                        throw new Exception("LOTE #$idLote NO VÁLIDO PARA PRODUCTO #$idProducto.");
                    }
                    // Restaurar stock al lote
                    $this->db->execute(
                        "UPDATE lotes SET cantidad_restante = cantidad_restante + ? WHERE id_lote = ?",
                        [$cantidad, $idLote]
                    );
                } else {
                    // Sin lote específico: crear lote nuevo con vencimiento lejano
                    $this->db->execute(
                        "INSERT INTO lotes (id_producto, cantidad, cantidad_restante, precio_costo, fecha_vencimiento, fecha_ingreso)
                         VALUES (?, ?, ?, 0, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), NOW())",
                        [$idProducto, $cantidad, $cantidad]
                    );
                }

                // Incrementar stock del producto
                $this->db->execute(
                    "UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?",
                    [$cantidad, $idProducto]
                );

                $totalDevuelto += $cantidad;
            }

            // Registrar movimiento de entrada (Devoluciones)
            $movId = $this->db->insert('movimientos', [
                'id_referencia'   => $idSalida,
                'tipo_referencia' => 'devolucion',
                'tipo'            => 'Entrada',
                'id_usuario'      => $idUsuario,
                'status'          => 'Activo',
            ]);

            // Registrar detalle del movimiento
            foreach ($productos as $prod) {
                $idProducto = (int)($prod['id_producto'] ?? 0);
                $cantidad = (int)($prod['cantidad'] ?? 0);
                if ($idProducto <= 0 || $cantidad <= 0) continue;
                $this->db->insert('detalle_movimientos', [
                    'id_movimiento'   => $movId,
                    'id_producto'     => $idProducto,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => 0,
                ]);
            }

            $this->db->commit();

            registrarAuditoria('crear', "Devolución de Venta #$idSalida: $totalDevuelto unidad(es), Motivo: $motivo");

            return ['ok' => true, 'id_devolucion' => $movId];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista devoluciones recientes.
     *
     * @param int $limit Cantidad máxima de registros.
     * @return array Devoluciones registradas.
     */
    public function listar(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT m.id_movimiento, m.fecha_movimiento, m.status,
                    s.nro_factura_manual, s.cliente, s.rif_cliente,
                    u.usuario as registrado_por,
                    GROUP_CONCAT(CONCAT(p.nombre_producto, ' x', dm.cantidad) SEPARATOR ', ') as productos_resumen,
                    SUM(dm.cantidad) as total_unidades
             FROM movimientos m
             JOIN salidas s ON m.id_referencia = s.id_salida
             JOIN usuarios u ON m.id_usuario = u.id_usuario
             JOIN detalle_movimientos dm ON m.id_movimiento = dm.id_movimiento
             JOIN productos p ON dm.id_producto = p.id_producto
             WHERE m.tipo_referencia = 'devolucion' AND m.status = 'Activo'
             GROUP BY m.id_movimiento
             ORDER BY m.fecha_movimiento DESC
             LIMIT ?",
            [$limit]
        );
    }
}
