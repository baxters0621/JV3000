
        let productos = [];
        const modalNP = new bootstrap.Modal(document.getElementById('modalNuevoProducto'));

        function abrirNuevoProducto() {
            document.getElementById('np_nombre').value = '';
            document.getElementById('np_categoria').value = '';
            document.getElementById('np_stock_minimo').value = 5;
            document.getElementById('np_stock_maximo').value = 0;
            document.getElementById('np_status').value = 'Activo';
            document.getElementById('np_fecha_vencimiento').value = '';
            document.getElementById('np_nombre').focus();
            modalNP.show();
        }

        function crearProducto() {
            const nombre = document.getElementById('np_nombre').value.trim();
            const cat = document.getElementById('np_categoria').value;
            const stockMin = parseInt(document.getElementById('np_stock_minimo').value) || 5;
            const stockMax = parseInt(document.getElementById('np_stock_maximo').value) || 0;
            const statusVal = document.getElementById('np_status').value;
            const fechaVenc = document.getElementById('np_fecha_vencimiento').value;
            const btn = document.getElementById('btnCrearProducto');

            if (!nombre || !cat) {
                Swal.fire({
                    title: 'Campos requeridos',
                    text: 'Completa nombre y categoría',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            if (stockMax < 0 || (stockMax > 0 && stockMax < stockMin)) {
                Swal.fire({
                    title: 'Capacidad inválida',
                    text: 'La capacidad máxima debe ser 0 (heredar categoría) o mayor/igual al stock mínimo.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';

            const formData = new FormData();
            formData.append('csrf_token', document.getElementById('np_csrf').value);
            formData.append('accion_producto', 'crear_ajax');
            formData.append('nombre_producto', nombre);
            formData.append('id_categoria', cat);
            formData.append('stock_minimo', stockMin);
            formData.append('stock_maximo', stockMax);
            formData.append('status', statusVal);
            formData.append('fecha_vencimiento', fechaVenc);

            fetch('compras.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const sel = document.getElementById('selProducto');
                        const opt = document.createElement('option');
                        opt.value = res.id;
                        opt.dataset.precio = '0';
                        opt.textContent = res.nombre + ' (Stock: 0)';
                        sel.appendChild(opt);
                        sel.value = res.id;
                        document.getElementById('inputPrecio').value = '';
                        document.getElementById('inputPrecio').focus();
                        modalNP.hide();
                    } else {
                    Swal.fire({
                        title: 'Error',
                        text: res.error || 'No se pudo crear',
                        icon: 'error',
                        background: '#fff',
                        color: '#212529',
                        confirmButtonColor: '#EA580C'
                    });
                    }
                })
                .catch(function(err) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error de conexión: ' + (err.message || 'desconocido'),
                        icon: 'error',
                        background: '#fff',
                        color: '#212529',
                        confirmButtonColor: '#EA580C'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear';
                });
        }

        document.getElementById('selProveedor').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const credRow = document.getElementById('rowCredito');
            if (opt && opt.value) {
                const cond = opt.dataset.condicion || 'Contado';
                const dias = opt.dataset.dias || '0';
                document.getElementById('displayCondicion').value = cond;
                document.getElementById('displayDias').value = dias;
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
                document.getElementById('displayCondicion').value = '-';
                document.getElementById('displayDias').value = '-';
                credRow.style.display = 'none';
            }
        });

        document.getElementById('selProducto').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.dataset.precio) {
                document.getElementById('inputPrecio').value = parseFloat(opt.dataset.precio).toFixed(2);
            }
        });

        function formatearPrecioCompra(el) {
            var raw = el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            var parts = raw.split('.');
            var entero = parts[0].replace(/^0+/, '') || '0';
            var decimales = parts[1] ? parts[1].slice(0, 2) : '';
            var formateado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (decimales) formateado += '.' + decimales;
            var num = parseFloat(entero + '.' + (decimales || '0'));
            if (num > 999999.99) {
                entero = '999999';
                decimales = '99';
                formateado = '999,999.99';
            }
            el.value = formateado;
        }

        function agregarProducto() {
            const sel = document.getElementById('selProducto');
            const precEl = document.getElementById('inputPrecio');
            precEl.value = precEl.value.replace(/,/g, '');
            const cant = parseInt(document.getElementById('inputCant').value) || 0;
            const precio = parseFloat(precEl.value) || 0;
            const venc = document.getElementById('inputVencimiento') ? document.getElementById('inputVencimiento').value : '';
            const tipoSel = document.querySelector('select[name="tipo_entrada"]');
            const sinPrecio = (tipoSel && tipoSel.value === 'Donación') || getDireccion() < 0;

            if (!sel.value || cant <= 0 || (!sinPrecio && precio <= 0)) {
                alert('Seleccione producto, cantidad y precio válidos');
                return;
            }

            const nombre = sel.options[sel.selectedIndex].text.split(' (')[0];

            productos.push({
                id: sel.value,
                nombre: nombre,
                cantidad: cant,
                precio: precio,
                fecha_vencimiento: venc,
                total: cant * precio
            });
            actualizarTabla();

            sel.value = '';
            document.getElementById('inputCant').value = 1;
            document.getElementById('inputPrecio').value = '';
            if (document.getElementById('inputVencimiento')) document.getElementById('inputVencimiento').value = '';
        }

        function quitarProducto(idx) {
            productos.splice(idx, 1);
            actualizarTabla();
        }

        function actualizarTabla() {
            const body = document.getElementById('productosBody');
            if (productos.length === 0) {
                body.innerHTML = '<tr id="filaVacia"><td colspan="7" style="padding:24px 12px;text-align:center;color:#64748b;font-size:.85rem;border-bottom:1px solid var(--jv-border);">⬆ Use los controles de arriba para agregar productos</td></tr>';
            } else {
                body.innerHTML = '';
                productos.forEach((p, i) => {
                    const tr = document.createElement('tr');
                    const fechaFmt = p.fecha_vencimiento ? p.fecha_vencimiento.split('-').reverse().join('/') : '—';
                    tr.innerHTML = '<td style="padding:8px 10px;color:#64748b;text-align:center;font-size:.85rem;border-bottom:1px solid var(--jv-border);">' + (i + 1) + '</td>' +
                        '<td style="padding:8px 10px;font-size:.85rem;border-bottom:1px solid var(--jv-border);">' + escapeHtml(p.nombre) + '</td>' +
                        '<td style="padding:8px 10px;font-size:.85rem;text-align:center;border-bottom:1px solid var(--jv-border);">' + p.cantidad + '</td>' +
                        '<td style="padding:8px 10px;font-size:.85rem;text-align:right;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">$' + p.precio.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</td>' +
                        '<td style="padding:8px 10px;font-size:.85rem;text-align:center;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + fechaFmt + '</td>' +
                        '<td style="padding:8px 10px;font-size:.85rem;text-align:right;color:var(--jv-navy);font-weight:700;border-bottom:1px solid var(--jv-border);">$' + p.total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</td>' +
                        '<td style="padding:8px 10px;border-bottom:1px solid var(--jv-border);"><button type="button" class="btn btn-sm border-0" style="padding:0;color:var(--jv-danger);font-size:.8rem;line-height:1;" onclick="quitarProducto(' + i + ')"><i class="bi bi-x-circle"></i></button></td>';
                    body.appendChild(tr);
                });
            }
            document.getElementById('totalItems').textContent = productos.length;
            const suma = productos.reduce(function(s, p) {
                return s + p.total;
            }, 0);
            document.getElementById('totalCosto').textContent = '$' + suma.toFixed(2);
            document.getElementById('btnGuardar').disabled = productos.length === 0;
            document.getElementById('productosData').value = JSON.stringify(productos);
        }

        function limpiarErrores() {
            document.querySelectorAll('.input-error').forEach(function(el) {
                el.classList.remove('input-error');
            });
        }

        function marcarError(el, mensaje) {
            el.classList.add('input-error');
            if (mensaje) {
                const errId = el.id + '_err';
                let errEl = document.getElementById(errId);
                if (!errEl) {
                    errEl = document.createElement('small');
                    errEl.id = errId;
                    errEl.className = 'field-error';
                    errEl.style.cssText = 'color:#DC2626;font-size:.7rem;margin-top:2px;display:block;';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = mensaje;
            }
        }

        function validarFormulario(btn) {
            limpiarErrores();
            const tipo = document.querySelector('select[name="tipo_entrada"]').value;
            const errores = [];
            let primerError = null;
            if (tipo === 'Compra a proveedor') {
                const prov = document.getElementById('selProveedor');
                if (!prov.value) {
                    errores.push('SELECCIONE UN PROVEEDOR');
                    marcarError(prov);
                    if (!primerError) primerError = prov;
                }
                const fac = document.querySelector('input[name="nro_factura"]');
                if (!fac.value.trim()) {
                    errores.push('NRO. FACTURA ES OBLIGATORIO');
                    marcarError(fac);
                    if (!primerError) primerError = fac;
                }
                const ctrl = document.querySelector('input[name="nro_control"]');
                if (!/^\d{2}-\d{8}$/.test(ctrl.value.trim())) {
                    errores.push('NRO. CONTROL INVÁLIDO (00-00000000)');
                    marcarError(ctrl);
                    if (!primerError) primerError = ctrl;
                }
            } else {
                const causa = document.querySelector('select[name="causa_ajuste"]');
                if (!causa.value) {
                    errores.push('SELECCIONE UNA CAUSA DE AJUSTE');
                    marcarError(causa);
                    if (!primerError) primerError = causa;
                }
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
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> GUARDANDO...';
            btn.form.submit();
            return false;
        }

        function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaEntradas').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        const DIR_MAP = {
            'Sobrante físico': 1,
            'Devolución': 1,
            'Error de conteo (+) Excedente': 1,
            'Producto vencido': -1,
            'Dañado/Averiado': -1,
            'Robo hormiga': -1,
            'Merma operativa': -1,
            'Error de conteo (-) Faltante': -1,
            'Regalo de proveedor': 1,
            'Muestra comercial': 1,
            'Promocional': 1,
            'Apoyo comunitario': -1,
            'Cortesía comercial': -1,
            'Regalo empleado': -1,
            'Lote promocional': -1,
        };
        const CAUSAS_AJUSTE = [{
                v: 'Sobrante físico',
                l: '➕ Sobrante físico'
            },
            {
                v: 'Devolución',
                l: '➕ Devolución'
            },
            {
                v: 'Error de conteo (+) Excedente',
                l: '➕ Error de conteo — Excedente'
            },
            {
                v: 'Producto vencido',
                l: '➖ Producto vencido'
            },
            {
                v: 'Dañado/Averiado',
                l: '➖ Dañado/Averiado'
            },
            {
                v: 'Robo hormiga',
                l: '➖ Robo hormiga'
            },
            {
                v: 'Merma operativa',
                l: '➖ Merma operativa'
            },
            {
                v: 'Error de conteo (-) Faltante',
                l: '➖ Error de conteo — Faltante'
            },
        ];
        const CAUSAS_DONACION = [{
                v: 'Regalo de proveedor',
                l: '➕ Regalo de proveedor'
            },
            {
                v: 'Muestra comercial',
                l: '➕ Muestra comercial'
            },
            {
                v: 'Promocional',
                l: '➕ Promocional'
            },
            {
                v: 'Apoyo comunitario',
                l: '➖ Apoyo comunitario'
            },
            {
                v: 'Cortesía comercial',
                l: '➖ Cortesía comercial'
            },
            {
                v: 'Regalo empleado',
                l: '➖ Regalo empleado'
            },
            {
                v: 'Lote promocional',
                l: '➖ Lote promocional'
            },
        ];

        function actualizarDireccion() {
            const causa = document.querySelector('select[name="causa_ajuste"]').value;
            const badge = document.getElementById('direccionBadge');
            const precioInput = document.getElementById('inputPrecio');
            const tipoSel = document.querySelector('select[name="tipo_entrada"]');
            const esDonacion = tipoSel && tipoSel.value === 'Donación';
            const dir = DIR_MAP[causa] || 0;
            if (dir > 0) {
                badge.style.display = 'inline-block';
                badge.style.background = '#16a34a';
                badge.textContent = 'SUMA STOCK +';
                if (precioInput && !esDonacion) {
                    precioInput.readOnly = false;
                }
            } else if (dir < 0) {
                badge.style.display = 'inline-block';
                badge.style.background = '#dc2626';
                badge.textContent = 'RESTA STOCK -';
                if (precioInput) {
                    precioInput.value = '0';
                    precioInput.readOnly = true;
                }
            } else {
                badge.style.display = 'none';
            }
        }

        function getDireccion() {
            const tipoSel = document.querySelector('select[name="tipo_entrada"]');
            if (!tipoSel || (tipoSel.value !== 'Ajuste' && tipoSel.value !== 'Donación')) return 0;
            const causa = document.querySelector('select[name="causa_ajuste"]').value;
            return DIR_MAP[causa] || 0;
        }

        function toggleCamposCompras(sel) {
            limpiarErrores();
            const tipo = sel.value;
            const esProv = tipo === 'Compra a proveedor';
            const esDonacion = tipo === 'Donación';
            const esAjuste = tipo === 'Ajuste';
            const esMov = esAjuste || esDonacion;
            document.querySelectorAll('.comp-proveedor-section').forEach(el => el.style.display = esProv ? '' : 'none');
            document.querySelectorAll('.comp-factura-section').forEach(el => el.style.display = esProv ? '' : 'none');
            document.querySelectorAll('.comp-motivo-section').forEach(el => el.style.display = esMov ? '' : 'none');
            document.getElementById('motivoLabel').textContent = esDonacion ? 'Motivo de la Donación' : 'Motivo del Ajuste';
            const provSel = document.getElementById('selProveedor');
            if (!esProv && provSel) provSel.removeAttribute('required');
            if (esProv && provSel) provSel.setAttribute('required', '');
            const facInput = document.querySelector('input[name="nro_factura"]');
            if (facInput) {
                if (esProv) facInput.setAttribute('required', '');
                else facInput.removeAttribute('required');
            }
            // Populate causas
            const causaSel = document.querySelector('select[name="causa_ajuste"]');
            if (causaSel && esMov) {
                var lista = esDonacion ? CAUSAS_DONACION : CAUSAS_AJUSTE;
                var html = '<option value="">Seleccionar...</option>';
                for (var i = 0; i < lista.length; i++) {
                    html += '<option value="' + lista[i].v + '">' + lista[i].l + '</option>';
                }
                causaSel.innerHTML = html;
            }
            const precioInput = document.getElementById('inputPrecio');
            if (precioInput) {
                if (esDonacion || getDireccion() < 0) {
                    precioInput.value = '0';
                    precioInput.readOnly = true;
                } else {
                    precioInput.readOnly = false;
                }
            }
            actualizarDireccion();
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿ANULAR?',
                text: 'El stock será revertido del inventario.',
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
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
                el.addEventListener('change', function() {
                    this.classList.remove('input-error');
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
            });
            const tipoSel = document.querySelector('select[name="tipo_entrada"]');
            if (tipoSel) toggleCamposCompras(tipoSel);
        });
    

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
    
