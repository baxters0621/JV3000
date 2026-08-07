<?php
// ==========================================
// LÓGICA COMPARTIDA DE ESTADÍSTICAS
// Calcula KPIs actuales + comparación con el
// periodo anterior equivalente + datos de gráficos.
// Usada por modules/estadisticas.php y includes/estadisticas_ajax.php
// ==========================================

function jv_est_periodos(): array
{
    return [
        'dia'      => ['label' => 'Diario',    'dias' => 1],
        'semana'   => ['label' => 'Semanal',   'dias' => 7],
        'quincena' => ['label' => 'Quincenal', 'dias' => 15],
        'mes'      => ['label' => 'Mensual',   'dias' => 30],
        'trimestre'=> ['label' => 'Trimestral','dias' => 90],
        'semestre' => ['label' => 'Semestral', 'dias' => 180],
    ];
}

// Determina la ventana [desde, hasta] y su equivalente anterior.
// Retorna: periodo (clave o 'rango'), etiquetas, fechas Y-m-d y mensaje dinámico.
function jv_est_ventana(string $periodo = 'semana', string $desde = '', string $hasta = ''): array
{
    $periodos = jv_est_periodos();

    if ($periodo === 'rango' && $desde !== '' && $hasta !== '') {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $desde);
        $h = DateTimeImmutable::createFromFormat('Y-m-d', $hasta);
        if ($d && $h && $d <= $h) {
            $hoy = (new DateTimeImmutable('today'));
            $duracion = $d->diff($h)->days + 1;
            $anterior = $d->modify("-$duracion days");
            $ant_desde = $anterior->format('Y-m-d');
            $ant_hasta = $d->modify('-1 day')->format('Y-m-d');
            return [
                'periodo'       => 'rango',
                'etiqueta'      => $d->format('d/m/Y') . ' al ' . $h->format('d/m/Y'),
                'desde'         => $d->format('Y-m-d'),
                'hasta'         => $h->format('Y-m-d'),
                'ant_desde'     => $ant_desde,
                'ant_hasta'     => $ant_hasta,
                'mensaje'       => 'Comparado con el periodo anterior equivalente (' . $anterior->format('d/m/Y') . ' al ' . $d->modify('-1 day')->format('d/m/Y') . ')',
            ];
        }
    }

    $clave = isset($periodos[$periodo]) ? $periodo : 'semana';
    $dias = (int)$periodos[$clave]['dias'];

    $desde = date('Y-m-d', strtotime("-" . ($dias - 1) . " days"));
    $hasta = date('Y-m-d');

    $ant_desde = date('Y-m-d', strtotime("-" . ($dias * 2 - 1) . " days"));
    $ant_hasta = date('Y-m-d', strtotime("-" . $dias . " days"));

    $mensajes = [
        'dia'       => 'Comparado con el día de ayer',
        'semana'    => 'Comparado con la semana anterior',
        'quincena'  => 'Comparado con la quincena anterior',
        'mes'       => 'Comparado con el mes anterior',
        'trimestre' => 'Comparado con el trimestre anterior',
        'semestre'  => 'Comparado con el semestre anterior',
    ];

    return [
        'periodo'   => $clave,
        'etiqueta'  => $periodos[$clave]['label'] . ' · ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)),
        'desde'     => $desde,
        'hasta'     => $hasta,
        'ant_desde' => $ant_desde,
        'ant_hasta' => $ant_hasta,
        'mensaje'   => $mensajes[$clave],
    ];
}

// Calcula los 3 KPIs en una ventana dada.
function jv_est_kpis($db, string $desde, string $hasta): array
{
    $f_desde = $desde . ' 00:00:00';
    $f_hasta = $hasta . ' 23:59:59';

    $ventas = (float)$db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa'", [$f_desde, $f_hasta])['total'];

    $compras = (float)$db->fetchOne("SELECT COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra BETWEEN ? AND ? AND c.status = 'Activa'", [$f_desde, $f_hasta])['total'];

    $ganancia = (float)$db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * (ds.precio_venta - p.precio_costo)), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa'", [$f_desde, $f_hasta])['total'];

    return ['ventas' => $ventas, 'compras' => $compras, 'ganancia' => $ganancia];
}

// Porcentaje de cambio: (actual - anterior) / anterior * 100. null si anterior es 0.
function jv_est_pct(float $actual, float $anterior): ?float
{
    if ($anterior == 0) return null;
    return round((($actual - $anterior) / $anterior) * 100, 1);
}

// Serie de un gráfico en una ventana (por hora para 1 día, por día, por semana o por mes).
// $fecha_col: columna de fecha con alias de tabla (ej: 's.fecha_salida').
// $sql: query con __BUCKET__ como expresión de agrupación (ya debe usar $fecha_col en el GROUP BY).
function jv_est_serie($db, string $desde, string $hasta, string $sql, string $fecha_col): array
{
    $f_desde = $desde . ' 00:00:00';
    $f_hasta = $hasta . ' 23:59:59';

    if ($desde === $hasta) {
        $rows = $db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d %H:00')", $sql), [$f_desde, $f_hasta]);
        $labels = [];
        $datos = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $datos[] = 0;
        }
        foreach ($rows as $r) {
            $ts = strtotime($r['bucket']);
            if ($ts === false) continue;
            $idx = (int)date('G', $ts);
            $datos[$idx] = (float)$r['total'];
        }
        return ['labels' => $labels, 'data' => $datos];
    }

    $dias = (int)floor((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
    $rows = $db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d')", $sql), [$f_desde, $f_hasta]);
    $map = [];
    foreach ($rows as $r) { $map[$r['bucket']] = (float)$r['total']; }

    // Día a día (hasta ~45 días) o agrupado por semana/mes en rangos largos.
    if ($dias <= 45) {
        $labels = [];
        $datos = [];
        for ($i = 0; $i < $dias; $i++) {
            $f = date('Y-m-d', strtotime("$desde +$i days"));
            $labels[] = date('d/m', strtotime($f));
            $datos[] = $map[$f] ?? 0;
        }
        return ['labels' => $labels, 'data' => $datos];
    }

    // Semanal (hasta 200 días)
    if ($dias <= 200) {
        $labels = [];
        $datos = [];
        for ($i = 0; $i < $dias; $i += 7) {
            $fin = min($i + 6, $dias - 1);
            $f_ini = date('Y-m-d', strtotime("$desde +$i days"));
            $f_fin = date('Y-m-d', strtotime("$desde +$fin days"));
            $labels[] = date('d/m', strtotime($f_ini)) . '–' . date('d/m', strtotime($f_fin));
            $suma = 0;
            for ($j = $i; $j <= $fin; $j++) {
                $f = date('Y-m-d', strtotime("$desde +$j days"));
                $suma += $map[$f] ?? 0;
            }
            $datos[] = $suma;
        }
        return ['labels' => $labels, 'data' => $datos];
    }

    // Mensual
    $labels = [];
    $datos = [];
    $dt = new DateTimeImmutable($desde);
    $dt = $dt->modify('first day of this month');
    $ultimo = new DateTimeImmutable($hasta);
    while ($dt <= $ultimo) {
        $key = $dt->format('Y-m');
        $labels[] = $dt->format('M Y');
        $suma = 0;
        foreach ($map as $fecha => $val) {
            if (strpos($fecha, $key . '-') === 0) { $suma += $val; }
        }
        $datos[] = $suma;
        $dt = $dt->modify('+1 month');
    }
    return ['labels' => $labels, 'data' => $datos];
}

// Recolecta todo lo que necesita la vista de estadísticas.
function jv_est_obtener_datos($db, string $periodo = 'semana', string $desde = '', string $hasta = ''): array
{
    $ventana = jv_est_ventana($periodo, $desde, $hasta);

    $kpi_act = jv_est_kpis($db, $ventana['desde'], $ventana['hasta']);
    $kpi_ant = jv_est_kpis($db, $ventana['ant_desde'], $ventana['ant_hasta']);

    $sql_ventas = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY __BUCKET__";
    $sql_compras = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra BETWEEN ? AND ? AND c.status = 'Activa' GROUP BY __BUCKET__";

    $serie_ventas = jv_est_serie($db, $ventana['desde'], $ventana['hasta'], $sql_ventas, 's.fecha_salida');
    $serie_compras = jv_est_serie($db, $ventana['desde'], $ventana['hasta'], $sql_compras, 'c.fecha_compra');

    $top_rows = $db->fetchAll("SELECT p.nombre_producto, COALESCE(SUM(ds.cantidad), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY ds.id_producto ORDER BY total DESC LIMIT 5", [$ventana['desde'] . ' 00:00:00', $ventana['hasta'] . ' 23:59:59']);

    $top_labels = [];
    $top_cant = [];
    foreach ($top_rows as $r) {
        $top_labels[] = $r['nombre_producto'];
        $top_cant[] = (int)$r['total'];
    }

    return [
        'periodo'   => $ventana['periodo'],
        'etiqueta'  => $ventana['etiqueta'],
        'mensaje'   => $ventana['mensaje'],
        'desde'     => $ventana['desde'],
        'hasta'     => $ventana['hasta'],

        'ventas'      => $kpi_act['ventas'],
        'compras'     => $kpi_act['compras'],
        'ganancia'    => $kpi_act['ganancia'],

        'ventas_ant'   => $kpi_ant['ventas'],
        'compras_ant'  => $kpi_ant['compras'],
        'ganancia_ant' => $kpi_ant['ganancia'],

        'pct_ventas'   => jv_est_pct($kpi_act['ventas'], $kpi_ant['ventas']),
        'pct_compras'  => jv_est_pct($kpi_act['compras'], $kpi_ant['compras']),
        'pct_ganancia' => jv_est_pct($kpi_act['ganancia'], $kpi_ant['ganancia']),

        'labels_ventas'  => $serie_ventas['labels'],
        'data_ventas'    => $serie_ventas['data'],
        'labels_compras' => $serie_compras['labels'],
        'data_compras'   => $serie_compras['data'],

        'top_labels' => $top_labels,
        'top_cant'   => $top_cant,
    ];
}
