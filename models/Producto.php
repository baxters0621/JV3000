<?php

// ==========================================
// MODELO: Producto
// ==========================================
// Única capa que consulta la base de datos.
// Incluye las reglas de negocio de inventario:
// toggle, baja por vencimiento y edición.
class Producto extends Model
{
    // Cambiar estado Activo/Inactivo
    // @return array ['ok'=>bool, 'mensaje'=>string]
    public function toggleStatus(int $idProducto): array
    {
        $p = $this->db->fetchOne("SELECT status FROM productos WHERE id_producto = ?", [$idProducto]);
        if (!$p) return ['ok' => false, 'mensaje' => 'PRODUCTO NO ENCONTRADO.'];
        $nuevo = $p['status'] === 'Activo' ? 'Inactivo' : 'Activo';
        $this->db->execute("UPDATE productos SET status = ? WHERE id_producto = ?", [$nuevo, $idProducto]);
        $accion = $nuevo === 'Activo' ? 'REACTIVADO' : 'DESACTIVADO';
        registrarAuditoria(strtolower($accion), "Producto $accion");
        return ['ok' => true, 'mensaje' => "PRODUCTO $accion."];
    }

    // Poner en cero los lotes vencidos y desactivar el producto
    public function bajaVencido(int $idProducto): void
    {
        $this->db->execute(
            "UPDATE lotes SET cantidad_restante = 0 WHERE id_producto = ? AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE()",
            [$idProducto]
        );
        $this->db->execute(
            "UPDATE productos p SET p.status = 'Inactivo', p.stock_actual = (
                SELECT COALESCE(SUM(l.cantidad_restante), 0) FROM lotes l WHERE l.id_producto = p.id_producto
             ) WHERE p.id_producto = ?",
            [$idProducto]
        );
        registrarAuditoria('baja_vencido', 'Producto dado de baja por vencimiento');
    }

    // Edición de un producto (solo admin)
    // @return array ['ok'=>bool, 'mensaje'=>string]
    public function editar(array $d): array
    {
        $id_prod = (int)$d['id_producto'];
        $stock_minimo = (int)$d['stock_minimo'];
        $stock_maximo = (int)$d['stock_maximo'];
        $precio_venta = (float)$d['precio_venta'];
        $precio_costo = (float)$d['precio_costo'];
        $status = $d['status'];
        $fecha_venc = $d['fecha_vencimiento'];
        $id_proveedor = (int)$d['id_proveedor'];

        if ($id_prod <= 0) return ['ok' => false, 'mensaje' => 'PRODUCTO INVÁLIDO.'];
        if ($stock_minimo <= 0) return ['ok' => false, 'mensaje' => 'STOCK MÍNIMO DEBE SER MAYOR A 0.'];
        if ($stock_maximo < 0) return ['ok' => false, 'mensaje' => 'CAPACIDAD MÁXIMA NO PUEDE SER NEGATIVA.'];
        if ($stock_maximo > 0 && $stock_maximo < $stock_minimo) {
            return ['ok' => false, 'mensaje' => 'LA CAPACIDAD MÁXIMA DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO (O 0 PARA HEREDAR LA DE LA CATEGORÍA).'];
        }
        if ($precio_venta <= 0) return ['ok' => false, 'mensaje' => 'PRECIO VENTA DEBE SER MAYOR A 0.'];
        if ($precio_costo <= 0) return ['ok' => false, 'mensaje' => 'PRECIO COSTO DEBE SER MAYOR A 0.'];
        if (!in_array($status, ['Activo', 'Inactivo'])) $status = 'Activo';
        if ($fecha_venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venc)) $fecha_venc = null;
        if ($id_proveedor <= 0) return ['ok' => false, 'mensaje' => 'DEBE SELECCIONAR UN PROVEEDOR.'];

        $this->db->execute(
            "UPDATE productos SET stock_minimo=?, stock_maximo=?, precio_venta=?, precio_costo=?, status=?, fecha_vencimiento=?, id_proveedor=? WHERE id_producto=?",
            [$stock_minimo, $stock_maximo, $precio_venta, $precio_costo, $status, $fecha_venc, $id_proveedor, $id_prod]
        );
        registrarAuditoria('editar', 'Producto modificado');
        return ['ok' => true, 'mensaje' => 'PRODUCTO ACTUALIZADO EN EL INVENTARIO.'];
    }

    // Listado paginado con categoría, capacidad y último proveedor
    public function listar(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.nombre as nombre_cat, COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as capacidad,
                COALESCE(pr.nombre_empresa, (
                    SELECT pr2.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr2 ON co.id_proveedor = pr2.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1
                )) as ultimo_proveedor
            FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor ORDER BY p.status DESC, p.nombre_producto ASC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    // Total de productos (para paginación)
    public function totalRegistros(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as total FROM productos")['total'] ?? 0);
    }

    // Proveedores activos para el select del modal
    public function proveedoresActivos(): array
    {
        return $this->db->fetchAll("SELECT id_proveedor, nombre_empresa FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa ASC");
    }
}
