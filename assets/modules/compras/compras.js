
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

        // Consulta la tasa BCV oficial al backend (proxy de ve.dolarapi.com)
        // Rellena el campo #tasaCambio y muestra info de la fuente.
        function obtenerTasaCambio(tipo) {
            var tasaEl = document.getElementById('tasaCambio');
            var infoEl = document.getElementById('tasaInfo');
            if (!tasaEl) return;
            tasaEl.value = '';
            tasaEl.readOnly = true;
            tasaEl.classList.add('comp-tasa-auto');
            tasaEl.classList.remove('comp-tasa-editable');
            var btn = document.getElementById('btnEditarTasa');
            if (btn) {
                btn.innerHTML = '<i class="bi bi-pencil"></i>';
                btn.setAttribute('data-tooltip', 'Clic para editar manualmente');
            }
            if (infoEl) infoEl.innerHTML = '<i class="bi bi-hourglass-split"></i> Consultando tasa BCV...';

            fetch('index.php?url=tasas/obtener')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.ok) {
                        if (infoEl) infoEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error al obtener tasa. Edita manualmente.';
                        return;
                    }
                    var tasa = tipo === 'Dolar' ? data.usd_oficial : data.eur_oficial;
                    if (!tasa || tasa <= 0) {
                        if (infoEl) infoEl.innerHTML = '<i class="bi bi-exclamation-circle"></i> Tasa no disponible. Edita manualmente.';
                        return;
                    }
                    tasaEl.value = Number(tasa).toFixed(4);
                    var moneda = tipo === 'Dolar' ? 'USD' : 'EUR';
                    if (infoEl) infoEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> BCV ' + data.fecha + ' &mdash; 1 ' + moneda + ' = ' + Number(tasa).toFixed(2) + ' VES';
                    calcularEquivalenteVES();
                })
                .catch(function() {
                    if (infoEl) infoEl.innerHTML = '<i class="bi bi-wifi-off"></i> Sin conexión. Edita la tasa manualmente.';
                });
        }

        // Calcula el equivalente en VES del monto original en divisa extranjera
        function calcularEquivalenteVES() {
            var tasaEl = document.getElementById('tasaCambio');
            var montoEl = document.getElementById('montoOriginal');
            var resultadoEl = document.getElementById('equivalenteVES');
            var hiddenEl = document.getElementById('equivalenteVESInput');
            var montoPagoEl = document.getElementById('montoPago');
            if (!tasaEl || !montoEl) return;
            var tasa = parseFloat(tasaEl.value.replace(/,/g, '')) || 0;
            var monto = parseFloat(montoEl.value.replace(/,/g, '')) || 0;
            var equivalente = tasa > 0 && monto > 0 ? tasa * monto : 0;
            if (resultadoEl) {
                resultadoEl.textContent = equivalente > 0 ? 'Bs. ' + equivalente.toFixed(2) : 'Bs. 0.00';
            }
            if (hiddenEl) hiddenEl.value = equivalente > 0 ? equivalente.toFixed(2) : '0';
            // Auto-llenar monto pagado con el equivalente VES
            if (montoPagoEl && equivalente > 0) {
                montoPagoEl.value = equivalente.toFixed(2);
                montoEditado = false;
            }
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
            const facVal = fac.value.trim();
            if (!facVal) {
                errores.push('EL NÚMERO DE FACTURA ES OBLIGATORIO');
                marcarError(fac);
                if (!primerError) primerError = fac;
            } else if (!/^\d{6,8}$/.test(facVal)) {
                errores.push('NRO. FACTURA: DEBE TENER ENTRE 6 Y 8 DÍGITOS');
                marcarError(fac);
                if (!primerError) primerError = fac;
            }

            const ctrl = document.querySelector('input[name="nro_control"]');
            const ctrlVal = ctrl.value.trim();
            if (!ctrlVal) {
                errores.push('EL NÚMERO DE CONTROL ES OBLIGATORIO');
                marcarError(ctrl);
                if (!primerError) primerError = ctrl;
            } else if (!/^\d{2}-\d{8}$/.test(ctrlVal)) {
                errores.push('NRO. CONTROL INVÁLIDO (00-00000000)');
                marcarError(ctrl);
                if (!primerError) primerError = ctrl;
            }

            const metodo = document.getElementById('selMetodo');
            if (!metodo.value) {
                errores.push('SELECCIONE UN MÉTODO DE PAGO');
                marcarError(metodo);
                if (!primerError) primerError = metodo;
            } else {
                if (metodo.value === 'Efectivo') {
                    const tipoDivisa = document.getElementById('selEfectivoTipo');
                    if (!tipoDivisa.value) {
                        errores.push('SELECCIONE EL TIPO DE DIVISA (BOLÍVARES O DÓLAR/EURO)');
                        marcarError(tipoDivisa);
                        if (!primerError) primerError = tipoDivisa;
                    } else if (tipoDivisa.value !== 'Bolivares') {
                        const tasa = document.getElementById('tasaCambio');
                        const tasaVal = parseFloat(tasa.value.replace(/,/g, '')) || 0;
                        if (tasaVal <= 0) {
                            errores.push('LA TASA DE CAMBIO DEBE SER MAYOR A 0');
                            marcarError(tasa);
                            if (!primerError) primerError = tasa;
                        }
                        const montoOrig = document.getElementById('montoOriginal');
                        const montoOrigVal = parseFloat(montoOrig.value.replace(/,/g, '')) || 0;
                        if (montoOrigVal <= 0) {
                            errores.push('EL MONTO EN LA DIVISA ES OBLIGATORIO');
                            marcarError(montoOrig);
                            if (!primerError) primerError = montoOrig;
                        }
                    }
                } else if (metodo.value === 'Transferencia') {
                    const cedula = document.getElementById('pmCedula');
                    const telefono = document.getElementById('pmTelefono');
                    const banco = document.getElementById('pmBanco');
                    const referencia = document.getElementById('pmReferencia');
                    if (!cedula.value.trim()) {
                        errores.push('LA CÉDULA DEL BENEFICIARIO ES OBLIGATORIA');
                        marcarError(cedula);
                        if (!primerError) primerError = cedula;
                    }
                    if (!telefono.value.trim()) {
                        errores.push('EL TELÉFONO ES OBLIGATORIO');
                        marcarError(telefono);
                        if (!primerError) primerError = telefono;
                    }
                    if (!banco.value) {
                        errores.push('SELECCIONE EL BANCO DESTINO');
                        marcarError(banco);
                        if (!primerError) primerError = banco;
                    }
                    if (!referencia.value.trim()) {
                        errores.push('EL NÚMERO DE REFERENCIA ES OBLIGATORIO');
                        marcarError(referencia);
                        if (!primerError) primerError = referencia;
                    }
                } else if (metodo.value === 'Cheque') {
                    const cheque = document.getElementById('chequeNumero');
                    if (!cheque.value.trim()) {
                        errores.push('EL NÚMERO DE CHEQUE ES OBLIGATORIO');
                        marcarError(cheque);
                        if (!primerError) primerError = cheque;
                    }
                } else if (metodo.value === 'Otro') {
                    const otro = document.getElementById('otroDescripcion');
                    if (!otro.value.trim()) {
                        errores.push('DESCRIBA LA FORMA DE PAGO');
                        marcarError(otro);
                        if (!primerError) primerError = otro;
                    }
                }
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
        // GESTIÓN INTEGRADA DE PROVEEDORES (pop-ups)
        // Registrar / modificar / desactivar proveedores y
        // administrar su catálogo de costos sin salir de Compras.
        // ==========================================

        let provEstado = 'todos';
        let provDesdeLista = false;

        // Abre el pop-up del gestor reiniciando búsqueda y filtro de estado.
        function abrirGestorProv() {
            const buscadorProv = document.getElementById('buscarProv');
            if (buscadorProv) buscadorProv.value = '';
            provEstado = 'todos';
            document.querySelectorAll('#modalProvList .btn-filter').forEach(function(b) { b.classList.remove('active'); });
            const btnTodos = document.querySelector('#modalProvList .btn-filter');
            if (btnTodos) btnTodos.classList.add('active');
            provFiltrar();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).show();
        }

        // Cambia el filtro por estado (todos/Activo/Inactivo) y reaplica el filtrado.
        function provSetFiltro(status, boton) {
            provEstado = status;
            document.querySelectorAll('#modalProvList .btn-filter').forEach(function(b) { b.classList.remove('active'); });
            boton.classList.add('active');
            provFiltrar();
        }

        // Filtra las filas por texto (empresa/RIF/teléfono) y estado seleccionado;
        // al ocultar un proveedor también oculta su panel de catálogo expandido.
        function provFiltrar() {
            const texto = (document.getElementById('buscarProv').value || '').toLowerCase().trim();
            document.querySelectorAll('#provTbody .prov-fila').forEach(function(fila) {
                const visible = (provEstado === 'todos' || fila.dataset.status === provEstado)
                    && (!texto || (fila.dataset.texto || '').indexOf(texto) !== -1);
                fila.style.display = visible ? '' : 'none';
                const detalle = fila.nextElementSibling;
                if (detalle && detalle.classList.contains('prov-detalle-row') && !visible) {
                    detalle.style.display = 'none';
                }
            });
        }

        // Muestra u oculta el panel con los productos que suministra un proveedor.
        function provToggleDetalle(idProveedor) {
            const detalle = document.getElementById('prov-detalle-' + idProveedor);
            if (detalle) detalle.style.display = (detalle.style.display === 'none') ? '' : 'none';
        }

        // Prepara y abre el formulario en modo registro (viene desde el listado).
        function nuevoProv() {
            provDesdeLista = true;
            provAbrirForm(null);
        }

        // Prepara y abre el formulario en modo edición con los datos recibidos.
        function editarProv(proveedorData) {
            provDesdeLista = true;
            provAbrirForm(proveedorData);
        }

        // Rellena el formulario de proveedor y cambia del listado al formulario
        // (oculta el listado para evitar modales apilados).
        function provAbrirForm(proveedorData) {
            const esEdicion = !!proveedorData;
            document.getElementById('p_accion').value = esEdicion ? 'editar' : 'registrar';
            document.getElementById('p_id_edit').value = esEdicion ? proveedorData.id_proveedor : '';
            document.getElementById('modalTitle').innerText = esEdicion ? 'Editar Proveedor' : 'Registrar Nuevo Proveedor';
            document.getElementById('p_rif').value = esEdicion ? proveedorData.rif : '';
            document.getElementById('p_empresa').value = esEdicion ? proveedorData.nombre_empresa : '';
            document.getElementById('p_contacto_nombre').value = esEdicion ? (proveedorData.contacto || '') : '';
            document.getElementById('p_email').value = esEdicion ? (proveedorData.email || '') : '';
            document.getElementById('p_direccion').value = esEdicion ? (proveedorData.direccion || '') : '';
            document.getElementById('p_lead_time').value = esEdicion ? (proveedorData.lead_time || '') : '';
            document.getElementById('p_moneda').value = esEdicion ? (proveedorData.moneda || 'USD') : 'USD';
            document.getElementById('p_status').value = esEdicion ? (proveedorData.status || 'Activo') : 'Activo';

            const btnSubmit = document.getElementById('btn-prov-submit');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';
            document.getElementById('p_rif').dispatchEvent(new Event('input'));

            if (window.provIti && typeof window.provIti.setNumber === 'function') {
                window.provIti.setNumber(esEdicion ? (proveedorData.telefono || '') : '');
                document.getElementById('p_tel_full').value = window.provIti.getNumber();
            } else if (window.provIti && typeof window.provIti.reset === 'function') {
                window.provIti.reset();
                document.getElementById('p_tel_full').value = '';
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
            }, 300);
        }

        // Confirma activar/desactivar un proveedor y envía la acción al servidor.
        function provToggleStatus(idProveedor, nombre, statusActual) {
            const activo = statusActual === 'Activo';
            Swal.fire({
                title: activo ? '\u00bfDesactivar proveedor?' : '\u00bfActivar proveedor?',
                html: activo ? 'Se desactivar\u00e1 <strong>' + escapeHtml(nombre) + '</strong>.' : 'Se reactivar\u00e1 <strong>' + escapeHtml(nombre) + '</strong>.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: activo ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: activo ? 'S\u00ed, desactivar' : 'S\u00ed, activar',
                cancelButtonText: 'Cancelar',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({
                        accion_proveedor: 'toggle_status',
                        id_proveedor: idProveedor,
                        csrf_token: window.JV_CONFIG.csrfToken
                    });
                }
            });
        }

        // Formatea en vivo el costo con separador de miles y un solo punto decimal.
        function provFormatMoney(inputElement) {
            let rawValue = inputElement.value.replace(/[^0-9.]/g, '');
            let valueParts = rawValue.split('.');
            if (valueParts.length > 2) valueParts = [valueParts[0], valueParts.slice(1).join('')];
            valueParts[0] = valueParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            inputElement.value = valueParts.join('.');
        }

        // Abre el pop-up de catálogo en modo registro para el proveedor indicado.
        function provAgregarCat(idProveedor, nombreProveedor) {
            document.getElementById('cat_accion').value = 'registrar';
            document.getElementById('cat_id_edit').value = '';
            document.getElementById('cat_id_prov').value = idProveedor;
            document.getElementById('cat_proveedor_nombre').value = nombreProveedor;
            document.getElementById('catTitulo').innerText = 'AGREGAR PRODUCTO';
            document.getElementById('catSubtitulo').innerText = 'Asocia un producto a este proveedor con su costo de compra.';
            document.getElementById('cat_producto').value = '';
            document.getElementById('cat_costo').value = '';
            document.getElementById('cat_codigo_prov').value = '';
            const btnCat = document.getElementById('btn-cat-submit');
            btnCat.disabled = false;
            btnCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR EN CAT\u00c1LOGO';
            provDesdeLista = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCatalogo')).show();
            }, 300);
        }

        // Abre el pop-up de catálogo en modo edición con los datos de la entrada.
        function editarProductoCatalogo(entradaData) {
            document.getElementById('cat_accion').value = 'editar';
            document.getElementById('cat_id_edit').value = entradaData.id_catalogo;
            document.getElementById('cat_id_prov').value = entradaData.id_proveedor;
            const proveedorOrigen = (window.JV_PROVS || []).find(function(p) { return parseInt(p.id_proveedor, 10) === parseInt(entradaData.id_proveedor, 10); });
            document.getElementById('cat_proveedor_nombre').value = proveedorOrigen ? proveedorOrigen.nombre_empresa : '';
            document.getElementById('catTitulo').innerText = 'EDITAR PRODUCTO DEL CAT\u00c1LOGO';
            document.getElementById('catSubtitulo').innerText = 'Actualiza el costo o el c\u00f3digo interno del producto.';
            document.getElementById('cat_producto').value = entradaData.id_producto;
            document.getElementById('cat_costo').value = parseFloat(entradaData.costo).toFixed(2);
            provFormatMoney(document.getElementById('cat_costo'));
            document.getElementById('cat_codigo_prov').value = entradaData.codigo_proveedor || '';
            const btnCat = document.getElementById('btn-cat-submit');
            btnCat.disabled = false;
            btnCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR EN CAT\u00c1LOGO';
            provDesdeLista = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCatalogo')).show();
            }, 300);
        }

        // Confirma quitar un producto del catálogo y envía la acción al servidor.
        function provEliminarCat(idCatalogo, nombreProducto) {
            Swal.fire({
                title: '\u00bfQUITAR DEL CAT\u00c1LOGO?',
                html: 'Se quitar\u00e1 <strong>' + escapeHtml(nombreProducto) + '</strong> del cat\u00e1logo de este proveedor.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EA580C',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'S\u00ed, quitar',
                cancelButtonText: 'Cancelar',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({ eliminar_catalogo: idCatalogo, csrf_token: window.JV_CONFIG.csrfToken });
                }
            });
        }

        // Inicialización exclusiva del gestor de proveedores.
        document.addEventListener('DOMContentLoaded', function() {
            // Selector internacional de teléfono del formulario de proveedor
            const telInputProv = document.getElementById('p_tel');
            if (telInputProv && window.intlTelInput) {
                window.provIti = window.intlTelInput(telInputProv, {
                    initialCountry: 've',
                    preferredCountries: ['ve', 'us', 'co', 'es', 'mx', 'pa'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
                });
                const sincronizarTel = function() {
                    document.getElementById('p_tel_full').value = window.provIti.getNumber();
                };
                telInputProv.addEventListener('countrychange', sincronizarTel);
                telInputProv.addEventListener('input', sincronizarTel);
            }

            // Máscara en vivo del RIF: letra-tipo + cuerpo de 8 dígitos + dígito verificador
            const rifInput = document.getElementById('p_rif');
            if (rifInput) {
                rifInput.addEventListener('input', function(e) {
                    let valor = e.target.value.toUpperCase().replace(/[^VEJGPC0-9]/g, '');
                    let formateado = '';
                    if (valor.length > 0) {
                        formateado += valor[0];
                        if (valor.length > 1) {
                            formateado += '-';
                            const cuerpo = valor.substring(1).slice(0, 9);
                            formateado += cuerpo.length > 8 ? cuerpo.substring(0, 8) + '-' + cuerpo.substring(8) : cuerpo;
                        }
                    }
                    e.target.value = formateado.substring(0, 13);
                });
            }

            // Formateo en vivo del costo del catálogo
            const costoCat = document.getElementById('cat_costo');
            if (costoCat) costoCat.addEventListener('input', function() { provFormatMoney(this); });

            // Validación del formulario de proveedor (con anti-doble-click)
            const formProv = document.getElementById('formProveedor');
            if (formProv) {
                formProv.addEventListener('submit', function(e) {
                    limpiarErrores();
                    let primerError = null;

                    const empresaEl = document.getElementById('p_empresa');
                    if (!empresaEl.value.trim()) {
                        marcarError(empresaEl, 'NOMBRE REQUERIDO');
                        e.preventDefault();
                        if (!primerError) primerError = empresaEl;
                    }

                    const rifValidar = document.getElementById('p_rif');
                    if (!/^[VEJGPC]-\d{8}-\d$/.test(rifValidar.value)) {
                        marcarError(rifValidar, 'RIF INV\u00c1LIDO (J-12345678-0)');
                        e.preventDefault();
                        if (!primerError) primerError = rifValidar;
                    }

                    if (window.provIti) {
                        document.getElementById('p_tel_full').value = window.provIti.getNumber();
                        var telRaw = window.provIti.getNumber().replace(/\D/g, '');
                        // Venezuela: +58 + 10 dígitos = 12 dígitos totales
                        if (telRaw.length < 12) {
                            const telValidar = document.getElementById('p_tel');
                            marcarError(telValidar, 'TEL\u00c9FONO INV\u00c1LIDO (10 d\u00edgitos requeridos)');
                            e.preventDefault();
                            if (!primerError) primerError = telValidar;
                        }
                    }

                    const emailValidar = document.getElementById('p_email');
                    if (emailValidar.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValidar.value.trim())) {
                        marcarError(emailValidar, 'EMAIL INV\u00c1LIDO');
                        e.preventDefault();
                        if (!primerError) primerError = emailValidar;
                    }

                    if (primerError) {
                        primerError.focus();
                        return;
                    }

                    const btnGuardarProv = document.getElementById('btn-prov-submit');
                    btnGuardarProv.disabled = true;
                    btnGuardarProv.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });
            }

            // Validación del formulario de catálogo (anti-doble-click incluido)
            const formCat = document.getElementById('formCatalogo');
            if (formCat) {
                formCat.addEventListener('submit', function(e) {
                    limpiarErrores();
                    const prodSel = document.getElementById('cat_producto');
                    if (!prodSel.value) {
                        marcarError(prodSel, 'SELECCIONA UN PRODUCTO');
                        e.preventDefault();
                        prodSel.focus();
                        return;
                    }
                    const costoRaw = document.getElementById('cat_costo').value.replace(/,/g, '');
                    if (!(parseFloat(costoRaw) > 0)) {
                        marcarError(document.getElementById('cat_costo'), 'COSTO REQUERIDO (MAYOR A 0)');
                        e.preventDefault();
                        document.getElementById('cat_costo').focus();
                        return;
                    }
                    const btnGuardarCat = document.getElementById('btn-cat-submit');
                    btnGuardarCat.disabled = true;
                    btnGuardarCat.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });
            }

            // Si el usuario cierra el formulario/catálogo sin guardar, vuelve al listado
            ['modalProveedor', 'modalCatalogo'].forEach(function(idModal) {
                const elemento = document.getElementById(idModal);
                if (elemento) {
                    elemento.addEventListener('hidden.bs.modal', function() {
                        if (provDesdeLista) {
                            provDesdeLista = false;
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).show();
                        }
                    });
                }
            });

            // Si el servidor rechazó el guardado, marca el campo exacto que lo causó
            // traduciendo el texto del flash de peligro al input correspondiente.
            const flashProv = document.getElementById('flashMsg');
            if (flashProv && flashProv.classList.contains('alert-jv-danger')) {
                const textoFlash = (flashProv.dataset.texto || '').toUpperCase();
                const mapaCampos = [
                    ['RIF', 'p_rif'],
                    ['NOMBRE', 'p_empresa'],
                    ['CORREO', 'p_email'],
                    ['EMAIL', 'p_email'],
                    ['TEL\u00c9FONO', 'p_tel']
                ];
                for (let i = 0; i < mapaCampos.length; i++) {
                    if (textoFlash.indexOf(mapaCampos[i][0]) !== -1) {
                        const campoFallido = document.getElementById(mapaCampos[i][1]);
                        if (campoFallido) {
                            marcarError(campoFallido, textoFlash);
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
                        }
                        break;
                    }
                }
            }

            // Limpia marcas de error del formulario de proveedor al escribir
            document.querySelectorAll('#formProveedor input, #formProveedor select, #formProveedor textarea, #formCatalogo input, #formCatalogo select')
                .forEach(function(elementoForm) {
                    elementoForm.addEventListener('input', function() {
                        this.classList.remove('input-error');
                        const err = document.getElementById(this.id + '_err');
                        if (err) err.remove();
                    });
                });
        });

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

            // Pago dinámico: mostrar/ocultar sub-campos según método seleccionado
            var selMetodo = document.getElementById('selMetodo');
            if (selMetodo) {
                selMetodo.addEventListener('change', function() {
                    var val = this.value;
                    document.querySelectorAll('.comp-pago-detalle').forEach(function(el) { el.style.display = 'none'; });
                    document.getElementById('pagoEfectivo').style.display = val === 'Efectivo' ? '' : 'none';
                    document.getElementById('pagoTransferencia').style.display = val === 'Transferencia' ? '' : 'none';
                    document.getElementById('pagoCheque').style.display = val === 'Cheque' ? '' : 'none';
                    document.getElementById('pagoOtro').style.display = val === 'Otro' ? '' : 'none';
                });
            }

            // Efectivo divisa: mostrar/ocultar campos y consultar tasa BCV automáticamente
            var selEfectivoTipo = document.getElementById('selEfectivoTipo');
            if (selEfectivoTipo) {
                selEfectivoTipo.addEventListener('change', function() {
                    var esDivisa = this.value !== 'Bolivares' && this.value !== '';
                    document.getElementById('divDivisaExtranjera').style.display = esDivisa ? '' : 'none';
                    // Reset campos al cambiar
                    document.getElementById('tasaCambio').value = '';
                    document.getElementById('tasaInfo').textContent = '';
                    document.getElementById('montoOriginal').value = '';
                    document.getElementById('equivalenteVES').textContent = 'Bs. 0.00';
                    document.getElementById('equivalenteVESInput').value = '0';
                    if (esDivisa) {
                        var sym = this.value === 'Dolar' ? 'USD' : 'EUR';
                        document.getElementById('labelMontoOriginal').innerHTML = 'Monto en ' + sym + ' <span class="text-danger">*</span>';
                        obtenerTasaCambio(this.value);
                    }
                });
            }

            // Botón editar tasa manualmente
            var btnEditarTasa = document.getElementById('btnEditarTasa');
            if (btnEditarTasa) {
                btnEditarTasa.addEventListener('click', function() {
                    var tasa = document.getElementById('tasaCambio');
                    if (tasa.readOnly) {
                        tasa.readOnly = false;
                        tasa.classList.remove('comp-tasa-auto');
                        tasa.classList.add('comp-tasa-editable');
                        this.innerHTML = '<i class="bi bi-check-lg"></i>';
                        this.setAttribute('data-tooltip', 'Confirmar tasa manual');
                        tasa.focus();
                        tasa.select();
                    } else {
                        tasa.readOnly = true;
                        tasa.classList.remove('comp-tasa-editable');
                        tasa.classList.add('comp-tasa-auto');
                        this.innerHTML = '<i class="bi bi-pencil"></i>';
                        this.setAttribute('data-tooltip', 'Clic para editar manualmente');
                        calcularEquivalenteVES();
                    }
                });
            }

            // Input monto original: recalcular equivalente al escribir
            var montoOriginalEl = document.getElementById('montoOriginal');
            if (montoOriginalEl) {
                montoOriginalEl.addEventListener('input', calcularEquivalenteVES);
            }

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

        // ==========================================
        // SOLICITUDES DE REPOSICIÓN (integradas)
        // Cancela una solicitud pendiente desde el listado de Compras.
        // ==========================================
        function confirmarCancelarSolicitud(idSolicitud) {
            Swal.fire({
                title: '\u00bfCANCELAR SOLICITUD?',
                text: 'La solicitud quedar\u00e1 anulada.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'S\u00ed, cancelar',
                cancelButtonText: 'Volver',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('accion_cancelar_solicitud', '1');
                formData.append('id_solicitud', idSolicitud);
                formData.append('csrf_token', (window.JV_CONFIG || {}).csrfToken || '');
                fetch((window.JV_BASE || '') + 'index.php?url=solicitudes/cancelar', {
                    method: 'POST',
                    body: formData
                }).then(function() {
                    window.location.reload();
                });
            });
        }

        // ==========================================
        // RECEPCIÓN DE MERCADERÍA (integrada en Compras)
        // Registra el ingreso a inventario: lotes, stock y costo promedio.
        // El stock solo sube al confirmar la recepción (FEFO con vencimiento).
        // ==========================================
        let recepcionCompraId = null;

        // Abre el modal de recepción cargando los datos de la compra y la tabla
        // de productos pendientes por recibir (desde window.JV_CONFIG.recepcionDatos).
        function abrirRecepcion(idCompra) {
            const recConfig = window.JV_CONFIG || {};
            const purchaseData = recConfig.recepcionDatos && recConfig.recepcionDatos[idCompra];
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
            document.getElementById('recDocumento').value = '';

            const recBody = document.getElementById('recItemsBody');
            recBody.innerHTML = '';
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
                recBody.appendChild(tr);
            });

            const recModal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
            recModal.show();
        }

        // Filtra por texto la tabla de compras pendientes de recepción, sin recargar.
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

        // Valida las cantidades a recibir (entre 1 y el restante), arma el array de ítems,
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
                    errores.push('Cantidad inv\u00e1lida para un producto (m\u00e1x. ' + remainingQuantity + ').');
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
                    title: 'CANTIDADES INV\u00c1LIDAS',
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

        // ==========================================
        // MODAL DE PAGO
        // ==========================================

        let pagoTotal = 0;
        let pagoSaldo = 0;

        function abrirModalPago(idCompra, factura, total, saldo) {
            pagoTotal = total;
            pagoSaldo = saldo;
            document.getElementById('pago_id_compra').value = idCompra;
            document.getElementById('pago_factura_num').textContent = factura || '-';
            document.getElementById('pago_total').value = formatCurrency(total);
            document.getElementById('pago_saldo').value = formatCurrency(saldo);
            document.getElementById('pago_monto').value = '';
            document.getElementById('pago_monto').max = saldo.toFixed(2);
            document.getElementById('pago_monto').placeholder = saldo.toFixed(2);
            document.getElementById('pago_metodo').value = 'Efectivo';
            togglePagoDetalle();
            var modal = new bootstrap.Modal(document.getElementById('modalPago'));
            modal.show();
        }

        function setPagoMonto(pct) {
            var monto = (pagoSaldo * pct / 100).toFixed(2);
            document.getElementById('pago_monto').value = monto;
        }

        function togglePagoDetalle() {
            var metodo = document.getElementById('pago_metodo').value;
            document.getElementById('pago_detalle_transferencia').style.display = metodo === 'Transferencia' ? 'block' : 'none';
            document.getElementById('pago_detalle_cheque').style.display = metodo === 'Cheque' ? 'block' : 'none';
            document.getElementById('pago_detalle_otro').style.display = metodo === 'Otro' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            var metodoSelect = document.getElementById('pago_metodo');
            if (metodoSelect) {
                metodoSelect.addEventListener('change', togglePagoDetalle);
            }
        });
