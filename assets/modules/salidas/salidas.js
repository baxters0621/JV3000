
        // ==========================================
        // SALIDAS / VENTAS — registro con toolbox y validación FEFO
        // ==========================================

        const salidaModal = new bootstrap.Modal(document.getElementById('modalSalida'));
        const movementTypeGroups = window.JV_CONFIG.movementTypeGroups;
        let selectedProducts = [];
        let productoSeleccionado = null;
        let toolboxTimer = null;
        let toolboxTimerCli = null;

        // Devuelve el grupo del tipo de movimiento seleccionado (venta, regalias, merma).
        function grupoActual() {
            const tipo = document.getElementById('s_tipo');
            return tipo && tipo.value ? (movementTypeGroups[tipo.value] || '') : '';
        }

        // ---- Toolbox de PRODUCTOS (AJAX) ----
        const toolboxInput = document.getElementById('buscarProductoSal');
        const resultadosBox = document.getElementById('resultadosBusquedaSal');

        // Cierra el panel de resultados de búsqueda de productos.
        function cerrarResultados() {
            if (resultadosBox) resultadosBox.classList.remove('abierto');
        }
        // Abre el panel de resultados de búsqueda de productos.
        function abrirResultados() {
            if (resultadosBox) resultadosBox.classList.add('abierto');
        }

        // Dibuja la lista de productos encontrados y bloquea los que no tienen stock.
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
                div.dataset.sku = product.sku;
                div.dataset.precioVenta = product.precio_venta;
                div.dataset.precioCosto = product.precio_costo;
                div.dataset.stock = product.stock;
                div.dataset.vencido = product.vencido;

                const bloqueado = product.stock <= 0;
                if (bloqueado) {
                    div.classList.add('com-resultado-bloqueado');
                    div.dataset.blocked = '1';
                }

                const left = document.createElement('div');
                const nombreEl = document.createElement('div');
                nombreEl.className = 'r-nombre';
                const etqBloqueo = product.stock <= 0 ? '«AGOTADO» ' : (product.vencido ? '«VENCIDO» ' : '');
                nombreEl.dataset.tooltip = etqBloqueo + product.nombre;
                nombreEl.textContent = etqBloqueo + product.nombre;
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

        // Busca productos por AJAX con retardo y según el grupo actual del movimiento.
        function buscarProductos() {
            const searchTerm = toolboxInput.value.trim();
            if (!searchTerm) {
                cerrarResultados();
                productoSeleccionado = null;
                return;
            }
            const grupo = grupoActual();
            window.clearTimeout(toolboxTimer);
            toolboxTimer = window.setTimeout(function() {
                jvBuscarProductos({ q: searchTerm, limit: 15, vencidos: grupo === 'merma' ? 1 : 0, solo_con_stock: grupo === 'merma' ? 1 : 0 }, function(searchResponse) {
                    if (searchResponse && searchResponse.success) renderResultados(searchResponse.items);
                    else renderResultados([]);
                });
            }, 350);
        }

        // Maneja la elección de un producto: bloquea venta sin stock y carga el precio del grupo.
        function seleccionarProducto(productElement) {
            if (!productElement || !productElement.dataset.id) return;
            const bloqueado = productElement.dataset.blocked === '1';
            const grupo = grupoActual();
            if (bloqueado && grupo !== 'merma') {
                const productWithoutStock = {
                    id: parseInt(productElement.dataset.id, 10),
                    nombre: productElement.dataset.nombre,
                    sku: productElement.dataset.sku,
                    stock: parseInt(productElement.dataset.stock, 10) || 0
                };
                Swal.fire({
                    icon: 'warning',
                    title: 'SIN STOCK DISPONIBLE',
                    html: '<div style="text-align:left;"><strong>' + escapeHtml(productWithoutStock.nombre) + '</strong><br><small>' + escapeHtml(productWithoutStock.sku || '') + '</small><br><span style="color:var(--jv-danger);font-weight:700;">Stock disponible: 0</span></div><div style="margin-top:10px;color:var(--jv-text-muted);font-size:.85rem;">Este producto no puede venderse. Puede solicitarlo a Compras para reponerlo.</div>',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C',
                    showCancelButton: true,
                    confirmButtonText: '🚚 Pedir a Compras',
                    cancelButtonText: 'CERRAR'
                }).then(r => {
                    if (r.isConfirmed) agregarSolicitudCompras(productWithoutStock);
                });
                return;
            }
            productoSeleccionado = {
                id: parseInt(productElement.dataset.id, 10),
                nombre: productElement.dataset.nombre,
                sku: productElement.dataset.sku,
                precioVenta: parseFloat(productElement.dataset.precioVenta) || 0,
                precioCosto: parseFloat(productElement.dataset.precioCosto) || 0,
                stock: parseInt(productElement.dataset.stock, 10) || 0
            };
            toolboxInput.value = productoSeleccionado.nombre;
            cargarPrecio();
            cerrarResultados();
            const cantEl = document.getElementById('s_cant');
            if (cantEl) cantEl.focus();
        }

        // ---- Toolbox de CLIENTES (AJAX) ----
        const toolboxCli = document.getElementById('buscarClienteSal');
        const resultadosCli = document.getElementById('resultadosBusquedaCli');

        // Cierra el panel de resultados de búsqueda de clientes.
        function cerrarResultadosCli() {
            if (resultadosCli) resultadosCli.classList.remove('abierto');
        }
        // Abre el panel de resultados de búsqueda de clientes.
        function abrirResultadosCli() {
            if (resultadosCli) resultadosCli.classList.add('abierto');
        }

        // Dibuja la lista de clientes encontrados en la búsqueda.
        function renderResultadosCli(items) {
            if (!resultadosCli) return;
            resultadosCli.innerHTML = '';
            if (!items || !items.length) {
                const vacio = document.createElement('div');
                vacio.className = 'com-sin-resultados';
                vacio.textContent = 'Sin clientes registrados. Escriba el nombre manualmente.';
                resultadosCli.appendChild(vacio);
                abrirResultadosCli();
                return;
            }
            items.forEach(function(it) {
                const div = document.createElement('div');
                div.className = 'com-resultado';
                div.dataset.id = it.id;
                div.dataset.nombre = it.nombre;
                div.dataset.documento = it.documento;

                const left = document.createElement('div');
                const nombreEl = document.createElement('div');
                nombreEl.className = 'r-nombre';
                nombreEl.textContent = it.nombre;
                const skuEl = document.createElement('div');
                skuEl.className = 'r-sku';
                skuEl.textContent = it.documento || '';
                left.appendChild(nombreEl);
                left.appendChild(skuEl);

                div.appendChild(left);
                resultadosCli.appendChild(div);
            });
            abrirResultadosCli();
        }

        // Busca clientes por AJAX con retardo según lo escrito en el campo de cliente.
        function buscarClientes() {
            const searchTerm = toolboxCli.value.trim();
            if (!searchTerm) {
                cerrarResultadosCli();
                return;
            }
            window.clearTimeout(toolboxTimerCli);
            toolboxTimerCli = window.setTimeout(function() {
                jvBuscarClientes({ q: searchTerm, limit: 15 }, function(searchResponse) {
                    if (searchResponse && searchResponse.success) renderResultadosCli(searchResponse.items);
                    else renderResultadosCli([]);
                });
            }, 350);
        }

        // Rellena los campos de cliente y RIF al elegir un cliente de la lista.
        function seleccionarCliente(clientElement) {
            if (!clientElement || !clientElement.dataset.id) return;
            document.getElementById('s_cliente').value = clientElement.dataset.nombre;
            document.getElementById('s_id_cliente').value = clientElement.dataset.id;
            toolboxCli.value = clientElement.dataset.nombre;
            const clientDocument = clientElement.dataset.documento || '';
            const documentMatch = clientDocument.match(/^([VEJGPC])-(\d+)(?:-(\d+))?/);
            if (documentMatch) {
                document.getElementById('s_rif_tipo').value = documentMatch[1];
                document.getElementById('s_rif_num').value = documentMatch[2] + (documentMatch[3] || '');
                validarRIFInput();
            }
            cerrarResultadosCli();
        }

        // ---- LÍNEA DE PRODUCTO ----
        // Valida cantidad, stock y precio del producto y lo agrega a la lista de la salida.
        function agregarProductoSalida() {
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
            const requestedQuantity = parseInt(document.getElementById('s_cant').value) || 0;
            if (requestedQuantity < 1 || requestedQuantity > 999999) {
                Swal.fire({
                    title: 'Cantidad inválida',
                    text: 'Ingrese una cantidad entre 1 y 999,999',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                document.getElementById('s_cant').focus();
                return;
            }
            const grupo = grupoActual();
            const availableStock = productoSeleccionado.stock;
            if (requestedQuantity > availableStock) {
                if (grupo === 'venta') {
                    const productWithoutStock = {
                        id: productoSeleccionado.id,
                        nombre: productoSeleccionado.nombre,
                        sku: productoSeleccionado.sku,
                        stock: availableStock
                    };
                    Swal.fire({
                        title: 'Stock insuficiente',
                        text: 'Disponible para este tipo de movimiento: ' + availableStock + ', solicitado: ' + requestedQuantity,
                        icon: 'warning',
                        background: '#fff',
                        color: '#212529',
                        confirmButtonColor: '#EA580C',
                        showCancelButton: true,
                        confirmButtonText: '🚚 Pedir a Compras',
                        cancelButtonText: 'CERRAR'
                    }).then(r => {
                        if (r.isConfirmed) {
                            const cantEl = document.getElementById('s_cant');
                            if (cantEl) cantEl.value = Math.max(1, parseInt(cantEl.value, 10) || 1);
                            agregarSolicitudCompras(productWithoutStock);
                        } else {
                            document.getElementById('s_cant').focus();
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Stock insuficiente',
                        text: 'Disponible para este tipo de movimiento: ' + availableStock + ', solicitado: ' + requestedQuantity,
                        icon: 'warning',
                        background: '#fff',
                        color: '#212529',
                        confirmButtonColor: '#EA580C'
                    });
                    document.getElementById('s_cant').focus();
                }
                return;
            }
            const precio = grupo === 'regalias' ? 0 : (grupo === 'merma' ? productoSeleccionado.precioCosto : productoSeleccionado.precioVenta);
            if (grupo === 'venta' && precio <= 0) {
                Swal.fire({
                    title: 'Precio inválido',
                    text: 'El producto debe tener un precio de venta mayor a 0',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            selectedProducts.push({
                id_producto: productoSeleccionado.id,
                sku: productoSeleccionado.sku,
                nombre_producto: productoSeleccionado.nombre,
                cantidad: requestedQuantity,
                precio_venta: precio
            });
            actualizarTablaSalida();
            productoSeleccionado = null;
            toolboxInput.value = '';
            document.getElementById('s_cant').value = 1;
            document.getElementById('s_precio').value = '';
            if (toolboxInput) toolboxInput.focus();
        }

        // Quita un producto de la lista de la salida por su índice.
        function quitarProductoSalida(productIndex) {
            selectedProducts.splice(productIndex, 1);
            actualizarTablaSalida();
        }

        // ==========================================
        // SOLICITUD DE COMPRA A PROVEEDOR (desde Ventas)
        // ==========================================
        let s_solicitud = [];

        // Agrega el producto a la solicitud de compra, acumulando cantidad si ya existe.
        function agregarSolicitudCompras(prod) {
            if (!prod || !prod.id) return;
            const requestedQuantity = Math.max(1, parseInt(document.getElementById('s_cant').value, 10) || 1);
            const existente = s_solicitud.find(p => p.id_producto === prod.id);
            if (existente) {
                existente.cantidad += requestedQuantity;
            } else {
                s_solicitud.push({
                    id_producto: prod.id,
                    sku: prod.sku || '',
                    nombre_producto: prod.nombre || '',
                    cantidad: requestedQuantity
                });
            }
            actualizarSolicitudCompras();
            Swal.fire({
                icon: 'success',
                title: 'AGREGADO A SOLICITUD',
                text: (prod.nombre || 'Producto') + ' se solicitará a Compras.',
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#EA580C',
                timer: 1600,
                showConfirmButton: false
            });
        }

        // Quita un producto de la solicitud de compra por su índice.
        function quitarSolicitudCompras(idx) {
            s_solicitud.splice(idx, 1);
            actualizarSolicitudCompras();
        }

        // Vuelve a dibujar la tabla de solicitud de compra y la oculta si no tiene productos.
        function actualizarSolicitudCompras() {
            const tbody = document.getElementById('s_solicitud_body');
            const box = document.getElementById('solicitud_compras_box');
            if (!tbody || !box) return;
            if (!s_solicitud.length) {
                box.style.display = 'none';
                tbody.innerHTML = '';
                return;
            }
            box.style.display = '';
            let html = '';
            s_solicitud.forEach((p, i) => {
                html += `<tr>
                    <td class="td-prod-num">${i+1}</td>
                    <td class="td-prod-nombre">${escapeHtml(p.sku + ' - ' + p.nombre_producto)}</td>
                    <td class="td-prod-cant">${p.cantidad}</td>
                    <td style="text-align:center;">
                        <button type="button" onclick="quitarSolicitudCompras(${i})" style="background:none;border:none;color:var(--jv-danger);font-size:1.2rem;cursor:pointer;padding:2px 6px;" title="Quitar">&times;</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        // Envía la solicitud de compra al servidor y muestra el resultado de la operación.
        function enviarSolicitudCompras() {
            if (!s_solicitud.length) return;
            const items = s_solicitud.map(p => ({ id_producto: p.id_producto, cantidad: p.cantidad }));
            const btn = document.getElementById('btnEnviarSolicitud');
            if (btn) { btn.disabled = true; btn.innerHTML = '⏳ ENVIANDO...'; }
            const fd = new FormData();
            fd.append('csrf_token', window.JV_CONFIG.csrfToken);
            fd.append('items', JSON.stringify(items));
            fetch((window.JV_BASE || '') + 'index.php?url=solicitudes/crear', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (btn) { btn.disabled = false; btn.innerHTML = '🚚 ENVIAR SOLICITUD A COMPRAS'; }
                    if (d.ok) {
                        s_solicitud = [];
                        actualizarSolicitudCompras();
                        Swal.fire({
                            icon: 'success',
                            title: 'SOLICITUD ENVIADA A COMPRAS',
                            text: 'Los productos quedaron pendientes de atención por el módulo de Compras.',
                            background: '#fff',
                            color: '#212529',
                            confirmButtonColor: '#16A34A'
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'ERROR', text: d.error || 'No se pudo enviar la solicitud.', background: '#fff', color: '#212529', confirmButtonColor: '#DC2626' });
                    }
                })
                .catch(e => {
                    if (btn) { btn.disabled = false; btn.innerHTML = '🚚 ENVIAR SOLICITUD A COMPRAS'; }
                    Swal.fire({ icon: 'error', title: 'ERROR DE CONEXIÓN', text: e.message, background: '#fff', color: '#212529', confirmButtonColor: '#DC2626' });
                });
        }

        // Redibuja la tabla de productos de la salida y actualiza totales de ítems y monto.
        function actualizarTablaSalida() {
            const tbody = document.getElementById('s_productos_body');
            if (!selectedProducts.length) {
                tbody.innerHTML = '<tr id="s_fila_vacia"><td colspan="6" class="sal-fila-vacia">⬆ Agregue productos con los controles de arriba</td></tr>';
                document.getElementById('s_total_items').textContent = '0';
                document.getElementById('s_total_monto').textContent = '$0.00';
                return;
            }
            let html = '';
            let totalItems = 0;
            let totalMonto = 0;
            selectedProducts.forEach((p, i) => {
                const subtotal = p.cantidad * p.precio_venta;
                totalItems += p.cantidad;
                totalMonto += subtotal;
                const desc = p.sku + ' - ' + p.nombre_producto;
                html += `<tr>
                    <td class="td-prod-num">${i+1}</td>
                    <td class="td-prod-nombre" data-tooltip="${escapeHtml(desc)}">${escapeHtml(desc)}</td>
                    <td class="td-prod-cant">${p.cantidad}</td>
                    <td class="td-prod-precio">$${p.precio_venta.toFixed(2)}</td>
                    <td class="td-prod-subtotal">$${subtotal.toFixed(2)}</td>
                    <td style="text-align:center;border-bottom:1px solid var(--jv-border);">
                        <button type="button" onclick="quitarProductoSalida(${i})" style="background:none;border:none;color:var(--jv-danger);font-size:1.2rem;cursor:pointer;padding:2px 6px;" title="Quitar">&times;</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
            document.getElementById('s_total_items').textContent = totalItems;
            document.getElementById('s_total_monto').textContent = '$' + totalMonto.toFixed(2);
        }

        // Muestra los campos según el tipo de movimiento y reinicia la lista de productos.
        function toggleCampos() {
            limpiarErrores();
            const sel = document.getElementById('s_tipo');
            const tipoId = sel.value;
            const grupo = movementTypeGroups[tipoId] || '';
            document.querySelectorAll('.sal-field-group').forEach(el => {
                el.classList.toggle('active', el.dataset.grupo === grupo);
            });
            const nombres = {venta:'REGISTRAR VENTA', regalias:'REGISTRAR REGALÍA', merma:'REGISTRAR AJUSTE'};
            document.getElementById('modalTitle').innerText = nombres[grupo] || 'REGISTRAR MOVIMIENTO';
            // reset productos al cambiar tipo
            selectedProducts = [];
            s_solicitud = [];
            productoSeleccionado = null;
            if (toolboxInput) toolboxInput.value = '';
            if (resultadosBox) resultadosBox.classList.remove('abierto');
            document.getElementById('s_precio').value = '';
            actualizarTablaSalida();
            actualizarSolicitudCompras();
        }

        // Limpia el formulario para registrar una nueva salida y abre el modal.
        function nuevaSalida() {
            limpiarErrores();
            document.getElementById('s_accion').value = 'registrar';
            document.getElementById('s_id_edit').value = '';
            document.getElementById('modalTitle').innerText = 'REGISTRAR MOVIMIENTO';
            selectedProducts = [];
            s_solicitud = [];
            productoSeleccionado = null;
            actualizarTablaSalida();
            actualizarSolicitudCompras();
            document.getElementById('s_cliente').value = '';
            document.getElementById('s_id_cliente').value = '';
            if (toolboxCli) toolboxCli.value = '';
            document.getElementById('s_rif_tipo').value = 'V';
            document.getElementById('s_rif_num').value = '';
            document.getElementById('s_rif').value = '';
            var m = document.getElementById('s-rif-msg'); if (m) m.innerHTML = '';
            var ri = document.getElementById('s_rif_num'); if (ri) ri.style.borderColor = '';
            document.getElementById('s_motivo_reg') && (document.getElementById('s_motivo_reg').value = '');
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = '');
            document.getElementById('s_obs').value = '';
            var hoy = new Date().toISOString().slice(0,10);
            document.getElementById('s_fecha').value = hoy;
            document.getElementById('s_fecha_hidden').value = hoy;
            document.getElementById('s_desc_motivo') && (document.getElementById('s_desc_motivo').value = '');
            document.getElementById('s_causa') && (document.getElementById('s_causa').value = '');
            document.getElementById('s_tipo').value = '';
            document.querySelectorAll('.sal-field-group').forEach(el => el.classList.remove('active'));
            if (toolboxInput) toolboxInput.value = '';
            if (resultadosBox) resultadosBox.classList.remove('abierto');
            document.getElementById('s_precio').value = '';
            salidaModal.show();
        }

        // Valida y formatea el RIF o cédula ingresado y muestra si es válido o incompleto.
        function validarRIFInput() {
            var tipo = document.getElementById('s_rif_tipo').value;
            var nums = document.getElementById('s_rif_num').value.replace(/\D/g, '');
            var msg = document.getElementById('s-rif-msg');
            var numInput = document.getElementById('s_rif_num');
            var hidden = document.getElementById('s_rif');
            var esRif = (tipo === 'J' || tipo === 'G' || tipo === 'P' || tipo === 'C');
            var maxDig = esRif ? 9 : 9;
            if (nums.length > maxDig) { nums = nums.slice(0, maxDig); }
            if (nums === '') {
                msg.innerHTML = ''; numInput.style.borderColor = ''; hidden.value = ''; numInput.value = '';
                return;
            }
            // Tanto RIF como cédula usan 9 dígitos fijos. Formato unificado SIN puntos:
            // el RIF jurídico muestra guión antes del dígito verificador (12345678-9)
            // y la cédula va seguida (123456789), igual que en Proveedores.
            var cuerpo, verif, formatted;
            if (esRif) {
                cuerpo = nums.slice(0, 8);
                verif = nums.slice(8);
                formatted = cuerpo + (verif ? '-' + verif : '');
                hidden.value = tipo + '-' + cuerpo + (verif ? '-' + verif : '');
            } else {
                cuerpo = nums;
                formatted = cuerpo;
                hidden.value = tipo + '-' + cuerpo;
            }
            var valido = nums.length === 9;
            numInput.value = formatted;
            if (valido) {
                msg.innerHTML = '<span style="color:var(--jv-success);">✓ Válido</span>';
                numInput.style.borderColor = '#16A34A';
            } else {
                msg.innerHTML = '<span style="color:var(--jv-danger);">RIF incompleto</span>';
                numInput.style.borderColor = '#DC2626';
            }
        }

        // Carga los datos de una salida existente en el formulario para editarla y abre el modal.
        function editarSalida(data) {
            document.getElementById('s_accion').value = 'editar';
            document.getElementById('s_id_edit').value = data.id_salida;
            document.getElementById('modalTitle').innerText = 'EDITAR SALIDA';
            var fEdit = (data.fecha_salida || '').slice(0,10) || new Date().toISOString().slice(0,10);
            document.getElementById('s_fecha').value = fEdit;
            document.getElementById('s_fecha_hidden').value = fEdit;
            document.getElementById('s_cliente').value = data.cliente;
            document.getElementById('s_id_cliente').value = data.id_cliente || '';
            if (toolboxCli) toolboxCli.value = data.cliente || '';
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = data.cliente);
            var rifMatch = (data.rif_cliente || '').match(/^([VEJGPC])-(\d+)(?:-(\d+))?/);
            if (rifMatch) {
                document.getElementById('s_rif_tipo').value = rifMatch[1];
                document.getElementById('s_rif_num').value = rifMatch[2] + (rifMatch[3] || '');
            } else {
                document.getElementById('s_rif_tipo').value = 'V';
                document.getElementById('s_rif_num').value = '';
            }
            validarRIFInput();
            document.getElementById('s_tipo').value = data.id_tipo_mov;
            document.getElementById('s_obs').value = data.observaciones;
            toggleCampos();
            // cargar productos desde JSON si existe (después de toggleCampos, que resetea la lista)
            try {
                var prods = JSON.parse(data.productos_json || '[]');
                if (prods.length) { selectedProducts = prods; actualizarTablaSalida(); }
            } catch(e) {}
            salidaModal.show();
        }

        // Valida el formulario completo y solicita la vista previa de la nota de entrega al servidor.
        function enviarPreview() {
            limpiarErrores();
            const btn = document.getElementById('btnPreview');
            let valido = true;
            let primerError = null;

            const tipo = document.getElementById('s_tipo');
            if (!tipo.value) { marcarError(tipo, 'SELECCIONE TIPO'); valido = false; if (!primerError) primerError = tipo; }

            if (!selectedProducts.length) { marcarError(document.getElementById('buscarProductoSal'), 'AGREGUE PRODUCTOS'); valido = false; if (!primerError) primerError = document.getElementById('buscarProductoSal'); }

            const grupo = movementTypeGroups[tipo.value] || '';
            if (grupo === 'merma') {
                const causa = document.getElementById('s_causa');
                if (!causa.value) { marcarError(causa, 'SELECCIONE CAUSA'); valido = false; if (!primerError) primerError = causa; }
            }
            if (grupo === 'regalias') {
                document.getElementById('s_precio').value = '0';
                document.getElementById('s_cliente').value = document.getElementById('s_cliente_reg').value;
                const motivo = document.getElementById('s_motivo_reg');
                if (!motivo.value) { marcarError(motivo, 'SELECCIONE MOTIVO'); valido = false; if (!primerError) primerError = motivo; }
                const cliReg = document.getElementById('s_cliente_reg');
                if (!cliReg.value.trim()) { marcarError(cliReg, 'CLIENTE OBLIGATORIO'); valido = false; if (!primerError) primerError = cliReg; }
            }
            if (grupo === 'venta') {
                const rifEl = document.getElementById('s_rif');
                const rifInput = document.getElementById('s_rif_num');
                const rifMsg = document.getElementById('s-rif-msg');
                const rifTipo = document.getElementById('s_rif_tipo');
                if (!rifEl.value) { marcarError(rifInput, 'RIF OBLIGATORIO'); valido = false; if (!primerError) primerError = rifInput; }
                else if (rifMsg && rifMsg.innerHTML.includes('incompleto')) { marcarError(rifInput, 'RIF INCOMPLETO'); valido = false; if (!primerError) primerError = rifInput; }
                const cli = document.getElementById('s_cliente');
                if (!cli.value.trim()) { marcarError(toolboxCli, 'CLIENTE OBLIGATORIO'); valido = false; if (!primerError) primerError = toolboxCli; }
            }

            if (!valido) {
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                if (primerError) { primerError.focus(); var p = primerError.closest('.modal-body') || primerError; p.scrollIntoView({behavior:'smooth',block:'center'}); }
                return;
            }

            btn.disabled = true; btn.innerHTML = '⏳ PROCESANDO...';

            let pj = document.getElementById('formSalida').querySelector('[name="productos_data"]');
            if (!pj) { pj = document.createElement('input'); pj.type = 'hidden'; pj.name = 'productos_data'; document.getElementById('formSalida').appendChild(pj); }
            const payload = selectedProducts.map(p => ({
                id_producto: parseInt(p.id_producto),
                cantidad: parseInt(p.cantidad),
                precio: parseFloat(p.precio_venta)
            }));
            pj.value = JSON.stringify(payload);

            const fd = new FormData(document.getElementById('formSalida'));

            fetch((window.JV_BASE || '') + 'index.php?url=nota_entrega/store', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body:fd })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    if (d.ok) {
                        window.location.href = (window.JV_BASE || '') + 'index.php?url=nota_entrega&token=' + (d.token || '');
                    } else {
                        Swal.fire({icon:'error',title:'ERROR',text:d.error||'Error al generar preview.',background:'#fff',color:'#212529',confirmButtonColor:'#DC2626'});
                    }
                })
                .catch(e => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    Swal.fire({icon:'error',title:'ERROR DE CONEXIÓN',text:e.message,background:'#fff',color:'#212529',confirmButtonColor:'#DC2626'});
                });
        }

        // Muestra el detalle del tipo de movimiento y su causa en un cuadro de diálogo.
        function verDetalleDano(tipo, causa) {
            Swal.fire({
                icon: 'info',
                title: 'Detalle del Movimiento',
                html: '<div style="text-align:left;"><strong>Tipo:</strong> ' + tipo + '<br><strong>Causa:</strong> ' + (causa || 'No especificada') + '</div>',
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#EA580C',
                confirmButtonText: 'OK'
            });
        }

        // Abre la nota de entrega correspondiente en una ventana nueva.
        function verFactura(id) {
            window.open((window.JV_BASE || '') + 'index.php?url=nota_entrega&id=' + id, '_blank');
        }

        // Filtra las filas de la tabla de salidas según el texto de búsqueda.
        function filtrarSalidas() {
            const input = document.getElementById('buscarSal');
            const filter = input ? input.value.toLowerCase() : '';
            const rows = document.getElementById('tablaSalidas') ? document.getElementById('tablaSalidas').getElementsByTagName('tr') : [];
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        // Pide confirmación para anular la salida; el stock vuelve al inventario.
        function confirmarEliminar(id) {
            Swal.fire({title:'¿ANULAR?',text:'El stock volverá al inventario.',icon:'warning',showCancelButton:true,background:'#fff',color:'#212529',confirmButtonColor:'#DC2626',cancelButtonColor:'#CED4DA',confirmButtonText:'SÍ, ANULAR',cancelButtonText:'CANCELAR'}).then(r => {
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.csrfToken });
            });
        }

        // Carga el precio del producto seleccionado según el grupo del movimiento.
        function cargarPrecio() {
            const precio = document.getElementById('s_precio');
            if (!productoSeleccionado) { precio.value = ''; return; }
            const grupo = grupoActual();
            if (grupo === 'regalias') {
                precio.value = '0.00';
            } else if (grupo === 'merma') {
                precio.value = (productoSeleccionado.precioCosto || 0).toFixed(2);
            } else {
                precio.value = (productoSeleccionado.precioVenta || 0).toFixed(2);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.flash-auto').forEach(el => {
                setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
            });

            document.querySelectorAll('#formSalida input, #formSalida select, #formSalida textarea').forEach(function(el) {
                el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
            });

            // Toolbox productos
            if (toolboxInput && resultadosBox) {
                toolboxInput.addEventListener('input', buscarProductos);
                toolboxInput.addEventListener('focus', function() { if (this.value.trim()) buscarProductos(); });
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

            // Toolbox clientes
            if (toolboxCli && resultadosCli) {
                toolboxCli.addEventListener('input', buscarClientes);
                toolboxCli.addEventListener('focus', function() { if (this.value.trim()) buscarClientes(); });
                toolboxCli.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') cerrarResultadosCli();
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const primero = resultadosCli.querySelector('.com-resultado');
                        if (primero) seleccionarCliente(primero);
                    }
                });
                resultadosCli.addEventListener('click', function(e) {
                    const item = e.target.closest('.com-resultado');
                    if (item) seleccionarCliente(item);
                });
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.com-toolbox')) cerrarResultadosCli();
                });
                // Sincronizar nombre manual si no se eligió de la lista
                toolboxCli.addEventListener('input', function() {
                    document.getElementById('s_id_cliente').value = '';
                    document.getElementById('s_cliente').value = this.value.trim();
                });
                toolboxCli.addEventListener('blur', function() {
                    if (!document.getElementById('s_id_cliente').value) {
                        document.getElementById('s_cliente').value = this.value.trim();
                    }
                });
            }

            // Sidebar
            if (typeof mainWrapper !== 'undefined' && mainWrapper) {
                const observer = new MutationObserver(() => {
                    if (document.body.classList.contains('sidebar-open')) {
                        mainWrapper.classList.add('sidebar-open');
                    } else {
                        mainWrapper.classList.remove('sidebar-open');
                    }
                });
                observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
            }
        });
