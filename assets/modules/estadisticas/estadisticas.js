
    Chart.defaults.color = '#6C757D';
    Chart.defaults.borderColor = 'rgba(222,226,230,0.5)';

    const cfg = window.JV_CONFIG || {};
    const animacionGrafico = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? false
        : { duration: 350 };

    // Obtiene la tasa configurada por el backend.
    function tasaBcvActual() {
        const tasa = Number(cfg.tasaBcv);
        return Number.isFinite(tasa) && tasa > 0 ? tasa : 0;
    }

    // Prefiere la tasa de la respuesta AJAX y cae a la tasa de la página.
    function tasaDeRespuesta(response) {
        const tasa = Number(response && response.tasa_bcv);
        return Number.isFinite(tasa) && tasa > 0 ? tasa : tasaBcvActual();
    }

    // ---------- HELPERS DE ESTADO ----------
    // Indica si una serie numérica tiene al menos un valor distinto de cero
    // (decide si los gráficos muestran el estado vacío).
    function tieneDatos(serie) {
        return Array.isArray(serie) && serie.some(v => Number(v) !== 0);
    }

    // Muestra u oculta el mensaje "sin datos" superpuesto a un canvas según existan datos.
    function toggleEmptyCanvas(canvasId, emptyId, hasData) {
        const empty = document.getElementById(emptyId);
        if (!empty) return;
        const canvas = document.getElementById(canvasId);
        if (canvas) canvas.classList.toggle('d-none', !hasData);
        empty.classList.toggle('d-none', hasData);
    }

    // Explica el periodo sin operaciones sin confundirlo con falta de datos guardados.
    function toggleAvisoSinDatos(statisticsResponse) {
        const aviso = document.getElementById('aviso-sin-datos');
        if (!aviso) return;
        const hayMovimiento = Number(statisticsResponse.ventas) !== 0 || Number(statisticsResponse.compras) !== 0;
        aviso.classList.toggle('d-none', hayMovimiento);
        if (hayMovimiento) return;

        const titulo = document.getElementById('aviso-sin-datos-titulo');
        const detalle = document.getElementById('aviso-sin-datos-detalle');
        const ventasAnteriores = Number(statisticsResponse.ventas_ant) || 0;
        const comprasAnteriores = Number(statisticsResponse.compras_ant) || 0;
        if (titulo) titulo.textContent = 'No hay movimientos en ' + (statisticsResponse.etiqueta || 'este período');
        if (detalle) {
            const tasa = tasaDeRespuesta(statisticsResponse);
            detalle.textContent = ventasAnteriores !== 0 || comprasAnteriores !== 0
                ? 'El período anterior registró ventas de ' + formatBs(ventasAnteriores, tasa) + ' y compras de ' + formatBs(comprasAnteriores, tasa) + '.'
                : 'Revisa un período más amplio para consultar las operaciones registradas.';
        }
    }

    // ---------- GRÁFICO DE LÍNEAS: VENTAS VS COMPRAS ----------
    let chartFlujo = null;
    // Dibuja o redibuja el gráfico de líneas comparando ventas y compras por periodo.
    // Convierte las series a bolívares cuando hay tasa; si no, conserva USD y lo advierte.
    function crearChartFlujo(labels, ventas, compras, tasa) {
        if (chartFlujo) chartFlujo.destroy();

        const tasaUsada = Number(tasa) > 0 ? Number(tasa) : tasaBcvActual();
        const hayTasa = tasaUsada > 0;
        const moneda = hayTasa ? 'Bs.' : 'USD';
        const ventasGrafico = (Array.isArray(ventas) ? ventas : []).map(valor => hayTasa ? Number(valor) * tasaUsada : Number(valor));
        const comprasGrafico = (Array.isArray(compras) ? compras : []).map(valor => hayTasa ? Number(valor) * tasaUsada : Number(valor));

        chartFlujo = new Chart(document.getElementById('chartFlujo'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Ventas (' + moneda + ')', data: ventasGrafico, borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,0.10)', fill: true, tension: 0.32, borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#DC2626' },
                    { label: 'Compras (' + moneda + ')', data: comprasGrafico, borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,0.10)', fill: true, tension: 0.32, borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#16A34A' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: animacionGrafico,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 14, weight: 'bold' }, usePointStyle: true, padding: 16 } }
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

        return tieneDatos(ventasGrafico) || tieneDatos(comprasGrafico);
    }

    // ---------- GRÁFICO DE BARRAS HORIZONTALES: TOP 5 ----------
    let chartTop = null;
    // Dibuja o redibuja el gráfico de barras horizontales con el top 5 de productos más vendidos.
    function crearChartTop(labels, quantities) {
        if (chartTop) chartTop.destroy();
        const chartData = (labels && labels.length > 0) ? quantities : [0];
        const chartLabels = (labels && labels.length > 0) ? labels : ['Sin datos'];
        chartTop = new Chart(document.getElementById('chartTop'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Unidades',
                    data: chartData,
                    backgroundColor: ['#c12738', '#d65361', '#df7c87', '#e9a7ae', '#f3d2d5'],
                    borderRadius: 8,
                    barThickness: 22
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: animacionGrafico,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => items[0] ? items[0].label : '',
                            label: (ctx) => ' ' + ctx.parsed.x + ' uds'
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(222,226,230,0.35)' }, ticks: { precision: 0, font: { size: 12 } } },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 13, weight: 'bold' },
                            callback: function(value) {
                                const label = String(this.getLabelForValue(value));
                                return label.length > 24 ? label.slice(0, 23) + '…' : label;
                            }
                        }
                    }
                }
            }
        });
    }

    // ---------- ACTUALIZAR INTERFAZ DESDE DATOS ----------
    // Actualiza los KPI, sellos de comparación y gráficos con los datos obtenidos del servidor.
    function actualizarUI(statisticsResponse) {
        if (!statisticsResponse || !statisticsResponse.success) return;

        const tasa = tasaDeRespuesta(statisticsResponse);
        const updateText = (elementId, value) => { const element = document.getElementById(elementId); if (element) element.textContent = value; };
        updateText('kpi-ventas', formatBs(statisticsResponse.ventas, tasa));
        updateText('kpi-compras', formatBs(statisticsResponse.compras, tasa));
        updateText('kpi-ganancia', formatBs(statisticsResponse.ganancia, tasa));

        const comparisonPercentages = {
            'kpi-ventas': statisticsResponse.pct_ventas,
            'kpi-compras': statisticsResponse.pct_compras,
            'kpi-ganancia': statisticsResponse.pct_ganancia
        };
        for (const [elementId, percentage] of Object.entries(comparisonPercentages)) {
            const comparisonContainer = document.getElementById(elementId)?.parentElement?.querySelector('.cmp-wrap');
            if (!comparisonContainer) continue;
            comparisonContainer.innerHTML = percentage === null || percentage === undefined
                ? '<span class="cmp-sello cmp-nulo">—</span>'
                : (percentage >= 0
                    ? `<span class="cmp-sello cmp-subida" title="Aumento respecto al periodo anterior"><i class="bi bi-arrow-up-right"></i> +${percentage.toFixed(1)}%</span>`
                    : `<span class="cmp-sello cmp-bajada" title="Descenso respecto al periodo anterior"><i class="bi bi-arrow-down-right"></i> ${percentage.toFixed(1)}%</span>`);
        }

        updateText('cmp-mensaje-texto', statisticsResponse.mensaje);
        updateText('cmp-periodo', statisticsResponse.etiqueta);

        const hayTop = Array.isArray(statisticsResponse.topLabels) && statisticsResponse.topLabels.length > 0;

        const hayFlujo = crearChartFlujo(statisticsResponse.labels, statisticsResponse.data_ventas, statisticsResponse.data_compras, tasa);
        toggleEmptyCanvas('chartFlujo', 'empty-flujo', hayFlujo);
        crearChartTop(statisticsResponse.topLabels, statisticsResponse.topCant);
        toggleEmptyCanvas('chartTop', 'empty-top', hayTop);
        toggleAvisoSinDatos(statisticsResponse);
    }

    // ---------- FILTROS: BOTONES DE PERIODO ----------
    document.querySelectorAll('.btn-filtro-periodo').forEach(periodButton => {
        periodButton.addEventListener('click', () => {
            const selectedPeriod = periodButton.dataset.periodo;
            window.location.href = (window.JV_BASE || '') + 'index.php?url=estadisticas&periodo=' + selectedPeriod;
        });
    });

    // ---------- RENDER INICIAL ----------
    if (cfg.labels) {
        const hayFlujoInicial = crearChartFlujo(cfg.labels, cfg.ventas, cfg.compras, cfg.tasaBcv);
        toggleEmptyCanvas('chartFlujo', 'empty-flujo', hayFlujoInicial);
    }
    if (cfg.topLabels) {
        crearChartTop(cfg.topLabels, cfg.topCant);
        toggleEmptyCanvas('chartTop', 'empty-top', cfg.topLabels.length > 0);
    }

    // ---------- VALIDACIÓN DEL FILTRO POR FECHAS ----------
    // Valida en el cliente que el rango sea coherente (no vacío, desde <= hasta,
    // y no futuro) antes de enviar el formulario al servidor.
    const filtroFechas = document.querySelector('.filtro-fechas');
    const avisoFechas = document.getElementById('error-fechas');
    if (filtroFechas) {
        filtroFechas.addEventListener('submit', (e) => {
            const desde = document.getElementById('desde_f');
            const hasta = document.getElementById('hasta_f');
            const hoy = new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());
            const mostrarError = (texto) => {
                if (avisoFechas) { avisoFechas.textContent = texto; avisoFechas.classList.remove('d-none'); }
            };

            if (!desde.value || !hasta.value) { mostrarError('Indica la fecha Desde y Hasta para filtrar por rango.'); e.preventDefault(); return; }
            const d = new Date(desde.value + 'T00:00:00');
            const h = new Date(hasta.value + 'T00:00:00');
            if (d > h) { mostrarError('La fecha Hasta no puede ser anterior a la fecha Desde.'); e.preventDefault(); return; }
            if (h > hoy) { mostrarError('La fecha Hasta no puede ser futura.'); e.preventDefault(); return; }
            if (avisoFechas) avisoFechas.classList.add('d-none');
        });
    }

    // ---------- AUTO-REFRESH CADA 60 S ----------
    const urlParams = new URLSearchParams(window.location.search);
    const periodoActivo = cfg.periodo || 'trimestre';
    const qs = periodoActivo === 'rango'
        ? '&periodo=rango&desde=' + (urlParams.get('desde') || '') + '&hasta=' + (urlParams.get('hasta') || '')
        : '&periodo=' + periodoActivo;

    // Consulta los datos de estadísticas del periodo activo y actualiza la
    // interfaz sin recargar la página. Si la espera supera 700 ms se muestran
    // skeletons sobre KPI y gráficos (las respuestas rápidas no parpadean) y,
    // si la petición vence su tope de 12 s, el aviso existente cambia a
    // "está tardando" — la espera nunca queda sin rostro ni sin salida.
    function refreshEstadisticas() {
        if (refreshEnCurso) return;
        refreshEnCurso = true;
        document.body.classList.add('stats-refrescando');

        const cargas = [];
        ['kpi-ventas', 'kpi-compras', 'kpi-ganancia'].forEach(id => {
            cargas.push(jvCargaIniciar(document.getElementById(id), { variante: 'kpi' }));
        });
        ['chartFlujo', 'chartTop'].forEach(id => {
            const lienzo = document.getElementById(id);
            if (lienzo && lienzo.parentElement) {
                cargas.push(jvCargaIniciar(lienzo.parentElement, { variante: 'grafico' }));
            }
        });

        jvFetchConTope((window.JV_BASE || '') + 'index.php?url=estadisticas/datos' + qs, { ms: 12000 })
            .then(response => {
                if (!response.ok) throw new Error('El servidor respondió con HTTP ' + response.status + '.');
                return response.json();
            })
            .then(statisticsResponse => {
                if (!statisticsResponse || !statisticsResponse.success) {
                    throw new Error('El servidor no pudo calcular las estadísticas.');
                }
                actualizarUI(statisticsResponse);
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
                const alert = document.getElementById('error-estadisticas');
                if (alert) alert.classList.remove('d-none');
            })
            .finally(() => {
                cargas.forEach(carga => carga.fin());
                refreshEnCurso = false;
                document.body.classList.remove('stats-refrescando');
            });
    }
    let refreshEnCurso = false;
    const retryButton = document.getElementById('reintentar-estadisticas');
    if (retryButton) retryButton.addEventListener('click', refreshEstadisticas);
    setInterval(refreshEstadisticas, 60000);
