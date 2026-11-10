/**
 * Estadísticas: KPIs, gráficos y tablas del panel comercial.
 *
 * Los datos iniciales llegan en window.JV_CONFIG (js_config). El refresco
 * automático y la paginación consultan los endpoints JSON del controlador
 * sin recargar la página.
 */
(function () {
    'use strict';

    Chart.defaults.color = '#6C757D';
    Chart.defaults.borderColor = 'rgba(222,226,230,0.5)';

    const cfg = window.JV_CONFIG || {};
    const animacionGrafico = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? false
        : { duration: 350 };
    const coloresCategoria = ['#0f2a4c', '#c12738', '#0e7490', '#16A34A', '#EA580C', '#7C3AED', '#CA8A04', '#DB2777'];

    let chartFlujo = null;
    let chartCategoria = null;
    let refreshEnCurso = false;
    let paginaRentabilidad = 1;
    let paginasRentabilidad = 1;

    // ---------- HELPERS ----------
    function tasaBcvActual() {
        const tasa = Number(cfg.tasaBcv);
        return Number.isFinite(tasa) && tasa > 0 ? tasa : 0;
    }

    function tasaDeRespuesta(response) {
        const tasa = Number(response && response.tasa_bcv);
        return Number.isFinite(tasa) && tasa > 0 ? tasa : tasaBcvActual();
    }

    function tieneDatos(serie) {
        return Array.isArray(serie) && serie.some(valor => Number(valor) !== 0);
    }

    function escaparHtml(texto) {
        return String(texto ?? '').replace(/[&<>"']/g, caracter => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[caracter]);
    }

    function formatearNumero(valor) {
        return Number(valor || 0).toLocaleString('es-VE', { maximumFractionDigits: 0 });
    }

    function toggleEmptyCanvas(canvasId, emptyId, hasData) {
        const empty = document.getElementById(emptyId);
        if (!empty) return;
        const canvas = document.getElementById(canvasId);
        if (canvas) canvas.classList.toggle('d-none', !hasData);
        empty.classList.toggle('d-none', hasData);
    }

    function toggleAvisoSinDatos(response) {
        const aviso = document.getElementById('aviso-sin-datos');
        if (!aviso) return;
        const hayMovimiento = Number(response.ventas) !== 0 || Number(response.compras) !== 0;
        aviso.classList.toggle('d-none', hayMovimiento);
        if (hayMovimiento) return;

        const titulo = document.getElementById('aviso-sin-datos-titulo');
        const detalle = document.getElementById('aviso-sin-datos-detalle');
        const ventasAnteriores = Number(response.ventas_ant) || 0;
        const comprasAnteriores = Number(response.compras_ant) || 0;
        if (titulo) titulo.textContent = 'No hay movimientos en ' + (response.etiqueta || 'este período');
        if (detalle) {
            const tasa = tasaDeRespuesta(response);
            detalle.textContent = ventasAnteriores !== 0 || comprasAnteriores !== 0
                ? 'El período anterior registró ventas de ' + formatBs(ventasAnteriores, tasa) + ' y compras de ' + formatBs(comprasAnteriores, tasa) + '.'
                : 'Revisa un período más amplio para consultar las operaciones registradas.';
        }
    }

    // ---------- GRÁFICO DE LÍNEAS ----------
    function crearChartFlujo(labels, ventas, compras, margenes, tasa) {
        if (chartFlujo) chartFlujo.destroy();

        const tasaUsada = Number(tasa) > 0 ? Number(tasa) : tasaBcvActual();
        const hayTasa = tasaUsada > 0;
        const moneda = hayTasa ? 'Bs.' : 'USD';
        const convertir = serie => (Array.isArray(serie) ? serie : []).map(valor => hayTasa ? Number(valor) * tasaUsada : Number(valor));
        const ventasGrafico = convertir(ventas);
        const comprasGrafico = convertir(compras);
        const margenGrafico = convertir(margenes);

        chartFlujo = new Chart(document.getElementById('chartFlujo'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Ventas (' + moneda + ')', data: ventasGrafico, borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,0.10)', fill: true, tension: 0.32, borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#DC2626' },
                    { label: 'Compras (' + moneda + ')', data: comprasGrafico, borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,0.08)', fill: true, tension: 0.32, borderWidth: 2, pointRadius: 2, pointBackgroundColor: '#16A34A' },
                    { label: 'Margen (' + moneda + ')', data: margenGrafico, borderColor: '#0e7490', backgroundColor: 'rgba(14,116,144,0.08)', fill: false, tension: 0.32, borderWidth: 2, borderDash: [6, 4], pointRadius: 2, pointBackgroundColor: '#0e7490' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: animacionGrafico,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 13, weight: 'bold' }, usePointStyle: true, padding: 14 } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(222,226,230,0.35)' },
                        ticks: {
                            font: { size: 12 },
                            callback: function (value) {
                                return hayTasa
                                    ? formatBsValue(value)
                                    : '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
                            }
                        }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });

        return tieneDatos(ventasGrafico) || tieneDatos(comprasGrafico) || tieneDatos(margenGrafico);
    }

    // ---------- DONA POR CATEGORÍA ----------
    function crearChartCategoria(labels, data, tasa) {
        if (chartCategoria) chartCategoria.destroy();
        if (!Array.isArray(labels) || labels.length === 0) return false;

        const tasaUsada = Number(tasa) > 0 ? Number(tasa) : tasaBcvActual();
        const hayTasa = tasaUsada > 0;
        const valores = data.map(valor => hayTasa ? Number(valor) * tasaUsada : Number(valor));

        chartCategoria = new Chart(document.getElementById('chartCategoria'), {            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: valores,
                    backgroundColor: labels.map((_, indice) => coloresCategoria[indice % coloresCategoria.length]),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: animacionGrafico,
                cutout: '62%',
                radius: '86%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12, weight: 'bold' }, usePointStyle: true, padding: 12, boxWidth: 10 } },
                    tooltip: {
                        callbacks: {
                            label: function (contexto) {
                                const valor = Number(contexto.parsed) || 0;
                                const total = contexto.dataset.data.reduce((suma, item) => suma + (Number(item) || 0), 0);
                                const parte = total > 0 ? (valor / total) * 100 : 0;
                                const monto = hayTasa ? formatBsValue(valor) : '$' + valor.toFixed(2);
                                return ' ' + contexto.label + ': ' + monto + ' (' + parte.toFixed(1) + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Recalcula el lienzo tras el primer layout (móvil monta la tarjeta
        // después de crear el gráfico y Chart.js conserva el tamaño inicial).
        requestAnimationFrame(() => {
            if (!chartCategoria) return;
            chartCategoria.resize();
            chartCategoria.update('none');
        });

        return valores.some(valor => Number(valor) !== 0);
    }

    // ---------- TABLAS ----------
    function renderTop(rows) {
        const cuerpo = document.getElementById('tablaTopCuerpo');
        const vacio = document.getElementById('vacio-top');
        if (!cuerpo) return;

        const filas = Array.isArray(rows) ? rows : [];
        const totalUnidades = filas.reduce((suma, fila) => suma + (Number(fila.unidades) || 0), 0);
        const maxUnidades = filas.reduce((maximo, fila) => Math.max(maximo, Number(fila.unidades) || 0), 0);
        cuerpo.innerHTML = filas.map((fila, indice) => {
            const unidades = Number(fila.unidades) || 0;
            const parte = totalUnidades > 0 ? (unidades / totalUnidades) * 100 : 0;
            const ancho = maxUnidades > 0 ? Math.max(2, Math.min(100, (unidades / maxUnidades) * 100)) : 0;
            return '<tr>' +
                '<td class="col-puesto">' + (indice + 1) + '</td>' +
                '<td class="celda-producto"><span class="prod-nombre">' + escaparHtml(fila.nombre_producto) + '</span><span class="prod-sku">' + escaparHtml(fila.sku) + '</span></td>' +
                '<td class="num">' + formatearNumero(unidades) + '</td>' +
                '<td class="col-barra"><div class="rank-linea">' +
                    '<div class="rank-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(parte) + '"><span style="width:' + ancho + '%"></span></div>' +
                    '<span class="rank-pct">' + parte.toFixed(1).replace('.', ',') + '%</span>' +
                '</div></td>' +
            '</tr>';
        }).join('');

        if (vacio) vacio.classList.toggle('d-none', filas.length > 0);
    }

    function claseMargen(porcentaje) {
        if (porcentaje >= 30) return 'margen-alto';
        if (porcentaje >= 15) return 'margen-medio';
        return 'margen-bajo';
    }

    function renderRentabilidad(payload) {
        const cuerpo = document.getElementById('tablaRentabilidadCuerpo');
        const vacio = document.getElementById('vacio-rentabilidad');
        const resumen = document.getElementById('rentResumen');
        const nav = document.getElementById('paginacionRentabilidad');
        const indicador = document.getElementById('rentIndicador');
        if (!cuerpo || !payload) return;

        const tasa = tasaDeRespuesta(payload);
        const filas = Array.isArray(payload.filas) ? payload.filas : [];

        cuerpo.innerHTML = filas.map(fila => {
            const margenPct = Number(fila.margen_pct) || 0;
            const ancho = Math.max(2, Math.min(100, margenPct));
            return '<tr>' +
                '<td class="celda-producto"><span class="prod-nombre">' + escaparHtml(fila.nombre_producto) + '</span><span class="prod-sku">' + escaparHtml(fila.sku) + '</span></td>' +
                '<td class="num">' + formatearNumero(fila.unidades) + '</td>' +
                '<td class="num col-ingreso">' + formatBs(fila.ingreso, tasa) + '</td>' +
                '<td class="num importe-margen">' + formatBs(fila.margen, tasa) + '</td>' +
                '<td class="col-barra"><div class="rank-linea">' +
                    '<div class="rank-bar rank-bar--margen ' + claseMargen(margenPct) + '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(margenPct) + '"><span style="width:' + ancho + '%"></span></div>' +
                    '<span class="rank-pct">' + margenPct.toFixed(1).replace('.', ',') + '%</span>' +
                '</div></td>' +
            '</tr>';
        }).join('');

        if (vacio) vacio.classList.toggle('d-none', filas.length > 0);
        paginaRentabilidad = Number(payload.pagina) || 1;
        paginasRentabilidad = Number(payload.paginas) || 1;
        if (nav) {
            nav.dataset.pagina = String(paginaRentabilidad);
            nav.dataset.paginas = String(paginasRentabilidad);
            const [anterior, siguiente] = nav.querySelectorAll('button');
            if (anterior) {
                anterior.dataset.pagina = String(Math.max(1, paginaRentabilidad - 1));
                anterior.disabled = paginaRentabilidad <= 1;
            }
            if (siguiente) {
                siguiente.dataset.pagina = String(Math.min(paginasRentabilidad, paginaRentabilidad + 1));
                siguiente.disabled = paginaRentabilidad >= paginasRentabilidad;
            }
        }
        if (indicador) indicador.textContent = paginaRentabilidad + ' / ' + paginasRentabilidad;
        if (resumen) {
            const total = Number(payload.total) || 0;
            if (total === 0) {
                resumen.textContent = '0 productos';
            } else {
                const desde = (paginaRentabilidad - 1) * (Number(payload.por_pagina) || 20) + 1;
                const hasta = desde + filas.length - 1;
                resumen.textContent = 'Mostrando ' + desde + '–' + hasta + ' de ' + total;
            }
        }
    }

    // ---------- ACTUALIZAR INTERFAZ ----------
    function actualizarUI(response) {
        if (!response || !response.success) return;

        const tasa = tasaDeRespuesta(response);
        const updateText = (elementId, value) => { const element = document.getElementById(elementId); if (element) element.textContent = value; };
        updateText('kpi-ventas', formatBs(response.ventas, tasa));
        updateText('kpi-ganancia', formatBs(response.ganancia, tasa));
        updateText('kpi-unidades', formatearNumero(response.unidades));
        updateText('kpi-compras', formatBs(response.compras, tasa));
        updateText('kpi-operaciones', formatearNumero(response.operaciones) + ' operaciones');
        updateText('kpi-ticket', 'Ticket promedio ' + formatBs(response.ticket, tasa));
        updateText('kpi-margen-pct', response.margen_pct === null || response.margen_pct === undefined
            ? 'Sin ventas en el periodo'
            : Number(response.margen_pct).toFixed(1).replace('.', ',') + ' % sobre ventas');

        const comparaciones = {
            'kpi-ventas': response.pct_ventas,
            'kpi-ganancia': response.pct_ganancia,
            'kpi-unidades': response.pct_unidades,
            'kpi-compras': response.pct_compras
        };
        for (const [elementId, porcentaje] of Object.entries(comparaciones)) {
            const contenedor = document.getElementById(elementId)?.parentElement?.querySelector('.cmp-wrap');
            if (!contenedor) continue;
            contenedor.innerHTML = porcentaje === null || porcentaje === undefined
                ? '<span class="cmp-sello cmp-nulo" title="Sin datos en el periodo anterior">—</span>'
                : (porcentaje >= 0
                    ? '<span class="cmp-sello cmp-subida" title="Aumento respecto al periodo anterior"><i class="bi bi-arrow-up-right"></i> +' + Number(porcentaje).toFixed(1) + '%</span>'
                    : '<span class="cmp-sello cmp-bajada" title="Descenso respecto al periodo anterior"><i class="bi bi-arrow-down-right"></i> ' + Number(porcentaje).toFixed(1) + '%</span>');
        }

        updateText('cmp-mensaje-texto', response.mensaje);
        updateText('cmp-periodo', response.etiqueta);

        const hayFlujo = crearChartFlujo(response.labels, response.data_ventas, response.data_compras, response.data_margen, tasa);
        toggleEmptyCanvas('chartFlujo', 'empty-flujo', hayFlujo);
        const hayCategoria = crearChartCategoria(response.catLabels, response.catData, tasa);
        toggleEmptyCanvas('chartCategoria', 'empty-categoria', hayCategoria);

        renderTop(response.topRows);
        if (paginaRentabilidad === 1) {
            renderRentabilidad({
                filas: response.rentRows,
                total: response.rentTotal,
                pagina: response.rentPagina,
                paginas: response.rentPaginas,
                por_pagina: 20,
                tasa_bcv: tasa
            });
        }
        toggleAvisoSinDatos(response);
    }

    // ---------- FILTROS ----------
    function parametrosFiltros() {
        const periodo = document.getElementById('filtro-periodo')?.value || 'trimestre';
        const parametros = new URLSearchParams({ periodo: periodo });
        if (periodo === 'rango') {
            const desde = document.getElementById('desde_f');
            const hasta = document.getElementById('hasta_f');
            parametros.set('desde', desde ? desde.value : '');
            parametros.set('hasta', hasta ? hasta.value : '');
        }
        parametros.set('categoria', document.getElementById('filtro-categoria')?.value || '0');
        parametros.set('proveedor', document.getElementById('filtro-proveedor')?.value || '0');
        return parametros;
    }

    const formFiltros = document.getElementById('formFiltrosStats');
    const filtroPeriodo = document.getElementById('filtro-periodo');
    const btnRango = document.querySelectorAll('.btn-filtro-periodo');

    btnRango.forEach(boton => {
        boton.addEventListener('click', () => {
            if (filtroPeriodo) filtroPeriodo.value = boton.dataset.periodo || 'trimestre';
            if (formFiltros) formFiltros.submit();
        });
    });

    ['desde_f', 'hasta_f'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', () => {
            if (filtroPeriodo) filtroPeriodo.value = 'rango';
        });
    });

    ['filtro-categoria', 'filtro-proveedor'].forEach(id => {
        const select = document.getElementById(id);
        if (!select) return;
        select.addEventListener('change', () => {
            if (filtroPeriodo && filtroPeriodo.value === 'rango') {
                const desde = document.getElementById('desde_f');
                const hasta = document.getElementById('hasta_f');
                if (!desde?.value || !hasta?.value) {
                    const aviso = document.getElementById('error-fechas');
                    if (aviso) {
                        aviso.textContent = 'Indica Desde y Hasta para aplicar el rango de fechas.';
                        aviso.classList.remove('d-none');
                    }
                    return;
                }
            }
            if (formFiltros) formFiltros.submit();
        });
    });

    const avisoFechas = document.getElementById('error-fechas');
    if (formFiltros) {
        formFiltros.addEventListener('submit', (evento) => {
            if (!filtroPeriodo || filtroPeriodo.value !== 'rango') return;
            const desde = document.getElementById('desde_f');
            const hasta = document.getElementById('hasta_f');
            const hoy = new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());
            const mostrarError = texto => {
                if (avisoFechas) { avisoFechas.textContent = texto; avisoFechas.classList.remove('d-none'); }
            };

            if (!desde.value || !hasta.value) { mostrarError('Indica la fecha Desde y Hasta para filtrar por rango.'); evento.preventDefault(); return; }
            const d = new Date(desde.value + 'T00:00:00');
            const h = new Date(hasta.value + 'T00:00:00');
            if (d > h) { mostrarError('La fecha Hasta no puede ser anterior a la fecha Desde.'); evento.preventDefault(); return; }
            if (h > hoy) { mostrarError('La fecha Hasta no puede ser futura.'); evento.preventDefault(); return; }
            if (avisoFechas) avisoFechas.classList.add('d-none');
        });
    }

    // ---------- PAGINACIÓN DE RENTABILIDAD ----------
    async function cargarRentabilidad(pagina) {
        const parametros = parametrosFiltros();
        parametros.set('pagina', String(pagina));
        try {
            const respuesta = await jvFetchConTope((window.JV_BASE || '') + 'index.php?url=estadisticas/rentabilidad&' + parametros.toString(), { ms: 12000 });
            if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
            const datos = await respuesta.json();
            if (!datos || !datos.success) throw new Error('Respuesta inválida');
            renderRentabilidad(datos);
        } catch (error) {
            console.error('No se pudo paginar la rentabilidad:', error);
            const alerta = document.getElementById('error-estadisticas');
            if (alerta) alerta.classList.remove('d-none');
        }
    }

    const navRentabilidad = document.getElementById('paginacionRentabilidad');
    if (navRentabilidad) {
        paginaRentabilidad = Number(navRentabilidad.dataset.pagina) || 1;
        paginasRentabilidad = Number(navRentabilidad.dataset.paginas) || 1;
        navRentabilidad.addEventListener('click', evento => {
            const boton = evento.target.closest('button[data-pagina]');
            if (!boton || boton.disabled) return;
            cargarRentabilidad(Number(boton.dataset.pagina));
        });
    }

    // ---------- RENDER INICIAL DE GRÁFICOS ----------
    if (cfg.labels) {
        const hayFlujoInicial = crearChartFlujo(cfg.labels, cfg.ventas, cfg.compras, cfg.margenes, cfg.tasaBcv);
        toggleEmptyCanvas('chartFlujo', 'empty-flujo', hayFlujoInicial);
    }
    if (cfg.catLabels) {
        const hayCategoriaInicial = crearChartCategoria(cfg.catLabels, cfg.catData, cfg.tasaBcv);
        toggleEmptyCanvas('chartCategoria', 'empty-categoria', hayCategoriaInicial);
    }

    // ---------- AUTO-REFRESH CADA 60 S ----------
    function refreshEstadisticas() {
        if (refreshEnCurso) return;
        refreshEnCurso = true;
        document.body.classList.add('stats-refrescando');

        const cargas = [];
        ['kpi-ventas', 'kpi-compras', 'kpi-ganancia', 'kpi-unidades'].forEach(id => {
            cargas.push(jvCargaIniciar(document.getElementById(id), { variante: 'kpi' }));
        });
        ['chartFlujo', 'chartCategoria'].forEach(id => {
            const lienzo = document.getElementById(id);
            if (lienzo && lienzo.parentElement) {
                cargas.push(jvCargaIniciar(lienzo.parentElement, { variante: 'grafico' }));
            }
        });

        jvFetchConTope((window.JV_BASE || '') + 'index.php?url=estadisticas/datos&' + parametrosFiltros().toString(), { ms: 12000 })
            .then(response => {
                if (!response.ok) throw new Error('El servidor respondió con HTTP ' + response.status + '.');
                return response.json();
            })
            .then(datos => {
                if (!datos || !datos.success) {
                    throw new Error('El servidor no pudo calcular las estadísticas.');
                }
                actualizarUI(datos);
                const error = document.getElementById('error-estadisticas');
                if (error) error.classList.add('d-none');
            })
            .catch(error => {
                console.error('No se pudieron actualizar las estadísticas:', error);
                const texto = document.getElementById('error-estadisticas-texto');
                if (texto) {
                    texto.textContent = error && error.jvTope
                        ? 'Esto está tardando más de lo normal. Reintenta en un momento.'
                        : 'No se pudieron actualizar las estadísticas.';
                }
                const alerta = document.getElementById('error-estadisticas');
                if (alerta) alerta.classList.remove('d-none');
            })
            .finally(() => {
                cargas.forEach(carga => carga.fin());
                refreshEnCurso = false;
                document.body.classList.remove('stats-refrescando');
            });
    }

    const retryButton = document.getElementById('reintentar-estadisticas');
    if (retryButton) retryButton.addEventListener('click', refreshEstadisticas);

    // Ajuste final del lienzo cuando termina de cargar todo (fuentes, iconos).
    window.addEventListener('load', () => {
        [chartFlujo, chartCategoria].forEach(grafico => {
            if (!grafico) return;
            grafico.resize();
            grafico.update('none');
        });
    });

    setInterval(refreshEstadisticas, 60000);
})();
