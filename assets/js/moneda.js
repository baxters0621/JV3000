/**
 * Utilidades de moneda para la interfaz.
 *
 * La base de datos y los cálculos internos siguen en dólares. Estas funciones
 * solo convierten y formatean montos para mostrarlos en bolívares.
 */
(function () {
    'use strict';

    // Convierte cualquier valor a un número seguro.
    function numero(valor) {
        const convertido = Number(valor);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    // Formatea un monto que YA está en bolívares.
    function formatearBolivares(monto, decimales) {
        const opciones = {
            minimumFractionDigits: Number.isInteger(decimales) ? decimales : 0,
            maximumFractionDigits: Number.isInteger(decimales) ? decimales : 0
        };
        return 'Bs. ' + new Intl.NumberFormat('es-VE', opciones).format(numero(monto));
    }

    // Obtiene la tasa explícita o la configurada globalmente por el backend.
    function obtenerTasa(tasa) {
        const tasaExplicita = numero(tasa);
        if (tasaExplicita > 0) return tasaExplicita;

        const tasaConfigurada = window.JV_CONFIG ? numero(window.JV_CONFIG.tasaBcv) : 0;
        return tasaConfigurada > 0 ? tasaConfigurada : 0;
    }

    /**
     * Convierte un monto en USD a bolívares y lo formatea.
     *
     * @param {number|string} montoUsd Monto en dólares.
     * @param {number|string} [tasa] Tasa VES por USD; si se omite usa JV_CONFIG.tasaBcv.
     * @returns {string} Ejemplo: "Bs. 1.771.275" o "—" si no hay tasa.
     */
    window.formatBs = function (montoUsd, tasa) {
        const tasaUsada = obtenerTasa(tasa);
        if (tasaUsada <= 0) return '—';
        return formatearBolivares(numero(montoUsd) * tasaUsada, 0);
    };

    /**
     * Formatea un monto que ya está en bolívares, sin multiplicar por la tasa.
     *
     * @param {number|string} montoBs Monto en bolívares.
     * @param {number} [decimales] Decimales a mostrar (0 por defecto).
     * @returns {string} Ejemplo: "Bs. 899,27".
     */
    window.formatBsValue = function (montoBs, decimales) {
        return formatearBolivares(montoBs, decimales);
    };
})();
