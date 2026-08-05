
    Chart.defaults.color = '#6C757D';
    Chart.defaults.borderColor = 'rgba(222,226,230,0.5)';

    const fechas = window.JV_CONFIG.c0;
    const ventas = window.JV_CONFIG.c1;
    const compras = window.JV_CONFIG.c2;

    new Chart(document.getElementById('chartFlujo'), {
        type: 'line',
        data: {
            labels: fechas,
            datasets: [
                { label: 'Ventas ($)', data: ventas, borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,0.1)', fill: true, tension: 0.4 },
                { label: 'Compras ($)', data: compras, borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, grid: { display: true } },
                x: { grid: { display: false } }
            }
        }
    });
    new Chart(document.getElementById('chartTop'), {
        type: 'doughnut',
        data: {
            labels: window.JV_CONFIG.c3,
            datasets: [{
                data: window.JV_CONFIG.c4,
                backgroundColor: window.JV_CONFIG.c5,
                borderWidth: 0, hoverOffset: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: '#212529',
                        usePointStyle: true,
                        padding: 14,
                        font: { size: 13, weight: 'bold' },
                        boxWidth: 16,
                        boxHeight: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            if (total === 0) return ctx.label + ': 0';
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return ctx.label + ': ' + ctx.parsed + ' uds (' + pct + '%)';
                        }
                    }
                }
            },
            cutout: '62%'
        }
    });

    // Auto-refresh cada 30s
    function refreshKPIs() {
        fetch('../includes/estadisticas_ajax.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                try {
                    if (d.success) {
                        document.getElementById('kpi-ventas').textContent = '$' + d.ventas_7d;
                        document.getElementById('kpi-compras').textContent = '$' + d.compras_7d;
                        document.getElementById('kpi-margen').textContent = '$' + d.margen_7d;
                        document.getElementById('kpi-tx').textContent = d.transacciones_7d;
                        document.getElementById('prof-ingresos').textContent = '$' + d.ventas_7d;
                        document.getElementById('prof-costo').textContent = '$' + d.costo_vendido_7d;
                        document.getElementById('prof-ganancia').textContent = '$' + d.margen_7d;
                        const pm = document.getElementById('prof-margen');
                        const porc = d.porc_margen;
                        pm.innerHTML = '<i class="bi bi-percent"></i> ' + porc + '%';
                        pm.className = 'margen-badge';
                        if (porc < 10) pm.classList.add('malo');
                        else if (porc < 20) pm.classList.add('bajo');
                        let htmlTop = '';
                        if (d.top_ganancia && d.top_ganancia.length > 0) {
                            d.top_ganancia.forEach(tp => {
                                htmlTop += `<tr><td>${escapeHtml(tp.producto)}</td><td class="text-center">${tp.unidades}</td><td class="text-end fw-bold" style="color:var(--jv-success);">$${tp.ganancia}</td><td class="text-end"><span class="profit-table-pct"><span class="pct-bar" style="--pct:${tp.pct}%"></span>${tp.pct}%</span></td></tr>`;
                            });
                        } else {
                            htmlTop = '<tr><td colspan="4" class="text-center text-secondary small py-3">Sin datos en los últimos 7 días</td></tr>';
                        }
                        document.getElementById('tabla-top-ganancia').innerHTML = htmlTop;
                    }
                } catch(e) { console.error('Stats refresh error:', e); }
            })
            .catch(() => {});
    }

    setInterval(refreshKPIs, 60000);
    

    const observer = new MutationObserver(() => {
        if (document.body.classList.contains('sidebar-open')) {
            mainWrapper.classList.add('sidebar-open');
        } else {
            mainWrapper.classList.remove('sidebar-open');
        }
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    
