<?php

// ==========================================
// MODELO: Consultas de auditoría y operaciones del Historial
// ==========================================
// Conserva la consulta interna de auditoría y lee las operaciones
// transaccionales que muestra la pantalla Historial.

/**
 * Auditoria: consultas de auditoría interna y operaciones del Historial.
 *
 * Este modelo conserva las consultas de solo lectura de la tabla de auditoría
 * y obtiene por separado compras y ventas desde sus tablas transaccionales.
 * La nueva pantalla de Historial no mezcla ambos tipos de información.
 */
class Auditoria extends Model
{
    /**
     * Construye la cláusula WHERE y sus parámetros a partir de filtros.
     *
     * Combina dinámicamente los filtros de usuario, acción, rango de fechas
     * y detalle, siempre restringiendo a las acciones relevantes
     * (crear/editar/eliminar/anular). Los valores se pasan como parámetros
     * preparados para evitar inyección SQL.
     *
     * @param array $filtros Arreglo con las claves usuario/accion/desde/hasta/detalle.
     * @return array [string $where, array $params].
     */
    private function where(array $filtros): array
    {
        $where = ["a.accion IN ('crear','editar','eliminar','anular')"];
        $params = [];

        if (($filtros['usuario'] ?? '') !== '') {
            $where[] = "a.usuario_nombre LIKE ?";
            $params[] = '%' . $filtros['usuario'] . '%';
        }
        if (($filtros['accion'] ?? '') !== '') {
            $where[] = "a.accion = ?";
            $params[] = $filtros['accion'];
        }
        if (($filtros['desde'] ?? '') !== '') {
            $where[] = "a.fecha_hora >= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (($filtros['hasta'] ?? '') !== '') {
            $where[] = "a.fecha_hora <= ?";
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }
        if (($filtros['detalle'] ?? '') !== '') {
            $where[] = "a.detalle LIKE ?";
            $params[] = '%' . $filtros['detalle'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Total de registros que coinciden con los filtros.
     *
     * Usado para mostrar el total y calcular la paginación del historial.
     *
     * @param array $filtros Filtros de búsqueda (ver where()).
     * @return int Cantidad total de registros.
     */
    public function totalRegistros(array $filtros): int
    {
        [$where, $params] = $this->where($filtros);
        $row = $this->db->fetchOne("SELECT COUNT(*) as total FROM auditoria a WHERE $where", $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Número total de páginas para la paginación del historial.
     *
     * Calcula el techo de (total registros / límite por página), con mínimo 1.
     *
     * @param array $filtros Filtros de búsqueda (ver where()).
     * @param int   $limit   Registros por página.
     * @return int Cantidad de páginas.
     */
    public function totalPaginas(array $filtros, int $limit): int
    {
        return max(1, (int)ceil($this->totalRegistros($filtros) / $limit));
    }

    /**
     * Página de registros del historial, ordenada de más reciente a más antiguo.
     *
     * Aplica los filtros, calcula el offset según la página y devuelve los
     * registros de la página solicitada con paginación LIMIT/OFFSET.
     *
     * @param array $filtros Filtros de búsqueda (ver where()).
     * @param int   $page    Número de página (1 = primera).
     * @param int   $limit   Registros por página.
     * @return array Registros de la página solicitada.
     */
    public function listar(array $filtros, int $page, int $limit): array
    {
        [$where, $params] = $this->where($filtros);
        $offset = ($page - 1) * $limit;
        return $this->db->fetchAll(
            "SELECT a.* FROM auditoria a WHERE $where ORDER BY a.fecha_hora DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }

    /**
     * Construye la consulta unificada de compras y ventas visibles para el rol.
     *
     * Los eventos de auditoría no se mezclan con las operaciones; se consultan
     * únicamente las compras y ventas que existen en sus tablas transaccionales.
     *
     * @param array $filtros Filtros validados por el controlador.
     * @param bool $incluir_compras Indica si el usuario puede consultar compras.
     * @param bool $incluir_ventas Indica si el usuario puede consultar ventas.
     * @return array{0: string, 1: array} Consulta derivada y parámetros.
     */
    private function consultaOperaciones(array $filtros, bool $incluir_compras, bool $incluir_ventas): array
    {
        $consultas = [];
        $params = [];
        $tipo = $filtros['tipo'] ?? '';

        if ($incluir_compras && $tipo !== 'venta') {
            $consultas[] = "
                SELECT
                    'compra' AS tipo,
                    c.id_compra AS id_operacion,
                    c.fecha_compra AS fecha,
                    c.nro_factura AS referencia,
                    COALESCE(pr.nombre_empresa, 'Proveedor no disponible') AS contraparte,
                    COALESCE(u.usuario, 'Usuario no disponible') AS usuario,
                    GROUP_CONCAT(DISTINCT p.nombre_producto ORDER BY p.nombre_producto SEPARATOR ', ') AS productos,
                    COALESCE(SUM(dc.cantidad), 0) AS cantidad_total,
                    c.total AS monto_total,
                    c.status AS estado,
                    c.status_pago AS estado_pago,
                    c.estado_recepcion AS estado_recepcion
                FROM compras c
                LEFT JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
                LEFT JOIN detalle_compras dc ON dc.id_compra = c.id_compra
                LEFT JOIN productos p ON p.id_producto = dc.id_producto
                GROUP BY c.id_compra
            ";
        }

        if ($incluir_ventas && $tipo !== 'compra') {
            $consultas[] = "
                SELECT
                    'venta' AS tipo,
                    s.id_salida AS id_operacion,
                    s.fecha_salida AS fecha,
                    COALESCE(NULLIF(s.nro_factura_manual, ''), CONCAT('Venta #', s.id_salida)) AS referencia,
                    COALESCE(NULLIF(s.cliente, ''), 'Venta General') AS contraparte,
                    COALESCE(u.usuario, 'Usuario no disponible') AS usuario,
                    GROUP_CONCAT(DISTINCT p.nombre_producto ORDER BY p.nombre_producto SEPARATOR ', ') AS productos,
                    COALESCE(SUM(ds.cantidad), 0) AS cantidad_total,
                    COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS monto_total,
                    s.status AS estado,
                    NULL AS estado_pago,
                    NULL AS estado_recepcion
                FROM salidas s
                INNER JOIN tipos_movimientos tm
                    ON tm.id_tipo_mov = s.id_tipo_mov
                    AND tm.tipo_movimiento = 'Salida'
                    AND UPPER(TRIM(tm.nombre)) = 'VENTA'
                LEFT JOIN usuarios u ON u.id_usuario = s.id_usuario
                LEFT JOIN detalle_salidas ds ON ds.id_salida = s.id_salida
                LEFT JOIN productos p ON p.id_producto = ds.id_producto
                GROUP BY s.id_salida
            ";
        }

        if ($consultas === []) {
            return [
                "SELECT '' AS tipo, 0 AS id_operacion, NULL AS fecha, '' AS referencia,
                        '' AS contraparte, '' AS usuario, '' AS productos, 0 AS cantidad_total,
                        0 AS monto_total, '' AS estado, NULL AS estado_pago, NULL AS estado_recepcion
                 WHERE 1 = 0",
                [],
            ];
        }

        $sql = implode(' UNION ALL ', $consultas);
        $condiciones = [];

        if (($filtros['estado'] ?? '') !== '') {
            $condiciones[] = 'operaciones.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (($filtros['desde'] ?? '') !== '') {
            $condiciones[] = 'operaciones.fecha >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (($filtros['hasta'] ?? '') !== '') {
            $condiciones[] = 'operaciones.fecha < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filtros['hasta'] . ' 00:00:00';
        }
        if (($filtros['buscar'] ?? '') !== '') {
            $condiciones[] = "(operaciones.referencia LIKE ? OR operaciones.contraparte LIKE ? OR operaciones.usuario LIKE ? OR operaciones.productos LIKE ?)";
            $buscar = '%' . $filtros['buscar'] . '%';
            array_push($params, $buscar, $buscar, $buscar, $buscar);
        }

        if ($condiciones !== []) {
            $sql = "SELECT * FROM ($sql) operaciones WHERE " . implode(' AND ', $condiciones);
        } else {
            $sql = "SELECT * FROM ($sql) operaciones";
        }

        return [$sql, $params];
    }

    /**
     * Cuenta las operaciones que coinciden con los filtros y permisos.
     *
     * @param array $filtros Filtros validados por el controlador.
     * @param bool $incluir_compras Indica si el usuario puede consultar compras.
     * @param bool $incluir_ventas Indica si el usuario puede consultar ventas.
     * @return int Cantidad de operaciones.
     */
    public function totalOperaciones(array $filtros, bool $incluir_compras, bool $incluir_ventas): int
    {
        [$consulta, $params] = $this->consultaOperaciones($filtros, $incluir_compras, $incluir_ventas);
        $fila = $this->db->fetchOne("SELECT COUNT(*) AS total FROM ($consulta) conteo_operaciones", $params);
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Devuelve una página de operaciones recientes de compras y ventas.
     *
     * @param array $filtros Filtros validados por el controlador.
     * @param int $page Página solicitada, comenzando en uno.
     * @param int $limit Máximo de operaciones por página.
     * @param bool $incluir_compras Indica si el usuario puede consultar compras.
     * @param bool $incluir_ventas Indica si el usuario puede consultar ventas.
     * @return array<int, array<string, mixed>> Operaciones ordenadas de más recientes a más antiguas.
     */
    public function listarOperaciones(
        array $filtros,
        int $page,
        int $limit,
        bool $incluir_compras,
        bool $incluir_ventas
    ): array {
        [$consulta, $params] = $this->consultaOperaciones($filtros, $incluir_compras, $incluir_ventas);
        $offset = ($page - 1) * $limit;
        return $this->db->fetchAll(
            "$consulta ORDER BY operaciones.fecha DESC, operaciones.tipo ASC, operaciones.id_operacion DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }
}
