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
     * Valida producto, stock mínimo/máximo, precios y estado; luego
     * actualiza el registro y registra la auditoría. El costo se recalcula
     * solo en la recepción de mercancía (promedio ponderado), no aquí.
     *
     * @param array $datosProducto Datos del formulario (id_producto, stocks, precios...).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function editar(array $datosProducto): array
    {
        $idProducto = (int)$datosProducto['id_producto'];
        $stockMinimo = (int)$datosProducto['stock_minimo'];
        $stockMaximo = (int)$datosProducto['stock_maximo'];
        $precioVentaRaw = trim((string)$datosProducto['precio_venta']);
        $precioVenta = (float)$precioVentaRaw;
        $status = $datosProducto['status'];
        $fechaVencimiento = $datosProducto['fecha_vencimiento'] ?? null;

        if ($idProducto <= 0) return ['ok' => false, 'mensaje' => 'PRODUCTO INVÁLIDO.'];
        // Stocks: enteros puros en rango (rechaza decimales, negativos y desbordes)
        if (!preg_match('/^\d{1,5}$/', trim((string)$datosProducto['stock_minimo'])) || $stockMinimo <= 0) {
            return ['ok' => false, 'mensaje' => 'STOCK MÍNIMO DEBE SER UN ENTERO ENTRE 1 Y 99.999.'];
        }
        if (!preg_match('/^\d{1,6}$/', trim((string)$datosProducto['stock_maximo']))) {
            return ['ok' => false, 'mensaje' => 'CAPACIDAD MÁXIMA DEBE SER 0 (HEREDAR CATEGORÍA) O UN ENTERO HASTA 999.999.'];
        }
        if ($stockMaximo > 0 && $stockMaximo < $stockMinimo) {
            return ['ok' => false, 'mensaje' => 'LA CAPACIDAD MÁXIMA DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO (O 0 PARA HEREDAR LA DE LA CATEGORÍA).'];
        }
        if (!preg_match('/^(?:0|[1-9]\d{0,4})\.\d{2}$/', $precioVentaRaw) || !is_finite($precioVenta) || $precioVenta < 0.01 || $precioVenta > 99999.99) return ['ok' => false, 'mensaje' => 'PRECIO VENTA DEBE TENER DOS DECIMALES Y ESTAR ENTRE 0,01 Y 99.999,99.'];
        if (!in_array($status, ['Activo', 'Inactivo'])) $status = 'Activo';
        if ($fechaVencimiento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) $fechaVencimiento = null;

        $this->db->execute(
            "UPDATE productos SET stock_minimo=?, stock_maximo=?, precio_venta=?, status=?, fecha_vencimiento=? WHERE id_producto=?",
            [$stockMinimo, $stockMaximo, $precioVenta, $status, $fechaVencimiento, $idProducto]
        );
        registrarAuditoria('editar', 'Producto modificado');
        return ['ok' => true, 'mensaje' => 'PRODUCTO ACTUALIZADO EN EL INVENTARIO.'];
    }

    /**
     * Listado paginado con categoría, capacidad y proveedores del catálogo.
     *
     * Devuelve los productos ordenados (activos primero) con el nombre de la
     * categoría, la capacidad efectiva (propia, de categoría o 100) y la
     * lista de proveedores que lo suministran según el catálogo de costos.
     * Paginado con LIMIT/OFFSET.
     *
     * @param int $limit  Registros por página.
     * @param int $offset Desplazamiento para la paginación.
     * @return array Productos de la página solicitada.
     */
    public function listar(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.nombre as nombre_cat, COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as capacidad,
                -- Vencimiento real del producto = el lote con stock que vence primero (FEFO);
                -- si no tiene lotes activos se usa la fecha legacy del producto
                COALESCE((
                    SELECT MIN(l.fecha_vencimiento) FROM lotes l
                    WHERE l.id_producto = p.id_producto AND l.cantidad_restante > 0
                ), p.fecha_vencimiento) as fecha_vencimiento,
                -- Proveedores que lo suministran según el catálogo de costos
                (SELECT GROUP_CONCAT(pr.nombre_empresa SEPARATOR ', ')
                    FROM catalogo_costos cc JOIN proveedores pr ON cc.id_proveedor = pr.id_proveedor
                    WHERE cc.id_producto = p.id_producto AND pr.status = 'Activo'
                ) as proveedores
            FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria ORDER BY p.status DESC, p.nombre_producto ASC LIMIT ? OFFSET ?",
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
}
