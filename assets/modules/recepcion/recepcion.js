
        // ==========================================
        // RECEPCIÓN DE MERCADERÍA — registro de ingreso a inventario
        // ==========================================

        let recepcionCompraId = null;

        function abrirRecepcion(idCompra) {
            const cfg = window.JV_CONFIG || {};
            const datos = cfg.recepcionDatos && cfg.recepcionDatos[idCompra];
            if (!datos || !datos.items || !datos.items.length) {
                Swal.fire({
                    title: 'Sin productos pendientes',
                    text: 'Esta compra no tiene productos pendientes por recibir.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            recepcionCompraId = idCompra;
            document.getElementById('recIdCompra').value = idCompra;
            document.getElementById('recFactura').value = datos.nro_factura;
            document.getElementById('recProveedor').value = datos.proveedor;
            document.getElementById('recCondiciones').value = datos.condiciones;

            const body = document.getElementById('recItemsBody');
            body.innerHTML = '';
            datos.items.forEach(function(it, i) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (i + 1) + '</td>' +
                    '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' +
                        '<div style="font-weight:600;">' + escapeHtml(it.nombre) + '</div>' +
                        '<div class="text-muted" style="font-size:.8rem;">' + escapeHtml(it.sku || '') + '</div>' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + it.cantidad + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + it.recibida + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="number" class="input-jv rec-cant" data-id="' + it.id_detalle + '" data-restante="' + it.restante + '" value="' + it.restante + '" min="1" max="' + it.restante + '" style="width:86px;padding:8px 10px;text-align:center;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="date" class="input-jv rec-venc" data-id="' + it.id_detalle + '" value="' + (it.vence || '') + '" style="width:130px;padding:8px 10px;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:right;color:var(--jv-text-primary);font-weight:600;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + it.precio.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</td>';
                body.appendChild(tr);
            });

            const modal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
            modal.show();
        }

        function filtrarPendientes() {
            const input = document.getElementById('buscarPendientes');
            const filter = input ? input.value.toLowerCase() : '';
            const rows = document.getElementById('tablaPendientes') ? document.getElementById('tablaPendientes').getElementsByTagName('tr') : [];
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        function filtrarRecepciones() {
            const input = document.getElementById('buscarRecepciones');
            const filter = input ? input.value.toLowerCase() : '';
            const rows = document.getElementById('tablaRecepciones') ? document.getElementById('tablaRecepciones').getElementsByTagName('tr') : [];
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        function confirmarRecepcion(btn) {
            if (!recepcionCompraId) return false;

            const filas = document.querySelectorAll('#recItemsBody tr');
            const items = [];
            const errores = [];

            filas.forEach(function(tr) {
                const cant = tr.querySelector('.rec-cant');
                if (!cant) return;
                const valor = parseInt(cant.value, 10);
                const max = parseInt(cant.dataset.restante, 10);
                if (isNaN(valor) || valor < 1 || valor > max) {
                    errores.push('Cantidad inválida para un producto (máx. ' + max + ').');
                    cant.classList.add('input-error');
                } else {
                    const venc = tr.querySelector('.rec-venc');
                    items.push({
                        id_detalle: parseInt(cant.dataset.id, 10),
                        cantidad: valor,
                        fecha_vencimiento: venc && venc.value ? venc.value : ''
                    });
                }
            });

            if (errores.length > 0 || items.length === 0) {
                Swal.fire({
                    title: 'CANTIDADES INVÁLIDAS',
                    text: errores.join(' '),
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return false;
            }

            document.getElementById('recItemsData').value = JSON.stringify(items);
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> REGISTRANDO...';
            document.getElementById('formRecepcion').submit();
            return false;
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.flash-auto').forEach(el => {
                setTimeout(() => {
                    el.style.transition = 'opacity .5s';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 500);
                }, 4000);
            });

            document.querySelectorAll('input, select, textarea').forEach(function(el) {
                el.addEventListener('input', function() {
                    this.classList.remove('input-error');
                });
                el.addEventListener('change', function() {
                    this.classList.remove('input-error');
                });
            });

            if (typeof mainWrapper !== 'undefined' && mainWrapper) {
                const observer = new MutationObserver(() => {
                    if (document.body.classList.contains('sidebar-open')) {
                        mainWrapper.classList.add('sidebar-open');
                    } else {
                        mainWrapper.classList.remove('sidebar-open');
                    }
                });
                observer.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        });
