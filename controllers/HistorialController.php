<?php

// ==========================================
// CONTROLADOR: Historial de Auditoría
// ==========================================
// Solo lectura. Recibe filtros GET + paginación,
// delega en el Modelo y entrega los datos a la Vista.

/**
 * HistorialController: consulta el historial de auditoría.
 *
 * Módulo de solo lectura. Recibe filtros GET (usuario, acción, fechas,
 * detalle) y paginación, delega en el modelo Auditoria y entrega los
 * datos junto con los totales a la vista.
 */
class HistorialController extends Controller
{
    /**
     * Lista paginada y filtrada del historial de auditoría.
     *
     * Valida que el usuario sea administrador, arma los filtros desde GET,
     * consulta el modelo y prepara los datos de paginación para la vista.
     *
     * @return void
     */
    public function index(): void
    {
        Security::soloAdmin();

        $filtros = [
            'usuario' => trim((string)($_GET['usuario'] ?? '')),
            'accion'  => trim((string)($_GET['accion'] ?? '')),
            'desde'   => trim((string)($_GET['desde'] ?? '')),
            'hasta'   => trim((string)($_GET['hasta'] ?? '')),
            'detalle' => trim((string)($_GET['detalle'] ?? '')),
        ];
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;

        $modelo = new Auditoria();
        $flash  = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('historial/index', [
            'titulo'       => 'Historial | JV3000 C.A.',
            'wrapper_class'=> 'pagina-aud',
            'css_extra'    => ['modules/historial/historial.css?v=5'],
            'js_extra'     => ['modules/historial/historial.js?v=5'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'filtro_usuario' => $filtros['usuario'],
            'filtro_accion'  => $filtros['accion'],
            'filtro_desde'   => $filtros['desde'],
            'filtro_hasta'   => $filtros['hasta'],
            'filtro_detalle' => $filtros['detalle'],
            'query_string' => http_build_query(array_filter($filtros, fn($v) => $v !== '')),
            'total_registros' => $modelo->totalRegistros($filtros),
            'total_paginas'   => $modelo->totalPaginas($filtros, $limit),
            'page'           => $page,
            'registros'      => $modelo->listar($filtros, $page, $limit),
            'acciones_disponibles' => ['crear', 'editar', 'eliminar', 'anular'],
            'accion_nombres' => ['crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'anular' => 'Anular'],
        ]);
    }
}
