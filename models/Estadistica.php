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
    /** @var array<int, int[]> Caché de ids por categoría (incluye su subárbol). */
    private array $cache_categoria_ids = [];

    /**
     * Ids de una categoría y todas sus descendientes.
     *
     * Usa el campo `categorias.ruta` (ruta legible separada por "/") para
     * resolver el subárbol sin recursión en SQL. Devuelve [] si la categoría
     * no existe, para que el filtro no muestre datos de otras ramas.
     *
     * @param int $id_categoria Categoría raíz del filtro.
     * @return int[] Ids de la categoría y su subárbol.
     */
    private function idsCategoria(int $id_categoria): array
    {
        if ($id_categoria <= 0) {
            return [];
        }
        if (isset($this->cache_categoria_ids[$id_categoria])) {
            return $this->cache_categoria_ids[$id_categoria];
        }

        $fila = $this->db->fetchOne("SELECT ruta FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        if (!$fila) {
            return $this->cache_categoria_ids[$id_categoria] = [];
        }

        $ruta = (string)($fila['ruta'] ?? '');
        $filas = $this->db->fetchAll(
            "SELECT id_categoria FROM categorias WHERE id_categoria = ? OR ruta = ? OR ruta LIKE CONCAT(?, '/%')",
            [$id_categoria, $ruta, $ruta]
        );

        return $this->cache_categoria_ids[$id_categoria] = array_map(
            static fn(array $registro): int => (int)$registro['id_categoria'],
            $filas
        );
    }

    /**
     * Condición SQL del filtro por categoría, con sus subcategorías.
     *
     * @param string $alias        Alias de `productos` en la consulta.
     * @param int    $id_categoria Categoría seleccionada (0 = sin filtro).
     * @param array  $params       Parámetros acumulados de la consulta.
     * @return string Fragmento " AND ..." o cadena vacía.
     */
    private function filtroCategoria(string $alias, int $id_categoria, array &$params): string
    {
        if ($id_categoria <= 0) {
            return '';
        }
        $ids = $this->idsCategoria($id_categoria);
        if ($ids === []) {
            return ' AND 1 = 0';
        }
        foreach ($ids as $id) {
            $params[] = $id;
        }
        return " AND {$alias}.id_categoria IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
    }

    /**
     * Condición SQL del filtro por proveedor sobre una columna concreta.
     *
     * @param string $columna       Columna a filtrar (p. ej. p.id_proveedor).
     * @param int    $id_proveedor  Proveedor seleccionado (0 = sin filtro).
     * @param array  $params        Parámetros acumulados de la consulta.
     * @return string Fragmento " AND ..." o cadena vacía.
     */
    private function filtroProveedor(string $columna, int $id_proveedor, array &$params): string
    {
        if ($id_proveedor <= 0) {
            return '';
        }
        $params[] = $id_proveedor;
        return " AND {$columna} = ?";
    }

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
    private function ventana(string $periodo = 'trimestre', string $desde = '', string $hasta = ''): array
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

        $clave = isset($periodos[$periodo]) ? $periodo : 'trimestre';
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
     * Expone la ventana resuelta para los endpoints paginados.
     *
     * @param string $periodo Clave del periodo o 'rango'.
     * @param string $desde   Fecha inicial (solo para rango).
     * @param string $hasta   Fecha final (solo para rango).
     * @return array Ventana con desde/hasta actuales y anteriores.
     */
    public function resolverVentana(string $periodo = 'trimestre', string $desde = '', string $hasta = ''): array
    {
        return $this->ventana($periodo, $desde, $hasta);
    }

    /**
     * Calcula los KPIs del periodo (ventas, compras, margen, unidades).
     *
     * Suma cantidad*precio en detalle_salidas (ventas tipo 1 activas) y en
     * detalle_compras (compras activas); el margen usa la diferencia entre
     * el precio de venta y el costo REAL del lote consumido (detalle_salidas
     * apunta al lote con su precio_costo de compra), con respaldo al precio
     * de costo actual del producto cuando el detalle no tiene lote.
     *
     * @param string $desde        Fecha inicial (YYYY-MM-DD).
     * @param string $hasta        Fecha final (YYYY-MM-DD).
     * @param int    $id_categoria Categoría del filtro global (0 = todas).
     * @param int    $id_proveedor Proveedor del filtro global (0 = todos).
     * @return array Ventas, compras, margen, unidades y nº de operaciones.
     */
    private function kpis(string $desde, string $hasta, int $id_categoria = 0, int $id_proveedor = 0): array
    {
        $f_desde = $desde . ' 00:00:00';
        $f_hasta = $hasta . ' 23:59:59';

        $params = [$f_desde, $f_hasta];
        $filtro_venta = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);

        $venta = $this->db->fetchOne("
            SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total,
                   COALESCE(SUM(ds.cantidad), 0) AS unidades,
                   COUNT(DISTINCT s.id_salida) AS operaciones
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro_venta}
        ", $params);

        $params = [$f_desde, $f_hasta];
        $filtro_compra = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('c.id_proveedor', $id_proveedor, $params);

        $compra = $this->db->fetchOne("
            SELECT COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total
            FROM compras c
            JOIN detalle_compras dc ON c.id_compra = dc.id_compra
            JOIN productos p ON p.id_producto = dc.id_producto
            WHERE c.fecha_compra BETWEEN ? AND ?
              AND c.status = 'Activa'
              {$filtro_compra}
        ", $params);

        $params = [$f_desde, $f_hasta];
        $filtro_margen = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);

        $margen = $this->db->fetchOne("
            SELECT COALESCE(SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo))), 0) AS total
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON ds.id_producto = p.id_producto
            LEFT JOIN lotes l ON ds.id_lote = l.id_lote
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro_margen}
        ", $params);

        return [
            'ventas'      => (float)$venta['total'],
            'compras'     => (float)$compra['total'],
            'ganancia'    => (float)$margen['total'],
            'unidades'    => (float)$venta['unidades'],
            'operaciones' => (int)$venta['operaciones'],
        ];
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
     * @param string $fecha_col   Columna de fecha usada en el agrupamiento.
     * @param array  $params_extra Parámetros adicionales del filtro global.
     * @return array ['labels'=>array, 'data'=>array].
     */
    private function serie(string $desde, string $hasta, string $sql, string $fecha_col, array $params_extra = []): array
    {
        $f_desde = $desde . ' 00:00:00';
        $f_hasta = $hasta . ' 23:59:59';
        $parametros = array_merge([$f_desde, $f_hasta], $params_extra);

        if ($desde === $hasta) {
            $rows = $this->db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d %H:00')", $sql), $parametros);
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
        $rows = $this->db->fetchAll(str_replace('__BUCKET__', "DATE_FORMAT($fecha_col, '%Y-%m-%d')", $sql), $parametros);
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
     * Ventas del periodo agrupadas por categoría (gráfico de dona).
     *
     * @param string $desde        Fecha inicial (YYYY-MM-DD).
     * @param string $hasta        Fecha final (YYYY-MM-DD).
     * @param int    $id_categoria Categoría del filtro global (0 = todas).
     * @param int    $id_proveedor Proveedor del filtro global (0 = todos).
     * @return array ['labels'=>string[], 'data'=>float[]].
     */
    private function ventasPorCategoria(string $desde, string $hasta, int $id_categoria = 0, int $id_proveedor = 0): array
    {
        $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
        $filtro = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);

        $filas = $this->db->fetchAll("
            SELECT cat.nombre AS categoria, COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            JOIN categorias cat ON cat.id_categoria = p.id_categoria
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro}
            GROUP BY cat.id_categoria, cat.nombre
            ORDER BY total DESC
        ", $params);

        $labels = [];
        $data = [];
        foreach ($filas as $fila) {
            $labels[] = $fila['categoria'];
            $data[] = (float)$fila['total'];
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Ranking de productos más vendidos por unidades.
     *
     * @param string $desde        Fecha inicial (YYYY-MM-DD).
     * @param string $hasta        Fecha final (YYYY-MM-DD).
     * @param int    $id_categoria Categoría del filtro global (0 = todas).
     * @param int    $id_proveedor Proveedor del filtro global (0 = todos).
     * @param int    $limite       Número máximo de filas.
     * @return array Filas con unidades, ingreso y margen por producto.
     */
    private function topProductos(string $desde, string $hasta, int $id_categoria = 0, int $id_proveedor = 0, int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));
        $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
        $filtro = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);
        $params[] = $limite;

        return $this->db->fetchAll("
            SELECT p.id_producto, p.sku, p.nombre_producto,
                   SUM(ds.cantidad) AS unidades,
                   SUM(ds.cantidad * ds.precio_venta) AS ingreso,
                   SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo))) AS margen
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            LEFT JOIN lotes l ON ds.id_lote = l.id_lote
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro}
            GROUP BY p.id_producto, p.sku, p.nombre_producto
            ORDER BY unidades DESC, ingreso DESC
            LIMIT ?
        ", $params);
    }

    /**
     * Tabla de rentabilidad por producto, paginada.
     *
     * El costo es el real del lote consumido (precio_costo del lote) y, si el
     * detalle no apunta a lote, el costo actual del producto. El margen % se
     * calcula sobre el ingreso (porcentaje de venta), no como markup.
     *
     * @param array $ventana       Ventana calculada con ventana().
     * @param int   $id_categoria  Categoría del filtro global (0 = todas).
     * @param int   $id_proveedor  Proveedor del filtro global (0 = todos).
     * @param int   $pagina        Página solicitada (1-based).
     * @param int   $por_pagina    Filas por página.
     * @return array Filas, total, página y páginas.
     */
    public function obtenerRentabilidad(array $ventana, int $id_categoria = 0, int $id_proveedor = 0, int $pagina = 1, int $por_pagina = 20): array
    {
        $por_pagina = max(5, min(100, $por_pagina));
        $pagina = max(1, $pagina);

        $params = [$ventana['desde'] . ' 00:00:00', $ventana['hasta'] . ' 23:59:59'];
        $filtro = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);

        $conteo = $this->db->fetchOne("
            SELECT COUNT(DISTINCT p.id_producto) AS total
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro}
        ", $params);

        $total = (int)($conteo['total'] ?? 0);
        $paginas = max(1, (int)ceil($total / $por_pagina));
        $pagina = min($pagina, $paginas);
        $desplazamiento = ($pagina - 1) * $por_pagina;

        $params = [$ventana['desde'] . ' 00:00:00', $ventana['hasta'] . ' 23:59:59'];
        $filtro = $this->filtroCategoria('p', $id_categoria, $params)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params);
        $params[] = $por_pagina;
        $params[] = $desplazamiento;

        $filas = $this->db->fetchAll("
            SELECT p.id_producto, p.sku, p.nombre_producto,
                   cat.nombre AS categoria,
                   prv.nombre_empresa AS proveedor,
                   SUM(ds.cantidad) AS unidades,
                   SUM(ds.cantidad * ds.precio_venta) AS ingreso,
                   SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo))) AS margen,
                   ROUND(SUM(ds.cantidad * COALESCE(l.precio_costo, p.precio_costo)) / NULLIF(SUM(ds.cantidad), 0), 2) AS costo_promedio,
                   ROUND(100 * SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo)))
                         / NULLIF(SUM(ds.cantidad * ds.precio_venta), 0), 1) AS margen_pct
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            LEFT JOIN categorias cat ON cat.id_categoria = p.id_categoria
            LEFT JOIN proveedores prv ON prv.id_proveedor = p.id_proveedor
            LEFT JOIN lotes l ON ds.id_lote = l.id_lote
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro}
            GROUP BY p.id_producto, p.sku, p.nombre_producto, cat.nombre, prv.nombre_empresa
            ORDER BY margen DESC, unidades DESC
            LIMIT ? OFFSET ?
        ", $params);

        return [
            'filas'      => $filas,
            'total'      => $total,
            'pagina'     => $pagina,
            'paginas'    => $paginas,
            'por_pagina' => $por_pagina,
        ];
    }

    /**
     * Recolecta todo lo que necesita la vista y el endpoint AJAX de estadísticas.
     *
     * Calcula la ventana, los KPIs actuales y anteriores, sus porcentajes de
     * variación, las series de ventas/compras/margen, la mezcla por categoría,
     * el ranking de productos y la primera página de rentabilidad.
     *
     * @param string $periodo      Clave del periodo ('trimestre' por defecto).
     * @param string $desde        Fecha inicial (solo para rango).
     * @param string $hasta        Fecha final (solo para rango).
     * @param int    $id_categoria Categoría del filtro global (0 = todas).
     * @param int    $id_proveedor Proveedor del filtro global (0 = todos).
     * @return array Datos completos de estadísticas para la vista/AJAX.
     */
    public function obtenerDatos(string $periodo = 'trimestre', string $desde = '', string $hasta = '', int $id_categoria = 0, int $id_proveedor = 0): array
    {
        $ventana = $this->ventana($periodo, $desde, $hasta);

        $kpi_act = $this->kpis($ventana['desde'], $ventana['hasta'], $id_categoria, $id_proveedor);
        $kpi_ant = $this->kpis($ventana['ant_desde'], $ventana['ant_hasta'], $id_categoria, $id_proveedor);

        $params_venta = [];
        $filtro_venta = $this->filtroCategoria('p', $id_categoria, $params_venta)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params_venta);
        $sql_ventas = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) AS total
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro_venta}
            GROUP BY __BUCKET__";

        $params_compra = [];
        $filtro_compra = $this->filtroCategoria('p', $id_categoria, $params_compra)
            . $this->filtroProveedor('c.id_proveedor', $id_proveedor, $params_compra);
        $sql_compras = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) AS total
            FROM compras c
            JOIN detalle_compras dc ON c.id_compra = dc.id_compra
            JOIN productos p ON p.id_producto = dc.id_producto
            WHERE c.fecha_compra BETWEEN ? AND ?
              AND c.status = 'Activa'
              {$filtro_compra}
            GROUP BY __BUCKET__";

        $params_margen = [];
        $filtro_margen = $this->filtroCategoria('p', $id_categoria, $params_margen)
            . $this->filtroProveedor('p.id_proveedor', $id_proveedor, $params_margen);
        $sql_margen = "SELECT __BUCKET__ AS bucket, COALESCE(SUM(ds.cantidad * (ds.precio_venta - COALESCE(l.precio_costo, p.precio_costo))), 0) AS total
            FROM salidas s
            JOIN tipos_movimientos tm ON tm.id_tipo_mov = s.id_tipo_mov
            JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
            JOIN productos p ON p.id_producto = ds.id_producto
            LEFT JOIN lotes l ON ds.id_lote = l.id_lote
            WHERE s.fecha_salida BETWEEN ? AND ?
              AND tm.tipo_movimiento = 'Salida'
              AND UPPER(TRIM(tm.nombre)) = 'VENTA'
              AND s.status = 'Activa'
              {$filtro_margen}
            GROUP BY __BUCKET__";

        $serie_ventas = $this->serie($ventana['desde'], $ventana['hasta'], $sql_ventas, 's.fecha_salida', $params_venta);
        $serie_compras = $this->serie($ventana['desde'], $ventana['hasta'], $sql_compras, 'c.fecha_compra', $params_compra);
        $serie_margen = $this->serie($ventana['desde'], $ventana['hasta'], $sql_margen, 's.fecha_salida', $params_margen);

        $categorias = $this->ventasPorCategoria($ventana['desde'], $ventana['hasta'], $id_categoria, $id_proveedor);
        $top = $this->topProductos($ventana['desde'], $ventana['hasta'], $id_categoria, $id_proveedor, 10);
        $rentabilidad = $this->obtenerRentabilidad($ventana, $id_categoria, $id_proveedor, 1);

        $top_labels = [];
        $top_cant = [];
        foreach ($top as $fila) {
            $top_labels[] = $fila['nombre_producto'];
            $top_cant[] = (int)$fila['unidades'];
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
            'unidades'    => $kpi_act['unidades'],
            'operaciones' => $kpi_act['operaciones'],
            'ticket'      => $kpi_act['operaciones'] > 0 ? $kpi_act['ventas'] / $kpi_act['operaciones'] : 0.0,
            'margen_pct'  => $kpi_act['ventas'] > 0 ? round($kpi_act['ganancia'] / $kpi_act['ventas'] * 100, 1) : null,

            'ventas_ant'   => $kpi_ant['ventas'],
            'compras_ant'  => $kpi_ant['compras'],
            'ganancia_ant' => $kpi_ant['ganancia'],
            'unidades_ant' => $kpi_ant['unidades'],

            'pct_ventas'   => $this->pct($kpi_act['ventas'], $kpi_ant['ventas']),
            'pct_compras'  => $this->pct($kpi_act['compras'], $kpi_ant['compras']),
            'pct_ganancia' => $this->pct($kpi_act['ganancia'], $kpi_ant['ganancia']),
            'pct_unidades' => $this->pct($kpi_act['unidades'], $kpi_ant['unidades']),

            'labels_ventas'  => $serie_ventas['labels'],
            'data_ventas'    => $serie_ventas['data'],
            'labels_compras' => $serie_compras['labels'],
            'data_compras'   => $serie_compras['data'],
            'data_margen'    => $serie_margen['data'],

            'cat_labels' => $categorias['labels'],
            'cat_data'   => $categorias['data'],

            'top_rows'   => $top,
            'top_labels' => $top_labels,
            'top_cant'   => $top_cant,

            'rent_rows'  => $rentabilidad['filas'],
            'rent_total' => $rentabilidad['total'],
            'rent_pagina' => $rentabilidad['pagina'],
            'rent_paginas' => $rentabilidad['paginas'],

            'filtro_categoria' => $id_categoria,
            'filtro_proveedor' => $id_proveedor,
        ];
    }
}
