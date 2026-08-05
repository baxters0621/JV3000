
        // Gráficos Chart.js
        const chartVentasCtx = document.getElementById('chartVentas').getContext('2d');
        const chartProductosCtx = document.getElementById('chartProductos').getContext('2d');

        // Datos iniciales para gráficos
        const datosVentas = window.JV_CONFIG.c0;
        const datosProductos = window.JV_CONFIG.c1;

        let chartVentas = null;
        let chartProductos = null;

        function renderCharts(vData, pData) {
            if (chartVentas) chartVentas.destroy();
            if (chartProductos) chartProductos.destroy();

                    chartVentas = new Chart(chartVentasCtx, {
                type: 'line',
                data: {
                    labels: vData.map(d => d.fecha.slice(5)),
                    datasets: [{
                        label: 'Ventas $',
                        data: vData.map(d => d.total),
                        borderColor: '#EA580C',
                        backgroundColor: 'rgba(234,88,12,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#EA580C'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(11,37,69,0.06)' },
                            ticks: { color: '#6C757D' }
                        },
                        y: {
                            grid: { color: 'rgba(11,37,69,0.06)' },
                            ticks: { color: '#6C757D' }
                        }
                    }
                }
            });

            chartProductos = new Chart(chartProductosCtx, {
                type: 'bar',
                data: {
                    labels: pData.map(d => d.producto.substring(0, 15)),
                    datasets: [{
                        label: 'Cantidad',
                        data: pData.map(d => d.cantidad),
                        backgroundColor: pData.map(d => d.color),
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#6C757D' }
                        },
                        y: {
                            grid: { color: 'rgba(11,37,69,0.06)' },
                            ticks: { color: '#6C757D' }
                        }
                    }
                }
            });
        }

        renderCharts(datosVentas, datosProductos);

        // Actualización en tiempo real del dashboard
        function actualizarDashboard() {
            fetch('index.php?ajax_dashboard=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(data => {
                    try {
                        if (data.success) {
                            document.getElementById('kpi-ventas-dia').textContent = '$' + data.ventas_dia;
                            document.getElementById('kpi-valor-inv').textContent = '$' + data.valor_inventario;
                            document.getElementById('kpi-criticos').textContent = data.productos_criticos;

                            let htmlFacturas = '';
                            data.ultimas_facturas.forEach(f => {
                                    htmlFacturas += `<tr><td>${escapeHtml(f.cliente)}</td><td>${f.fecha}</td><td style="text-align:right;color:#16A34A;font-weight:700;">$${f.total}</td></tr>`;
                            });
                            document.getElementById('tabla-facturas').innerHTML = htmlFacturas;

                            let htmlCriticos = '';
                            data.tabla_criticos.forEach(c => {
                                let badge = c.estado === 'critico' ? 'Critico' : 'Bajo';
                                htmlCriticos += `<tr><td>${escapeHtml(c.producto)}</td><td>${c.stock}</td><td><span class="stock-badge ${c.estado}">${badge}</span></td></tr>`;
                            });
                            document.getElementById('tabla-criticos').innerHTML = htmlCriticos;

                            if (data.productos_vencer || data.productos_pronto) {
                                let htmlV = '';
                                const todos = [...(data.productos_vencer||[]), ...(data.productos_pronto||[])];
                                todos.slice(0, 5).forEach(v => {
                                    const estilo = v.dias < 0 ? 'color:#DC2626;font-weight:700;' : (v.dias <= 7 ? 'color:#EA580C;font-weight:700;' : 'color:#D97706;');
                                    const label = v.dias < 0 ? 'VENCIDO' : v.dias + 'd';
                                    htmlV += `<tr><td>${escapeHtml(v.nombre)}</td><td style="${estilo}">${v.fecha} (${label})</td><td style="text-align:center;">${v.stock}</td></tr>`;
                                });
                                document.getElementById('tabla-vencer').innerHTML = htmlV || '<tr><td colspan="3" style="text-align:center;color:#64748b;padding:20px;">Sin productos próximos a vencer</td></tr>';
                            }

                            if (data.grafico_ventas) renderCharts(data.grafico_ventas, data.grafico_productos);
                        }
                    } catch (e) {
                        console.error('Panel de Inicio refresh error:', e);
                    }
                })
                .catch(error => console.warn('Panel de Inicio sync error:', error));
        }

        // Auto-actualización inteligente
        var intervaloDash = null;

        function iniciarDashboard() {
            if (intervaloDash) return;
            actualizarDashboard();
            intervaloDash = setInterval(actualizarDashboard, 45000);
        }

        function detenerDashboard() {
            if (intervaloDash) {
                clearInterval(intervaloDash);
                intervaloDash = null;
            }
        }

        // Solo actualiza si la pestaña está visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                detenerDashboard();
            } else {
                iniciarDashboard();
            }
        });

        // Detener al salir de la página
        window.addEventListener('beforeunload', detenerDashboard);

        iniciarDashboard();
    
