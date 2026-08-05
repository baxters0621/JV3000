
        const modalU = new bootstrap.Modal(document.getElementById('modalUser'));

        function togglePassword() {
            const passInput = document.getElementById('u_pass');
            const icon = document.getElementById('toggleIcon');
            if (passInput.type === "password") {
                passInput.type = "text";
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            } else {
                passInput.type = "password";
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            }
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
        function validarFormulario() {
            const user = document.getElementById('u_nombre').value.trim();
            const pass = document.getElementById('u_pass').value;
            const correo = document.getElementById('u_correo').value.trim();
            const preg = document.getElementById('u_preg').value;
            const resp = document.getElementById('u_resp').value.trim();
            const btn = document.getElementById('btn-user-submit');
            const uError = document.getElementById('u_error_text');
            const fill = document.getElementById('meter-fill');
            const meterText = document.getElementById('meter-text');

            const userRegex = /^[a-zA-Z0-9_]{4,}$/;
            const userValido = userRegex.test(user);
            if (uError) {
                if (user.length > 0) {
                    uError.className = userValido ? 'text-jv-success mt-1 d-block fw-bold' : 'text-jv-danger mt-1 d-block fw-bold';
                } else {
                    uError.className = 'text-info mt-1 d-block fw-bold';
                }
            }
            const correoValido = correo === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);

            // Validación de respuesta de seguridad
            const respOk = resp.length >= 5 && resp.length <= 20 && /[a-zA-Z]/.test(resp) && /[aeiouAEIOU]/.test(resp) && !/(.)\1{3,}/.test(resp) && !/abcdef|bcdefg|cdefgh|defghi|efghij|fghijk|ghijkl|hijklm|ijklmn/i.test(resp) && !/asdf|qwerty|zxcv|abcd|1234/i.test(resp);
            const respInput = document.getElementById('u_resp');
            const respHint = document.getElementById('u_resp_hint');
            let pregRespOk = true;
            if (preg !== '' && resp === '') {
                pregRespOk = false;
                respInput.classList.add('input-error');
                if (respHint) respHint.textContent = 'Seleccionaste una pregunta, debes escribir tu respuesta.';
            } else if (preg === '' && resp !== '') {
                pregRespOk = false;
                respInput.classList.add('input-error');
                if (respHint) respHint.textContent = 'Primero selecciona una pregunta de seguridad.';
            } else if (preg !== '' && resp !== '') {
                pregRespOk = respOk;
                respInput.classList.toggle('input-error', !respOk);
                if (respHint) respHint.textContent = respOk ? '' : 'Mín. 5 y máx. 20 caracteres, sin patrones (asdf, 1234, etc).';
            } else {
                respInput.classList.remove('input-error');
                if (respHint) respHint.textContent = '';
            }

            if (pass.length > 0) {
                let score = 0;
                if (pass.length >= 8) score++;
                if (/[a-z]/.test(pass)) score++;
                if (/[A-Z]/.test(pass)) score++;
                if (/[0-9]/.test(pass)) score++;
                if (/[\W_]/.test(pass)) score++;

                const colors = ['#DC2626', '#DC2626', '#D97706', '#2563EB', '#16A34A'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];
                if (fill) {
                    fill.style.width = widths[score - 1] || '10%';
                    fill.style.backgroundColor = colors[score - 1] || colors[0];
                }

                if (meterText) {
                    if (score < 3) { meterText.textContent = 'Contraseña débil'; meterText.className = 'text-jv-danger mt-1 d-block fw-bold'; }
                    else if (score < 5) { meterText.textContent = 'Contraseña aceptable'; meterText.className = 'text-jv-warning mt-1 d-block fw-bold'; }
                    else { meterText.textContent = 'Contraseña fuerte'; meterText.className = 'text-jv-success mt-1 d-block fw-bold'; }
                }
            } else {
                if (fill) fill.style.width = '0%';
                if (meterText) { meterText.textContent = 'Mín. 8 caracteres: Mayúsculas, Minúsculas, Números y Símbolos.'; meterText.className = 'text-info mt-1 d-block fw-bold'; }
            }

            // Highlight errores
            document.getElementById('u_nombre').classList.toggle('input-error', !userValido && user.length > 0);
            document.getElementById('u_correo').classList.toggle('input-error', !correoValido && correo.length > 0);

            var esEdicion = document.getElementById('u_accion').value === 'editar';
            if (esEdicion && pass === '') { btn.disabled = !userValido || !pregRespOk; return; }
            btn.disabled = !userValido || !correoValido || !pregRespOk;
        }

        function editarUsuario(data) {
            document.getElementById('u_accion').value = "editar";
            document.getElementById('u_id_edit').value = data.id_usuario;
            document.getElementById('modalTitle').innerText = "EDITAR USUARIO";
            document.getElementById('u_nombre').value = data.usuario;
            document.getElementById('u_correo').value = data.correo || "";
            document.getElementById('u_pass').value = "";
            document.getElementById('u_pass').required = false;
            document.getElementById('passHelp').style.display = "block";

            document.getElementById('u_pass').type = "password";
            document.getElementById('toggleIcon').className = "bi bi-eye-slash-fill text-secondary";

            document.getElementById('meter-fill').style.width = '0%';
            document.getElementById('btn-user-submit').disabled = false;

            const selectRol = document.getElementById('u_rol');
            selectRol.value = data.id_rol;
            const esPropio = (data.id_usuario == window.JV_CONFIG.c0);
            selectRol.disabled = esPropio;

            const selectStatus = document.getElementById('u_status');
            if (selectStatus) selectStatus.value = data.status || 'Activo';

            const selectPreg = document.getElementById('u_preg');
            if (selectPreg) {
                selectPreg.value = data.pregunta_seguridad || '';
            }
            const inputResp = document.getElementById('u_resp');
            if (inputResp) inputResp.value = '';

            modalU.show();
        }

        function confirmarToggle(id, nombre, accion) {
            const esSuspender = (accion === 'suspender');
            Swal.fire({
                title: esSuspender ? '¿SUSPENDER USUARIO?' : '¿REACTIVAR USUARIO?',
                text: esSuspender ? `El usuario ${nombre} ya no podrá acceder al sistema.` : `Se restaurará el acceso al sistema para ${nombre}.`,
                icon: esSuspender ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonColor: esSuspender ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: esSuspender ? 'SÍ, SUSPENDER' : 'SÍ, ACTIVAR',
                cancelButtonText: 'CANCELAR',
                background: '#fff',
                color: '#212529'
            }).then((result) => {
                if (result.isConfirmed) jvPost({ toggle_status: id, csrf_token: window.JV_CONFIG.c1 });
            });
        }
    

        // Sincronizar main-wrapper con sidebar
        const observer = new MutationObserver(() => {
            if (document.body.classList.contains('sidebar-open')) {
                mainWrapper.classList.add('sidebar-open');
            } else {
                mainWrapper.classList.remove('sidebar-open');
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    

    document.querySelectorAll('.flash-auto').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
    });
    document.querySelectorAll('#formUsuario input, #formUsuario select, #formUsuario textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
        el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
    });
    
