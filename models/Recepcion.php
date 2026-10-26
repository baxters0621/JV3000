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
     * Devuelve el resultado existente si se recibió de nuevo la misma clave.
     *
     * @param string $claveIdempotencia Clave técnica del intento.
     * @param int $idCompra Compra a la que pertenece el intento.
     * @param int $idUsuario Operador que envió el intento.
     * @param string $huellaSolicitud Huella del contenido normalizado.
     * @param bool $bloquear Si debe bloquear la fila durante la transacción.
     * @return array|null Resultado de replay/conflicto, o null si no existe.
     */
    private function resultadoIdempotencia(
        string $claveIdempotencia,
        int $idCompra,
        int $idUsuario,
        string $huellaSolicitud,
        bool $bloquear = false
    ): ?array {
        $sql = "SELECT id_referencia, tipo_referencia, tipo, id_usuario, huella_solicitud
                FROM movimientos
                WHERE clave_idempotencia = ?";
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }
        $movimiento = $this->db->fetchOne($sql, [$claveIdempotencia]);
        if (!$movimiento) {
            return null;
        }

        if (
            (int)$movimiento['id_referencia'] !== $idCompra
            || $movimiento['tipo_referencia'] !== 'compra'
            || $movimiento['tipo'] !== 'Entrada'
            || (int)$movimiento['id_usuario'] !== $idUsuario
            || !hash_equals((string)$movimiento['huella_solicitud'], $huellaSolicitud)
        ) {
            return [
                'ok' => false,
                'mensaje' => 'LA CLAVE DE RECEPCIÓN YA SE UTILIZÓ CON DATOS DISTINTOS. REVISE EL ESTADO ANTES DE CONTINUAR.',
            ];
        }

        return [
            'ok' => true,
            'mensaje' => 'ESTA RECEPCIÓN YA FUE REGISTRADA. NO SE REPITIERON CAMBIOS.',
        ];
    }

    /**
     * Procesa la recepción de mercancía de una compra.
     *
     * Valida la compra, los ítems recibidos (cantidad vs. pendiente) y, en
     * una transacción, crea los lotes, recalcula el costo promedio ponderado
     * de cada producto, actualiza cantidad_recibida y stock, registra el
     * movimiento y marca la compra como Completa o Parcial según quedó
     * mercancía pendiente.
     *
     * @param array $receptionFormData Datos del formulario (id_compra, items_data, etc.).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function registrar(array $receptionFormData): array
    {
        $id_compra = intval($receptionFormData['id_compra'] ?? 0);
        $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);
        $clave_idempotencia = strtolower(trim((string)($receptionFormData['clave_idempotencia'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/D', $clave_idempotencia)) {
            return ['ok' => false, 'mensaje' => 'CLAVE DE RECEPCIÓN INVÁLIDA. RECARGUE COMPRAS E INTENTE DE NUEVO.'];
        }

        $items_raw = json_decode($receptionFormData['items_data'] ?? '[]', true);
        $items = is_array($items_raw) ? $items_raw : [];
        if (empty($items)) {
            return ['ok' => false, 'mensaje' => 'DEBE INDICAR AL MENOS UN PRODUCTO PARA RECIBIR.'];
        }

        $solicitado = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                return ['ok' => false, 'mensaje' => 'LÍNEA DE RECEPCIÓN INVÁLIDA.'];
            }
            $id_detalle = filter_var($it['id_detalle'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_INT);
            if ($id_detalle === false || $id_detalle <= 0 || $cantidad === false || $cantidad < 0) {
                return ['ok' => false, 'mensaje' => 'CANTIDAD O PRODUCTO INVÁLIDOS PARA RECIBIR.'];
            }
            if ($cantidad === 0) continue;
            $venc = null;
            if (!empty($it['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($it['fecha_vencimiento']))) {
                $venc = trim($it['fecha_vencimiento']);
            }
            $solicitado[$id_detalle] = ['cantidad' => $cantidad, 'fecha_vencimiento' => $venc];
        }
        if (empty($solicitado)) {
            return ['ok' => false, 'mensaje' => 'DEBE INDICAR CANTIDADES VÁLIDAS PARA RECIBIR.'];
        }

        ksort($solicitado, SORT_NUMERIC);
        $documento_recepcion = trim(substr((string)($receptionFormData['documento_recepcion'] ?? ''), 0, 100));
        $huella_solicitud = hash('sha256', json_encode([
            'id_compra' => $id_compra,
            'id_usuario' => $id_usuario_sesion,
            'items' => $solicitado,
            'documento_recepcion' => $documento_recepcion,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));

        $resultadoRepetido = $this->resultadoIdempotencia(
            $clave_idempotencia,
            $id_compra,
            $id_usuario_sesion,
            $huella_solicitud
        );
        if ($resultadoRepetido !== null) {
            return $resultadoRepetido;
        }

        $compra = $this->db->fetchOne(
            "SELECT id_compra, nro_factura, id_proveedor, status, estado_recepcion FROM compras WHERE id_compra = ? AND status = 'Activa'",
            [$id_compra]
        );
        if (!$compra || $compra['estado_recepcion'] === 'Completa') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE O YA FUE RECIBIDA POR COMPLETO.'];
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

        $id_proveedor = $compra['id_proveedor'] ? intval($compra['id_proveedor']) : null;

        $this->db->begin();
        try {
            $compra = $this->db->fetchOne(
                "SELECT id_compra, nro_factura, id_proveedor, status, estado_recepcion
                 FROM compras WHERE id_compra = ? FOR UPDATE",
                [$id_compra]
            );
            $resultadoRepetido = $this->resultadoIdempotencia(
                $clave_idempotencia,
                $id_compra,
                $id_usuario_sesion,
                $huella_solicitud,
                true
            );
            if ($resultadoRepetido !== null) {
                $this->db->rollback();
                return $resultadoRepetido;
            }
            if (!$compra || $compra['status'] !== 'Activa' || $compra['estado_recepcion'] === 'Completa') {
                throw new RuntimeException('LA COMPRA YA NO ESTÁ DISPONIBLE PARA RECIBIR.');
            }

            $filas = $this->db->fetchAll(
                "SELECT d.id_detalle, d.id_producto, d.cantidad, d.cantidad_recibida, d.precio_costo, d.fecha_vencimiento, p.nombre_producto
                 FROM detalle_compras d JOIN productos p ON d.id_producto = p.id_producto
                 WHERE d.id_compra = ? AND d.id_detalle IN ($placeholders)
                 ORDER BY d.id_detalle
                 FOR UPDATE",
                array_merge([$id_compra], $ids)
            );
            if (count($filas) !== count($ids)) {
                throw new RuntimeException('ALGUNO DE LOS PRODUCTOS YA NO PERTENECE A LA COMPRA.');
            }
            foreach ($filas as $fila) {
                $pendiente = (int)$fila['cantidad'] - (int)$fila['cantidad_recibida'];
                if ($solicitado[(int)$fila['id_detalle']]['cantidad'] > $pendiente) {
                    throw new RuntimeException('LA RECEPCIÓN EXCEDE LA CANTIDAD PENDIENTE.');
                }
            }

            $ids_producto = array_values(array_unique(array_map(
                static fn($fila): int => (int)$fila['id_producto'],
                $filas
            )));
            sort($ids_producto, SORT_NUMERIC);
            $placeholders_producto = implode(',', array_fill(0, count($ids_producto), '?'));
            $this->db->fetchAll(
                "SELECT id_producto FROM productos WHERE id_producto IN ($placeholders_producto) ORDER BY id_producto FOR UPDATE",
                $ids_producto
            );

            $id_proveedor = $compra['id_proveedor'] ? (int)$compra['id_proveedor'] : null;
            $movimientoStmt = $this->db->execute(
                "INSERT INTO movimientos (
                    id_referencia, tipo_referencia, tipo, id_usuario, status,
                    documento_recepcion, clave_idempotencia, huella_solicitud
                 ) VALUES (?, 'compra', 'Entrada', ?, 'Activo', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE id_movimiento = LAST_INSERT_ID(id_movimiento)",
                [
                    $id_compra,
                    $id_usuario_sesion,
                    $documento_recepcion !== '' ? $documento_recepcion : null,
                    $clave_idempotencia,
                    $huella_solicitud,
                ]
            );
            $filasAfectadas = $movimientoStmt->affected_rows;
            $mov_id = (int)$movimientoStmt->insert_id;
            $movimientoStmt->close();
            if ($filasAfectadas !== 1) {
                $resultadoRepetido = $this->resultadoIdempotencia(
                    $clave_idempotencia,
                    $id_compra,
                    $id_usuario_sesion,
                    $huella_solicitud,
                    true
                );
                $this->db->rollback();
                if ($resultadoRepetido !== null) {
                    return $resultadoRepetido;
                }
                return ['ok' => false, 'mensaje' => 'NO SE PUDO REGISTRAR LA CLAVE ÚNICA DE RECEPCIÓN.'];
            }

            $total_productos = 0;
            $total_unidades = 0;

            foreach ($filas as $f) {
                $id_detalle = (int)$f['id_detalle'];
                $recibir = $solicitado[$id_detalle]['cantidad'];
                $venc = $solicitado[$id_detalle]['fecha_vencimiento'] ?? $f['fecha_vencimiento'];

                // Costo promedio ponderado: mezcla el costo del inventario existente
                // con el de la mercancía que entra, para reflejar el costo real.
                // Fórmula: (stock_viejo × costo_viejo + unidades × costo_lote) / total.
                $producto_actual = $this->db->fetchOne(
                    "SELECT stock_actual, precio_costo FROM productos WHERE id_producto = ?",
                    [(int)$f['id_producto']]
                );
                $stock_anterior = (float)($producto_actual['stock_actual'] ?? 0);
                $costo_anterior = (float)($producto_actual['precio_costo'] ?? 0);
                $costo_lote = (float)$f['precio_costo'];
                $stock_total = $stock_anterior + $recibir;
                if ($stock_anterior <= 0 || $costo_anterior <= 0) {
                    $costo_promedio = $costo_lote;
                } else {
                    $costo_promedio = round((($stock_anterior * $costo_anterior) + ($recibir * $costo_lote)) / $stock_total, 2);
                }

                $this->db->insert('lotes', [
                    'id_producto'       => (int)$f['id_producto'],
                    'id_proveedor'      => $id_proveedor,
                    'id_compra'         => $id_compra,
                    'cantidad'          => $recibir,
                    'cantidad_restante' => $recibir,
                    'precio_costo'      => $costo_lote,
                    'fecha_vencimiento' => $venc,
                ]);

                $this->db->execute("UPDATE detalle_compras SET cantidad_recibida = cantidad_recibida + ? WHERE id_detalle = ?", [$recibir, $id_detalle]);
                $this->db->execute(
                    "UPDATE productos SET stock_actual = stock_actual + ?, precio_costo = ? WHERE id_producto = ?",
                    [$recibir, $costo_promedio, (int)$f['id_producto']]
                );

                $this->db->insert('detalle_movimientos', [
                    'id_movimiento'   => $mov_id,
                    'id_producto'     => (int)$f['id_producto'],
                    'cantidad'        => $recibir,
                    'precio_unitario' => (float)$f['precio_costo'],
                ]);

                $total_productos++;
                $total_unidades += $recibir;
            }

            $detalles_compra = $this->db->fetchAll(
                "SELECT cantidad, cantidad_recibida
                 FROM detalle_compras
                 WHERE id_compra = ?
                 ORDER BY id_detalle
                 FOR UPDATE",
                [$id_compra]
            );
            $faltante = false;
            foreach ($detalles_compra as $detalle_compra) {
                if ((int)$detalle_compra['cantidad_recibida'] < (int)$detalle_compra['cantidad']) {
                    $faltante = true;
                    break;
                }
            }

            $this->db->execute("UPDATE compras SET estado_recepcion = ? WHERE id_compra = ?", [$faltante ? 'Parcial' : 'Completa', $id_compra]);
            registrarAuditoria('crear', "Recepción de mercancía (factura {$compra['nro_factura']}, $total_productos producto(s), $total_unidades und(s))");
            $this->db->commit();
            // Entró mercancía: recalcular el estado automático de las categorías.
            (new Categoria())->sincronizarPorStock();

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
            SELECT c.id_compra, c.nro_factura, c.nro_control, c.fecha_compra, c.estado_recepcion, c.total,
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
