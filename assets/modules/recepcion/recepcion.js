
        // ==========================================
        // RECEPCIÓN DE MERCADERÍA — registro de ingreso a inventario
        // ==========================================

        let recepcionCompraId = null;

        // Abre el modal de recepción cargando los datos de la compra y la tabla de productos pendientes por recibir.
        function abrirRecepcion(idCompra) {
            const cfg = window.JV_CONFIG || {};
            const purchaseData = cfg.recepcionDatos && cfg.recepcionDatos[idCompra];
            if (!purchaseData || !purchaseData.items || !purchaseData.items.length) {
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
            document.getElementById('recFactura').value = purchaseData.nro_factura;
            document.getElementById('recProveedor').value = purchaseData.proveedor;

            const body = document.getElementById('recItemsBody');
            body.innerHTML = '';
            purchaseData.items.forEach(function(pendingItem, itemIndex) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (itemIndex + 1) + '</td>' +
                    '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' +
                        '<div style="font-weight:600;">' + escapeHtml(pendingItem.nombre) + '</div>' +
                        '<div class="text-muted" style="font-size:.8rem;">' + escapeHtml(pendingItem.sku || '') + '</div>' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.cantidad + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.recibida + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="number" class="input-jv rec-cant" data-id="' + pendingItem.id_detalle + '" data-restante="' + pendingItem.restante + '" value="' + pendingItem.restante + '" min="1" max="' + pendingItem.restante + '" style="width:86px;padding:8px 10px;text-align:center;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="date" class="input-jv rec-venc" data-id="' + pendingItem.id_detalle + '" value="' + (pendingItem.vence || '') + '" style="width:130px;padding:8px 10px;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:right;color:var(--jv-text-primary);font-weight:600;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.precio.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</td>';
                body.appendChild(tr);
            });

            const modal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
            modal.show();
        }

        // Filtra por texto la tabla de compras con productos pendientes de recepción, sin recargar.
        function filtrarPendientes() {
            const input = document.getElementById('buscarPendientes');
            const searchValue = input ? input.value.toLowerCase() : '';
            const tableRows = document.getElementById('tablaPendientes') ? document.getElementById('tablaPendientes').getElementsByTagName('tr') : [];
            for (let rowIndex = 0; rowIndex < tableRows.length; rowIndex++) {
                tableRows[rowIndex].style.display = tableRows[rowIndex].textContent.toLowerCase().includes(searchValue) ? '' : 'none';
            }
        }

        // Filtra por texto la tabla de recepciones ya registradas, sin recargar.
        function filtrarRecepciones() {
            const input = document.getElementById('buscarRecepciones');
            const searchValue = input ? input.value.toLowerCase() : '';
            const tableRows = document.getElementById('tablaRecepciones') ? document.getElementById('tablaRecepciones').getElementsByTagName('tr') : [];
            for (let rowIndex = 0; rowIndex < tableRows.length; rowIndex++) {
                tableRows[rowIndex].style.display = tableRows[rowIndex].textContent.toLowerCase().includes(searchValue) ? '' : 'none';
            }
        }

        // Valida las cantidades a recibir (entre 1 y el restante), arma el array de items,
        // lo guarda en el campo oculto y envía el formulario para registrar la recepción.
        function confirmarRecepcion(submitButton) {
            if (!recepcionCompraId) return false;

            const filas = document.querySelectorAll('#recItemsBody tr');
            const items = [];
            const errores = [];

            filas.forEach(function(receivingRow) {
                const quantityInput = receivingRow.querySelector('.rec-cant');
                if (!quantityInput) return;
                const receivedQuantity = parseInt(quantityInput.value, 10);
                const remainingQuantity = parseInt(quantityInput.dataset.restante, 10);
                if (isNaN(receivedQuantity) || receivedQuantity < 1 || receivedQuantity > remainingQuantity) {
                    errores.push('Cantidad inválida para un producto (máx. ' + remainingQuantity + ').');
                    quantityInput.classList.add('input-error');
                } else {
                    const expirationInput = receivingRow.querySelector('.rec-venc');
                    // REGLA DE NEGOCIO: todo lote exige fecha de vencimiento (control FEFO)
                    if (!expirationInput || !expirationInput.value) {
                        errores.push('Indique la fecha de vencimiento de todos los productos a recibir.');
                        if (expirationInput) expirationInput.classList.add('input-error');
                    }
                    items.push({
                        id_detalle: parseInt(quantityInput.dataset.id, 10),
                        cantidad: receivedQuantity,
                        fecha_vencimiento: expirationInput && expirationInput.value ? expirationInput.value : ''
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
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> REGISTRANDO...';
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
