
        const modalS = new bootstrap.Modal(document.getElementById('modalSalida'));
        const TIPO_MAP = window.JV_CONFIG.c0;
        let s_productos = [];

        function agregarProductoSalida() {
            const sel = document.getElementById('s_prod');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { alert('Seleccione un producto.'); sel.focus(); return; }
            const cant = parseInt(document.getElementById('s_cant').value) || 0;
            if (cant < 1) { alert('Cantidad debe ser mayor a 0.'); document.getElementById('s_cant').focus(); return; }
            const tipo = document.getElementById('s_tipo');
            const grupo = tipo && tipo.value ? (TIPO_MAP[tipo.value] || '') : '';
            if (cant > 999999) { alert('Cantidad máxima permitida: 999,999.'); document.getElementById('s_cant').focus(); return; }
            const stockDisp = grupo === 'merma' ? (parseInt(opt.dataset.stockVencido) || 0) : (parseInt(opt.dataset.stockVigente) || 0);
            if (cant > stockDisp) { alert('Stock insuficiente. Disponible para este tipo de movimiento: ' + stockDisp + ', solicitado: ' + cant + '.'); document.getElementById('s_cant').focus(); return; }
            const precio = parseFloat(document.getElementById('s_precio').value) || 0;
            if (grupo !== 'regalias' && grupo !== 'merma' && precio <= 0) { alert('El precio debe ser mayor a 0.'); document.getElementById('s_precio').focus(); return; }
            s_productos.push({
                id_producto: opt.value,
                sku: opt.text.split(' - ')[0].replace(/«.*?»\s*/g, ''),
                nombre_producto: opt.text.split(' - ').slice(1).join(' - ').replace(/«.*?»\s*/g, ''),
                cantidad: cant,
                precio_venta: precio
            });
            actualizarTablaSalida();
            sel.selectedIndex = 0;
            document.getElementById('s_cant').value = 1;
            document.getElementById('s_precio').value = '';
        }

        function quitarProductoSalida(idx) {
            s_productos.splice(idx, 1);
            actualizarTablaSalida();
        }

        function actualizarTablaSalida() {
            const tbody = document.getElementById('s_productos_body');
            if (!s_productos.length) {
                tbody.innerHTML = '<tr id="s_fila_vacia"><td colspan="6" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;border-bottom:1px solid var(--jv-border);">⬆ Agregue productos con los controles de arriba</td></tr>';
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
                html += `<tr>
                    <td style="padding:6px 8px;text-align:center;color:var(--jv-text-muted);font-size:.8rem;border-bottom:1px solid var(--jv-border);">${i+1}</td>
                    <td style="padding:6px 8px;color:var(--jv-text-primary);font-size:.85rem;border-bottom:1px solid var(--jv-border);">${escapeHtml(p.sku)} - ${escapeHtml(p.nombre_producto)}</td>
                    <td style="padding:6px 8px;text-align:center;color:var(--jv-text-primary);font-size:.85rem;border-bottom:1px solid var(--jv-border);">${p.cantidad}</td>
                    <td style="padding:6px 8px;text-align:right;color:var(--jv-text-secondary);font-size:.85rem;border-bottom:1px solid var(--jv-border);">$${p.precio_venta.toFixed(2)}</td>
                    <td style="padding:6px 8px;text-align:right;color:var(--jv-navy);font-weight:600;font-size:.85rem;border-bottom:1px solid var(--jv-border);">$${subtotal.toFixed(2)}</td>
                    <td style="padding:6px 8px;text-align:center;border-bottom:1px solid var(--jv-border);">
                        <button type="button" onclick="quitarProductoSalida(${i})" style="background:none;border:none;color:var(--jv-danger);font-size:1rem;cursor:pointer;padding:2px 6px;" title="Quitar">&times;</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
            document.getElementById('s_total_items').textContent = totalItems;
            document.getElementById('s_total_monto').textContent = '$' + totalMonto.toFixed(2);
        }

        function filtrarProductosPorGrupo(grupo) {
            const sel = document.getElementById('s_prod');
            if (!sel) return;
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) { opt.hidden = false; opt.disabled = false; return; }
                const vencido = opt.getAttribute('data-vencido') === '1';
                let mostrar = true;
                if (grupo === 'merma') mostrar = vencido;
                else if (grupo === 'venta' || grupo === 'regalias') mostrar = !vencido;
                opt.hidden = !mostrar;
                opt.disabled = !mostrar;
            });
            sel.value = '';
            cargarPrecio();
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
            actualizarTablaSalida();
            filtrarProductosPorGrupo(grupo);
        }

        function nuevaSalida() {
            limpiarErrores();
            document.getElementById('s_accion').value = 'registrar';
            document.getElementById('s_id_edit').value = '';
            document.getElementById('modalTitle').innerText = 'REGISTRAR MOVIMIENTO';
            s_productos = [];
            actualizarTablaSalida();
            document.getElementById('s_cliente').value = '';
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = '');
            document.getElementById('s_rif_tipo').value = 'V';
            document.getElementById('s_rif_num').value = '';
            document.getElementById('s_rif').value = '';
            var m = document.getElementById('s-rif-msg'); if (m) m.innerHTML = '';
            var ri = document.getElementById('s_rif_num'); if (ri) ri.style.borderColor = '';
            document.getElementById('s_motivo_reg') && (document.getElementById('s_motivo_reg').value = '');
            document.getElementById('s_obs').value = '';
            var hoy = new Date().toISOString().slice(0,10);
            document.getElementById('s_fecha').value = hoy;
            document.getElementById('s_fecha_hidden').value = hoy;
            document.getElementById('s_desc_motivo') && (document.getElementById('s_desc_motivo').value = '');
            document.getElementById('s_causa') && (document.getElementById('s_causa').value = '');
            document.getElementById('s_tipo').value = '';
            document.querySelectorAll('.sal-field-group').forEach(el => el.classList.remove('active'));
            filtrarProductosPorGrupo('');
            modalS.show();
        }

        function validarRIFInput() {
            var tipo = document.getElementById('s_rif_tipo').value;
            var nums = document.getElementById('s_rif_num').value.replace(/\D/g, '');
            var msg = document.getElementById('s-rif-msg');
            var numInput = document.getElementById('s_rif_num');
            var hidden = document.getElementById('s_rif');
            var maxDig = (tipo === 'J' || tipo === 'G') ? 9 : 8;
            if (nums.length > maxDig) { nums = nums.slice(0, maxDig); }
            if (nums === '') {
                msg.innerHTML = ''; numInput.style.borderColor = ''; hidden.value = ''; numInput.value = '';
                return;
            }
            var formatted = nums.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            var valido = (tipo === 'V' || tipo === 'E') ? nums.length >= 7 : nums.length >= 8;
            hidden.value = tipo + '-' + nums;
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
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = data.cliente);
            var rifMatch = (data.rif_cliente || '').match(/^([VJGPE])-(\d+)/);
            if (rifMatch) {
                document.getElementById('s_rif_tipo').value = rifMatch[1];
                document.getElementById('s_rif_num').value = rifMatch[2];
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

            if (!s_productos.length) { marcarError(document.getElementById('s_prod'), 'AGREGUE PRODUCTOS'); valido = false; if (!primerError) primerError = document.getElementById('s_prod'); }

            // Validar stock de cada producto antes de enviar
            const grupoPv = TIPO_MAP[tipo.value] || '';
            for (let i = 0; i < s_productos.length; i++) {
                const p = s_productos[i];
                const opt = document.querySelector('#s_prod option[value="' + p.id_producto + '"]');
                if (opt) {
                    const stockDisp = grupoPv === 'merma' ? (parseInt(opt.dataset.stockVencido) || 0) : (parseInt(opt.dataset.stockVigente) || 0);
                    if (p.cantidad > stockDisp) {
                        marcarError(document.getElementById('s_prod'), 'Stock insuficiente para ' + (p.nombre_producto || 'producto #' + p.id_producto) + '. Disponible: ' + stockDisp);
                        valido = false; if (!primerError) primerError = document.getElementById('s_prod');
                        break;
                    }
                }
            }

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
                if (!cli.value.trim()) { marcarError(cli, 'CLIENTE OBLIGATORIO'); valido = false; if (!primerError) primerError = cli; }
            }

            if (!valido) {
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                if (primerError) { primerError.focus(); var p = primerError.closest('.modal-body') || primerError; p.scrollIntoView({behavior:'smooth',block:'center'}); }
                return;
            }

            btn.disabled = true; btn.innerHTML = '⏳ PROCESANDO...';

    // inyectar productos como JSON en el campo que espera el backend
    let pj = document.getElementById('formSalida').querySelector('[name="productos_data"]');
    if (!pj) { pj = document.createElement('input'); pj.type = 'hidden'; pj.name = 'productos_data'; document.getElementById('formSalida').appendChild(pj); }
    // mapear a lo que espera el backend: id_producto, cantidad, precio
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

        function confirmarEliminar(id) {
            Swal.fire({title:'¿ANULAR?',text:'El stock volverá al inventario.',icon:'warning',showCancelButton:true,background:'#fff',color:'#212529',confirmButtonColor:'#DC2626',cancelButtonColor:'#CED4DA',confirmButtonText:'SÍ, ANULAR',cancelButtonText:'CANCELAR'}).then(r => {
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.c1 });
            });
        }

        function cargarPrecio() {
            const sel = document.getElementById('s_prod');
            const opt = sel.options[sel.selectedIndex];
            const precio = document.getElementById('s_precio');
            if (!opt || !opt.value) { precio.value = ''; return; }
            const tipo = document.getElementById('s_tipo');
            const grupo = tipo && tipo.value ? (TIPO_MAP[tipo.value] || '') : '';
            if (grupo === 'regalias') {
                precio.value = '0.00';
            } else if (grupo === 'merma' && opt.dataset.costo) {
                precio.value = parseFloat(opt.dataset.costo).toFixed(2);
            } else if (opt.dataset.precio) {
                precio.value = parseFloat(opt.dataset.precio).toFixed(2);
            }
        }
    

    document.querySelectorAll('.flash-auto').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
    });
    document.querySelectorAll('#formSalida input, #formSalida select, #formSalida textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
        el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
    });
    

        const observer = new MutationObserver(() => {
            if (document.body.classList.contains('sidebar-open')) {
                mainWrapper.classList.add('sidebar-open');
            } else {
                mainWrapper.classList.remove('sidebar-open');
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    
