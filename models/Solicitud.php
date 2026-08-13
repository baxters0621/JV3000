<?php

// ==========================================
// MODELO: Solicitud de Reposición
// ==========================================
// Única capa que consulta la base de datos.
// No sabe de pantallas ni de peticiones web.

/**
 * Solicitud: modelo del módulo de solicitudes de reposición.
 *
 * Única capa autorizada para consultar la base de datos. No sabe de
 * pantallas ni de peticiones web. Gestiona el alta y cancelación de
 * solicitudes de reposición y la consulta de pendientes e historial.
 */
class Solicitud extends Model
{
    /**
     * Regla de negocio: un producto no puede estar en dos solicitudes
     * Pendientes a la vez (evita compras dobles).
     */
    private const LIMITE_UNIDADES = 999999;

    /**
     * Solicitudes pendientes de atención.
     *
     * @return array Solicitudes Pendientes con solicitante, nº de productos y unidades.
     */
    public function obtenerPendientes(): array
    {
        return $this->db->fetchAll("
            SELECT s.id_solicitud, s.motivo, s.fecha_solicitud, s.estado, s.id_compra,
                   u.usuario AS solicitante,
                   COUNT(d.id_detalle) AS num_productos,
                   COALESCE(SUM(d.cantidad_solicitada),0) AS total_unidades
            FROM solicitudes_compra s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            LEFT JOIN detalle_solicitud_compra d ON s.id_solicitud = d.id_solicitud
            WHERE s.estado = 'Pendiente'
            GROUP BY s.id_solicitud
            ORDER BY s.fecha_solicitud ASC, s.id_solicitud ASC
        ");
    }

    /**
     * Historial de solicitudes atendidas / canceladas.
     *
     * Devuelve las solicitudes que ya no están Pendientes, ordenadas de más
     * reciente a más antiguo según fecha de atención, con su número de
     * factura asociado si fue atendida.
     *
     * @param int $limite Cantidad máxima de registros a devolver.
     * @return array Historial de solicitudes procesadas.
     */
    public function obtenerHistorial(int $limite = 30): array
    {
        return $this->db->fetchAll("
            SELECT s.id_solicitud, s.motivo, s.fecha_solicitud, s.estado, s.id_compra, s.fecha_atendida,
                   u.usuario AS solicitante,
                   COUNT(d.id_detalle) AS num_productos,
                   COALESCE(SUM(d.cantidad_solicitada),0) AS total_unidades,
                   c.nro_factura
            FROM solicitudes_compra s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            LEFT JOIN detalle_solicitud_compra d ON s.id_solicitud = d.id_solicitud
            LEFT JOIN compras c ON s.id_compra = c.id_compra
            WHERE s.estado != 'Pendiente'
            GROUP BY s.id_solicitud
            ORDER BY s.fecha_atendida DESC, s.id_solicitud DESC
            LIMIT " . (int)$limite . "
        ");
    }

    /**
     * Crea una solicitud desde Ventas (cuando no hay stock).
     *
     * Normaliza y valida cantidades, verifica que los productos existan y
     * estén activos y que no haya otra solicitud Pendiente con los mismos
     * productos. Inserta la solicitud y sus detalles en una transacción.
     *
     * @param array  $itemsRaw   Ítems crudos [['id_producto'=>.., 'cantidad'=>..]].
     * @param string $motivo     Motivo de la solicitud (por defecto 'Venta sin stock').
     * @param int    $idUsuario  Usuario solicitante.
     * @return array ['ok'=>bool, 'id_solicitud'?=>int, 'error'?=>string].
     */
    public function crear(array $itemsRaw, string $motivo, int $idUsuario): array
    {
        // Normalizar y validar cantidades
        $vistos = [];
        $detalles = [];
        foreach ($itemsRaw as $it) {
            $id_producto = intval($it['id_producto'] ?? 0);
            $cantidad = intval($it['cantidad'] ?? 0);
            if ($id_producto <= 0 || $cantidad < 1 || $cantidad > self::LIMITE_UNIDADES) continue;
            if (isset($vistos[$id_producto])) continue;
            $vistos[$id_producto] = true;
            $detalles[$id_producto] = $cantidad;
        }

        if (empty($detalles)) {
            return ['ok' => false, 'error' => 'DEBE INDICAR CANTIDADES VÁLIDAS (1 A 999,999).'];
        }

        $ids = array_keys($detalles);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $productos = $this->db->fetchAll(
            "SELECT id_producto, nombre_producto FROM productos WHERE id_producto IN ($placeholders) AND status = 'Activo'",
            $ids
        );
        if (count($productos) !== count($ids)) {
            return ['ok' => false, 'error' => 'ALGUNO DE LOS PRODUCTOS NO EXISTE O ESTÁ INACTIVO.'];
        }

        // Evitar duplicados pendientes
        $dups = $this->db->fetchAll("
            SELECT d.id_producto FROM detalle_solicitud_compra d
            JOIN solicitudes_compra s ON d.id_solicitud = s.id_solicitud
            WHERE s.estado = 'Pendiente' AND d.id_producto IN ($placeholders)
        ", $ids);

        if (!empty($dups)) {
            $mapa = [];
            foreach ($productos as $p) { $mapa[(int)$p['id_producto']] = $p['nombre_producto']; }
            $nombres = [];
            foreach ($dups as $d) { if (isset($mapa[(int)$d['id_producto']])) $nombres[] = $mapa[(int)$d['id_producto']]; }
            return ['ok' => false, 'error' => 'YA HAY UNA SOLICITUD PENDIENTE PARA: ' . implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '...' : '')];
        }

        $motivoFinal = trim($motivo) === '' ? 'Venta sin stock' : trim($motivo);

        $this->db->begin();
        try {
            $id_solicitud = $this->db->insert('solicitudes_compra', [
                'id_usuario_solicitante' => $idUsuario,
                'motivo'                 => substr($motivoFinal, 0, 150),
                'estado'                 => 'Pendiente',
            ]);
            foreach ($detalles as $idProd => $cant) {
                $this->db->insert('detalle_solicitud_compra', [
                    'id_solicitud'        => $id_solicitud,
                    'id_producto'         => $idProd,
                    'cantidad_solicitada' => $cant,
                ]);
            }
            registrarAuditoria('crear', "Solicitud de reposición #$id_solicitud (" . count($detalles) . " producto(s), $motivoFinal)");
            $this->db->commit();
            return ['ok' => true, 'id_solicitud' => $id_solicitud];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'ERROR AL CREAR LA SOLICITUD. INTENTA DE NUEVO.'];
        }
    }

    /**
     * Cancela una solicitud que sigue Pendiente.
     *
     * Verifica que la solicitud exista y esté Pendiente, la marca como
     * Cancelada y registra la auditoría.
     *
     * @param int $idSolicitud Identificador de la solicitud.
     * @return array ['ok'=>bool, 'error'?=>string].
     */
    public function cancelar(int $idSolicitud): array
    {
        $sol = $this->db->fetchOne("SELECT estado FROM solicitudes_compra WHERE id_solicitud = ?", [$idSolicitud]);
        if (!$sol || $sol['estado'] !== 'Pendiente') {
            return ['ok' => false, 'error' => 'LA SOLICITUD NO EXISTE O YA FUE PROCESADA.'];
        }
        $this->db->execute("UPDATE solicitudes_compra SET estado = 'Cancelada' WHERE id_solicitud = ?", [$idSolicitud]);
        registrarAuditoria('anular', "Solicitud de reposición #$idSolicitud cancelada");
        return ['ok' => true];
    }

    /**
     * KPIs de la cabecera (derivados de las consultas del modelo).
     *
     * Calcula solicitudes pendientes, productos solicitados, unidades y
     * atenciones completadas a partir de obtenerPendientes/obtenerHistorial.
     *
     * @return array ['pendientes'=>int, 'productos'=>int, 'unidades'=>int, 'atendidas'=>int].
     */
    public function kpis(): array
    {
        $pendientes = $this->obtenerPendientes();
        $historial = $this->obtenerHistorial();

        return [
            'pendientes' => count($pendientes),
            'productos'  => array_sum(array_map(fn($r) => (int)$r['num_productos'], $pendientes)),
            'unidades'   => array_sum(array_map(fn($r) => (int)$r['total_unidades'], $pendientes)),
            'atendidas'  => count(array_filter($historial, fn($r) => $r['estado'] === 'Atendida')),
        ];
    }
}
