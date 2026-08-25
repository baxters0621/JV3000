
            // ==========================================
            // VALIDAR ANTES DE ENVIAR EL FORMULARIO
            // ==========================================
            // Valida que el nombre de la categoría no esté vacío antes de enviar el formulario.
            // Se llama desde el botón "Guardar" del modal.
            // Si el nombre está vacío, marca el campo en rojo y NO envía.
            function validarCategoria(submitButton) {
                limpiarErrores(); // quita marcas rojas previas (helper global en diseno.js)
                const categoryNameInput = document.getElementById('cat_nombre');
                if (!categoryNameInput.value.trim()) { marcarError(categoryNameInput, 'NOMBRE REQUERIDO'); categoryNameInput.focus(); return false; }
                // Anti-doble-click: deshabilita el botón y muestra "GUARDANDO..."
                submitButton.disabled = true; submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> GUARDANDO...';
                submitButton.form.submit(); return false;
            }
            // Instancia del modal de Bootstrap (se usa en nuevaCat/editarCat para abrirlo).
            const modalC = new bootstrap.Modal(document.getElementById('modalCat'));

            // ==========================================
            // NUEVA CATEGORÍA: prepara el modal en modo "crear"
            // ==========================================
            // Limpia el formulario y abre el modal configurado para registrar una categoría nueva.
            function nuevaCat() {
                // cat_accion = "registrar" indica al servidor que la operación es una creación.
                document.getElementById('cat_accion').value = "registrar";
                document.getElementById('cat_id_edit').value = "";
                document.getElementById('cat_status').value = "Activo";
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag-fill me-2"></i>NUEVA CATEGORÍA';
                // Limpiar todos los campos para empezar de cero.
                document.getElementById('cat_nombre').value = "";
                document.getElementById('cat_desc').value = "";
                document.getElementById('cat_abc').value = "";
                document.getElementById('cat_manejo').value = "normal";
                document.getElementById('cat_nombre').focus();
                modalC.show(); // abrir la ventana
            }

            // ==========================================
            // EDITAR CATEGORÍA: rellena el modal con datos existentes
            // ==========================================
            // Carga los datos de la categoría elegida en el modal para modificarlos.
            // data es la fila de la tabla convertida a objeto por la vista
            // (onclick='editarCat(<?php json_encode($row) ?>)').
            function editarCat(data) {
                document.getElementById('cat_accion').value = "editar"; // modo edición
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

            // ==========================================
            // ACTIVAR / DESACTIVAR con confirmación (SweetAlert)
            // ==========================================
            // Pide confirmación al usuario y, si la acepta, envía el POST que cambia el estado de la categoría.
            function confirmarToggle(id, nombre, accion) {
                const isDeactivation = accion === 'desactivar';
                Swal.fire({
                    title: isDeactivation ? '¿DESACTIVAR CATEGORÍA?' : '¿REACTIVAR CATEGORÍA?',
                    text: isDeactivation ? `Se desactivará '${nombre}'` : `Se reactivará '${nombre}'`,
                    icon: isDeactivation ? 'warning' : 'info',
                    showCancelButton: true,
                    confirmButtonColor: isDeactivation ? '#DC2626' : '#16A34A',
                    cancelButtonColor: '#CED4DA',
                    confirmButtonText: isDeactivation ? 'SÍ, DESACTIVAR' : 'SÍ, ACTIVAR',
                    cancelButtonText: 'CANCELAR',
                    background: '#fff',
                    color: '#212529'
                }).then((result) => {
                    // Si el usuario confirma, se envía un POST con el id y el
                    // token CSRF (window.JV_CONFIG.csrfToken lo inyecta el layout).
                    // jvPost es un helper global (diseno.js) que arma un form.
                    if (result.isConfirmed) jvPost({ toggle_status: id, csrf_token: window.JV_CONFIG.csrfToken });
                });
            }

            // ==========================================
            // BUSCADOR LOCAL de la tabla (sin recargar)
            // ==========================================
            // Muestra solo las filas de la tabla que contienen el texto buscado.
            function filtrar() {
                const searchInput = document.getElementById('buscar');
                const searchValue = searchInput.value.toLowerCase();
                const categoryRows = document.getElementById('tablaCategorias').getElementsByTagName('tr');
                for (let rowIndex = 0; rowIndex < categoryRows.length; rowIndex++) {
                    // Mostrar la fila solo si su texto contiene lo buscado.
                    categoryRows[rowIndex].style.display = categoryRows[rowIndex].textContent.toLowerCase().includes(searchValue) ? '' : 'none';
                }
            }

            // ==========================================
            // AL CARGAR LA PÁGINA
            // ==========================================
            document.addEventListener('DOMContentLoaded', function() {
                // (El auto-cierre de mensajes flash lo maneja diseno.js globalmente.)
                // Mientras el usuario escribe/cambia un campo del modal,
                // se quita la marca de error de ese campo automáticamente.
                document.querySelectorAll('#formCat input, #formCat select, #formCat textarea').forEach(function(el) {
                    el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                    el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                });
            });
        
