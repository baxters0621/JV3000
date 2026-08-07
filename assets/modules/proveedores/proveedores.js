
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

                // Auto-cierre de flash messages
                document.querySelectorAll('.flash-auto').forEach(function(el) {
                    setTimeout(function() {
                        el.style.transition = 'opacity .5s';
                        el.style.opacity = '0';
                        setTimeout(function() {
                            el.remove();
                        }, 500);
                    }, 4000);
                });

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

        function formatMoney(el) {
            let val = el.value.replace(/[^0-9.]/g, '');
            let parts = val.split('.');
            if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            el.value = parts.join('.');
        }

        document.getElementById('p_limite_credito').addEventListener('input', function() {
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
            document.getElementById('p_limite_credito').value = "";
            document.getElementById('p_dias_credito').value = "0";
            document.getElementById('p_condiciones_pago').value = "Contado";
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
            document.getElementById('p_limite_credito').value = data.limite_credito || "";
            if (document.getElementById('p_limite_credito').value) {
                formatMoney(document.getElementById('p_limite_credito'));
            }
            document.getElementById('p_dias_credito').value = data.dias_credito || 0;
            document.getElementById('p_condiciones_pago').value = data.condiciones_pago || "Contado";
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

        function filtrarProv(status) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('f-' + status).classList.add('active');
            aplicarFiltroProveedores();
        }

        function filtrarProvTexto() {
            aplicarFiltroProveedores();
        }

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

        function toggleProv(el) {
            el.closest('.prov-premium').classList.toggle('expanded');
        }

        function verHistorial(idProveedor) {
            window.location.href = 'compras.php?filtro_proveedor=' + idProveedor;
        }

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
                        <input type="hidden" name="csrf_token" value="${window.JV_CONFIG.c0}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ============================
        // Validación del formulario (con anti-doble-click)
        // ============================
        function limpiarErrores() {
            document.querySelectorAll('.input-error').forEach(function(el) {
                el.classList.remove('input-error');
            });
            document.querySelectorAll('.field-error').forEach(function(el) {
                el.remove();
            });
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

        // ==========================================
        // TOOLTIP GRANDE (nombre completo del proveedor)
        // ==========================================
        let provTip = null;
        let provTipTimer = null;

        function provMostrarTip(e, texto) {
            if (!texto) return;
            if (!provTip) {
                provTip = document.createElement('div');
                provTip.className = 'jv-tooltip';
                document.body.appendChild(provTip);
            }
            provTip.textContent = texto;
            provTip.classList.add('jv-tooltip-visible');
            provPosicionarTip(e);
        }

        function provPosicionarTip(e) {
            if (!provTip) return;
            const pad = 16;
            let x = e.clientX + pad;
            let y = e.clientY + pad;
            const r = provTip.getBoundingClientRect();
            if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
            if (y + r.height > window.innerHeight - 8) y = e.clientY - r.height - pad;
            provTip.style.left = Math.max(8, x) + 'px';
            provTip.style.top = Math.max(8, y) + 'px';
        }

        function provOcultarTip() {
            if (provTipTimer) window.clearTimeout(provTipTimer);
            provTipTimer = window.setTimeout(function() {
                if (provTip) provTip.classList.remove('jv-tooltip-visible');
            }, 80);
        }

        document.addEventListener('mouseover', function(e) {
            const t = e.target.closest('[data-tooltip]');
            if (t) {
                window.clearTimeout(provTipTimer);
                provMostrarTip(e, t.dataset.tooltip);
            }
        });
        document.addEventListener('mousemove', function(e) {
            if (provTip && provTip.classList.contains('jv-tooltip-visible')) provPosicionarTip(e);
        });
        document.addEventListener('mouseout', function(e) {
            if (e.target.closest('[data-tooltip]')) provOcultarTip();
        });
    
