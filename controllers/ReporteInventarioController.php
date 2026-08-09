<?php

// ==========================================
// CONTROLADOR: Reporte de Inventario
// ==========================================
// Página imprimible standalone (HTML completo
// con doctype/head/body, sin layout principal).
// Por eso usa renderRaw en lugar de view().
class ReporteInventarioController extends Controller
{
    // GET  index.php?url=reporte_inventario
    public function index(): void
    {
        Security::verificarPermisoVenta();

        $modelo = new ReporteInventario();

        $this->renderRaw('reporte_inventario/index', [
            'productos' => $modelo->productosActivos(),
        ]);
    }
}
