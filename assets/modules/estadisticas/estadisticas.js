
    Chart.defaults.color = '#6C757D';
    Chart.defaults.borderColor = 'rgba(222,226,230,0.5)';

    const cfg = window.JV_CONFIG || {};

    // ---------- GRÁFICO DE LÍNEAS: VENTAS VS COMPRAS ----------
    let chartFlujo = null;
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
    function crearChartTop(labels, cant) {
        if (chartTop) chartTop.destroy();
        const datos = (labels && labels.length > 0) ? cant : [0];
        const etiquetas = (labels && labels.length > 0) ? labels : ['Sin datos'];
        chartTop = new Chart(document.getElementById('chartTop'), {
            type: 'bar',
            data: {
                labels: etiquetas,
                datasets: [{
                    label: 'Unidades',
                    data: datos,
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
    function actualizarUI(d) {
        if (!d || !d.success) return;

        const fmt = n => '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const set = (id, valor) => { const el = document.getElementById(id); if (el) el.textContent = valor; };
        set('kpi-ventas', fmt(d.ventas));
        set('kpi-compras', fmt(d.compras));
        set('kpi-ganancia', fmt(d.ganancia));

        const sellos = {
            'kpi-ventas': d.pct_ventas,
            'kpi-compras': d.pct_compras,
            'kpi-ganancia': d.pct_ganancia
        };
        for (const [id, pct] of Object.entries(sellos)) {
            const wrap = document.getElementById(id)?.parentElement?.querySelector('.cmp-wrap');
            if (!wrap) continue;
            wrap.innerHTML = pct === null || pct === undefined
                ? '<span class="cmp-sello cmp-nulo">—</span>'
                : (pct >= 0
                    ? `<span class="cmp-sello cmp-subida" title="Aumento respecto al periodo anterior"><i class="bi bi-arrow-up-right"></i> +${pct.toFixed(1)}%</span>`
                    : `<span class="cmp-sello cmp-bajada" title="Descenso respecto al periodo anterior"><i class="bi bi-arrow-down-right"></i> ${pct.toFixed(1)}%</span>`);
        }

        set('cmp-mensaje-texto', d.mensaje);
        set('cmp-periodo', d.etiqueta);

        crearChartFlujo(d.labels, d.data_ventas, d.data_compras);
        crearChartTop(d.topLabels, d.topCant);
    }

    // ---------- FILTROS: BOTONES DE PERIODO ----------
    document.querySelectorAll('.btn-filtro-periodo').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = btn.dataset.periodo;
            window.location.href = (window.JV_BASE || '') + 'index.php?url=estadisticas&periodo=' + p;
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

    function refreshEstadisticas() {
        fetch((window.JV_BASE || '') + 'index.php?url=estadisticas/datos' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { try { actualizarUI(d); } catch (e) { console.error('Stats refresh error:', e); } })
            .catch(() => {});
    }
    setInterval(refreshEstadisticas, 60000);
