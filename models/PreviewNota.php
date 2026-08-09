<?php

// ==========================================
// MODELO: Preview Nota / Nota de Entrega
// ==========================================
// Única capa que consulta la base de datos y arma los
// datos de la nota imprimible. No imprime HTML.
class PreviewNota extends Model
{
    public const LIMITE_PRODUCTOS = 200;
    public const LIMITE_UNIDADES = 999999;

    // Consulta una salida para reimpresión (con nombre del tipo de movimiento)
    public function obtenerPorId(int $idSalida): ?array
    {
        return $this->db->fetchOne("
            SELECT s.*, tm.nombre as tipo_nombre
            FROM salidas s
            LEFT JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
            WHERE s.id_salida = ?
        ", [$idSalida]);
    }

    // Detalle de una salida para reimpresión
    public function obtenerDetalles(int $idSalida): array
    {
        return $this->db->fetchAll("
            SELECT ds.*, p.nombre_producto, p.sku, p.precio_venta as precio_original, p.fecha_vencimiento, l.fecha_vencimiento as lote_vencimiento
            FROM detalle_salidas ds
            JOIN productos p ON ds.id_producto = p.id_producto
            LEFT JOIN lotes l ON ds.id_lote = l.id_lote
            WHERE ds.id_salida = ?
        ", [$idSalida]);
    }

    // Nombre de un tipo de movimiento ('' si no existe)
    public function obtenerTipoNombre(int $idTipoMov): string
    {
        $row = $this->db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$idTipoMov]);
        return $row['nombre'] ?? '';
    }

    // Construye el detalle de la nota a partir de un preview en sesión
    // (consulta los productos guardados en productos_data).
    public function obtenerDetallesPreview(array $data): array
    {
        $productos_raw = [];
        if (isset($data['productos_data'])) {
            $productos_raw = json_decode($data['productos_data'], true) ?: [];
        } else {
            // Fallback para preview de un solo producto (formato antiguo)
            $productos_raw[] = [
                'id_producto' => intval($data['id_producto'] ?? 0),
                'cantidad'    => intval($data['cantidad'] ?? 0),
                'precio'      => floatval($data['precio_venta'] ?? 0),
            ];
        }

        $detalles = [];
        foreach ($productos_raw as $p) {
            $pid = intval($p['id_producto'] ?? 0);
            $prod = $this->db->fetchOne("SELECT nombre_producto, sku, precio_venta, fecha_vencimiento FROM productos WHERE id_producto = ?", [$pid]);
            $detalles[] = [
                'id_producto'       => $pid,
                'cantidad'          => intval($p['cantidad'] ?? 0),
                'precio_venta'      => floatval($p['precio'] ?? 0),
                'precio_original'   => floatval($prod['precio_venta'] ?? 0),
                'nombre_producto'   => $prod['nombre_producto'] ?? '—',
                'sku'               => $prod['sku'] ?? '—',
                'fecha_vencimiento' => $prod['fecha_vencimiento'] ?? null,
            ];
        }

        return $detalles;
    }

    // Valida los datos del formulario de venta y devuelve el preview listo
    // para guardar en sesión. No toca $_SESSION.
    // @return array ['ok'=>bool, 'data'?, 'error'?]
    public function construirPreview(array $input, int $idUsuario): array
    {
        $productos_data = $input['productos_data'] ?? '';
        $id_tipo_mov = intval($input['id_tipo_mov'] ?? 0);
        $cliente = mb_strtoupper(trim($input['cliente'] ?? ''));
        $rif_cliente = mb_strtoupper(trim($input['rif_cliente'] ?? ''));
        $id_cliente = intval($input['id_cliente'] ?? 0) ?: null;
        $fecha_salida = $input['fecha_salida'] ?? date('Y-m-d');
        $nro_control = generarControlNumero();

        $tipo_nombre = $this->obtenerTipoNombre($id_tipo_mov);
        $n2 = mb_strtoupper(trim($tipo_nombre));
        $grupo = $n2 === 'VENTA' ? 'venta' : ($n2 === 'REGALIAS' ? 'regalias' : 'merma');

        $causa_ajuste = $grupo === 'merma' ? trim($input['causa_ajuste'] ?? '') : '';
        $motivo_merma = trim($input['descripcion_motivo'] ?? '');
        $motivo_reg = trim($input['motivo_regalia'] ?? '');
        $obs_extra = trim($input['observaciones'] ?? '');
        $partes = [];
        if ($causa_ajuste) $partes[] = "Causa: $causa_ajuste";
        if ($motivo_merma) $partes[] = "Motivo: $motivo_merma";
        if ($motivo_reg) $partes[] = "Regalía: $motivo_reg";
        if ($obs_extra) $partes[] = $obs_extra;
        $observaciones = implode(' | ', $partes);

        $accion_salida = in_array($input['accion_salida'] ?? '', ['registrar', 'editar']) ? $input['accion_salida'] : 'registrar';
        $id_salida = intval($input['id_salida'] ?? 0);

        // Parse productos: desde JSON o campos individuales
        $productos = [];
        if (!empty($productos_data)) {
            $parsed = json_decode($productos_data, true);
            if (is_array($parsed)) {
                if (count($parsed) > self::LIMITE_PRODUCTOS) {
                    return ['ok' => false, 'error' => 'MÁXIMO ' . self::LIMITE_PRODUCTOS . ' PRODUCTOS POR VENTA.'];
                }
                $productos = $parsed;
            }
        } else {
            $productos[] = [
                'id_producto' => intval($input['id_producto'] ?? 0),
                'cantidad'    => intval($input['cantidad'] ?? 0),
                'precio'      => floatval($input['precio_venta'] ?? 0),
            ];
        }

        if (empty($productos) || !$id_tipo_mov) {
            return ['ok' => false, 'error' => 'DATOS INCOMPLETOS (FALTAN PRODUCTOS O TIPO).'];
        }
        if ($grupo === 'venta') {
            if (empty($cliente)) {
                return ['ok' => false, 'error' => 'CLIENTE OBLIGATORIO PARA VENTAS.'];
            }
            if (empty($rif_cliente)) {
                return ['ok' => false, 'error' => 'RIF OBLIGATORIO PARA VENTAS.'];
            }
            $rif_cliente = normalizarDocumento($rif_cliente);
            if (!validarDocumentoFiscal($rif_cliente)) {
                return ['ok' => false, 'error' => 'DOCUMENTO FISCAL INVÁLIDO (CÉDULA O RIF).'];
            }
        }
        if ($grupo === 'regalias') {
            if (empty($cliente)) {
                return ['ok' => false, 'error' => 'CLIENTE OBLIGATORIO PARA REGALÍAS.'];
            }
            if (empty($motivo_reg)) {
                return ['ok' => false, 'error' => 'MOTIVO OBLIGATORIO PARA REGALÍAS.'];
            }
        }
        if ($grupo === 'merma') {
            if (empty($causa_ajuste)) {
                return ['ok' => false, 'error' => 'CAUSA OBLIGATORIA PARA AJUSTES/MERMAS.'];
            }
        }

        // Check de vencimiento y stock disponible (según grupo y lotes FEFO)
        foreach ($productos as $p) {
            $pid = intval($p['id_producto'] ?? 0);
            $cant = intval($p['cantidad'] ?? 0);
            if ($cant < 1 || $cant > self::LIMITE_UNIDADES) {
                return ['ok' => false, 'error' => 'CANTIDAD INVÁLIDA POR PRODUCTO. RANGO: 1 A 999,999.'];
            }
            $precio_entrante = floatval($p['precio'] ?? 0);
            if ($grupo === 'venta' && ($precio_entrante <= 0 || $precio_entrante > 99999999.99)) {
                return ['ok' => false, 'error' => "PRECIO DE VENTA INVÁLIDO PARA PRODUCTO #$pid."];
            }
            if ($grupo === 'merma' && ($precio_entrante < 0 || $precio_entrante > 99999999.99)) {
                return ['ok' => false, 'error' => "PRECIO DE AJUSTE INVÁLIDO PARA PRODUCTO #$pid."];
            }
            if ($pid) {
                $pc = $this->db->fetchOne("SELECT stock_actual, fecha_vencimiento FROM productos WHERE id_producto = ?", [$pid]);
                if (!$pc) {
                    return ['ok' => false, 'error' => "PRODUCTO #$pid NO EXISTE."];
                }
                $tiene_lotes = (int)$this->db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$pid])['n'];
                if ($tiene_lotes > 0) {
                    $solo_venc = $grupo === 'merma';
                    $disp = stockLoteDisponible($this->db, $pid, $solo_venc);
                    if ($disp < $cant) {
                        $modo = $solo_venc ? 'VENCIDO' : 'VIGENTE';
                        return ['ok' => false, 'error' => "STOCK $modo INSUFICIENTE. Disponible: $disp, solicitado: $cant."];
                    }
                } else {
                    if ($grupo === 'merma') {
                        if (empty($pc['fecha_vencimiento']) || $pc['fecha_vencimiento'] > date('Y-m-d')) {
                            return ['ok' => false, 'error' => 'EN EL MODO AJUSTE SOLO SE PUEDEN SELECCIONAR PRODUCTOS VENCIDOS.'];
                        }
                    } elseif ($pc['fecha_vencimiento'] && $pc['fecha_vencimiento'] <= date('Y-m-d')) {
                        return ['ok' => false, 'error' => 'PRODUCTO VENCIDO. NO SE PUEDE VENDER.'];
                    }
                    if ((int)$pc['stock_actual'] < $cant) {
                        return ['ok' => false, 'error' => "STOCK INSUFICIENTE. Disponible: {$pc['stock_actual']}, solicitado: $cant."];
                    }
                }
            }
        }

        // REGALIAS: precio forzado a 0 para todos los productos
        if (mb_strtoupper(trim($tipo_nombre)) === 'REGALIAS') {
            foreach ($productos as &$p) $p['precio'] = 0;
            unset($p);
        }

        return [
            'ok' => true,
            'data' => [
                'productos_data'     => json_encode($productos),
                'cliente'            => $cliente,
                'rif_cliente'        => $rif_cliente ?: 'N/A',
                'id_cliente'         => $id_cliente,
                'nro_factura_manual' => 'PENDIENTE',
                'nro_control'        => $nro_control,
                'fecha_salida'       => $fecha_salida,
                'id_tipo_mov'        => $id_tipo_mov,
                'grupo'              => $grupo,
                'causa_ajuste'       => $causa_ajuste,
                'observaciones'      => $observaciones,
                'id_usuario'         => $idUsuario,
                'accion_salida'      => $accion_salida,
                'id_salida'          => $id_salida,
            ],
        ];
    }

    // Arma todos los valores derivados que necesita la nota imprimible:
    // alertas de vencimiento, totales, banderas del tipo y datos de empresa.
    // @return array (los mismos datos + valores calculados para la vista)
    public function armarNota(array $data, array $detalles, string $previewToken = ''): array
    {
        // Alerta de vencimiento (por cada producto)
        $alertas_venc = [];
        foreach ($detalles as $det) {
            $vf = $det['lote_vencimiento'] ?? ($det['fecha_vencimiento'] ?? null);
            if ($vf && $vf <= date('Y-m-d')) {
                $alertas_venc[] = ['tipo' => 'vencido', 'producto' => $det['nombre_producto'], 'fecha' => $vf];
            } elseif ($vf && $vf <= date('Y-m-d', strtotime('+7 days'))) {
                $alertas_venc[] = ['tipo' => 'proximo', 'producto' => $det['nombre_producto'], 'fecha' => $vf];
            }
        }

        $tipo_mov = strtoupper(trim($data['tipo_nombre'] ?? 'VENTA'));
        $es_venta = $tipo_mov === 'VENTA';
        $es_regalias = $tipo_mov === 'REGALIAS';
        $es_merma = in_array($tipo_mov, ['MERMAS', 'DAÑOS']);

        $subtotal = 0;
        foreach ($detalles as $det) {
            $subtotal += $det['cantidad'] * $det['precio_venta'];
        }
        $iva_pct = (float)getConfig('iva_porcentaje', '16');
        $iva = $es_venta ? ($subtotal * ($iva_pct / 100)) : 0;
        $total_neto = $subtotal + $iva;

        $empresa = getConfig('empresa_nombre', 'JV3000');
        $rif_emp = getConfig('empresa_rif', 'J-00000000-0');
        $tel_emp = getConfig('empresa_telefono', '');
        $dir_emp = getConfig('empresa_direccion', 'Naguanagua, Edo. Carabobo');
        $email_emp = getConfig('empresa_email', '');

        $badge_color = '#DC2626';
        $badge_label = $tipo_mov;
        if ($es_regalias) {
            $badge_color = '#D97706';
            $badge_label = 'REGALÍA';
        }
        if ($es_merma) $badge_color = '#6C757D';

        return [
            'data'           => $data,
            'detalles'       => $detalles,
            'preview_token'  => $previewToken,
            'alertas_venc'   => $alertas_venc,
            'es_venta'       => $es_venta,
            'es_regalias'    => $es_regalias,
            'es_merma'       => $es_merma,
            'tipo_mov'       => $tipo_mov,
            'subtotal'       => $subtotal,
            'iva_pct'        => $iva_pct,
            'iva'            => $iva,
            'total_neto'     => $total_neto,
            'empresa'        => $empresa,
            'rif_emp'        => $rif_emp,
            'tel_emp'        => $tel_emp,
            'dir_emp'        => $dir_emp,
            'email_emp'      => $email_emp,
            'badge_color'    => $badge_color,
            'badge_label'    => $badge_label,
            'hora_actual'    => date('H:i:s'),
        ];
    }
}
