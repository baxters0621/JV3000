<?php

/**
 * Verifica que el KPI "Pagado (Mes)" de Compras sume los pagos del mes
 * y no el total de compras registradas.
 */
$raiz = dirname(__DIR__);
$modelo = file_get_contents($raiz . '/models/Compra.php');
$vista = file_get_contents($raiz . '/views/compras/index.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');

function afirmarPagadoMes(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarPagadoMes(strpos($modelo, 'FROM pagos_compra p') !== false, 'El KPI debe sumar pagos_compra.');
afirmarPagadoMes(strpos($modelo, 'SUM(p.monto)') !== false, 'El KPI debe sumar el monto pagado.');
afirmarPagadoMes(strpos($modelo, "'pagado_mes'") !== false, 'El modelo debe devolver la clave pagado_mes.');
afirmarPagadoMes(strpos($modelo, 'invertido_mes') === false, 'El modelo no debe conservar invertido_mes.');
afirmarPagadoMes(
    strpos($modelo, "DATE_FORMAT(CURDATE(),'%Y-%m-01')") !== false
        && strpos($modelo, "DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01')") !== false,
    'El KPI debe filtrar el mes en curso.'
);

afirmarPagadoMes(strpos($vista, 'Pagado (Mes)') !== false, 'La tarjeta debe rotularse Pagado (Mes).');
afirmarPagadoMes(strpos($vista, 'id="widgetPagadoMes"') !== false, 'Falta el id widgetPagadoMes.');
afirmarPagadoMes(strpos($vista, "\$kpis['pagado_mes']") !== false, 'La vista debe usar pagado_mes.');
afirmarPagadoMes(strpos($vista, 'widgetInvertidoMes') === false, 'La vista no debe conservar widgetInvertidoMes.');

afirmarPagadoMes(strpos($javascript, "widgetPagadoMes") !== false, 'El JS debe actualizar widgetPagadoMes.');
afirmarPagadoMes(strpos($javascript, 'widgetInvertidoMes') === false, 'El JS no debe conservar widgetInvertidoMes.');

echo "OK KPI Pagado (Mes) calculado con pagos del mes\n";
