

        function filtrarPorAlerta(clase) {
            var btn = document.querySelector('.btn-filtro-venc[data-venc="' + clase + '"]');
            if (btn) filtrarVenc(btn);
        }

        function aplicarFiltros() {
            var vencBtn = document.querySelector('.btn-filtro-venc.active');
            var filtroVenc = vencBtn ? vencBtn.getAttribute('data-venc') : 'todas';
            var statusBtn = document.querySelector('.btn-filter-prod.active');
            var filtroStatus = statusBtn ? statusBtn.getAttribute('data-status') : 'todas';
            var searchVal = document.getElementById('buscar').value.toLowerCase();
            var rows = document.getElementById('tablaProductos').getElementsByTagName('tr');
            for (var i = 0; i < rows.length; i++) {
                var st = rows[i].getAttribute('data-status') || '';
                if (filtroStatus !== 'todas' && st !== filtroStatus) { rows[i].style.display = 'none'; continue; }
                var vc = rows[i].getAttribute('data-venc-cls') || '';
                if (filtroVenc !== 'todas' && vc !== filtroVenc) { rows[i].style.display = 'none'; continue; }
                if (filtroBajos) {
                    var stk = parseInt(rows[i].getAttribute('data-stock') || '', 10);
                    var min = parseInt(rows[i].getAttribute('data-minimo') || '', 10);
                    if (isNaN(stk) || stk > min) { rows[i].style.display = 'none'; continue; }
                }
                var texto = rows[i].textContent.toLowerCase();
                rows[i].style.display = (searchVal === '' || texto.includes(searchVal)) ? '' : 'none';
            }
        }
        function filtrarVenc(btn) {
            document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#EA580C';
            });
            btn.classList.add('active');
            btn.style.background = 'rgba(234,88,12,0.15)';
            btn.style.color = '#EA580C';
            aplicarFiltros();
        }

        function bajaVencido(id, nombre) {
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
                if (r.isConfirmed) jvPost({ baja_vencido: id, csrf_token: window.JV_CONFIG.c0 });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-jv');
            alerts.forEach(function(a) {
                setTimeout(function() {
                    a.style.transition = 'opacity 0.6s';
                    a.style.opacity = '0';
                    setTimeout(function() {
                        a.remove();
                    }, 600);
                }, 4000);
            });
            document.querySelectorAll('#formEditar input, #formEditar select').forEach(function(el) {
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
        });

        var modalEditar = null;
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('modalEditar');
            if (el) modalEditar = new bootstrap.Modal(el);
        });

        function validarEditarProducto(btn) {
            limpiarErrores();
            let primerError = null;
            const minimo = document.getElementById('edit_minimo');
            const maximo = document.getElementById('edit_maximo');
            const pvp = document.getElementById('edit_pvp');
            const costo = document.getElementById('edit_costo');
            const proveedor = document.getElementById('edit_proveedor');
            if (!minimo.value || parseInt(minimo.value) <= 0) {
                marcarError(minimo, 'OBLIGATORIO (> 0)');
                if (!primerError) primerError = minimo;
            }
            if (maximo && (maximo.value === '' || parseInt(maximo.value) < 0)) {
                marcarError(maximo, 'MÍNIMO 0 (0 = heredar categoría)');
                if (!primerError) primerError = maximo;
            }
            if (maximo && parseInt(maximo.value) > 0 && parseInt(maximo.value) < parseInt(minimo.value)) {
                marcarError(maximo, 'DEBE SER ≥ STOCK MÍNIMO');
                if (!primerError) primerError = maximo;
            }
            if (!pvp.value || parseFloat(pvp.value) <= 0) {
                marcarError(pvp, 'OBLIGATORIO (> 0)');
                if (!primerError) primerError = pvp;
            }
            if (!costo.value || parseFloat(costo.value) <= 0) {
                marcarError(costo, 'OBLIGATORIO (> 0)');
                if (!primerError) primerError = costo;
            }
            if (!proveedor.value || parseInt(proveedor.value) <= 0) {
                marcarError(proveedor, 'OBLIGATORIO');
                if (!primerError) primerError = proveedor;
            }
            if (primerError) {
                primerError.focus();
                return false;
            }
            btn.disabled = true;
            btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span>GUARDANDO...';
            btn.form.submit();
            return false;
        }

        function editarProducto(id) {
            limpiarErrores();
            var row = document.querySelector('tr[data-id="' + id + '"]');
            if (!row) return;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = row.getAttribute('data-nombre').toUpperCase();
            document.getElementById('edit_sku').value = row.getAttribute('data-sku');
            document.getElementById('edit_stock').value = row.getAttribute('data-stock');
            document.getElementById('edit_minimo').value = row.getAttribute('data-minimo');
            document.getElementById('edit_maximo').value = row.getAttribute('data-maximo');
            document.getElementById('edit_pvp').value = parseFloat(row.getAttribute('data-pvp')).toFixed(2);
            document.getElementById('edit_costo').value = parseFloat(row.getAttribute('data-costo')).toFixed(2);
            document.getElementById('edit_status').value = row.getAttribute('data-status');
            document.getElementById('edit_vencimiento').value = row.getAttribute('data-venc');
            document.getElementById('edit_proveedor').value = row.getAttribute('data-prov-id');
            if (modalEditar) modalEditar.show();
        }

        function toggleProducto(id, nombre, accion) {
            var esActivar = accion === 'activar';
            Swal.fire({
                title: esActivar ? '¿REACTIVAR?' : '¿DESACTIVAR?',
                html: (esActivar ? 'Se reactivará <strong>' : 'Se desactivará <strong>') + escapeHtml(nombre) + '</strong>' + (esActivar ? '' : ' del inventario.'),
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: esActivar ? '#16A34A' : '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: esActivar ? 'SÍ, REACTIVAR' : 'SÍ, DESACTIVAR',
                cancelButtonText: 'CANCELAR'
            }).then(function(r) {
                if (r.isConfirmed) {
                    jvPost({ toggle: id, csrf_token: window.JV_CONFIG.c1 });
                }
            });
        }

        function filtrarStatus(btn) {
            document.querySelectorAll('.btn-filter-prod').forEach(function(b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            aplicarFiltros();
        }

        function filtrar() {
            aplicarFiltros();
        }

        var filtroBajos = false;

        function filtrarBajos() {
            filtroBajos = !filtroBajos;
            document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#EA580C';
            });
            aplicarFiltros();
        }

        function destacarProducto(id) {
            var fila = document.querySelector('#tablaProductos tr[data-id="' + id + '"]');
            if (!fila) return;
            var nombre = fila.getAttribute('data-nombre') || '';
            var buscar = document.getElementById('buscar');
            if (buscar && nombre) {
                buscar.value = nombre.toUpperCase();
                aplicarFiltros();
            }
            setTimeout(function() {
                fila.scrollIntoView({ block: 'center', behavior: 'smooth' });
                fila.classList.add('flash-prod');
                setTimeout(function() { fila.classList.remove('flash-prod'); }, 3000);
            }, 250);
        }

        function iniciarDesdeURL() {
            var params = new URLSearchParams(window.location.search);
            var pid = params.get('producto');
            var alerta = params.get('alerta');
            if (pid) {
                destacarProducto(pid);
            } else if (alerta === 'vencidos') {
                filtrarPorAlerta('vencido');
            } else if (alerta === 'proximos') {
                filtrarPorAlerta('proximo');
            } else if (alerta === 'prontos') {
                filtrarPorAlerta('pronto');
            } else if (alerta === 'bajos') {
                filtrarBajos();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            iniciarDesdeURL();
        });
    
