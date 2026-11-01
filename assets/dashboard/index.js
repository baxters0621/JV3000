
        const $id = (el) => document.getElementById(el);

        function obtenerGrupoActividad(fecha) {
            const partes = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(fecha || '');
            if (!partes) return 'anteriores';

            const fechaItem = new Date(Number(partes[3]), Number(partes[2]) - 1, Number(partes[1]));
            if (Number.isNaN(fechaItem.getTime())) return 'anteriores';

            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const inicioSemana = new Date(hoy);
            inicioSemana.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
            if (fechaItem.getTime() === hoy.getTime()) return 'hoy';
            if (fechaItem >= inicioSemana && fechaItem < hoy) return 'semana';
            return 'anteriores';
        }

        function renderActividadPorFecha(items, renderItem, mensajeVacio) {
            const grupos = {
                hoy: { titulo: 'HOY', items: [] },
                semana: { titulo: 'ESTA SEMANA', items: [] },
                anteriores: { titulo: 'ANTERIORES', items: [] }
            };

            (items || []).forEach(item => grupos[obtenerGrupoActividad(item.fecha)].items.push(item));
            const contenido = Object.values(grupos)
                .filter(grupo => grupo.items.length > 0)
                .map(grupo => `<section class="activity-date-group"><h4 class="activity-date-heading">${grupo.titulo}</h4>${grupo.items.map(renderItem).join('')}</section>`)
                .join('');

            return contenido || `<p class="mb-0 text-secondary">${mensajeVacio}</p>`;
        }

        // Actualización en tiempo real del dashboard
        function actualizarDashboard() {
            fetch('index.php?ajax_dashboard=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.json())
                .then(data => {
                    try {
                        if (data.success) {
                            if ($id('kpi-ventas-dia')) $id('kpi-ventas-dia').textContent = '$' + data.ventas_dia;
                            if ($id('kpi-valor-inv')) $id('kpi-valor-inv').textContent = '$' + data.valor_inventario;

                            if ($id('lista-facturas')) {
                                $id('lista-facturas').innerHTML = renderActividadPorFecha(
                                    data.ultimas_facturas,
                                    f => `<div class="activity-item"><span class="activity-status-dot activity-status-dot-venta" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(f.cliente)}</div><div class="small text-secondary">Fecha: ${escapeHtml(f.fecha)} · Total: $${escapeHtml(f.total)}</div></div></div>`,
                                    'Sin ventas registradas.'
                                );
                            }

                            if ($id('lista-criticos')) {
                                const productos = data.tabla_criticos || [];
                                $id('lista-criticos').innerHTML = productos.length
                                    ? productos.map(c => {
                                        const stock = Math.max(0, Number(c.stock) || 0);
                                        const estado = c.estado === 'critico' ? 'Crítico' : 'Bajo';
                                        const claseStock = stock === 0 ? 'text-danger fw-semibold' : 'text-secondary';
                                        const porcentajeStock = stock === 0 ? 0 : 50;
                                        const claseIndicador = stock === 0 ? 'activity-status-dot-stock-vacio' : 'activity-status-dot-critico';
                                        const claseBarra = stock === 0 ? 'is-empty' : '';
                                        const estadoAccesible = stock === 0 ? 'Sin existencias' : 'Stock bajo';
                                        return `<div class="activity-item"><span class="activity-status-dot ${claseIndicador}" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(c.producto)}</div><div class="small ${claseStock}">Stock: ${stock} unidades · ${estado}</div><div class="progress activity-stock-progress ${claseBarra}" role="progressbar" aria-label="Nivel visual del stock" aria-valuenow="${porcentajeStock}" aria-valuemin="0" aria-valuemax="100" aria-valuetext="${estadoAccesible}"><div class="progress-bar ${stock === 0 ? 'bg-danger' : 'activity-stock-progress-low'}" style="width: ${porcentajeStock}%"></div></div></div></div>`;
                                    }).join('')
                                    : '<p class="mb-0 text-secondary">Sin productos críticos.</p>';
                            }

                            if ($id('lista-compras')) {
                                $id('lista-compras').innerHTML = renderActividadPorFecha(
                                    data.tabla_compras,
                                    c => `<div class="activity-item"><span class="activity-status-dot activity-status-dot-compra" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(c.proveedor)}</div><div class="small text-secondary">Fecha: ${escapeHtml(c.fecha)} · Total: $${escapeHtml(c.total)}</div></div></div>`,
                                    'Sin compras registradas.'
                                );
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
        // Campana de Alertas Críticas de stock
        // ==========================================
        var alertasIntervalo = null;

        function posicionarPanelAlertas() {
            var panel = $id('dashBellPanel');
            var alertButton = $id('dashBellBtn');
            if (!panel || !alertButton) return;
            var buttonBounds = alertButton.getBoundingClientRect();
            var panelWidth = panel.offsetWidth || 360;
            var panelHeight = panel.offsetHeight || 300;
            var left = buttonBounds.right - panelWidth;
            if (left < 12) left = 12;
            var top = buttonBounds.bottom + 8;
            if (top + panelHeight > window.innerHeight - 10) {
                top = Math.max(12, window.innerHeight - panelHeight - 10);
            }
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        }

        function toggleAlertas(e) {
            if (e) e.stopPropagation();
            var panel = $id('dashBellPanel');
            var btn = $id('dashBellBtn');
            if (!panel) return;
            if (panel.classList.contains('open')) {
                panel.classList.remove('open');
                if (btn) btn.setAttribute('aria-expanded', 'false');
                return;
            }
            posicionarPanelAlertas();
            panel.classList.add('open');
            if (btn) btn.setAttribute('aria-expanded', 'true');
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
            var alertButton = $id('dashBellBtn');
            if (panel && panel.classList.contains('open')) {
                if (alertButton && !alertButton.contains(e.target)) {
                    panel.classList.remove('open');
                    if (alertButton) alertButton.setAttribute('aria-expanded', 'false');
                }
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
        iniciarReloj();

        // Reloj compacto: hora local de Venezuela (Caracas) en 12h SIN segundos
        // y con el periodo abreviado («pm»/«am»); cifras tabulares para que no
        // "salte". Se refresca cada 10 s: al mostrar solo minutos sobra el
        // ritmo de 1 s.
        function iniciarReloj() {
            var reloj = $id('dash-clock');
            if (!reloj) return;
            var formateador = new Intl.DateTimeFormat('es-VE', {
                timeZone: 'America/Caracas',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            function pintar() {
                var hora = '', minuto = '', periodo = '';
                formateador.formatToParts(new Date()).forEach(function (parte) {
                    if (parte.type === 'hour') hora = parte.value;
                    if (parte.type === 'minute') minuto = parte.value;
                    if (parte.type === 'dayPeriod') periodo = parte.value;
                });
                // «p. m.» → «pm»: sin puntos ni espacios queda más compacto.
                reloj.textContent = hora + ':' + minuto + ' ' + periodo.toLowerCase().replace(/[\s.]/g, '');
            }
            pintar();
            setInterval(pintar, 10000);
        }
