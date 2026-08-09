<?php

// ==========================================
// MODELO: Auditoría (Historial)
// ==========================================
// Única capa que consulta la base de datos.
// Historial es inmutable por política: solo lectura.
class Auditoria extends Model
{
    // Construye la cláusula WHERE a partir de filtros
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

    // Total de registros que coinciden con los filtros
    public function totalRegistros(array $filtros): int
    {
        [$where, $params] = $this->where($filtros);
        $row = $this->db->fetchOne("SELECT COUNT(*) as total FROM auditoria a WHERE $where", $params);
        return (int)($row['total'] ?? 0);
    }

    // Número total de páginas
    public function totalPaginas(array $filtros, int $limit): int
    {
        return max(1, (int)ceil($this->totalRegistros($filtros) / $limit));
    }

    // Página de registros ordenada de más reciente a más antiguo
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
