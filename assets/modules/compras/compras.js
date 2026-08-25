
        // ==========================================
        // COMPRAS — Registro de factura del proveedor + comprobante de pago
        // (No mueve stock: la recepción se hace desde Inventario)
        // ==========================================

        let productos = [];
        let montoEditado = false;
        let toolboxTimer = null;
        let productoSeleccionado = null;

        const taxPercentage = (window.JV_CONFIG && typeof window.JV_CONFIG.taxPercentage === 'number') ? window.JV_CONFIG.taxPercentage : 16;

        // Formatea un número como moneda en dólares: $ con separador de miles y 2 decimales.
        function formatCurrency(amount) {
            return '$' + Number(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // ---- Formateo de precio en el input ----
        // Normaliza lo escrito en el input de precio: solo dígitos y punto, separador de miles y tope de 99,999,999.99.
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
        // Pone montoEditado en true para que el monto no se recalcule y quita la marca de error del campo.
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

        // Oculta el panel de resultados de búsqueda de productos.
        function cerrarResultados() {
            if (resultadosBox) resultadosBox.classList.remove('abierto');
        }

        // Muestra el panel de resultados de búsqueda de productos.
        function abrirResultados() {
            if (resultadosBox) resultadosBox.classList.add('abierto');
        }

        // Dibuja en el panel los productos devueltos por la búsqueda AJAX; si no hay, muestra "Sin resultados".
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
            items.forEach(function(product) {
                const div = document.createElement('div');
                div.className = 'com-resultado';
                div.dataset.id = product.id;
                div.dataset.nombre = product.nombre;
                div.dataset.precio = product.precio_costo;

                const left = document.createElement('div');
                const nombreEl = document.createElement('div');
                nombreEl.className = 'r-nombre';
                nombreEl.textContent = product.nombre;
                const skuEl = document.createElement('div');
                skuEl.className = 'r-sku';
                skuEl.textContent = product.sku || '';
                left.appendChild(nombreEl);
                left.appendChild(skuEl);

                const stockEl = document.createElement('span');
                stockEl.className = 'r-stock';
                stockEl.textContent = 'Stock: ' + product.stock;

                div.appendChild(left);
                div.appendChild(stockEl);
                resultadosBox.appendChild(div);
            });
            abrirResultados();
        }

        // Ejecuta la búsqueda de productos por AJAX con debounce de 350 ms para no consultar en cada tecla.
        function buscarProductos() {
            const searchTerm = toolboxInput.value.trim();
            if (!searchTerm) {
                cerrarResultados();
                productoSeleccionado = null;
                return;
            }
            window.clearTimeout(toolboxTimer);
            toolboxTimer = window.setTimeout(function() {
                jvBuscarProductos({ q: searchTerm, limit: 15 }, function(searchResponse) {
                    if (searchResponse && searchResponse.success) renderResultados(searchResponse.items);
                    else renderResultados([]);
                });
            }, 350);
        }

        // Al elegir un resultado guarda el producto seleccionado, rellena el input y sugiere el precio de costo.
        // Prioridad de la sugerencia: catálogo del proveedor elegido → costo referencial del producto.
        function seleccionarProducto(productElement) {
            if (!productElement || !productElement.dataset.id) return;
            const idProducto = parseInt(productElement.dataset.id, 10);
            productoSeleccionado = {
                id: idProducto,
                nombre: productElement.dataset.nombre
            };
            toolboxInput.value = productoSeleccionado.nombre;
            const precioEl = document.getElementById('inputPrecio');
            if (precioEl && !precioEl.value.trim()) {
                let suggestedPrice = costoDesdeCatalogo(idProducto);
                if (suggestedPrice === null) suggestedPrice = parseFloat(productElement.dataset.precio) || 0;
                precioEl.value = suggestedPrice > 0 ? suggestedPrice.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '0.00';
            }
            cerrarResultados();
            const cantEl = document.getElementById('inputCant');
            if (cantEl) cantEl.focus();
        }

        // Busca el costo del producto en el catálogo del proveedor seleccionado.
        // Devuelve el costo (number) o null si no hay proveedor/entrada en catálogo.
        function costoDesdeCatalogo(idProducto) {
            if (!window.JV_CATALOGO) return null;
            const provSel = document.getElementById('selProveedor');
            if (!provSel || !provSel.value) return null;
            const catalogoProv = window.JV_CATALOGO[provSel.value] || {};
            return Object.prototype.hasOwnProperty.call(catalogoProv, idProducto) ? catalogoProv[idProducto] : null;
        }

        // ==========================================
        // LÍNEA DE PRODUCTO
        // ==========================================
        // Valida cantidad y precio y agrega el producto elegido al array 'productos' como línea de la factura.
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
            const requestedQuantity = parseInt(document.getElementById('inputCant').value) || 0;
            const precio = parseFloat(document.getElementById('inputPrecio').value.replace(/,/g, '')) || 0;
            const expirationDate = document.getElementById('inputVencimiento').value || '';

            if (requestedQuantity < 1 || requestedQuantity > 999999) {
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
            // REGLA DE NEGOCIO: todo lote exige fecha de vencimiento (control FEFO)
            if (!expirationDate) {
                Swal.fire({
                    title: 'Fecha de vencimiento requerida',
                    text: 'Indique la fecha de vencimiento del lote para agregar el producto',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                document.getElementById('inputVencimiento').focus();
                return;
            }

            productos.push({
                id: productoSeleccionado.id,
                nombre: productoSeleccionado.nombre,
                cantidad: requestedQuantity,
                precio: precio,
                fecha_vencimiento: expirationDate,
                total: requestedQuantity * precio
            });
            actualizarTabla();

            productoSeleccionado = null;
            toolboxInput.value = '';
            document.getElementById('inputCant').value = 1;
            document.getElementById('inputPrecio').value = '';
            document.getElementById('inputVencimiento').value = '';
            if (toolboxInput) toolboxInput.focus();
        }

        // Elimina del array la línea de producto en la posición dada y redibuja la tabla.
        function quitarProducto(productIndex) {
            productos.splice(productIndex, 1);
            actualizarTabla();
        }

        // Redibuja la tabla del carrito, calcula subtotal, IVA y total, y actualiza el campo oculto JSON y el botón Guardar.
        function actualizarTabla() {
            const body = document.getElementById('productosBody');
            if (productos.length === 0) {
                body.innerHTML = '<tr id="filaVacia"><td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;border-bottom:1px solid var(--jv-border);">⬆ Busque un producto y presione + para agregarlo</td></tr>';
            } else {
                body.innerHTML = '';
                productos.forEach((product, productIndex) => {
                    const tr = document.createElement('tr');
                    const formattedExpirationDate = product.fecha_vencimiento ? product.fecha_vencimiento.split('-').reverse().join('/') : '—';
                    tr.innerHTML = '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (productIndex + 1) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + escapeHtml(product.nombre) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;border-bottom:1px solid var(--jv-border);">' + product.cantidad + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:right;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + formatCurrency(product.precio) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + formattedExpirationDate + '</td>' +
                        '<td style="padding:10px 12px;font-size:1rem;text-align:right;color:var(--jv-navy);font-weight:700;border-bottom:1px solid var(--jv-border);">' + formatCurrency(product.total) + '</td>' +
                        '<td style="padding:10px 12px;border-bottom:1px solid var(--jv-border);"><button type="button" class="btn btn-sm border-0" style="padding:0;color:var(--jv-danger);font-size:1rem;line-height:1;" onclick="quitarProducto(' + productIndex + ')"><i class="bi bi-x-circle"></i></button></td>';
                    body.appendChild(tr);
                });
            }
            const subtotal = productos.reduce(function(runningSubtotal, product) {
                return runningSubtotal + product.total;
            }, 0);
            const iva = subtotal * taxPercentage / 100;
            const total = subtotal + iva;
            document.getElementById('totalItems').textContent = productos.length;
            document.getElementById('totalSubtotal').textContent = formatCurrency(subtotal);
            document.getElementById('totalIva').textContent = formatCurrency(iva);
            document.getElementById('totalCosto').textContent = formatCurrency(total);
            document.getElementById('btnGuardar').disabled = productos.length === 0;
            document.getElementById('productosData').value = JSON.stringify(productos);
        }

        // ==========================================
        // PROVEEDOR — RIF del proveedor elegido
        // ==========================================
        document.getElementById('selProveedor').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const rifEl = document.getElementById('displayRif');
            if (opt && opt.value) {
                const rif = opt.dataset.rif || '';
                rifEl.value = rif;
                const rifOk = /^[VEJGPC]-\d{8}-\d$/.test(rif);
                rifEl.style.color = rifOk ? 'var(--jv-text-muted)' : '#DC2626';
            } else {
                rifEl.value = '-';
                rifEl.style.color = 'var(--jv-text-muted)';
            }
        });

        // ==========================================
        // VALIDACIÓN DEL FORMULARIO
        // ==========================================
        // Valida todos los campos obligatorios (proveedor, factura, método, monto y líneas);
        // si hay errores los marca, los muestra en un resumen y evita el envío.
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
        // Filtra localmente el listado de compras según el texto escrito, sin recargar la página.
        function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaEntradas').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        // Pide confirmación con SweetAlert y, si se acepta, envía el POST para anular la factura.
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
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.csrfToken });
            });
        }

        // ==========================================
        // INICIALIZACIÓN
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            // (El auto-cierre de mensajes flash lo maneja diseno.js globalmente.)

            // Prefill desde una solicitud de compra (atender_solicitud)
            if (window.COMPRAS_SOLICITUD && window.COMPRAS_SOLICITUD.items && window.COMPRAS_SOLICITUD.items.length) {
                window.COMPRAS_SOLICITUD.items.forEach(function(requestItem) {
                    productos.push({
                        id: requestItem.id,
                        nombre: requestItem.nombre,
                        cantidad: requestItem.cantidad,
                        precio: requestItem.precio,
                        fecha_vencimiento: requestItem.fecha_vencimiento || '',
                        total: requestItem.cantidad * requestItem.precio
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
