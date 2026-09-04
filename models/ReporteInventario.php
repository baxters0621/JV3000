<?php

// ==========================================
// MODELO: Reporte de Inventario
// ==========================================
// Productos activos con stock, valorizado a
// costo y a venta, último proveedor (directo o
// por compras) y capacidad efectiva.
// Sin HTML: solo datos.

/**
 * ReporteInventario: modelo del reporte imprimible de inventario.
 *
 * Devuelve los productos activos valorizados a costo y a venta, con el
 * último proveedor (directo o por compras) y la capacidad efectiva.
 * Sin HTML: solo datos para la vista imprimible.
 */
class ReporteInventario extends Model
{
    /**
     * Productos activos con valorización, proveedor y capacidad.
     *
     * @return array Productos activos enriquecidos para el reporte.
     */
    public function productosActivos(): array
    {
        return $this->db->fetchAll("
            SELECT p.*, c.nombre as nombre_cat,
                COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as capacidad,
                (SELECT pr2.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr2 ON co.id_proveedor = pr2.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1) as ultimo_proveedor,
                (p.stock_actual * p.precio_costo) as valor_costo,
                (p.stock_actual * p.precio_venta) as valor_venta
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            WHERE p.status = 'Activo'
            ORDER BY p.nombre_producto ASC
        ");
    }
}
