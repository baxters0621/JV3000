<?php

// ==========================================
// MODELO: Salida (Ventas / Salidas de inventario)
// ==========================================
// Única capa que consulta la base de datos. No sabe de
// pantallas ni de peticiones web. Preserva las reglas de
// negocio de ventas: consumo FEFO por lote, descuento de
// stock, anulación y confirmación de salidas.

/**
 * Salida: modelo del módulo de salidas/ventas.
 *
 * Única capa autorizada para consultar la base de datos. No sabe de pantallas
 * ni de peticiones web. Preserva las reglas de negocio de ventas: consumo
 * FEFO por lote, descuento de stock, anulación y confirmación de salidas.
 */
class Salida extends Model
{
    public const LIMITE_PRODUCTOS = 200;

    /**
     * Agrupa un tipo de movimiento para las reglas de venta.
     *
     * Devuelve 'venta' para VENTA, 'regalias' para REGALIAS y 'merma' para
     * cualquier otro tipo. Determina las validaciones aplicables.
     *
     * @param string $nombre Nombre del tipo de movimiento.
     * @return string Grupo: 'venta', 'regalias' o 'merma'.
     */
    public static function grupoDeTipo(string $nombre): string
    {
        $n = mb_strtoupper(trim($nombre));
        if ($n === 'VENTA') return 'venta';
        if ($n === 'REGALIAS') return 'regalias';
        return 'merma';
    }

    /**
     * Listado de salidas activas con resumen por venta.
     *
     * Devuelve las salidas activas agrupadas con el listado de productos,
     * totales de cantidad y monto, y el primer detalle (para la vista previa).
     * Además enriquece cada salida con productos_json para el modal.
     *
     * @return array Salidas activas con resumen y detalles.
     */
    public function obtenerSalidas(): array
    {
        $salidas = $this->db->fetchAll("
            SELECT s.*,
                   GROUP_CONCAT(p.nombre_producto SEPARATOR ', ') as productos_list,
                   SUM(ds.cantidad) as total_cantidad,
                   SUM(ds.cantidad * ds.precio_venta) as total_monto,
                   COUNT(ds.id_detalle) as num_productos,
                   tm.nombre as tipo_mov_nombre,
                   (SELECT ds2.id_producto FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_id_producto,
                   (SELECT ds2.cantidad FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_cantidad,
                   (SELECT ds2.precio_venta FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_precio_venta
            FROM salidas s
            LEFT JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            LEFT JOIN productos p ON ds.id_producto = p.id_producto
            LEFT JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
            WHERE s.status = 'Activa'
            GROUP BY s.id_salida
            ORDER BY s.fecha_salida DESC, s.id_salida DESC
        ");

        if ($salidas) {
            $ids_sal = implode(',', array_map(fn($r) => (int)$r['id_salida'], $salidas));
            $detalle_all = $this->db->fetchAll("
                SELECT ds.id_salida, ds.id_producto, ds.cantidad, ds.precio_venta, p.nombre_producto, p.sku
                FROM detalle_salidas ds
                JOIN productos p ON ds.id_producto = p.id_producto
                WHERE ds.id_salida IN ($ids_sal)
            ");
            $mapa_det = [];
            foreach ($detalle_all as $d) {
                $mapa_det[$d['id_salida']][] = [
                    'id_producto'    => (int)$d['id_producto'],
                    'nombre_producto' => $d['nombre_producto'],
                    'sku'            => $d['sku'] ?? '',
                    'cantidad'       => (int)$d['cantidad'],
                    'precio_venta'   => (float)$d['precio_venta'],
                ];
            }
            foreach ($salidas as &$s) {
                $s['productos_json'] = json_encode($mapa_det[$s['id_salida']] ?? []);
            }
            unset($s);
        }

        return $salidas;
    }

    /**
     * Tipos de movimiento de tipo Salida (para el selector del modal).
     *
     * @return array Lista de tipos de movimiento con tipo 'Salida'.
     */
    public function obtenerTiposMov(): array
    {
        return $this->db->fetchAll("SELECT id_tipo_mov, nombre FROM tipos_movimientos WHERE tipo_movimiento = 'Salida' ORDER BY id_tipo_mov");
    }

    /**
     * Nombre de un tipo de movimiento ('' si no existe).
     *
     * @param int $idTipoMov Identificador del tipo de movimiento.
     * @return string Nombre del tipo o cadena vacía.
     */
    public function obtenerTipoNombre(int $idTipoMov): string
    {
        $movementType = $this->db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$idTipoMov]);
        return $movementType['nombre'] ?? '';
    }

    /**
     * Mapa id_tipo_mov → grupo (para el JS: window.JV_CONFIG.movementTypeGroups).
     *
     * @return array Mapa [id_tipo_mov => grupo].
     */
    public function mapaTiposGrupo(): array
    {
        $movementTypeGroups = [];
        foreach ($this->obtenerTiposMov() as $movementType) {
            $movementTypeGroups[$movementType['id_tipo_mov']] = self::grupoDeTipo($movementType['nombre']);
        }
        return $movementTypeGroups;
    }

    /**
     * KPIs de la cabecera (ventas del mes, unidades y ventas de hoy).
     *
     * Considera solo salidas activas de tipo VENTA dentro del mes en curso.
     *
     * @return array ['ventas_mes'=>float, 'und_mes'=>int, 'ventas_hoy'=>int].
     */
    public function kpis(): array
    {
        $ventas_mes = $this->db->fetchOne("
            SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta),0) as t
            FROM salidas s
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
            WHERE s.status = 'Activa' AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.fecha_salida >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
              AND s.fecha_salida < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01')
        ");
        $und_mes = $this->db->fetchOne("
            SELECT COALESCE(SUM(ds.cantidad),0) as t
            FROM salidas s
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
            WHERE s.status = 'Activa' AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.fecha_salida >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
              AND s.fecha_salida < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01')
        ");
        $ventas_hoy = $this->db->fetchOne("
            SELECT COUNT(*) as t
            FROM salidas s
            JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
            WHERE s.status = 'Activa' AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.fecha_salida >= CURDATE()
        ");

        return [
            'ventas_mes' => (float)($ventas_mes['t'] ?? 0),
            'und_mes'    => (int)($und_mes['t'] ?? 0),
            'ventas_hoy' => (int)($ventas_hoy['t'] ?? 0),
        ];
    }

    /**
     * Anula una salida (solo admin): restaura stock y lotes.
     *
     * Devuelve las unidades a los productos y lotes, marca la salida como
     * Anulada, anula su movimiento y registra la auditoría. Todo dentro de
     * una transacción.
     *
     * @param int $id_salida Identificador de la salida.
     * @return array ['ok'=>bool, 'error'?=>string].
     */
    public function anular(int $id_salida): array
    {
        $detalles = $this->db->fetchAll("SELECT id_producto, id_lote, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_salida]);
        if (empty($detalles)) {
            return ['ok' => false, 'error' => 'SALIDA SIN DETALLES PARA ANULAR.'];
        }

        $this->db->begin();
        try {
            foreach ($detalles as $det) {
                $this->db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
                if (!empty($det['id_lote'])) devolverLote($this->db, (int)$det['id_lote'], (int)$det['cantidad']);
            }
            $this->db->execute("UPDATE salidas SET status = 'Anulada' WHERE id_salida = ?", [$id_salida]);
            $this->db->execute("UPDATE movimientos SET status = 'Anulado' WHERE id_referencia = ? AND tipo_referencia = 'venta'", [$id_salida]);
            $this->db->commit();
            registrarAuditoria('anular', "Salida #$id_salida anulada, " . count($detalles) . " producto(s)");
            return ['ok' => true];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'ERROR EN LA BASE DE DATOS.'];
        }
    }

    /**
     * Confirma una venta guardada en preview_data.
     *
     * Inserta o actualiza la salida, su detalle, descuenta stock_actual con
     * consumo FEFO de lotes, registra el movimiento de inventario y la
     * auditoría. En edición primero restaura stock y lotes de la salida
     * original. Todo dentro de una transacción.
     *
     * @param array $data Datos del preview guardado en sesión.
     * @return array ['ok'=>bool, 'id_salida'?=>int, 'edicion'?=>bool, 'error'?=>string].
     */
    public function confirmar(array $data): array
    {
        $productos_raw = [];
        if (isset($data['productos_data'])) {
            $productos_raw = json_decode($data['productos_data'], true) ?: [];
        } else {
            $productos_raw[] = [
                'id_producto' => intval($data['id_producto'] ?? 0),
                'cantidad'    => intval($data['cantidad'] ?? 0),
                'precio'      => floatval($data['precio_venta'] ?? 0),
            ];
        }

        $es_edicion = ($data['accion_salida'] ?? '') === 'editar';
        $id_editar = intval($data['id_salida'] ?? 0);
        $grupo_data = $data['grupo'] ?? 'venta';

        $this->db->begin();
        try {
            if (count($productos_raw) > self::LIMITE_PRODUCTOS) {
                throw new Exception("MÁXIMO " . self::LIMITE_PRODUCTOS . " PRODUCTOS POR VENTA.");
            }

            // Validar documento fiscal (cédula o RIF) en ventas
            if ($grupo_data === 'venta') {
                $rif_confirm = normalizarDocumento($data['rif_cliente'] ?? 'N/A');
                if ($rif_confirm !== 'N/A' && !validarDocumentoFiscal($rif_confirm)) {
                    throw new Exception("DOCUMENTO FISCAL INVÁLIDO (CÉDULA O RIF).");
                }
                $data['rif_cliente'] = $rif_confirm;
            }

            // 0. Si es edición: restaurar el stock y los lotes de los detalles actuales
            if ($es_edicion) {
                $salida_vieja = $this->db->fetchOne("SELECT id_salida FROM salidas WHERE id_salida = ? AND status = 'Activa'", [$id_editar]);
                if (!$salida_vieja) throw new Exception("LA SALIDA A EDITAR NO EXISTE O YA FUE ANULADA.");
                $ant_detalles = $this->db->fetchAll("SELECT id_producto, id_lote, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_editar]);
                foreach ($ant_detalles as $det) {
                    $this->db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
                    if (!empty($det['id_lote'])) devolverLote($this->db, (int)$det['id_lote'], (int)$det['cantidad']);
                }
            }

            // Validar stock de todos los productos (después de restaurar, en caso de edición)
            $solo_vencidos = $grupo_data === 'merma';

            // Bloquear filas de productos involucrados (prevenir race condition)
            $ids_producto = array_unique(array_filter(array_map(fn($p) => intval($p['id_producto'] ?? 0), $productos_raw), fn($id) => $id > 0));
            if (!empty($ids_producto)) {
                $ph = implode(',', array_fill(0, count($ids_producto), '?'));
                $this->db->fetchAll("SELECT id_producto, stock_actual FROM productos WHERE id_producto IN ($ph) FOR UPDATE", $ids_producto);
            }

            foreach ($productos_raw as $prod) {
                $id_producto = intval($prod['id_producto'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                if ($id_producto <= 0 || $cantidad <= 0) continue;
                $pi = $this->db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);
                if (!$pi) throw new Exception("Producto #$id_producto no encontrado");
                $tiene_lotes = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$id_producto])['n'];
                if ($tiene_lotes > 0) {
                    $disp = stockLoteDisponible($this->db, $id_producto, $solo_vencidos);
                    if ($disp < $cantidad) {
                        $modo = $solo_vencidos ? 'VENCIDO' : 'VIGENTE';
                        throw new Exception("STOCK $modo INSUFICIENTE para producto (ID:$id_producto). Disponible: $disp, solicitado: $cantidad");
                    }
                } elseif ((int)$pi['stock_actual'] < $cantidad) {
                    throw new Exception("Stock insuficiente para producto (ID:$id_producto). Disponible:{$pi['stock_actual']}, solicitado:$cantidad");
                }
            }

            // 1. Cabecera: actualizar o insertar
            if ($es_edicion) {
                $this->db->execute(
                    "UPDATE salidas SET nro_control=?, cliente=?, rif_cliente=?, fecha_salida=?, id_tipo_mov=?, id_cliente=?, observaciones=? WHERE id_salida=?",
                    [$data['nro_control'] ?? '', $data['cliente'] ?? '', $data['rif_cliente'] ?? 'N/A', $data['fecha_salida'] ?? date('Y-m-d H:i:s'), intval($data['id_tipo_mov']), intval($data['id_cliente'] ?? 0) ?: null, $data['observaciones'] ?? '', $id_editar]
                );
                $this->db->execute("DELETE FROM detalle_salidas WHERE id_salida = ?", [$id_editar]);
                $salida_id = $id_editar;
            } else {
                $salida_id = $this->db->insert('salidas', [
                    'nro_factura_manual' => generarFacturaNumero(),
                    'nro_control'        => $data['nro_control'] ?? '',
                    'cliente'            => $data['cliente'] ?? '',
                    'rif_cliente'        => $data['rif_cliente'] ?? 'N/A',
                    'id_cliente'         => intval($data['id_cliente'] ?? 0) ?: null,
                    'id_tipo_mov'        => intval($data['id_tipo_mov']),
                    'id_usuario'         => $data['id_usuario'],
                    'fecha_salida'       => $data['fecha_salida'] ?? date('Y-m-d H:i:s'),
                    'status'             => 'Activa',
                    'observaciones'      => $data['observaciones'] ?? '',
                ]);
            }

            // 2. Insertar detalles en lote y descontar stock (consumo FEFO por lote)
            foreach ($productos_raw as $prod) {
                $id_producto = intval($prod['id_producto'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                $precio_venta = floatval($prod['precio'] ?? 0);
                if ($id_producto <= 0 || $cantidad <= 0) continue;

                if ($grupo_data === 'venta' && ($precio_venta <= 0 || $precio_venta > 99999999.99)) {
                    throw new Exception("PRECIO DE VENTA INVÁLIDO PARA PRODUCTO (ID:$id_producto).");
                }
                if ($grupo_data === 'merma' && ($precio_venta < 0 || $precio_venta > 99999999.99)) {
                    throw new Exception("PRECIO DE AJUSTE INVÁLIDO PARA PRODUCTO (ID:$id_producto).");
                }

                $tiene_lotes = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$id_producto])['n'];
                if ($tiene_lotes > 0) {
                    $usados = consumirLotes($this->db, $id_producto, $cantidad, $solo_vencidos, true);
                    foreach ($usados as $u) {
                        $this->db->insert('detalle_salidas', [
                            'id_salida'    => $salida_id,
                            'id_producto'  => $id_producto,
                            'id_lote'      => $u['id_lote'],
                            'cantidad'     => $u['cantidad'],
                            'precio_venta' => $precio_venta,
                        ]);
                        $this->db->execute("UPDATE lotes SET cantidad_restante = cantidad_restante - ? WHERE id_lote = ?", [$u['cantidad'], $u['id_lote']]);
                    }
                } else {
                    $this->db->insert('detalle_salidas', [
                        'id_salida'    => $salida_id,
                        'id_producto'  => $id_producto,
                        'id_lote'      => null,
                        'cantidad'     => $cantidad,
                        'precio_venta' => $precio_venta,
                    ]);
                }

                $this->db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);
            }

            // 3. Movimiento de inventario
            if ($es_edicion) {
                $mov = $this->db->fetchOne("SELECT id_movimiento FROM movimientos WHERE id_referencia = ? AND tipo_referencia = 'venta'", [$id_editar]);
                if ($mov) {
                    $this->db->execute("DELETE FROM detalle_movimientos WHERE id_movimiento = ?", [$mov['id_movimiento']]);
                    $mov_id = $mov['id_movimiento'];
                } else {
                    $mov_id = $this->db->insert('movimientos', [
                        'id_referencia'   => $id_editar,
                        'tipo_referencia' => 'venta',
                        'tipo'            => 'Salida',
                        'id_usuario'      => $data['id_usuario'],
                        'status'          => 'Activo',
                    ]);
                }
            } else {
                $mov_id = $this->db->insert('movimientos', [
                    'id_referencia'   => $salida_id,
                    'tipo_referencia' => 'venta',
                    'tipo'            => 'Salida',
                    'id_usuario'      => $data['id_usuario'],
                    'status'          => 'Activo',
                ]);
            }

            // 4. Insertar detalle de movimiento (re-iterate productos)
            foreach ($productos_raw as $prod) {
                $id_producto = intval($prod['id_producto'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                $precio_venta = floatval($prod['precio'] ?? 0);
                if ($id_producto <= 0 || $cantidad <= 0) continue;
                $this->db->insert('detalle_movimientos', [
                    'id_movimiento'   => $mov_id,
                    'id_producto'     => $id_producto,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precio_venta,
                ]);
            }

            $this->db->commit();

            // Auditoría y resultado
            $causa_data = $data['causa_ajuste'] ?? '';
            if ($es_edicion) {
                $det_auditoria = $grupo_data === 'merma'
                    ? "Ajuste (-) editado: Causa: $causa_data, " . count($productos_raw) . " producto(s)"
                    : "Venta editada, " . count($productos_raw) . " producto(s)";
                registrarAuditoria('editar', $det_auditoria);
                return ['ok' => true, 'id_salida' => $salida_id, 'edicion' => true];
            }

            $det_auditoria = $grupo_data === 'merma'
                ? "Ajuste (-): Causa: $causa_data, " . count($productos_raw) . " producto(s)"
                : "Venta registrada, " . count($productos_raw) . " producto(s)";
            registrarAuditoria('crear', $det_auditoria);
            return ['ok' => true, 'id_salida' => $salida_id, 'edicion' => false];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
