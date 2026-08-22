
    Chart.defaults.color = '#6C757D';
    Chart.defaults.borderColor = 'rgba(222,226,230,0.5)';

    const cfg = window.JV_CONFIG || {};

    // ---------- GRÁFICO DE LÍNEAS: VENTAS VS COMPRAS ----------
    let chartFlujo = null;
    // Dibuja o redibuja el gráfico de líneas comparando ventas y compras por periodo.
    function crearChartFlujo(labels, ventas, compras) {
        if (chartFlujo) chartFlujo.destroy();
        chartFlujo = new Chart(document.getElementById('chartFlujo'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Ventas ($)', data: ventas, borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,0.12)', fill: true, tension: 0.4, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#16A34A' },
                    { label: 'Compras ($)', data: compras, borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,0.12)', fill: true, tension: 0.4, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#2563EB' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 14, weight: 'bold' }, usePointStyle: true, padding: 16 } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(222,226,230,0.35)' }, ticks: { font: { size: 12 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 } }
                }
            }
        });
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
                    backgroundColor: ['#EA580C', '#2563EB', '#6F42C1', '#16A34A', '#D97706'],
                    borderRadius: 8,
                    barThickness: 22
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
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
                    y: { grid: { display: false }, ticks: { font: { size: 13, weight: 'bold' } } }
                }
            }
        });
    }

    // ---------- ACTUALIZAR INTERFAZ DESDE DATOS ----------
    // Actualiza los KPI, sellos de comparación y gráficos con los datos obtenidos del servidor.
    function actualizarUI(statisticsResponse) {
        if (!statisticsResponse || !statisticsResponse.success) return;

        const formatCurrency = amount => '$' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const updateText = (elementId, value) => { const element = document.getElementById(elementId); if (element) element.textContent = value; };
        updateText('kpi-ventas', formatCurrency(statisticsResponse.ventas));
        updateText('kpi-compras', formatCurrency(statisticsResponse.compras));
        updateText('kpi-ganancia', formatCurrency(statisticsResponse.ganancia));

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

        crearChartFlujo(statisticsResponse.labels, statisticsResponse.data_ventas, statisticsResponse.data_compras);
        crearChartTop(statisticsResponse.topLabels, statisticsResponse.topCant);
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
        crearChartFlujo(cfg.labels, cfg.ventas, cfg.compras);
    }
    if (cfg.topLabels) {
        crearChartTop(cfg.topLabels, cfg.topCant);
    }

    // ---------- AUTO-REFRESH CADA 60 S ----------
    const urlParams = new URLSearchParams(window.location.search);
    const periodoActivo = cfg.periodo || 'semana';
    const qs = periodoActivo === 'rango'
        ? '&periodo=rango&desde=' + (urlParams.get('desde') || '') + '&hasta=' + (urlParams.get('hasta') || '')
        : '&periodo=' + periodoActivo;

    // Consulta los datos de estadísticas del periodo activo y actualiza la interfaz sin recargar la página.
    function refreshEstadisticas() {
        fetch((window.JV_BASE || '') + 'index.php?url=estadisticas/datos' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(statisticsResponse => { try { actualizarUI(statisticsResponse); } catch (error) { console.error('Stats refresh error:', error); } })
            .catch(() => {});
    }
    setInterval(refreshEstadisticas, 60000);
