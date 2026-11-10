<?php

// ==========================================
// MODELO: Compra
// ==========================================
// Única capa que consulta la base de datos.
// Registro de facturas de proveedores, anulación,
// consultas del tablero y prefill de solicitudes.
// (La reserva de existencias del proveedor ocurre aquí; el inventario
//  propio y los lotes solo se actualizan desde Recepción.)

/**
 * Compra: modelo del módulo de compras (facturas de proveedores).
 *
 * Única capa autorizada para consultar la base de datos. Maneja el registro
 * de facturas, la anulación, el marcado como pagada, las consultas del
 * tablero (filtros y KPIs) y el prefill de solicitudes.
 * Reserva existencias del catálogo del proveedor al registrar; la recepción
 * es la única que mueve el inventario propio y crea lotes.
 */
class Compra extends Model
{
    /** Bancos de Venezuela para Pago Móvil (código BIN → nombre). */
    private const BANCOS_VZLA = [
        '0102' => 'Banco de Venezuela',
        '0104' => 'Banco Provincial',
        '0105' => 'Banco Mercantil',
        '0108' => 'Banco BBVA Provincial',
        '0114' => 'Banco Continental',
        '0116' => 'Banco Plaza',
        '0128' => 'Banco Caroní',
        '0134' => 'Bancaribe',
        '0137' => 'Banco Sofitasa',
        '0151' => 'BFC Banco Fondo Común',
        '0156' => '100% Banco',
        '0157' => 'Banco Exterior',
        '0163' => 'Banco del Tesoro',
        '0166' => 'Banco Agrícola',
        '0168' => 'Bancrecer',
        '0169' => 'Mi Banco',
        '0171' => 'Banco Digital de los Trabajadores',
        '0172' => 'Bancamiga',
        '0173' => 'Banco Internacional',
        '0174' => 'Banplus',
        '0175' => 'Banco Copper National Bank',
        '0177' => 'Banco de las Fuerzas Armadas',
        '0178' => 'Banco Peal',
        '0182' => 'Banco Together Bank',
        '0183' => 'Novel Bank',
        '0185' => 'Banco Nova',
        '0186' => 'Spare Bank',
        '0187' => 'Banco Tp',
        '0188' => 'Activobank',
        '0189' => 'Bangente',
        '0190' => 'Banco Digital Credipag',
        '0191' => 'Banco Nacional de Crédito',
    ];

    /**
     * Facturas activas para el tablero, con filtros opcionales.
     *
     * Agrupa cada compra con el resumen de sus productos (lista, cantidad,
     * número de ítems) y el proveedor. Aplica filtro por proveedor y por
     * estado de pago. Limitado a las 100 facturas más recientes.
     *
     * @param int    $filtro_proveedor Id del proveedor (0 = sin filtro).
     * @param string $filtro_pago      Estado de pago (Pendiente/Parcial/Pagada o '').
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
     * Obtiene la cabecera y los ítems de una compra activa.
     *
     * Se usa en el modal "Ver detalle" del tablero de Compras. Solo expone
     * compras con status Activa, igual que el listado principal. Devuelve la
     * cabecera de la factura (proveedor con RIF, dirección y teléfono, número,
     * fecha e importes) y todas sus líneas con el total calculado.
     *
     * @param int $id_compra Identificador de la compra.
     * @return array|null ['compra' => array, 'items' => array] o null si no existe.
     */
    public function obtenerDetalle(int $id_compra): ?array
    {
        $compra = $this->db->fetchOne(
            "SELECT c.id_compra, c.nro_factura, c.nro_control, c.fecha_compra,
                    c.subtotal, c.iva, c.total, c.status_pago,
                    COALESCE(pr.nombre_empresa, 'S/P') AS proveedor,
                    COALESCE(pr.rif, '') AS proveedor_rif,
                    COALESCE(pr.direccion, '') AS proveedor_direccion,
                    COALESCE(pr.telefono, '') AS proveedor_telefono
             FROM compras c
             LEFT JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
             WHERE c.id_compra = ? AND c.status = 'Activa'",
            [$id_compra]
        );
        if (!$compra) {
            return null;
        }

        $items = $this->db->fetchAll(
            "SELECT dc.id_detalle, p.nombre_producto, p.sku,
                    dc.cantidad, dc.precio_costo,
                    ROUND(dc.cantidad * dc.precio_costo, 2) AS total_linea
             FROM detalle_compras dc
             INNER JOIN productos p ON p.id_producto = dc.id_producto
             WHERE dc.id_compra = ?
             ORDER BY dc.id_detalle ASC",
            [$id_compra]
        );

        $compra['id_compra'] = (int)$compra['id_compra'];
        $compra['subtotal'] = (float)$compra['subtotal'];
        $compra['iva'] = (float)$compra['iva'];
        $compra['total'] = (float)$compra['total'];
        foreach ($items as &$item) {
            $item['id_detalle'] = (int)$item['id_detalle'];
            $item['cantidad'] = (int)$item['cantidad'];
            $item['precio_costo'] = (float)$item['precio_costo'];
            $item['total_linea'] = (float)$item['total_linea'];
        }
        unset($item);

        return ['compra' => $compra, 'items' => $items];
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
     * Catálogo de productos activos agrupado por proveedor.
     *
     * @return array Mapa [id_proveedor => lista de productos y costo].
     */
    public function catalogoProductosPorProveedor(): array
    {
        $mapa = [];
        foreach ($this->db->fetchAll(
            "SELECT cc.id_proveedor, cc.id_producto, cc.costo, cc.stock_disponible, p.nombre_producto, p.sku
             FROM catalogo_costos cc
             JOIN productos p ON p.id_producto = cc.id_producto
             WHERE p.status = 'Activo'
             ORDER BY p.nombre_producto"
        ) as $fila) {
            $mapa[(int)$fila['id_proveedor']][] = [
                'id' => (int)$fila['id_producto'],
                'nombre' => (string)$fila['nombre_producto'],
                'sku' => (string)$fila['sku'],
                'costo' => (float)$fila['costo'],
                'stock_disponible' => (int)$fila['stock_disponible'],
            ];
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
        $por_pagar = (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa' AND status_pago IN ('Pendiente', 'Parcial')")['t'] ?? 0);
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
     * Consulta el estado de un número de factura y el último registrado.
     *
     * Devuelve si el número ya existe en cualquier compra (Activa o Anulada,
     * porque el índice único las cubre a ambas) y si pertenece al mismo
     * proveedor, además del máximo histórico registrado para el proveedor
     * indicado y el consecutivo sugerido. Se usa tanto en la validación en
     * vivo del formulario como en la generación automática del campo.
     *
     * @param string $nro_factura Número de factura a consultar (puede ir vacío).
     * @param int    $id_proveedor Proveedor seleccionado (0 = último global).
     * @return array{existe:bool,id_proveedor:?int,proveedor:?string,mismo_proveedor:bool,ultima:?string,siguiente:?string}
     */
    public function estadoFactura(string $nro_factura, int $id_proveedor = 0): array
    {
        $nro_factura = trim($nro_factura);
        $resultado = [
            'existe'          => false,
            'id_proveedor'    => null,
            'proveedor'       => null,
            'mismo_proveedor' => false,
            'ultima'          => null,
            'siguiente'       => null,
        ];

        if ($nro_factura !== '' && preg_match('/^\d{6,8}$/', $nro_factura)) {
            $fila = $this->db->fetchOne(
                "SELECT c.id_proveedor, pr.nombre_empresa
                 FROM compras c
                 LEFT JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                 WHERE c.nro_factura = ?
                 ORDER BY (c.id_proveedor = ?) DESC, c.id_compra DESC
                 LIMIT 1",
                [$nro_factura, $id_proveedor]
            );
            if ($fila) {
                $resultado['existe'] = true;
                $resultado['id_proveedor'] = (int)$fila['id_proveedor'];
                $resultado['proveedor'] = $fila['nombre_empresa'] ?? null;
                $resultado['mismo_proveedor'] = (int)$fila['id_proveedor'] === $id_proveedor;
            }
        }

        $sql = "SELECT MAX(CAST(nro_factura AS UNSIGNED)) AS ultima
                FROM compras
                WHERE nro_factura REGEXP '^[0-9]{6,8}$'";
        $parametros = [];
        if ($id_proveedor > 0) {
            $sql .= ' AND id_proveedor = ?';
            $parametros[] = $id_proveedor;
        }
        $ultima = $this->db->fetchOne($sql, $parametros);
        if ($ultima && $ultima['ultima'] !== null) {
            $maximo = (int)$ultima['ultima'];
            $resultado['ultima'] = str_pad((string)$maximo, 8, '0', STR_PAD_LEFT);
            if ($maximo < 99999999) {
                $resultado['siguiente'] = str_pad((string)($maximo + 1), 8, '0', STR_PAD_LEFT);
            }
        } else {
            $resultado['siguiente'] = '00000001';
        }

        return $resultado;
    }

    /**
     * Registra una compra (factura del proveedor + comprobante de pago).
     *
     * Valida proveedor (activo, RIF válido), número de factura único por
     * proveedor, formato de nro. de control, ítems (cantidad, precio y
     * vencimiento) y monto de pago. Inserta la compra con sus detalles
     * dentro de una transacción y, si se atiende una solicitud, la marca
     * Atendida.
     * Reserva existencias del proveedor, pero no mueve el inventario propio
     * ni crea lotes; esas operaciones corresponden a Recepción.
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
        $fecha_compra_valor = $purchaseFormData['fecha_compra'] ?? null;
        $fecha_compra_raw = is_scalar($fecha_compra_valor) ? trim((string)$fecha_compra_valor) : '';
        $fecha_compra_parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_compra_raw);
        $errores_fecha = DateTimeImmutable::getLastErrors();
        if (
            $fecha_compra_parsed === false
            || ($errores_fecha !== false && ($errores_fecha['warning_count'] > 0 || $errores_fecha['error_count'] > 0))
            || $fecha_compra_parsed->format('Y-m-d') !== $fecha_compra_raw
        ) {
            return ['ok' => false, 'mensaje' => 'FECHA DE COMPRA INVÁLIDA. SELECCIONE UNA FECHA REAL.'];
        }
        if ($fecha_compra_parsed < new DateTimeImmutable('1970-01-01') || $fecha_compra_parsed > new DateTimeImmutable('today')) {
            return ['ok' => false, 'mensaje' => 'LA FECHA DE COMPRA DEBE ESTAR ENTRE 01/01/1970 Y HOY.'];
        }
        $fecha_compra = $fecha_compra_parsed->format('Y-m-d') . ' 00:00:00';

        // Número de factura: manual, única por proveedor, 6-8 dígitos (formato SENIAT)
        $nro_factura = trim($purchaseFormData['nro_factura'] ?? '');
        if ($nro_factura === '') {
            return ['ok' => false, 'mensaje' => 'EL NÚMERO DE FACTURA ES OBLIGATORIO.'];
        }
        if (!preg_match('/^\d{6,8}$/', $nro_factura)) {
            return ['ok' => false, 'mensaje' => 'NRO. FACTURA INVÁLIDO. DEBE TENER ENTRE 6 Y 8 DÍGITOS.'];
        }
        if ($this->db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = ? LIMIT 1", [$id_proveedor, $nro_factura])) {
            return ['ok' => false, 'mensaje' => 'ESTA FACTURA YA FUE REGISTRADA ANTERIORMENTE PARA ESTE PROVEEDOR.'];
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
            if (!is_array($prod)) {
                return ['ok' => false, 'mensaje' => 'LÍNEA DE PRODUCTO INVÁLIDA.'];
            }
            $id_producto = intval($prod['id'] ?? 0);
            $cantidad = normalizarCantidadUnidades($prod['cantidad'] ?? null);
            if ($cantidad === null) {
                return ['ok' => false, 'mensaje' => 'CANTIDAD INVÁLIDA POR PRODUCTO. RANGO: 1 A 999,999.'];
            }
            if ($id_producto <= 0) {
                return ['ok' => false, 'mensaje' => 'PRODUCTO INVÁLIDO EN EL DETALLE DE LA COMPRA.'];
            }
            $prod_fila = $this->db->fetchOne(
                "SELECT p.sku, p.nombre_producto, p.status, cc.id_catalogo, cc.costo
                 FROM productos p
                 LEFT JOIN catalogo_costos cc
                    ON cc.id_producto = p.id_producto AND cc.id_proveedor = ?
                 WHERE p.id_producto = ?
                 LIMIT 1",
                [$id_proveedor, $id_producto]
            );
            if (!$prod_fila) {
                return ['ok' => false, 'mensaje' => "EL PRODUCTO #$id_producto NO EXISTE."];
            }
            if ($prod_fila['status'] !== 'Activo') {
                return ['ok' => false, 'mensaje' => "EL PRODUCTO {$prod_fila['nombre_producto']} ESTÁ INACTIVO."];
            }
            if (empty($prod_fila['id_catalogo'])) {
                return ['ok' => false, 'mensaje' => "EL PRODUCTO {$prod_fila['nombre_producto']} NO ESTÁ EN EL CATÁLOGO DEL PROVEEDOR. AGRÉGUELO AL CATÁLOGO ANTES DE REGISTRAR LA COMPRA."];
            }
            if (!is_numeric($prod_fila['costo'] ?? null)) {
                return ['ok' => false, 'mensaje' => "EL COSTO DEL CATÁLOGO ES INVÁLIDO PARA {$prod_fila['nombre_producto']}. REVISE EL CATÁLOGO DEL PROVEEDOR."];
            }
            $precio_costo = round((float)$prod_fila['costo'], 2);
            if ($precio_costo < 0 || $precio_costo > 99999999.99) {
                return ['ok' => false, 'mensaje' => "EL COSTO DEL CATÁLOGO ES INVÁLIDO PARA {$prod_fila['nombre_producto']}. RANGO: 0 A 99,999,999.99."];
            }
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

        // El alta registra la factura pendiente; los pagos se agregan después.
        // Se conservan pagos iniciales para integraciones anteriores.
        $metodo_pago = null;
        $monto_pago = round((float)($purchaseFormData['monto_pago'] ?? 0), 2);
        if ($monto_pago < 0) $monto_pago = 0;
        if ($monto_pago > 99999999.99) {
            return ['ok' => false, 'mensaje' => 'MONTO DE PAGO INVÁLIDO. MÁXIMO 99,999,999.99.'];
        }
        $status_pago = $monto_pago <= 0 ? 'Pendiente' : ($monto_pago >= $total ? 'Pagada' : 'Parcial');
        $fecha_pago = date('Y-m-d H:i:s');

        // Construir detalle_pago JSON según método
        $detalle_pago = null;
        if ($monto_pago > 0) {
            $metodo_pago = trim((string)($purchaseFormData['metodo_pago'] ?? ''));
            if (!in_array($metodo_pago, ['Efectivo', 'Transferencia', 'Cheque', 'Otro'], true)) {
                return ['ok' => false, 'mensaje' => 'MÉTODO DE PAGO INVÁLIDO.'];
            }
        }
        if ($monto_pago > 0 && $metodo_pago === 'Efectivo') {
            $tipo = trim($purchaseFormData['efectivo_tipo'] ?? '');
            if (!in_array($tipo, ['Bolivares', 'Dolar', 'Euro'], true)) {
                return ['ok' => false, 'mensaje' => 'SELECCIONE UN TIPO DE DIVISA VÁLIDO.'];
            }
            $detalle_pago = ['tipo' => $tipo];
            if ($tipo !== 'Bolivares') {
                $tasa = round((float)($purchaseFormData['tasa_cambio'] ?? 0), 4);
                if ($tasa <= 0) {
                    return ['ok' => false, 'mensaje' => 'LA TASA DE CAMBIO DEBE SER MAYOR A 0.'];
                }
                $moneda = $tipo === 'Dolar' ? 'USD' : 'EUR';
                $monto_original = round((float)($purchaseFormData['monto_original'] ?? 0), 2);
                if ($monto_original <= 0) {
                    return ['ok' => false, 'mensaje' => 'EL MONTO EN LA DIVISA ES OBLIGATORIO.'];
                }
                $equivalente_ves = round((float)($purchaseFormData['equivalente_ves'] ?? 0), 2);
                if ($equivalente_ves <= 0) {
                    return ['ok' => false, 'mensaje' => 'EL EQUIVALENTE EN VES ES INVÁLIDO.'];
                }
                $detalle_pago['tasa_cambio'] = $tasa;
                $detalle_pago['moneda'] = $moneda;
                $detalle_pago['monto_original'] = $monto_original;
                $detalle_pago['equivalente_ves'] = $equivalente_ves;
                $detalle_pago['fecha_tasa'] = date('Y-m-d');
                // Sobrescribir monto_pago con el equivalente en VES
                $monto_pago = $equivalente_ves;
                $status_pago = $monto_pago >= $total ? 'Pagada' : 'Parcial';
            }
        } elseif ($monto_pago > 0 && $metodo_pago === 'Transferencia') {
            $cedula = trim($purchaseFormData['pm_cedula'] ?? '');
            $telefono = trim($purchaseFormData['pm_telefono'] ?? '');
            $banco = trim($purchaseFormData['pm_banco'] ?? '');
            $referencia = trim($purchaseFormData['pm_referencia'] ?? '');
            if ($cedula === '') {
                return ['ok' => false, 'mensaje' => 'LA CÉDULA DEL BENEFICIARIO ES OBLIGATORIA.'];
            }
            if ($telefono === '') {
                return ['ok' => false, 'mensaje' => 'EL TELÉFONO ES OBLIGATORIO.'];
            }
            if (!in_array($banco, array_keys(self::BANCOS_VZLA), true)) {
                return ['ok' => false, 'mensaje' => 'SELECCIONE UN BANCO DESTINO VÁLIDO.'];
            }
            if ($referencia === '') {
                return ['ok' => false, 'mensaje' => 'EL NÚMERO DE REFERENCIA ES OBLIGATORIO.'];
            }
            $detalle_pago = [
                'cedula'    => $cedula,
                'telefono'  => $telefono,
                'banco'     => $banco,
                'referencia'=> $referencia,
            ];
        } elseif ($monto_pago > 0 && $metodo_pago === 'Cheque') {
            $cheque = trim($purchaseFormData['cheque_numero'] ?? '');
            if ($cheque === '') {
                return ['ok' => false, 'mensaje' => 'EL NÚMERO DE CHEQUE ES OBLIGATORIO.'];
            }
            $detalle_pago = ['numero' => $cheque];
        } elseif ($monto_pago > 0 && $metodo_pago === 'Otro') {
            $desc = trim($purchaseFormData['otro_descripcion'] ?? '');
            if ($desc === '') {
                return ['ok' => false, 'mensaje' => 'DESCRIBA LA FORMA DE PAGO.'];
            }
            $detalle_pago = ['descripcion' => $desc];
        }

        if ($monto_pago > $total) {
            return ['ok' => false, 'mensaje' => 'EL PAGO INICIAL NO PUEDE EXCEDER EL TOTAL DE LA COMPRA.'];
        }
        $monto_pago = min($monto_pago, $total);
        $status_pago = $monto_pago <= 0 ? 'Pendiente' : ($monto_pago >= $total ? 'Pagada' : 'Parcial');

        $this->db->begin();
        try {
            foreach ($items_validos as $it) {
                $this->db->execute(
                    "UPDATE catalogo_costos
                     SET stock_disponible = stock_disponible - ?
                     WHERE id_proveedor = ? AND id_producto = ? AND stock_disponible >= ?",
                    [$it['cantidad'], $id_proveedor, $it['id'], $it['cantidad']]
                );
                if ($this->db->affectedRows() !== 1) {
                    throw new DomainException("EXISTENCIAS INSUFICIENTES DEL PROVEEDOR PARA EL PRODUCTO #{$it['id']}. AJUSTE LA CANTIDAD O ACTUALICE SU CATÁLOGO.");
                }
            }

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
                'fecha_pago'       => $monto_pago > 0 ? $fecha_pago : null,
                'metodo_pago'      => $metodo_pago,
                'detalle_pago'     => $detalle_pago ? json_encode($detalle_pago) : null,
                'estado_recepcion' => 'Pendiente',
                'stock_proveedor_reservado' => 1,
            ]);

            if ($monto_pago > 0) {
                $this->db->execute(
                    "INSERT INTO pagos_compra (id_compra, id_usuario, monto, metodo_pago, detalle_pago, fecha_pago)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$compra_id, $id_usuario, $monto_pago, $metodo_pago, $detalle_pago ? json_encode($detalle_pago) : null, $fecha_pago]
                );
            }

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
                if ($this->db->affectedRows() !== 1) {
                    throw new DomainException('LA SOLICITUD YA FUE ATENDIDA POR OTRA COMPRA. NO SE REGISTRÓ ESTA COMPRA.');
                }
            }

            $this->db->commit();

            $msg = "COMPRA REGISTRADA: factura $nro_factura, " . count($items_validos) . " producto(s). ";
            if ($status_pago === 'Pagada') {
                $msg .= 'COMPROBANTE DE PAGO REGISTRADO.';
            } elseif ($status_pago === 'Parcial') {
                $msg .= 'ABONO REGISTRADO: ' . format_bs((float)$monto_pago) . ' DE ' . format_bs((float)$total) . '.';
            } else {
                $msg .= 'PAGO PENDIENTE (' . format_bs((float)$monto_pago) . ' DE ' . format_bs((float)$total) . ').';
            }
            return ['ok' => true, 'mensaje' => $msg];
        } catch (DomainException $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => $e->getMessage()];
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
        try {
            $this->db->begin();
            $compra = $this->db->fetchOne(
                "SELECT nro_factura, id_proveedor, status, stock_proveedor_reservado
                 FROM compras WHERE id_compra = ? FOR UPDATE",
                [$id_compra]
            );
            if (!$compra || $compra['status'] !== 'Activa') {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE O YA FUE ANULADA.'];
            }

            $recibida = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS n FROM lotes WHERE id_compra = ?",
                [$id_compra]
            )['n'] ?? 0);
            if ($recibida > 0) {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => 'NO SE PUEDE ANULAR: la compra ya tiene mercancía recibida en inventario.'];
            }

            if ((int)$compra['stock_proveedor_reservado'] === 1) {
                $detalles = $this->db->fetchAll(
                    'SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = ? ORDER BY id_producto FOR UPDATE',
                    [$id_compra]
                );
                if ($detalles === []) {
                    throw new RuntimeException('La compra reservada no tiene detalles para devolver al catálogo del proveedor.');
                }
                foreach ($detalles as $detalle) {
                    $cantidad = (int)$detalle['cantidad'];
                    if ($cantidad <= 0) {
                        throw new RuntimeException('La compra reservada tiene una cantidad inválida para devolver al catálogo.');
                    }
                    $this->db->execute(
                        "UPDATE catalogo_costos
                         SET stock_disponible = stock_disponible + ?
                         WHERE id_proveedor = ? AND id_producto = ?
                           AND stock_disponible <= 4294967295 - ?",
                        [$cantidad, (int)$compra['id_proveedor'], (int)$detalle['id_producto'], $cantidad]
                    );
                    if ($this->db->affectedRows() !== 1) {
                        throw new RuntimeException('No se pudo devolver el disponible reservado al catálogo del proveedor.');
                    }
                }
            }

            $this->db->execute(
                "UPDATE compras SET status = 'Anulada' WHERE id_compra = ? AND status = 'Activa'",
                [$id_compra]
            );
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('No se pudo actualizar el estado de la compra.');
            }

            registrarAuditoria('anular', "Compra #$id_compra (factura {$compra['nro_factura']}) anulada");
            $this->db->commit();
            return ['ok' => true, 'mensaje' => 'COMPRA ANULADA.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('Error al anular la compra #' . $id_compra . ': ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'ERROR AL ANULAR LA COMPRA. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
        }
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
