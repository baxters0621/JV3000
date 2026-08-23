<?php

// ==========================================
// MODELO: Producto
// ==========================================
// Única capa que consulta la base de datos.
// Incluye las reglas de negocio de inventario:
// toggle, baja por vencimiento y edición.

/**
 * Producto: modelo del módulo de productos/inventario.
 *
 * Única capa autorizada para consultar la base de datos. Contiene las reglas
 * de negocio del inventario: cambio de estado, baja por vencimiento (lotes
 * vencidos en cero y producto desactivado) y edición de producto (solo admin).
 */
class Producto extends Model
{
    /**
     * Cambia el estado Activo/Inactivo de un producto.
     *
     * Consulta el estado actual, lo invierte y actualiza el producto,
     * registrando la auditoría con la acción reactivado/desactivado.
     *
     * @param int $idProducto Identificador del producto.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function toggleStatus(int $idProducto): array
    {
        $producto = $this->db->fetchOne("SELECT status FROM productos WHERE id_producto = ?", [$idProducto]);
        if (!$producto) return ['ok' => false, 'mensaje' => 'PRODUCTO NO ENCONTRADO.'];
        $nuevoStatus = $producto['status'] === 'Activo' ? 'Inactivo' : 'Activo';
        $this->db->execute("UPDATE productos SET status = ? WHERE id_producto = ?", [$nuevoStatus, $idProducto]);
        $accion = $nuevoStatus === 'Activo' ? 'REACTIVADO' : 'DESACTIVADO';
        registrarAuditoria(strtolower($accion), "Producto $accion");
        return ['ok' => true, 'mensaje' => "PRODUCTO $accion."];
    }

    /**
     * Pone en cero los lotes vencidos y desactiva el producto.
     *
     * Actualiza a 0 la cantidad restante de todos los lotes vencidos del
     * producto y recalcula su stock_actual según lo que quede en lotes,
     * desactivándolo. Registra la auditoría de la baja.
     *
     * @param int $idProducto Identificador del producto.
     * @return void
     */
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

    /**
     * Edición de un producto (solo admin).
     *
     * Valida producto, stock mínimo/máximo, precios, estado, fecha de
     * vencimiento y proveedor; luego actualiza el registro y registra la
     * auditoría.
     *
     * @param array $datosProducto Datos del formulario (id_producto, stocks, precios...).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function editar(array $datosProducto): array
    {
        $idProducto = (int)$datosProducto['id_producto'];
        $stockMinimo = (int)$datosProducto['stock_minimo'];
        $stockMaximo = (int)$datosProducto['stock_maximo'];
        $precioVenta = (float)$datosProducto['precio_venta'];
        $precioCosto = (float)$datosProducto['precio_costo'];
        $status = $datosProducto['status'];
        $fechaVencimiento = $datosProducto['fecha_vencimiento'];
        $idProveedor = (int)$datosProducto['id_proveedor'];

        if ($idProducto <= 0) return ['ok' => false, 'mensaje' => 'PRODUCTO INVÁLIDO.'];
        if ($stockMinimo <= 0) return ['ok' => false, 'mensaje' => 'STOCK MÍNIMO DEBE SER MAYOR A 0.'];
        if ($stockMaximo < 0) return ['ok' => false, 'mensaje' => 'CAPACIDAD MÁXIMA NO PUEDE SER NEGATIVA.'];
        if ($stockMaximo > 0 && $stockMaximo < $stockMinimo) {
            return ['ok' => false, 'mensaje' => 'LA CAPACIDAD MÁXIMA DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO (O 0 PARA HEREDAR LA DE LA CATEGORÍA).'];
        }
        if (!is_finite($precioVenta) || $precioVenta <= 0 || $precioVenta > 999999) return ['ok' => false, 'mensaje' => 'PRECIO VENTA DEBE ESTAR ENTRE 0,01 Y 999.999,00.'];
        if (!is_finite($precioCosto) || $precioCosto <= 0 || $precioCosto > 999999) return ['ok' => false, 'mensaje' => 'PRECIO COSTO DEBE ESTAR ENTRE 0,01 Y 999.999,00.'];
        if ($precioVenta < $precioCosto) return ['ok' => false, 'mensaje' => 'EL PRECIO DE VENTA DEBE SER MAYOR O IGUAL AL PRECIO COSTO.'];
        if (!in_array($status, ['Activo', 'Inactivo'])) $status = 'Activo';
        if ($fechaVencimiento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) $fechaVencimiento = null;
        if ($idProveedor <= 0) return ['ok' => false, 'mensaje' => 'DEBE SELECCIONAR UN PROVEEDOR.'];

        $this->db->execute(
            "UPDATE productos SET stock_minimo=?, stock_maximo=?, precio_venta=?, precio_costo=?, status=?, fecha_vencimiento=?, id_proveedor=? WHERE id_producto=?",
            [$stockMinimo, $stockMaximo, $precioVenta, $precioCosto, $status, $fechaVencimiento, $idProveedor, $idProducto]
        );
        registrarAuditoria('editar', 'Producto modificado');
        return ['ok' => true, 'mensaje' => 'PRODUCTO ACTUALIZADO EN EL INVENTARIO.'];
    }

    /**
     * Listado paginado con categoría, capacidad y último proveedor.
     *
     * Devuelve los productos ordenados (activos primero) con el nombre de la
     * categoría, la capacidad efectiva (propia, de categoría o 100) y el
     * último proveedor (directo o por compras). Paginado con LIMIT/OFFSET.
     *
     * @param int $limit  Registros por página.
     * @param int $offset Desplazamiento para la paginación.
     * @return array Productos de la página solicitada.
     */
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

    /**
     * Total de productos (para la paginación).
     *
     * @return int Cantidad total de productos en la tabla.
     */
    public function totalRegistros(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as total FROM productos")['total'] ?? 0);
    }

    /**
     * Proveedores activos para el select del modal de edición.
     *
     * @return array Lista de proveedores activos (id_proveedor, nombre_empresa).
     */
    public function proveedoresActivos(): array
    {
        return $this->db->fetchAll("SELECT id_proveedor, nombre_empresa FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa ASC");
    }
}
