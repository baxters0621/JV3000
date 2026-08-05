
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
            function validarCategoria(btn) {
                limpiarErrores();
                const nom = document.getElementById('cat_nombre');
                if (!nom.value.trim()) { marcarError(nom, 'NOMBRE REQUERIDO'); nom.focus(); return false; }
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> GUARDANDO...';
                btn.form.submit(); return false;
            }
            const modalC = new bootstrap.Modal(document.getElementById('modalCat'));

            function nuevaCat() {
                document.getElementById('cat_accion').value = "registrar";
                document.getElementById('cat_id_edit').value = "";
                document.getElementById('cat_status').value = "Activo";
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag-fill me-2"></i>NUEVA CATEGORÍA';
                document.getElementById('cat_nombre').value = "";
                document.getElementById('cat_desc').value = "";
                document.getElementById('cat_abc').value = "";
                document.getElementById('cat_manejo').value = "normal";
                document.getElementById('cat_nombre').focus();
                modalC.show();
            }

            function editarCat(data) {
                document.getElementById('cat_accion').value = "editar";
                document.getElementById('cat_id_edit').value = data.id_categoria;
                document.getElementById('cat_status').value = data.status || 'Activo';
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag-fill me-2"></i>EDITAR CATEGORÍA';
                document.getElementById('cat_nombre').value = data.nombre;
                document.getElementById('cat_desc').value = data.descripcion || '';
                document.getElementById('cat_abc').value = data.clasificacion_abc || '';
                document.getElementById('cat_manejo').value = data.tipo_manejo || 'normal';
                document.getElementById('cat_nombre').focus();
                modalC.show();
            }

            function confirmarToggle(id, nombre, accion) {
                const esDes = accion === 'desactivar';
                Swal.fire({
                    title: esDes ? '¿DESACTIVAR CATEGORÍA?' : '¿REACTIVAR CATEGORÍA?',
                    text: esDes ? `Se desactivará '${nombre}'` : `Se reactivará '${nombre}'`,
                    icon: esDes ? 'warning' : 'info',
                    showCancelButton: true,
                    confirmButtonColor: esDes ? '#DC2626' : '#16A34A',
                    cancelButtonColor: '#CED4DA',
                    confirmButtonText: esDes ? 'SÍ, DESACTIVAR' : 'SÍ, ACTIVAR',
                    cancelButtonText: 'CANCELAR',
                    background: '#fff',
                    color: '#212529'
                }).then((result) => {
                    if (result.isConfirmed) jvPost({ toggle_status: id, csrf_token: window.JV_CONFIG.c0 });
                });
            }

            function filtrar() {
                const input = document.getElementById('buscar');
                const filter = input.value.toLowerCase();
                const rows = document.getElementById('tablaCategorias').getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const nombre = rows[i].getAttribute('data-nombre') || '';
                    const codigo = rows[i].getAttribute('data-codigo') || '';
                    rows[i].style.display = (nombre.includes(filter) || codigo.includes(filter)) ? '' : 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const alerts = document.querySelectorAll('.alert-jv');
                alerts.forEach(function(a) {
                    setTimeout(function() {
                        a.style.transition = 'opacity 0.6s';
                        a.style.opacity = '0';
                        setTimeout(function() { a.remove(); }, 600);
                    }, 4000);
                });
                document.querySelectorAll('#formCat input, #formCat select, #formCat textarea').forEach(function(el) {
                    el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                    el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                });
            });
        
