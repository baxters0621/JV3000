<?php

// ==========================================
// CONTROLADOR: Historial de Auditoría
// ==========================================
// Solo lectura. Recibe filtros GET + paginación,
// delega en el Modelo y entrega los datos a la Vista.
class HistorialController extends Controller
{
    // GET  index.php?url=historial?usuario=..&accion=..&desde=..&hasta=..&detalle=..&page=..
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
            'js_extra'     => ['modules/historial/historial.js?v=2'],
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
