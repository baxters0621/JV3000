<?php

// ==========================================
// CONTROLADOR: Reporte de Inventario
// ==========================================
// Página imprimible standalone (HTML completo
// con doctype/head/body, sin layout principal).
// Por eso usa renderRaw en lugar de view().

/**
 * ReporteInventarioController: genera el reporte imprimible de inventario.
 *
 * Página standalone con HTML completo (doctype/head/body) que no usa el
 * layout principal, por eso renderiza con renderRaw en lugar de view().
 */
class ReporteInventarioController extends Controller
{
    /**
     * Renderiza el reporte de inventario de productos activos.
     *
     * Consulta los productos activos al modelo ReporteInventario y los
     * entrega a la vista imprimible, sin layout del sistema.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoVenta();

        $modelo = new ReporteInventario();

        $this->renderRaw('reporte_inventario/index', [
            'productos' => $modelo->productosActivos(),
        ]);
    }
}
