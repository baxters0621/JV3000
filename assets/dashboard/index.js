
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
                            const tasaBcv = Number(data.tasa_bcv) || Number(window.JV_CONFIG && window.JV_CONFIG.tasaBcv) || 0;
                            if ($id('kpi-ventas-dia')) $id('kpi-ventas-dia').textContent = formatBs(data.ventas_dia, tasaBcv);
                            if ($id('kpi-valor-inv')) $id('kpi-valor-inv').textContent = formatBs(data.valor_inventario, tasaBcv);

                            if ($id('lista-facturas')) {
                                $id('lista-facturas').innerHTML = renderActividadPorFecha(
                                    data.ultimas_facturas,
                                    f => `<div class="activity-item activity-financial-item"><span class="activity-status-dot activity-status-dot-venta" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(f.cliente)}</div><div class="activity-financial-date">Fecha: ${escapeHtml(f.fecha)}</div></div><div class="activity-total fs-5 fw-bold text-success">${formatBs(f.total, tasaBcv)}</div></div>`,
                                    'Sin ventas registradas.'
                                );
                            }

                            if ($id('lista-criticos')) {
                                const productos = data.tabla_criticos || [];
                                $id('lista-criticos').innerHTML = productos.length
                                    ? productos.map(c => {
                                        const stock = Math.max(0, Number(c.stock) || 0);
                                        const estado = stock === 0 ? 'Agotado' : 'Crítico';
                                        const claseStock = stock === 0 ? 'text-danger' : 'text-secondary';
                                        const claseIndicador = stock === 0 ? 'activity-status-dot-stock-vacio' : 'activity-status-dot-critico';
                                        return `<div class="activity-item"><span class="activity-status-dot ${claseIndicador}" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(c.producto)}</div><div class="d-flex flex-wrap align-items-center gap-2 mt-1"><span class="activity-stock-label">Stock actual: <strong class="${claseStock}">${stock}</strong></span><span class="badge bg-danger">${estado}</span></div></div></div>`;
                                    }).join('')
                                    : '<p class="mb-0 text-secondary">Sin productos críticos.</p>';
                            }

                            if ($id('lista-compras')) {
                                $id('lista-compras').innerHTML = renderActividadPorFecha(
                                    data.tabla_compras,
                                    c => `<div class="activity-item activity-financial-item"><span class="activity-status-dot activity-status-dot-compra" aria-hidden="true"></span><div class="activity-item-copy"><div class="fs-6 fw-bold activity-item-name">${escapeHtml(c.proveedor)}</div><div class="activity-financial-date">Fecha: ${escapeHtml(c.fecha)}</div></div><div class="activity-total fs-5 fw-bold text-success">${formatBs(c.total, tasaBcv)}</div></div>`,
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

        window.addEventListener('storage', function(evento) {
            if (evento.key === 'jv3000_dashboard_actualizado' && !document.hidden) {
                actualizarDashboard();
            }
        });

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

        // Base absoluta del panel (funciona tanto en el dashboard raíz como en
        // la vista enrutada ?url=dashboard).
        function baseUrlAlertas() {
            var base = (window.JV_CONFIG && window.JV_CONFIG.prefijo) || window.JV_BASE || '../';
            if (base.slice(-1) !== '/') base += '/';
            return base;
        }

        function renderAlertaSeccion(titulo, clase, urlAlerta, count, items, icono, tipo, puedeBaja, puedeReponer) {
            var base = baseUrlAlertas();
            var total = Number(count) || 0;
            var html = '<section class="dash-bell-sec dash-bell-' + clase + '">';
            html += '<div class="dash-bell-sec-titulo"><span><i class="bi ' + icono + '" aria-hidden="true"></i> ' + escapeHtml(titulo) + ' (' + total + ')</span>';
            if (total > 0) {
                html += '<a class="dash-bell-ver" href="' + base + 'index.php?url=productos&alerta=' + urlAlerta + '">Ver todos</a>';
            }
            html += '</div>';

            if (total <= 0) {
                html += '<p class="dash-bell-sec-vacio">' + (tipo === 'vencidos' ? 'No hay productos vencidos' : 'No hay productos con stock bajo') + '</p>';
                return html + '</section>';
            }

            (items || []).forEach(function(it) {
                var nombre = escapeHtml(it.nombre);
                html += '<div class="dash-bell-item">';
                html += '<i class="bi ' + icono + '" aria-hidden="true"></i>';
                html += '<div class="dash-bell-item-copy"><a class="dash-bell-item-nombre" href="' + base + 'index.php?url=productos&producto=' + (Number(it.id) || 0) + '">' + nombre + '</a>';
                if (tipo === 'vencidos') {
                    html += '<span class="dash-bell-item-meta dash-bell-meta-venc">Vence: ' + escapeHtml(it.fecha) + '</span>';
                } else {
                    html += '<span class="dash-bell-item-meta dash-bell-meta-bajo">Quedan ' + (Number(it.stock) || 0) + ' <span class="dash-bell-min">(mín ' + (Number(it.minimo) || 0) + ')</span>';
                    if (Number(it.en_camino) > 0) {
                        html += ' · <span class="dash-bell-camino">En camino ' + Number(it.en_camino) + '</span>';
                    }
                    html += '</span>';
                }
                html += '</div>';
                if (tipo === 'vencidos' && puedeBaja) {
                    html += '<button type="button" class="dash-bell-accion dash-bell-accion-danger" data-baja-id="' + (Number(it.id) || 0) + '" data-baja-nombre="' + nombre + '">Dar de baja</button>';
                } else if (tipo === 'bajos' && puedeReponer) {
                    var href = base + 'index.php?url=compras';
                    var idCompraPendiente = Number(it.id_compra) || 0;
                    if (idCompraPendiente > 0) {
                        href += '&recepcion_compra=' + idCompraPendiente;
                    } else if (Number(it.id_proveedor) > 0) {
                        href += '&reponer_producto=' + (Number(it.id) || 0) + '&reponer_proveedor=' + Number(it.id_proveedor);
                    }
                    html += '<a class="dash-bell-accion dash-bell-accion-warn" href="' + href + '">' + (idCompraPendiente > 0 ? 'Recibir' : 'Reponer') + '</a>';
                }
                html += '</div>';
            });

            return html + '</section>';
        }

        function actualizarAlertas() {
            fetch(baseUrlAlertas() + 'includes/ajax/alertas_ajax.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data || !data.success) return;
                    var panel = $id('dashBellPanel');
                    if (!panel) return;

                    var badge = $id('dashBellBadge');
                    var btn = $id('dashBellBtn');
                    if (Number(data.total) > 0) {
                        if (!badge && btn) {
                            badge = document.createElement('span');
                            badge.className = 'dash-bell-badge';
                            badge.id = 'dashBellBadge';
                            btn.appendChild(badge);
                        }
                        if (badge) {
                            badge.textContent = Math.min(Number(data.total), 99);
                            badge.style.display = 'inline-flex';
                        }
                    } else if (badge) {
                        badge.style.display = 'none';
                    }

                    var puedeBaja = (typeof data.puede_baja !== 'undefined')
                        ? !!data.puede_baja
                        : panel.getAttribute('data-puede-baja') === '1';
                    var puedeReponer = (typeof data.puede_reponer !== 'undefined')
                        ? !!data.puede_reponer
                        : panel.getAttribute('data-puede-reponer') === '1';
                    var counts = data.counts || {};
                    var html = '<div class="dash-bell-head">ALERTAS CRÍTICAS</div><div class="dash-bell-body">';
                    html += renderAlertaSeccion('VENCIDOS', 'ven', 'vencidos', counts.vencidos, data.vencidos || [],
                        'bi bi-calendar-x-fill', 'vencidos', puedeBaja, puedeReponer);
                    html += renderAlertaSeccion('STOCK BAJO', 'bajo', 'bajos', counts.bajos, data.bajos || [],
                        'bi bi-exclamation-triangle-fill', 'bajos', puedeBaja, puedeReponer);
                    html += '</div>';
                    panel.innerHTML = html;
                    if (panel.classList.contains('open')) posicionarPanelAlertas();
                    if (window.JVAlarmaStock) window.JVAlarmaStock.mostrar(data);
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

        // Delegado: confirma y envía la baja de un producto vencido reutilizando
        // el POST existente de Productos (solo visible para administradores).
        document.addEventListener('click', function(e) {
            var panel = $id('dashBellPanel');
            if (!panel) return;
            var boton = e.target && e.target.closest ? e.target.closest('.dash-bell-accion-danger') : null;
            if (!boton || !panel.contains(boton)) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof Swal === 'undefined' || typeof jvPost !== 'function') return;
            var id = boton.getAttribute('data-baja-id');
            var nombre = boton.getAttribute('data-baja-nombre') || '';
            Swal.fire({
                title: '¿DAR DE BAJA?',
                html: 'Se marcará como <strong>Inactivo</strong> por vencimiento: ' + escapeHtml(nombre),
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#64748B',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'SÍ, DAR DE BAJA',
                cancelButtonText: 'CANCELAR'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                var token = panel.getAttribute('data-csrf') || (window.JV_CONFIG && window.JV_CONFIG.csrfToken) || '';
                jvPost({ baja_vencido: id, csrf_token: token }, baseUrlAlertas() + 'index.php?url=productos');
            });
        });

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
