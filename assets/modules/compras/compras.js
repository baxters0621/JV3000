
        // ==========================================
        // COMPRAS — Registro de factura del proveedor + comprobante de pago
        // (No mueve stock: la recepción se hace desde Inventario)
        // ==========================================

        let productos = [];
        let montoEditado = false;
        let toolboxTimer = null;
        let productoSeleccionado = null;

        const IVA_PCT = (window.JV_CONFIG && typeof window.JV_CONFIG.c1 === 'number') ? window.JV_CONFIG.c1 : 16;

        function fmt(n) {
            return '$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // ---- Formateo de precio en el input ----
        function formatearPrecioCompra(el) {
            var raw = el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            var parts = raw.split('.');
            var entero = parts[0].replace(/^0+/, '') || '0';
            var decimales = parts[1] ? parts[1].slice(0, 2) : '';
            var formateado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (decimales) formateado += '.' + decimales;
            var num = parseFloat(entero + '.' + (decimales || '0'));
            if (num > 99999999.99) {
                entero = '99999999';
                decimales = '99';
                formateado = '99,999,999.99';
            }
            el.value = formateado;
        }

        // Marca que el usuario editó el monto de pago
        function marcarMontoEditado() {
            montoEditado = true;
            var el = document.getElementById('montoPago');
            if (el) el.classList.remove('input-error');
        }

        // ==========================================
        // TOOLBOX — Búsqueda de productos (AJAX)
        // ==========================================
        const toolboxInput = document.getElementById('buscarProducto');
        const resultadosBox = document.getElementById('resultadosBusqueda');

        function cerrarResultados() {
            if (resultadosBox) resultadosBox.classList.remove('abierto');
        }

        function abrirResultados() {
            if (resultadosBox) resultadosBox.classList.add('abierto');
        }

        function renderResultados(items) {
            if (!resultadosBox) return;
            resultadosBox.innerHTML = '';
            if (!items || !items.length) {
                const vacio = document.createElement('div');
                vacio.className = 'com-sin-resultados';
                vacio.textContent = 'Sin resultados';
                resultadosBox.appendChild(vacio);
                abrirResultados();
                return;
            }
            items.forEach(function(it) {
                const div = document.createElement('div');
                div.className = 'com-resultado';
                div.dataset.id = it.id;
                div.dataset.nombre = it.nombre;
                div.dataset.precio = it.precio_costo;

                const left = document.createElement('div');
                const nombreEl = document.createElement('div');
                nombreEl.className = 'r-nombre';
                nombreEl.textContent = it.nombre;
                const skuEl = document.createElement('div');
                skuEl.className = 'r-sku';
                skuEl.textContent = it.sku || '';
                left.appendChild(nombreEl);
                left.appendChild(skuEl);

                const stockEl = document.createElement('span');
                stockEl.className = 'r-stock';
                stockEl.textContent = 'Stock: ' + it.stock;

                div.appendChild(left);
                div.appendChild(stockEl);
                resultadosBox.appendChild(div);
            });
            abrirResultados();
        }

        function buscarProductos() {
            const q = toolboxInput.value.trim();
            if (!q) {
                cerrarResultados();
                productoSeleccionado = null;
                return;
            }
            window.clearTimeout(toolboxTimer);
            toolboxTimer = window.setTimeout(function() {
                jvBuscarProductos({ q: q, limit: 15 }, function(d) {
                    if (d && d.success) renderResultados(d.items);
                    else renderResultados([]);
                });
            }, 350);
        }

        function seleccionarProducto(el) {
            if (!el || !el.dataset.id) return;
            productoSeleccionado = {
                id: parseInt(el.dataset.id, 10),
                nombre: el.dataset.nombre
            };
            toolboxInput.value = productoSeleccionado.nombre;
            const precioEl = document.getElementById('inputPrecio');
            if (precioEl && !precioEl.value.trim()) {
                const sugerido = parseFloat(el.dataset.precio) || 0;
                precioEl.value = sugerido > 0 ? sugerido.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '0.00';
            }
            cerrarResultados();
            const cantEl = document.getElementById('inputCant');
            if (cantEl) cantEl.focus();
        }

        // ==========================================
        // LÍNEA DE PRODUCTO
        // ==========================================
        function agregarProducto() {
            if (!productoSeleccionado || !productoSeleccionado.id) {
                Swal.fire({
                    title: 'Seleccione un producto',
                    text: 'Busque y elija un producto de la lista',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                if (toolboxInput) toolboxInput.focus();
                return;
            }
            const cant = parseInt(document.getElementById('inputCant').value) || 0;
            const precio = parseFloat(document.getElementById('inputPrecio').value.replace(/,/g, '')) || 0;
            const venc = document.getElementById('inputVencimiento').value || '';

            if (cant < 1 || cant > 999999) {
                Swal.fire({
                    title: 'Cantidad inválida',
                    text: 'Ingrese una cantidad entre 1 y 999,999',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            if (precio < 0 || precio > 99999999.99) {
                Swal.fire({
                    title: 'Precio inválido',
                    text: 'Ingrese un precio entre 0 y 99,999,999.99',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }

            productos.push({
                id: productoSeleccionado.id,
                nombre: productoSeleccionado.nombre,
                cantidad: cant,
                precio: precio,
                fecha_vencimiento: venc,
                total: cant * precio
            });
            actualizarTabla();

            productoSeleccionado = null;
            toolboxInput.value = '';
            document.getElementById('inputCant').value = 1;
            document.getElementById('inputPrecio').value = '';
            document.getElementById('inputVencimiento').value = '';
            if (toolboxInput) toolboxInput.focus();
        }

        function quitarProducto(idx) {
            productos.splice(idx, 1);
            actualizarTabla();
        }

        function actualizarTabla() {
            const body = document.getElementById('productosBody');
            if (productos.length === 0) {
                body.innerHTML = '<tr id="filaVacia"><td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;border-bottom:1px solid var(--jv-border);">⬆ Busque un producto y presione + para agregarlo</td></tr>';
            } else {
                body.innerHTML = '';
                productos.forEach((p, i) => {
                    const tr = document.createElement('tr');
                    const fechaFmt = p.fecha_vencimiento ? p.fecha_vencimiento.split('-').reverse().join('/') : '—';
                    tr.innerHTML = '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (i + 1) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + escapeHtml(p.nombre) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;border-bottom:1px solid var(--jv-border);">' + p.cantidad + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:right;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + fmt(p.precio) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + fechaFmt + '</td>' +
                        '<td style="padding:10px 12px;font-size:1rem;text-align:right;color:var(--jv-navy);font-weight:700;border-bottom:1px solid var(--jv-border);">' + fmt(p.total) + '</td>' +
                        '<td style="padding:10px 12px;border-bottom:1px solid var(--jv-border);"><button type="button" class="btn btn-sm border-0" style="padding:0;color:var(--jv-danger);font-size:1rem;line-height:1;" onclick="quitarProducto(' + i + ')"><i class="bi bi-x-circle"></i></button></td>';
                    body.appendChild(tr);
                });
            }
            const subtotal = productos.reduce(function(s, p) {
                return s + p.total;
            }, 0);
            const iva = subtotal * IVA_PCT / 100;
            const total = subtotal + iva;
            document.getElementById('totalItems').textContent = productos.length;
            document.getElementById('totalSubtotal').textContent = fmt(subtotal);
            document.getElementById('totalIva').textContent = fmt(iva);
            document.getElementById('totalCosto').textContent = fmt(total);
            document.getElementById('btnGuardar').disabled = productos.length === 0;
            document.getElementById('productosData').value = JSON.stringify(productos);
        }

        // ==========================================
        // PROVEEDOR — condición / crédito
        // ==========================================
        document.getElementById('selProveedor').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const credRow = document.getElementById('rowCredito');
            if (opt && opt.value) {
                const cond = opt.dataset.condicion || 'Contado';
                const dias = opt.dataset.dias || '0';
                const rif = opt.dataset.rif || '';
                document.getElementById('displayRif').value = rif;
                document.getElementById('displayCondicion').value = cond;
                document.getElementById('displayDias').value = dias;
                const rifOk = /^[VEJGPC]-\d{8}-\d$/.test(rif);
                document.getElementById('displayRif').style.color = rifOk ? 'var(--jv-text-muted)' : '#DC2626';
                const limite = parseFloat(opt.dataset.limite) || 0;
                const usado = parseFloat(opt.dataset.usado) || 0;
                if (limite > 0 && cond === 'Credito') {
                    credRow.style.display = '';
                    document.getElementById('displayLimite').value = '$ ' + limite.toFixed(2);
                    document.getElementById('displayUsado').value = '$ ' + usado.toFixed(2);
                    const disp = Math.max(0, limite - usado);
                    document.getElementById('displayDisponible').value = '$ ' + disp.toFixed(2);
                    document.getElementById('displayDisponible').style.color = disp > 0 ? (disp < limite * 0.3 ? '#DC2626' : '#16A34A') : '#DC2626';
                } else {
                    credRow.style.display = 'none';
                }
            } else {
                document.getElementById('displayRif').value = '-';
                document.getElementById('displayCondicion').value = '-';
                document.getElementById('displayDias').value = '-';
                credRow.style.display = 'none';
            }
        });

        // ==========================================
        // VALIDACIÓN DEL FORMULARIO
        // ==========================================
        function validarFormulario(btn) {
            limpiarErrores();
            const errores = [];
            let primerError = null;

            const prov = document.getElementById('selProveedor');
            if (!prov.value) {
                errores.push('SELECCIONE UN PROVEEDOR');
                marcarError(prov);
                if (!primerError) primerError = prov;
            } else {
                const opt = prov.options[prov.selectedIndex];
                const rifProv = (opt.dataset.rif || '').toUpperCase().replace(/\s+/g, '');
                if (!/^[VEJGPC]-\d{8}-\d$/.test(rifProv)) {
                    errores.push('EL PROVEEDOR SELECCIONADO TIENE UN RIF INVÁLIDO');
                    marcarError(prov);
                    if (!primerError) primerError = prov;
                }
            }

            const fac = document.querySelector('input[name="nro_factura"]');
            if (!fac.value.trim()) {
                errores.push('NRO. FACTURA ES OBLIGATORIO');
                marcarError(fac);
                if (!primerError) primerError = fac;
            }

            const ctrl = document.querySelector('input[name="nro_control"]');
            const ctrlVal = ctrl.value.trim();
            if (ctrlVal && !/^\d{2}-\d{8}$/.test(ctrlVal)) {
                errores.push('NRO. CONTROL INVÁLIDO (00-00000000)');
                marcarError(ctrl);
                if (!primerError) primerError = ctrl;
            }

            const metodo = document.getElementById('selMetodo');
            if (!metodo.value) {
                errores.push('SELECCIONE UN MÉTODO DE PAGO');
                marcarError(metodo);
                if (!primerError) primerError = metodo;
            }

            const montoEl = document.getElementById('montoPago');
            const monto = parseFloat(montoEl.value.replace(/,/g, '')) || 0;
            if (monto < 0 || monto > 99999999.99) {
                errores.push('MONTO DE PAGO INVÁLIDO (MÁXIMO 99,999,999.99)');
                marcarError(montoEl);
                if (!primerError) primerError = montoEl;
            }

            if (productos.length === 0) errores.push('AGREGUE AL MENOS UN PRODUCTO');

            if (errores.length > 0) {
                if (primerError) {
                    primerError.focus();
                    primerError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                Swal.fire({
                    title: 'CAMPOS REQUERIDOS',
                    html: errores.join('<br>'),
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return false;
            }

            montoEl.value = monto.toFixed(2);
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> GUARDANDO...';
            btn.form.submit();
            return false;
        }

        // ==========================================
        // LISTADO — filtro local y anulación
        // ==========================================
        function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaEntradas').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿ANULAR?',
                text: 'La factura quedará anulada. No se puede anular si la mercancía ya fue recibida.',
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'SÍ, ANULAR',
                cancelButtonText: 'CANCELAR'
            }).then(r => {
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.c0 });
            });
        }

        // ==========================================
        // INICIALIZACIÓN
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.flash-auto').forEach(el => {
                setTimeout(() => {
                    el.style.transition = 'opacity .5s';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 500);
                }, 4000);
            });

            // Prefill desde una solicitud de compra (atender_solicitud)
            if (window.COMPRAS_SOLICITUD && window.COMPRAS_SOLICITUD.items && window.COMPRAS_SOLICITUD.items.length) {
                window.COMPRAS_SOLICITUD.items.forEach(function(it) {
                    productos.push({
                        id: it.id,
                        nombre: it.nombre,
                        cantidad: it.cantidad,
                        precio: it.precio,
                        fecha_vencimiento: it.fecha_vencimiento || '',
                        total: it.cantidad * it.precio
                    });
                });
                actualizarTabla();
                const modalC = document.getElementById('modalCompra');
                if (modalC) {
                    setTimeout(function() {
                        const m = bootstrap.Modal.getOrCreateInstance(modalC);
                        m.show();
                    }, 200);
                }
            }

            document.querySelectorAll('input, select, textarea').forEach(function(el) {
                el.addEventListener('input', function() {
                    this.classList.remove('input-error');
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
                el.addEventListener('change', function() {
                    this.classList.remove('input-error');
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
            });

            // Toolbox: búsqueda con debounce
            if (toolboxInput && resultadosBox) {
                toolboxInput.addEventListener('input', buscarProductos);
                toolboxInput.addEventListener('focus', function() {
                    if (this.value.trim()) buscarProductos();
                });
                toolboxInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') cerrarResultados();
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const primero = resultadosBox.querySelector('.com-resultado');
                        if (primero) seleccionarProducto(primero);
                    }
                });
                resultadosBox.addEventListener('click', function(e) {
                    const item = e.target.closest('.com-resultado');
                    if (item) seleccionarProducto(item);
                });
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.com-toolbox')) cerrarResultados();
                });
            }

            // Sidebar (mainWrapper definido en sidebar.js)
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
