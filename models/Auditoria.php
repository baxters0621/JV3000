<?php

// ==========================================
// MODELO: Auditoría (Historial)
// ==========================================
// Única capa que consulta la base de datos.
// Historial es inmutable por política: solo lectura.

/**
 * Auditoria: modelo del historial de auditoría.
 *
 * Única capa autorizada para consultar la tabla de auditoría. Por política
 * el historial es inmutable: este modelo solo expone consultas de lectura
 * (filtrado y paginación) y nunca escribe en esa tabla.
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
}
