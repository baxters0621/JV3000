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
     * Registra un evento en la bitácora empresarial `logs_auditoria`.
     *
     * Es el punto único de escritura para todo el sistema. Normaliza la acción
     * a mayúsculas, resuelve el usuario de la sesión cuando no se indica uno y
     * conserva su nombre aunque la cuenta se elimine después. Si el usuario
     * indicado ya no existe, guarda la FK como NULL para no romper la
     * integridad referencial.
     *
     * @param string   $accion      Acción realizada (LOGIN, CREAR, EDITAR...).
     * @param string   $modulo      Módulo del sistema que originó el evento.
     * @param string   $descripcion Detalle legible para el administrador.
     * @param int|null $id_usuario  Usuario responsable; null usa la sesión.
     * @return void
     */
    public static function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        ?int $id_usuario = null
    ): void {
        $db = Database::getInstance();

        $accion = mb_strtoupper(trim($accion), 'UTF-8');
        if ($accion === '') {
            $accion = 'EVENTO';
        }
        $accion = mb_substr($accion, 0, 50, 'UTF-8');

        $modulo = trim($modulo);
        if ($modulo === '') {
            $modulo = 'Sistema';
        }
        $modulo = mb_substr($modulo, 0, 50, 'UTF-8');

        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            $descripcion = 'Sin descripción';
        }

        $id_final = $id_usuario ?? (int)($_SESSION['id_usuario'] ?? 0);
        $usuario_nombre = trim((string)($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? ''));

        if ($id_final > 0) {
            try {
                $usuario = $db->fetchOne(
                    'SELECT id_usuario, usuario FROM usuarios WHERE id_usuario = ? LIMIT 1',
                    [$id_final]
                );
            } catch (Throwable) {
                $usuario = null;
            }

            if ($usuario) {
                $id_final = (int)$usuario['id_usuario'];
                if ($id_usuario !== null || $usuario_nombre === '') {
                    $usuario_nombre = (string)$usuario['usuario'];
                }
            } else {
                $id_final = null;
            }
        } else {
            $id_final = null;
        }

        if ($usuario_nombre === '') {
            $usuario_nombre = 'Sistema';
        }
        $usuario_nombre = mb_substr($usuario_nombre, 0, 100, 'UTF-8');

        $db->execute(
            'INSERT INTO logs_auditoria
                (id_usuario, usuario_nombre, accion, modulo, descripcion, ip_origen)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $id_final,
                $usuario_nombre,
                $accion,
                $modulo,
                $descripcion,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

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
        $where = ["a.accion IN ('CREAR','EDITAR','ELIMINAR','ANULAR')"];
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
            $where[] = "a.descripcion LIKE ?";
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
        $row = $this->db->fetchOne("SELECT COUNT(*) as total FROM logs_auditoria a WHERE $where", $params);
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
            "SELECT a.* FROM logs_auditoria a WHERE $where ORDER BY a.fecha_hora DESC, a.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }

    /**
     * Construye el WHERE de la pantalla de Auditoría con filtros preparados.
     *
     * @param array $filtros Claves buscar, modulo y accion.
     * @return array{0: string, 1: array} Cláusula WHERE (vacía si no hay filtros) y parámetros.
     */
    private function whereEventos(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        $buscar = trim((string)($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $condiciones[] = '(a.usuario_nombre LIKE ? OR a.descripcion LIKE ? OR a.ip_origen LIKE ? OR a.modulo LIKE ? OR a.accion LIKE ?)';
            $patron = '%' . $buscar . '%';
            array_push($params, $patron, $patron, $patron, $patron, $patron);
        }

        $modulo = trim((string)($filtros['modulo'] ?? ''));
        if ($modulo !== '') {
            $condiciones[] = 'a.modulo = ?';
            $params[] = $modulo;
        }

        $accion = trim((string)($filtros['accion'] ?? ''));
        if ($accion !== '') {
            $condiciones[] = 'a.accion = ?';
            $params[] = $accion;
        }

        return [
            $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones),
            $params,
        ];
    }

    /**
     * Obtiene las métricas de las tarjetas superiores del módulo.
     *
     * @return array{total: int, hoy: int, ultimo_login: array|null}
     */
    public function resumen(): array
    {
        $total = $this->db->fetchOne('SELECT COUNT(*) AS total FROM logs_auditoria');
        $hoy = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM logs_auditoria
             WHERE fecha_hora >= CURDATE() AND fecha_hora < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
        );
        $ultimo_login = $this->db->fetchOne(
            "SELECT usuario_nombre, fecha_hora
             FROM logs_auditoria
             WHERE accion = 'LOGIN'
             ORDER BY fecha_hora DESC, id DESC
             LIMIT 1"
        );

        return [
            'total' => (int)($total['total'] ?? 0),
            'hoy' => (int)($hoy['total'] ?? 0),
            'ultimo_login' => $ultimo_login,
        ];
    }

    /**
     * Lista los módulos distintos presentes en la bitácora.
     *
     * @return array<int, string> Módulos ordenados alfabéticamente.
     */
    public function modulosDisponibles(): array
    {
        $filas = $this->db->fetchAll(
            "SELECT DISTINCT modulo FROM logs_auditoria WHERE modulo <> '' ORDER BY modulo ASC"
        );
        return array_map(static fn(array $fila): string => (string)$fila['modulo'], $filas);
    }

    /**
     * Lista las acciones distintas presentes en la bitácora.
     *
     * @return array<int, string> Acciones ordenadas alfabéticamente.
     */
    public function accionesDisponibles(): array
    {
        $filas = $this->db->fetchAll(
            "SELECT DISTINCT accion FROM logs_auditoria WHERE accion <> '' ORDER BY accion ASC"
        );
        return array_map(static fn(array $fila): string => (string)$fila['accion'], $filas);
    }

    /**
     * Cuenta los eventos que coinciden con los filtros de la pantalla.
     *
     * @param array $filtros Claves buscar, modulo y accion.
     * @return int Total de eventos filtrados.
     */
    public function totalEventosFiltrados(array $filtros): int
    {
        [$where, $params] = $this->whereEventos($filtros);
        $fila = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM logs_auditoria a $where",
            $params
        );
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Devuelve una página de eventos, del más reciente al más antiguo.
     *
     * @param array $filtros Claves buscar, modulo y accion.
     * @param int   $pagina  Página solicitada, desde 1.
     * @param int   $limite  Eventos por página.
     * @return array<int, array<string, mixed>> Eventos de la página.
     */
    public function listarEventos(array $filtros, int $pagina, int $limite): array
    {
        [$where, $params] = $this->whereEventos($filtros);
        $offset = max(0, ($pagina - 1) * $limite);

        return $this->db->fetchAll(
            "SELECT a.id, a.id_usuario, a.usuario_nombre, a.accion, a.modulo,
                    a.descripcion, a.ip_origen, a.fecha_hora
             FROM logs_auditoria a
             $where
             ORDER BY a.fecha_hora DESC, a.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limite, $offset])
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
