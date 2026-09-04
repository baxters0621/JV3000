<?php

// ==========================================
// CONTROLADOR: Dashboard (Panel de Inicio)
// ==========================================
// Renderiza el panel de inicio con KPIs, alertas
// y accesos rápidos. Usa renderRaw para salida
// HTML completa sin layout principal.

/**
 * DashboardController: panel de inicio del sistema.
 *
 * Renderiza el dashboard con indicadores clave, alertas de stock,
 * actividad reciente y accesos rápidos según rol.
 */
class DashboardController extends Controller
{
    /**
     * Panel de inicio.
     *
     * Calcula KPIs, alertas por rol y actividad reciente,
     * y renderiza la vista standalone del dashboard.
     *
     * @return void
     */
    public function index(): void
    {
        // Seguridad: validar sesión (init.php ya lo hizo, pero por si se accede directo)
        if (!isset($_SESSION['id_rol'])) {
            header('Location: ' . BASE_PATH . 'login/login.php');
            exit;
        }

        $db = Database::getInstance();

        $nombre_user = $_SESSION['usuario'] ?? 'Usuario';
        $rol_user_id = (int)($_SESSION['id_rol'] ?? 0);
        $rol_data = $db->fetchOne("SELECT nombre_rol FROM roles WHERE id_rol = ?", [$rol_user_id]);
        $rol_user = $rol_data ? $rol_data['nombre_rol'] : 'Sin Rol';

        $esAdmin = ($rol_user_id === 1);
        $esOpVentas = ($rol_user_id === 3);
        $esOpCarga = ($rol_user_id === 2);

        $fecha_hoy = date('d/m/Y');

        $alertas = jv_alertas_por_rol($rol_user_id);

        // Saludo contextual según la hora local de Venezuela (formato 12h)
        $hora_ve = (int)(new DateTime('now', new DateTimeZone('America/Caracas')))->format('G');
        if ($hora_ve >= 5 && $hora_ve < 12) {
            $saludo = 'Buenos días';
        } elseif ($hora_ve >= 12 && $hora_ve < 18) {
            $saludo = 'Buenas tardes';
        } else {
            $saludo = 'Buenas noches';
        }

        // Iniciales del usuario para el avatar
        $iniciales = mb_strtoupper(mb_substr($nombre_user, 0, 1), 'UTF-8');
        if (str_contains($nombre_user, '_')) {
            $partes = explode('_', $nombre_user);
            $iniciales .= mb_strtoupper(mb_substr($partes[count($partes) - 1], 0, 1), 'UTF-8');
        }

        // Datos del dashboard
        $datos = $this->obtenerDatosDashboard($db);

        // Endpoint AJAX
        if (isset($_GET['ajax_dashboard'])) {
            header('Content-Type: application/json');
            if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
                $this->json(['success' => false, 'error' => 'acceso_denegado']);
            }
            $this->json(['success' => true] + $datos);
        }

        // Renderizar vista standalone (sin layout principal)
        $this->view('dashboard/index', [
            'titulo'        => 'Panel de Inicio | JV3000 C.A.',
            'css_extra'     => ['dashboard/index.css?v=31'],
            'js_extra'      => ['js/chart.umd.min.js', 'dashboard/index.js?v=13'],
            'csrf'          => Security::generateToken(),
            'nombre_user'   => $nombre_user,
            'rol_user'      => $rol_user,
            'esAdmin'       => $esAdmin,
            'esOpVentas'    => $esOpVentas,
            'esOpCarga'     => $esOpCarga,
            'fecha_hoy'     => $fecha_hoy,
            'saludo'        => $saludo,
            'iniciales'     => $iniciales,
            'alertas'       => $alertas,
            'rol_user_id'   => $rol_user_id,
            'ventas_dia'    => $datos['ventas_dia'],
            'valor_inventario' => $datos['valor_inventario'],
            'ultimas_facturas' => $datos['ultimas_facturas'],
            'tabla_criticos'   => $datos['tabla_criticos'],
            'tabla_compras'    => $datos['tabla_compras'],
        ]);
    }

    /**
     * Obtiene los datos del dashboard.
     *
     * @param Database $db
     * @return array
     */
    private function obtenerDatosDashboard(Database $db): array
    {
        $datos = [];

        // Última venta registrada
        $vd = $db->fetchOne("SELECT COALESCE(ds.cantidad * ds.precio_venta, 0) as total
            FROM salidas s
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            WHERE s.id_tipo_mov = 1 AND s.status = 'Activa'
            ORDER BY s.fecha_salida DESC, s.id_salida DESC
            LIMIT 1");
        $datos['ventas_dia'] = number_format((float)($vd['total'] ?? 0), 2);

        // Valor del inventario (costo de lotes + fallback a productos)
        $vi = $db->fetchOne("SELECT COALESCE(SUM(CASE WHEN lotes.valor_lotes IS NULL THEN p.stock_actual * p.precio_costo ELSE lotes.valor_lotes END), 0) AS valor
            FROM productos p
            LEFT JOIN (
                SELECT id_producto, SUM(cantidad_restante * precio_costo) AS valor_lotes
                FROM lotes
                WHERE cantidad_restante > 0
                GROUP BY id_producto
            ) lotes ON lotes.id_producto = p.id_producto
            WHERE p.status = 'Activo'");
        $datos['valor_inventario'] = number_format((float)($vi['valor'] ?? 0), 2);

        // Últimas 5 notas de entrega
        $fac = $db->fetchAll("SELECT s.cliente, MAX(s.fecha_salida) as fecha_salida, SUM(ds.cantidad * ds.precio_venta) as total, s.nro_factura_manual FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY s.id_salida, s.nro_factura_manual ORDER BY MAX(s.fecha_salida) DESC LIMIT 5");
        $datos['ultimas_facturas'] = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

        // Últimas 5 compras
        $compras = $db->fetchAll("SELECT c.nro_factura, c.fecha_compra, c.total, COALESCE(pr.nombre_empresa, 'S/P') as proveedor FROM compras c LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor WHERE c.status = 'Activa' ORDER BY c.fecha_compra DESC LIMIT 5");
        $datos['tabla_compras'] = array_map(fn($r) => [
            'proveedor' => $r['proveedor'],
            'fecha' => date('d/m/Y', strtotime($r['fecha_compra'])),
            'total' => number_format($r['total'], 2)
        ], $compras);

        // Productos críticos (stock <= mínimo)
        $crit = $db->fetchAll("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE (stock_actual <= stock_minimo OR stock_actual = 0) AND status = 'Activo' ORDER BY stock_actual ASC LIMIT 5");
        $datos['tabla_criticos'] = array_map(fn($r) => [
            'producto' => $r['nombre_producto'],
            'stock' => (int)$r['stock_actual'],
            'estado' => $r['stock_actual'] <= 0 ? 'critico' : 'bajo'
        ], $crit);

        return $datos;
    }
}