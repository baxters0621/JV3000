
        // ==========================================
        // SALIDAS / VENTAS — registro con toolbox y validación FEFO
        // ==========================================

        // ---------- TOOLTIP GRANDE (nombre completo de productos) ----------
        (function() {
            let tip = null;
            let tipTimer = null;

            function mostrarTip(e, texto) {
                if (!texto) return;
                if (!tip) {
                    tip = document.createElement('div');
                    tip.className = 'jv-tooltip';
                    document.body.appendChild(tip);
                }
                tip.textContent = texto;
                tip.classList.add('jv-tooltip-visible');
                posicionarTip(e);
            }

            function posicionarTip(e) {
                if (!tip) return;
                const pad = 16;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                const r = tip.getBoundingClientRect();
                if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
                if (y + r.height > window.innerHeight - 8) y = e.clientY - r.height - pad;
                tip.style.left = Math.max(8, x) + 'px';
                tip.style.top = Math.max(8, y) + 'px';
            }

            function ocultarTip() {
                if (tipTimer) window.clearTimeout(tipTimer);
                tipTimer = window.setTimeout(function() {
                    if (tip) tip.classList.remove('jv-tooltip-visible');
                }, 80);
            }

            document.addEventListener('mouseover', function(e) {
                const t = e.target.closest('[data-tooltip]');
                if (t) {
                    window.clearTimeout(tipTimer);
                    mostrarTip(e, t.dataset.tooltip);
                }
            });
            document.addEventListener('mousemove', function(e) {
                if (tip && tip.classList.contains('jv-tooltip-visible')) posicionarTip(e);
            });
            document.addEventListener('mouseout', function(e) {
                if (e.target.closest('[data-tooltip]')) ocultarTip();
            });
        })();

        const modalS = new bootstrap.Modal(document.getElementById('modalSalida'));
        const TIPO_MAP = window.JV_CONFIG.c0;
        let s_productos = [];
        let productoSeleccionado = null;
        let toolboxTimer = null;
        let toolboxTimerCli = null;

        function grupoActual() {
            const tipo = document.getElementById('s_tipo');
            return tipo && tipo.value ? (TIPO_MAP[tipo.value] || '') : '';
        }

        // ---- Toolbox de PRODUCTOS (AJAX) ----
        const toolboxInput = document.getElementById('buscarProductoSal');
        const resultadosBox = document.getElementById('resultadosBusquedaSal');

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
                div.dataset.sku = it.sku;
                div.dataset.precioVenta = it.precio_venta;
                div.dataset.precioCosto = it.precio_costo;
                div.dataset.stock = it.stock;
                div.dataset.vencido = it.vencido;

                const left = document.createElement('div');
                const nombreEl = document.createElement('div');
                nombreEl.className = 'r-nombre';
                nombreEl.dataset.tooltip = (it.vencido ? '«VENCIDO» ' : '') + it.nombre;
                nombreEl.textContent = (it.vencido ? '«VENCIDO» ' : '') + it.nombre;
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
            const grupo = grupoActual();
            window.clearTimeout(toolboxTimer);
            toolboxTimer = window.setTimeout(function() {
                jvBuscarProductos({ q: q, limit: 15, vencidos: grupo === 'merma' ? 1 : 0, solo_con_stock: 1 }, function(d) {
                    if (d && d.success) renderResultados(d.items);
                    else renderResultados([]);
                });
            }, 350);
        }

        function seleccionarProducto(el) {
            if (!el || !el.dataset.id) return;
            productoSeleccionado = {
                id: parseInt(el.dataset.id, 10),
                nombre: el.dataset.nombre,
                sku: el.dataset.sku,
                precioVenta: parseFloat(el.dataset.precioVenta) || 0,
                precioCosto: parseFloat(el.dataset.precioCosto) || 0,
                stock: parseInt(el.dataset.stock, 10) || 0
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

        function cerrarResultadosCli() {
            if (resultadosCli) resultadosCli.classList.remove('abierto');
        }
        function abrirResultadosCli() {
            if (resultadosCli) resultadosCli.classList.add('abierto');
        }

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

        function buscarClientes() {
            const q = toolboxCli.value.trim();
            if (!q) {
                cerrarResultadosCli();
                return;
            }
            window.clearTimeout(toolboxTimerCli);
            toolboxTimerCli = window.setTimeout(function() {
                jvBuscarClientes({ q: q, limit: 15 }, function(d) {
                    if (d && d.success) renderResultadosCli(d.items);
                    else renderResultadosCli([]);
                });
            }, 350);
        }

        function seleccionarCliente(el) {
            if (!el || !el.dataset.id) return;
            document.getElementById('s_cliente').value = el.dataset.nombre;
            document.getElementById('s_id_cliente').value = el.dataset.id;
            toolboxCli.value = el.dataset.nombre;
            const doc = el.dataset.documento || '';
            const m = doc.match(/^([VEJGPC])-(\d+)(?:-(\d+))?/);
            if (m) {
                document.getElementById('s_rif_tipo').value = m[1];
                document.getElementById('s_rif_num').value = m[2] + (m[3] || '');
                validarRIFInput();
            }
            cerrarResultadosCli();
        }

        // ---- LÍNEA DE PRODUCTO ----
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
            const cant = parseInt(document.getElementById('s_cant').value) || 0;
            if (cant < 1 || cant > 999999) {
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
            const stockDisp = productoSeleccionado.stock;
            if (cant > stockDisp) {
                Swal.fire({
                    title: 'Stock insuficiente',
                    text: 'Disponible para este tipo de movimiento: ' + stockDisp + ', solicitado: ' + cant,
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                document.getElementById('s_cant').focus();
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
            s_productos.push({
                id_producto: productoSeleccionado.id,
                sku: productoSeleccionado.sku,
                nombre_producto: productoSeleccionado.nombre,
                cantidad: cant,
                precio_venta: precio
            });
            actualizarTablaSalida();
            productoSeleccionado = null;
            toolboxInput.value = '';
            document.getElementById('s_cant').value = 1;
            document.getElementById('s_precio').value = '';
            if (toolboxInput) toolboxInput.focus();
        }

        function quitarProductoSalida(idx) {
            s_productos.splice(idx, 1);
            actualizarTablaSalida();
        }

        function actualizarTablaSalida() {
            const tbody = document.getElementById('s_productos_body');
            if (!s_productos.length) {
                tbody.innerHTML = '<tr id="s_fila_vacia"><td colspan="6" class="sal-fila-vacia">⬆ Agregue productos con los controles de arriba</td></tr>';
                document.getElementById('s_total_items').textContent = '0';
                document.getElementById('s_total_monto').textContent = '$0.00';
                return;
            }
            let html = '';
            let totalItems = 0;
            let totalMonto = 0;
            s_productos.forEach((p, i) => {
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

        function toggleCampos() {
            limpiarErrores();
            const sel = document.getElementById('s_tipo');
            const tipoId = sel.value;
            const grupo = TIPO_MAP[tipoId] || '';
            document.querySelectorAll('.sal-field-group').forEach(el => {
                el.classList.toggle('active', el.dataset.grupo === grupo);
            });
            const nombres = {venta:'REGISTRAR VENTA', regalias:'REGISTRAR REGALÍA', merma:'REGISTRAR AJUSTE'};
            document.getElementById('modalTitle').innerText = nombres[grupo] || 'REGISTRAR MOVIMIENTO';
            // reset productos al cambiar tipo
            s_productos = [];
            productoSeleccionado = null;
            if (toolboxInput) toolboxInput.value = '';
            if (resultadosBox) resultadosBox.classList.remove('abierto');
            document.getElementById('s_precio').value = '';
            actualizarTablaSalida();
        }

        function nuevaSalida() {
            limpiarErrores();
            document.getElementById('s_accion').value = 'registrar';
            document.getElementById('s_id_edit').value = '';
            document.getElementById('modalTitle').innerText = 'REGISTRAR MOVIMIENTO';
            s_productos = [];
            productoSeleccionado = null;
            actualizarTablaSalida();
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
            modalS.show();
        }

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
            var cuerpo, verif, formatted;
            if (esRif) {
                cuerpo = nums.slice(0, 8);
                verif = nums.slice(8);
                formatted = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (verif ? '-' + verif : '');
                hidden.value = tipo + '-' + cuerpo + (verif ? '-' + verif : '');
            } else {
                cuerpo = nums;
                formatted = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                hidden.value = tipo + '-' + cuerpo;
            }
            var valido = esRif ? nums.length === 9 : nums.length >= 6;
            numInput.value = formatted;
            if (valido) {
                msg.innerHTML = '<span style="color:var(--jv-success);">✓ Válido</span>';
                numInput.style.borderColor = '#16A34A';
            } else {
                msg.innerHTML = '<span style="color:var(--jv-danger);">RIF incompleto</span>';
                numInput.style.borderColor = '#DC2626';
            }
        }

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
                if (prods.length) { s_productos = prods; actualizarTablaSalida(); }
            } catch(e) {}
            modalS.show();
        }

        function limpiarErrores() {
            document.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
            document.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
        }
        function marcarError(el, msg) {
            el.classList.add('input-error');
            if (msg && el.id) {
                var errEl = document.getElementById(el.id + '_err');
                if (!errEl) {
                    errEl = document.createElement('small');
                    errEl.id = el.id + '_err';
                    errEl.className = 'field-error';
                    errEl.style.cssText = 'color:#DC2626;font-size:.7rem;margin-top:2px;display:block;';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = msg;
            }
        }

        function enviarPreview() {
            limpiarErrores();
            const btn = document.getElementById('btnPreview');
            let valido = true;
            let primerError = null;

            const tipo = document.getElementById('s_tipo');
            if (!tipo.value) { marcarError(tipo, 'SELECCIONE TIPO'); valido = false; if (!primerError) primerError = tipo; }

            if (!s_productos.length) { marcarError(document.getElementById('buscarProductoSal'), 'AGREGUE PRODUCTOS'); valido = false; if (!primerError) primerError = document.getElementById('buscarProductoSal'); }

            const grupo = TIPO_MAP[tipo.value] || '';
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
            const payload = s_productos.map(p => ({
                id_producto: parseInt(p.id_producto),
                cantidad: parseInt(p.cantidad),
                precio: parseFloat(p.precio_venta)
            }));
            pj.value = JSON.stringify(payload);

            const fd = new FormData(document.getElementById('formSalida'));

            fetch('preview_factura.php?store=1', { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    if (d.ok) {
                        window.open('preview_factura.php?token=' + (d.token || ''), '_blank');
                        modalS.hide();
                    } else {
                        Swal.fire({icon:'error',title:'ERROR',text:d.error||'Error al generar preview.',background:'#fff',color:'#212529',confirmButtonColor:'#DC2626'});
                    }
                })
                .catch(e => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    Swal.fire({icon:'error',title:'ERROR DE CONEXIÓN',text:e.message,background:'#fff',color:'#212529',confirmButtonColor:'#DC2626'});
                });
        }

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

        function verFactura(id) {
            window.open('preview_factura.php?id=' + id, '_blank');
        }

        function filtrarSalidas() {
            const input = document.getElementById('buscarSal');
            const filter = input ? input.value.toLowerCase() : '';
            const rows = document.getElementById('tablaSalidas') ? document.getElementById('tablaSalidas').getElementsByTagName('tr') : [];
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        function confirmarEliminar(id) {
            Swal.fire({title:'¿ANULAR?',text:'El stock volverá al inventario.',icon:'warning',showCancelButton:true,background:'#fff',color:'#212529',confirmButtonColor:'#DC2626',cancelButtonColor:'#CED4DA',confirmButtonText:'SÍ, ANULAR',cancelButtonText:'CANCELAR'}).then(r => {
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.c1 });
            });
        }

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
