
        const $id = (el) => document.getElementById(el);

        // Actualización en tiempo real del dashboard
        function actualizarDashboard() {
            fetch('index.php?ajax_dashboard=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(data => {
                    try {
                        if (data.success) {
                            if ($id('kpi-ventas-dia')) $id('kpi-ventas-dia').textContent = '$' + data.ventas_dia;
                            if ($id('kpi-valor-inv')) $id('kpi-valor-inv').textContent = '$' + data.valor_inventario;

                            if ($id('tabla-facturas')) {
                                let htmlFacturas = '';
                                data.ultimas_facturas.forEach(f => {
                                    htmlFacturas += `<tr><td class="producto-tooltip" data-nombre="${escapeHtml(f.cliente)}">${escapeHtml(f.cliente)}</td><td>${f.fecha}</td><td class="monto">$${f.total}</td></tr>`;
                                });
                                $id('tabla-facturas').innerHTML = htmlFacturas || '<tr><td colspan="3" class="vacio">Sin ventas registradas</td></tr>';
                            }

                            if ($id('tabla-criticos')) {
                                let htmlCriticos = '';
                                data.tabla_criticos.forEach(c => {
                                    let badge = c.estado === 'critico' ? 'Crítico' : 'Bajo';
                                    htmlCriticos += `<tr><td class="producto-tooltip" data-nombre="${escapeHtml(c.producto)}">${escapeHtml(c.producto)}</td><td>${c.stock}</td><td><span class="stock-badge ${c.estado}">${badge}</span></td></tr>`;
                                });
                                $id('tabla-criticos').innerHTML = htmlCriticos || '<tr><td colspan="3" class="vacio">Sin productos críticos</td></tr>';
                            }

                            if ($id('tabla-compras')) {
                                let htmlCompras = '';
                                (data.tabla_compras || []).forEach(c => {
                                    htmlCompras += `<tr><td class="producto-tooltip" data-nombre="${escapeHtml(c.proveedor)}">${escapeHtml(c.proveedor)}</td><td>${c.fecha}</td><td class="monto">$${c.total}</td></tr>`;
                                });
                                $id('tabla-compras').innerHTML = htmlCompras || '<tr><td colspan="3" class="vacio">Sin compras registradas</td></tr>';
                            }
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
                detenerAlertas();
            } else {
                iniciarDashboard();
                iniciarAlertas();
            }
        });

        // Detener al salir de la página
        window.addEventListener('beforeunload', detenerDashboard);

        // ==========================================
        // Tooltip nombre completo (hover)
        // ==========================================
        var tipProducto = null;
        var tipVisible = false;

        function crearTooltip() {
            if (tipProducto) return;
            tipProducto = document.createElement('div');
            tipProducto.id = 'tip-producto';
            tipProducto.setAttribute('aria-hidden', 'true');
            document.body.appendChild(tipProducto);
        }

        function posicionarTooltip(x, y) {
            if (!tipProducto) return;
            var r = tipProducto.getBoundingClientRect();
            var px = x + 18;
            var py = y + 20;
            if (px + r.width > window.innerWidth - 10) px = Math.max(10, x - r.width - 18);
            if (py + r.height > window.innerHeight - 10) py = Math.max(10, y - r.height - 14);
            tipProducto.style.left = px + 'px';
            tipProducto.style.top = py + 'px';
        }

        document.addEventListener('mouseover', function(e) {
            var el = e.target.closest('.producto-tooltip');
            if (el) {
                crearTooltip();
                tipProducto.textContent = el.getAttribute('data-nombre');
                tipProducto.style.display = 'block';
                tipVisible = true;
            }
        });

        document.addEventListener('mouseout', function(e) {
            var el = e.target.closest('.producto-tooltip');
            if (el && !el.contains(e.relatedTarget) && tipVisible) {
                tipProducto.style.display = 'none';
                tipVisible = false;
            }
        });

        document.addEventListener('mousemove', function(e) {
            if (tipVisible) posicionarTooltip(e.clientX, e.clientY);
        });

        document.addEventListener('touchstart', function() {
            if (tipVisible) { tipProducto.style.display = 'none'; tipVisible = false; }
        });

        // ==========================================
        // Campana de Alertas Críticas de stock
        // ==========================================
        var alertasIntervalo = null;

        function posicionarPanelAlertas() {
            var panel = $id('dashBellPanel');
            var btn = $id('dashBellBtn');
            if (!panel || !btn) return;
            var r = btn.getBoundingClientRect();
            var pw = panel.offsetWidth || 360;
            var ph = panel.offsetHeight || 300;
            var left = r.right - pw;
            if (left < 12) left = 12;
            var top = r.bottom + 8;
            if (top + ph > window.innerHeight - 10) {
                top = Math.max(12, window.innerHeight - ph - 10);
            }
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        }

        function toggleAlertas(e) {
            if (e) e.stopPropagation();
            var panel = $id('dashBellPanel');
            if (!panel) return;
            if (panel.classList.contains('open')) {
                panel.classList.remove('open');
                return;
            }
            posicionarPanelAlertas();
            panel.classList.add('open');
        }

        function renderAlertaSeccion(titulo, clase, urlAlerta, count, items, icono) {
            if (count <= 0) return '';
            var html = '<div class="dash-bell-sec dash-bell-' + clase + '">';
            html += '<div class="dash-bell-sec-titulo"><span>' + escapeHtml(titulo) + ' (' + count + ')</span>';
            html += '<a class="dash-bell-ver" href="../index.php?url=productos&alerta=' + urlAlerta + '">Ver todos</a></div>';
            (items || []).forEach(function(it) {
                html += '<a class="dash-bell-item" href="../index.php?url=productos&producto=' + it.id + '">';
                html += '<i class="bi ' + icono + '"></i>';
                html += '<span class="dash-bell-item-nombre">' + escapeHtml(it.nombre) + '</span>';
                html += '<span class="dash-bell-item-meta">' + escapeHtml(it.meta) + '</span></a>';
            });
            html += '</div>';
            return html;
        }

        function actualizarAlertas() {
            fetch('../includes/ajax/alertas_ajax.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data || !data.success) return;
                    var badge = $id('dashBellBadge');
                    if (badge) {
                        if (data.total > 0) {
                            badge.textContent = Math.min(data.total, 99);
                            badge.style.display = 'inline-flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                    var panel = $id('dashBellPanel');
                    if (!panel) return;
                    var html = '<div class="dash-bell-head">ALERTAS CRÍTICAS</div>';
                    html += renderAlertaSeccion('VENCIDOS', 'ven', 'vencidos', data.counts.vencidos,
                        data.vencidos.map(function(v) { return { id: v.id, nombre: v.nombre, meta: v.fecha }; }),
                        'bi bi-x-octagon');
                    html += renderAlertaSeccion('PRÓXIMOS (1-7 DÍAS)', 'prox', 'proximos', data.counts.proximos,
                        data.proximos.map(function(v) { return { id: v.id, nombre: v.nombre, meta: v.fecha }; }),
                        'bi bi-clock-history');
                    html += renderAlertaSeccion('PRONTO (8-30 DÍAS)', 'pronto', 'prontos', data.counts.prontos,
                        data.prontos.map(function(v) { return { id: v.id, nombre: v.nombre, meta: v.fecha }; }),
                        'bi bi-calendar3');
                    html += renderAlertaSeccion('STOCK BAJO', 'bajo', 'bajos', data.counts.bajos,
                        data.bajos.map(function(v) { return { id: v.id, nombre: v.nombre, meta: v.stock + ' / mín ' + v.minimo }; }),
                        'bi bi-exclamation-triangle');
                    if (html === '<div class="dash-bell-head">ALERTAS CRÍTICAS</div>') {
                        html += '<div class="dash-bell-empty"><i class="bi bi-check-circle"></i> Sin alertas críticas</div>';
                    }
                    panel.innerHTML = html;
                    if (panel.classList.contains('open')) posicionarPanelAlertas();
                })
                .catch(function() {});
        }

        function iniciarAlertas() {
            if (alertasIntervalo) return;
            actualizarAlertas();
            alertasIntervalo = setInterval(actualizarAlertas, 60000);
        }

        function detenerAlertas() {
            if (alertasIntervalo) {
                clearInterval(alertasIntervalo);
                alertasIntervalo = null;
            }
        }

        document.addEventListener('click', function(e) {
            var panel = $id('dashBellPanel');
            if (panel && panel.classList.contains('open')) {
                var btn = $id('dashBellBtn');
                if (btn && !btn.contains(e.target)) panel.classList.remove('open');
            }
        });

        window.addEventListener('resize', function() {
            var panel = $id('dashBellPanel');
            if (panel && panel.classList.contains('open')) posicionarPanelAlertas();
        });

        window.addEventListener('scroll', function() {
            var panel = $id('dashBellPanel');
            if (panel && panel.classList.contains('open')) posicionarPanelAlertas();
        }, { passive: true });

        iniciarDashboard();
        iniciarAlertas();
