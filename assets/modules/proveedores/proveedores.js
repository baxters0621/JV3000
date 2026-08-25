
        // ============================
        //  CORRECCIÓN 1: Evitar conflicto con 'mainWrapper'
        //  Envuelvo todo en una función para no contaminar el ámbito global
        // ============================
        (function() {
            // Inicialización de intl-tel-input solo cuando el DOM esté listo
            document.addEventListener('DOMContentLoaded', function() {
                const telInput = document.querySelector("#p_tel");
                if (telInput) {
                    window.iti = window.intlTelInput(telInput, {
                        initialCountry: "ve",
                        preferredCountries: ["ve", "us", "co", "es", "mx", "pa"],
                        separateDialCode: true,
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
                    });

                    // Actualizar campo oculto
                    document.getElementById('p_tel_full').value = window.iti.getNumber();
                    telInput.addEventListener('countrychange', function() {
                        document.getElementById('p_tel_full').value = window.iti.getNumber();
                    });
                    telInput.addEventListener('input', function() {
                        document.getElementById('p_tel_full').value = window.iti.getNumber();
                    });
                }

                // Manejo de sidebar (mainWrapper)
                if (mainWrapper) {
                    const observer = new MutationObserver(function() {
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

                // Marcar el campo con error cuando el servidor rechazó el formulario:
                // el flash de peligro trae su texto en data-texto y aquí se traduce
                // ese mensaje al input exacto que lo causó, pintándolo con marcarError().
                var flashEl = document.getElementById('flashMsg');
                if (flashEl && flashEl.classList.contains('alert-jv-danger')) {
                    var texto = (flashEl.dataset.texto || '').toUpperCase();
                    var mapaCampoError = [
                        ['RIF', 'p_rif'],
                        ['NOMBRE', 'p_empresa'],
                        ['CORREO', 'p_email'],
                        ['EMAIL', 'p_email'],
                        ['TELÉFONO', 'p_tel']
                    ];
                    for (var i = 0; i < mapaCampoError.length; i++) {
                        if (texto.indexOf(mapaCampoError[i][0]) !== -1) {
                            var campo = document.getElementById(mapaCampoError[i][1]);
                            if (campo) marcarError(campo, texto);
                            break;
                        }
                    }
                }

                // (El auto-cierre de flash messages lo maneja diseno.js globalmente.)

                // Limpiar errores al hacer focus/change
                document.querySelectorAll('#formProveedor input, #formProveedor select, #formProveedor textarea')
                    .forEach(function(el) {
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
        })();

        // ============================
        //  CORRECCIÓN 2: Funciones globales (necesarias para onclick en HTML)
        // ============================

        const modalP = new bootstrap.Modal(document.getElementById('modalProveedor'));
        const formP = document.getElementById('formProveedor');
        const modalCat = new bootstrap.Modal(document.getElementById('modalCatalogo'));

        // Formatea en vivo el valor monetario con separadores de miles y un solo punto decimal.
        function formatMoney(moneyInput) {
            let rawValue = moneyInput.value.replace(/[^0-9.]/g, '');
            let valueParts = rawValue.split('.');
            if (valueParts.length > 2) valueParts = [valueParts[0], valueParts.slice(1).join('')];
            valueParts[0] = valueParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            moneyInput.value = valueParts.join('.');
        }

        document.getElementById('cat_costo').addEventListener('input', function() {
            formatMoney(this);
        });

        // RIF formatter
        document.getElementById('p_rif').addEventListener('input', function(e) {
            let val = e.target.value.toUpperCase().replace(/[^VEJGPC0-9]/g, '');
            let formatted = '';
            if (val.length > 0) {
                formatted += val[0];
                if (val.length > 1) {
                    formatted += '-';
                    let body = val.substring(1).slice(0, 9);
                    if (body.length > 8) {
                        formatted += body.substring(0, 8) + '-' + body.substring(8);
                    } else {
                        formatted += body;
                    }
                }
            }
            e.target.value = formatted.substring(0, 13);
        });

        // ============================
        // FUNCIÓN nuevoProveedor (con verificación de iti)
        // ============================
        // Limpia el formulario, reinicia el selector de teléfono y abre el modal en modo registro.
        function nuevoProveedor() {
            document.getElementById('p_accion').value = "registrar";
            document.getElementById('p_id_edit').value = "";
            document.getElementById('modalTitle').innerText = "REGISTRAR PROVEEDOR";
            document.getElementById('p_rif').value = "";
            document.getElementById('p_empresa').value = "";
            document.getElementById('p_contacto_nombre').value = "";
            document.getElementById('p_email').value = "";
            document.getElementById('p_direccion').value = "";
            document.getElementById('p_lead_time').value = "";
            document.getElementById('p_moneda').value = "USD";
            document.getElementById('p_status').value = "Activo";
            document.getElementById('btn-prov-submit').disabled = false;
            document.getElementById('btn-prov-submit').innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';

            // Verificar que 'iti' existe y tiene el método reset
            if (window.iti && typeof window.iti.reset === 'function') {
                window.iti.reset();
            } else {
                // Si no está disponible, intentamos reinicializar (por si el input no existía)
                const telInput = document.querySelector("#p_tel");
                if (telInput && !window.iti) {
                    window.iti = window.intlTelInput(telInput, {
                        initialCountry: "ve",
                        preferredCountries: ["ve", "us", "co", "es", "mx", "pa"],
                        separateDialCode: true,
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
                    });
                }
                if (window.iti && typeof window.iti.reset === 'function') {
                    window.iti.reset();
                }
            }

            modalP.show();
        }

        // Carga los datos de un proveedor en el formulario y abre el modal en modo edición.
        function editarProveedor(data) {
            document.getElementById('p_accion').value = "editar";
            document.getElementById('p_id_edit').value = data.id_proveedor;
            document.getElementById('modalTitle').innerText = "EDITAR PROVEEDOR";
            document.getElementById('p_rif').value = data.rif;
            document.getElementById('p_empresa').value = data.nombre_empresa;
            document.getElementById('p_contacto_nombre').value = data.contacto || "";
            document.getElementById('p_email').value = data.email || "";
            document.getElementById('p_direccion').value = data.direccion || "";
            document.getElementById('p_lead_time').value = data.lead_time || "";
            document.getElementById('p_moneda').value = data.moneda || "USD";
            document.getElementById('p_status').value = data.status || "Activo";
            document.getElementById('btn-prov-submit').disabled = false;
            document.getElementById('btn-prov-submit').innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';
            document.getElementById('p_rif').dispatchEvent(new Event('input'));

            // Establecer número en iti
            if (window.iti && typeof window.iti.setNumber === 'function') {
                window.iti.setNumber(data.telefono || "");
                document.getElementById('p_tel_full').value = window.iti.getNumber();
            }

            modalP.show();
        }

        // Marca como activo el botón de filtro por estado y reaplica el filtrado de tarjetas.
        function filtrarProv(status) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('f-' + status).classList.add('active');
            aplicarFiltroProveedores();
        }

        // Reaplica el filtrado cuando cambia el texto de búsqueda por nombre o datos del proveedor.
        function filtrarProvTexto() {
            aplicarFiltroProveedores();
        }

        // Muestra u oculta las tarjetas de proveedores según el estado seleccionado y el texto de búsqueda.
        function aplicarFiltroProveedores() {
            const activo = document.querySelector('.btn-filter.active');
            const status = activo ? activo.id.replace('f-', '') : 'todos';
            const input = document.getElementById('buscarProv');
            const texto = input ? input.value.toLowerCase() : '';
            document.querySelectorAll('.prov-card').forEach(card => {
                const okStatus = (status === 'todos' || card.dataset.status === status);
                const okTexto = texto === '' || card.textContent.toLowerCase().includes(texto);
                card.style.display = (okStatus && okTexto) ? 'block' : 'none';
            });
        }

        // Expande o contrae el panel de detalles de una tarjeta de proveedor premium.
        function toggleProv(providerElement) {
            providerElement.closest('.prov-premium').classList.toggle('expanded');
        }

        // Redirige al módulo de compras prefiltrando las órdenes del proveedor indicado.
        function verHistorial(idProveedor) {
            window.location.href = 'index.php?url=compras&filtro_proveedor=' + idProveedor;
        }

        // Pide confirmación para activar o desactivar un proveedor y envía la acción al servidor.
        function toggleStatusProveedor(id, nombre, statusActual) {
            var activo = statusActual === 'Activo';
            Swal.fire({
                title: activo ? '¿Desactivar proveedor?' : '¿Activar proveedor?',
                html: activo ? `Se desactivará <strong>${escapeHtml(nombre)}</strong>.` : `Se reactivará <strong>${escapeHtml(nombre)}</strong>.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: activo ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: activo ? 'Sí, desactivar' : 'Sí, activar',
                cancelButtonText: 'Cancelar',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(result => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="accion_proveedor" value="toggle_status">
                        <input type="hidden" name="id_proveedor" value="${id}">
                        <input type="hidden" name="csrf_token" value="${window.JV_CONFIG.csrfToken}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ============================
        // CATÁLOGO DE COSTOS: agregar / editar / eliminar entradas
        // ============================

        // Abre el modal de catálogo en modo registro para el proveedor indicado.
        function agregarProductoCatalogo(idProveedor, nombreProveedor) {
            document.getElementById('cat_accion').value = "registrar";
            document.getElementById('cat_id_edit').value = "";
            document.getElementById('cat_id_prov').value = idProveedor;
            document.getElementById('cat_proveedor_nombre').value = nombreProveedor;
            document.getElementById('catTitulo').innerText = 'AGREGAR PRODUCTO';
            document.getElementById('catSubtitulo').innerText = 'Asocia un producto a este proveedor con su costo de compra.';
            document.getElementById('cat_producto').value = "";
            document.getElementById('cat_costo').value = "";
            document.getElementById('cat_codigo_prov').value = "";
            document.getElementById('btn-cat-submit').disabled = false;
            modalCat.show();
        }

        // Abre el modal de catálogo en modo edición con los datos de la entrada.
        function editarProductoCatalogo(entrada, nombreProveedor) {
            document.getElementById('cat_accion').value = "editar";
            document.getElementById('cat_id_edit').value = entrada.id_catalogo;
            document.getElementById('cat_id_prov').value = entrada.id_proveedor;
            document.getElementById('cat_proveedor_nombre').value = nombreProveedor;
            document.getElementById('catTitulo').innerText = 'EDITAR PRODUCTO DEL CATÁLOGO';
            document.getElementById('catSubtitulo').innerText = 'Actualiza el costo o el código interno del producto.';
            document.getElementById('cat_producto').value = entrada.id_producto;
            document.getElementById('cat_costo').value = parseFloat(entrada.costo).toFixed(2);
            formatMoney(document.getElementById('cat_costo'));
            document.getElementById('cat_codigo_prov').value = entrada.codigo_proveedor || "";
            document.getElementById('btn-cat-submit').disabled = false;
            modalCat.show();
        }

        // Confirma quitar un producto del catálogo y envía la acción al servidor.
        function confirmarEliminarCatalogo(idCatalogo, nombreProducto) {
            Swal.fire({
                title: '¿QUITAR DEL CATÁLOGO?',
                html: `Se quitará <strong>${escapeHtml(nombreProducto)}</strong> del catálogo de este proveedor.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EA580C',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'Sí, quitar',
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

        // Validación del formulario de catálogo (anti-doble-click incluido).
        document.getElementById('formCatalogo').addEventListener('submit', function(e) {
            limpiarErrores();
            const prodEl = document.getElementById('cat_producto');
            if (!prodEl.value) {
                marcarError(prodEl, 'SELECCIONA UN PRODUCTO');
                e.preventDefault();
                prodEl.focus();
                return;
            }
            const costoRaw = document.getElementById('cat_costo').value.replace(/,/g, '');
            if (!(parseFloat(costoRaw) > 0)) {
                marcarError(document.getElementById('cat_costo'), 'COSTO REQUERIDO (MAYOR A 0)');
                e.preventDefault();
                document.getElementById('cat_costo').focus();
                return;
            }
            const btn = document.getElementById('btn-cat-submit');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
        });

        // ============================
        // Validación del formulario (con anti-doble-click)
        // ============================
        formP.addEventListener('submit', function(e) {
            limpiarErrores();
            let primerError = null;

            const emp = document.getElementById('p_empresa');
            if (!emp.value.trim()) {
                marcarError(emp, 'NOMBRE REQUERIDO');
                e.preventDefault();
                if (!primerError) primerError = emp;
            }

            const rifEl = document.getElementById('p_rif');
            const rifValue = rifEl.value;
            if (!/^[VEJGPC]-\d{8}-\d$/.test(rifValue)) {
                marcarError(rifEl, 'RIF INVÁLIDO (J-12345678-0)');
                e.preventDefault();
                if (!primerError) primerError = rifEl;
            }

            // Validar teléfono con iti
            if (window.iti) {
                document.getElementById('p_tel_full').value = window.iti.getNumber();
                if (!window.iti.isValidNumber()) {
                    const raw = window.iti.getNumber().replace(/\D/g, '');
                    if (raw.length < 8) {
                        const telEl = document.getElementById('p_tel');
                        marcarError(telEl, 'TELÉFONO INVÁLIDO');
                        e.preventDefault();
                        if (!primerError) primerError = telEl;
                    }
                }
            }

            const emailEl = document.getElementById('p_email');
            if (emailEl.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                marcarError(emailEl, 'EMAIL INVÁLIDO');
                e.preventDefault();
                if (!primerError) primerError = emailEl;
            }

            if (primerError) {
                primerError.focus();
                return;
            }

            // Anti-doble-click
            const btn = document.getElementById('btn-prov-submit');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
        });
    
