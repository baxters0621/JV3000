<?php

// ==========================================
// MODELO: Estadísticas
// ==========================================
// Única capa que consulta la base de datos.
// Calcula KPIs actuales + comparación con el
// periodo anterior equivalente + series de
// gráficos. Reemplaza includes/estadisticas_logic.php.

/**
 * Estadistica: modelo de estadísticas de ventas/compras.
 *
 * Única capa autorizada para consultar la base de datos. Calcula los KPIs
 * actuales, su comparación con el periodo anterior equivalente y las series
 * de datos para los gráficos. Reemplaza a includes/estadisticas_logic.php.
 */
class Estadistica extends Model
{
    /**
     * Catálogo de periodos disponibles (label + duración en días).
     *
     * Define los periodos predefinidos del selector: desde Diario (1 día)
     * hasta Semestral (180 días). No incluye 'rango' (se maneja aparte).
     *
     * @return array Mapa [clave => ['label'=>string, 'dias'=>int]].
     */
    public function periodos(): array
    {
        return [
            'dia'       => ['label' => 'Diario',    'dias' => 1],
            'semana'    => ['label' => 'Semanal',   'dias' => 7],
            'quincena'  => ['label' => 'Quincenal', 'dias' => 15],
            'mes'       => ['label' => 'Mensual',   'dias' => 30],
            'trimestre' => ['label' => 'Trimestral','dias' => 90],
            'semestre'  => ['label' => 'Semestral', 'dias' => 180],
        ];
    }

    /**
     * Ventana [desde, hasta] actual y su equivalente anterior + mensaje.
     *
     * Para 'rango' con fechas válidas calcula la duración exacta y el rango
     * anterior equivalente; para periodos predefinidos desplaza días hacia
     * atrás. Devuelve las fechas, la etiqueta y el mensaje comparativo.
     *
     * @param string $periodo Clave del periodo o 'rango'.
     * @param string $desde   Fecha inicial (solo para rango).
     * @param string $hasta   Fecha final (solo para rango).
     * @return array Detalle de la ventana (periodo, fechas, mensaje, etc.).
     */
    private function ventana(string $periodo = 'semana', string $desde = '', string $hasta = ''): array
    {
        $periodos = $this->periodos();

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

    /**
     * Calcula los 3 KPIs (ventas, compras, ganancia) en una ventana dada.
     *
     * Suma cantidad*precio en detalle_salidas (ventas tipo 1 activas) y en
     * detalle_compras (compras activas); la ganancia usa la diferencia entre
     * el precio de venta y el costo REAL del lote consumido (detalle_salidas
     * apunta al lote con su precio_costo de compra), con respaldo al precio
     * de costo actual del producto cuando el detalle no tiene lote.
     *
     * @param string $desde Fecha inicial (YYYY-MM-DD).
     * @param string $hasta Fecha final (YYYY-MM-DD).
     * @return array ['ventas'=>float, 'compras'=>float, 'ganancia'=>float].
     */
    private function kpis(string $desde, string $hasta): array
    {
        $f_desde = $desde . ' 00:00:00';
        $f_hasta = $hasta . ' 23:59:59';

        $ventas = (float)$this->db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa'", [$f_desde, $f_hasta])['total'];

        $compras = (float)$this->db->fetchOne("SELECT COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra BETWEEN ? AND ? AND c.status = 'Activa'", [$f_desde, $f_hasta])['total'];

        $ganancia = (float)$this->db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo))), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto LEFT JOIN lotes l ON ds.id_lote = l.id_lote WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa'", [$f_desde, $f_hasta])['total'];

        return ['ventas' => $ventas, 'compras' => $compras, 'ganancia' => $ganancia];
    }

    /**
     * Porcentaje de cambio entre el valor actual y el anterior.
     *
     * Devuelve (actual - anterior) / anterior * 100 redondeado a 1 decimal,
     * o null si el valor anterior es 0 (no se puede dividir).
     *
     * @param float $actual   Valor del periodo actual.
     * @param float $anterior Valor del periodo anterior.
     * @return float|null Porcentaje de variación o null.
     */
    private function pct(float $actual, float $anterior): ?float
    {
        if ($anterior == 0) return null;
        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * Serie de datos de un gráfico en una ventana.
     *
     * Ejecuta la consulta SQL (que debe contener el marcador __BUCKET__ para
     * el agrupamiento temporal) y genera etiquetas + datos. Para 1 día agrupa
     * por hora; para rangos cortos (≤45 días) día a día; rangos medios (≤200
     * días) por semana y rangos largos por mes.
     *
     * @param string $desde     Fecha inicial (YYYY-MM-DD).
     * @param string $hasta     Fecha final (YYYY-MM-DD).
     * @param string $sql       SQL con marcador __BUCKET__ y columnas bucket/total.
     * @param string $fecha_col Columna de fecha usada en el agrupamiento.
     * @return array ['labels'=>array, 'data'=>array].
     */
    private function serie(string $desde, string $hasta, string $sql, string $fecha_col): array
    {
        $f_desde = $desde . ' 00:00:00';
        $f_hasta = $hasta . ' 23:59:59';

        if ($desde === $hasta) {
            $rows = $this->db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d %H:00')", $sql), [$f_desde, $f_hasta]);
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
        $rows = $this->db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d')", $sql), [$f_desde, $f_hasta]);
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

    /**
     * Recolecta todo lo que necesita la vista y el endpoint AJAX de estadísticas.
     *
     * Calcula la ventana, los KPIs actuales y anteriores, sus porcentajes de
     * variación, las series de ventas/compras y el top 5 de productos más
     * vendidos. Devuelve un arreglo completo listo para la vista o el JSON.
     *
     * @param string $periodo Clave del periodo ('semana' por defecto).
     * @param string $desde   Fecha inicial (solo para rango).
     * @param string $hasta   Fecha final (solo para rango).
     * @return array Datos completos de estadísticas para la vista/AJAX.
     */
    public function obtenerDatos(string $periodo = 'semana', string $desde = '', string $hasta = ''): array
    {
        $ventana = $this->ventana($periodo, $desde, $hasta);

        $kpi_act = $this->kpis($ventana['desde'], $ventana['hasta']);
        $kpi_ant = $this->kpis($ventana['ant_desde'], $ventana['ant_hasta']);

        $sql_ventas = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY __BUCKET__";
        $sql_compras = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra BETWEEN ? AND ? AND c.status = 'Activa' GROUP BY __BUCKET__";

        $serie_ventas = $this->serie($ventana['desde'], $ventana['hasta'], $sql_ventas, 's.fecha_salida');
        $serie_compras = $this->serie($ventana['desde'], $ventana['hasta'], $sql_compras, 'c.fecha_compra');

        $top_rows = $this->db->fetchAll("SELECT p.nombre_producto, COALESCE(SUM(ds.cantidad), 0) AS total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida BETWEEN ? AND ? AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY ds.id_producto ORDER BY total DESC LIMIT 5", [$ventana['desde'] . ' 00:00:00', $ventana['hasta'] . ' 23:59:59']);

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

            'pct_ventas'   => $this->pct($kpi_act['ventas'], $kpi_ant['ventas']),
            'pct_compras'  => $this->pct($kpi_act['compras'], $kpi_ant['compras']),
            'pct_ganancia' => $this->pct($kpi_act['ganancia'], $kpi_ant['ganancia']),

            'labels_ventas'  => $serie_ventas['labels'],
            'data_ventas'    => $serie_ventas['data'],
            'labels_compras' => $serie_compras['labels'],
            'data_compras'   => $serie_compras['data'],

            'top_labels' => $top_labels,
            'top_cant'   => $top_cant,
        ];
    }
}
