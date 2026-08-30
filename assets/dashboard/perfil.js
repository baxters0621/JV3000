
// ==========================================
// PERFIL — JS DEL MÓDULO
// ==========================================

// Toggle ver contraseña
function togglePass(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// Permitir solo dígitos en el PIN de emergencia
(function() {
    var pin = document.getElementById('perfil_pin');
    var pinConfirm = document.getElementById('perfil_pin_confirm');
    ['perfil_pin', 'perfil_pin_confirm'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function() {
            el.value = el.value.replace(/\D/g, '').slice(0, 6);
        });
    });
    if (pin) {
        pin.addEventListener('input', function() {
            if (!pinConfirm) return;
            // Si se limpia el PIN, también se limpia la confirmación
            if (pin.value === '') pinConfirm.value = '';
        });
    }
})();

// Confirmación antes de guardar los cambios del perfil
(function() {
    var form = document.getElementById('formPerfil');
    if (!form) return;

    // Normaliza el nombre de usuario mientras se escribe: solo minúsculas,
    // números y guiones bajos; espacios se convierten en guion bajo.
    var fUser = document.getElementById('perfil_usuario');
    if (fUser) {
        fUser.addEventListener('input', function() {
            var raw = fUser.value;
            var norm = raw.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '').slice(0, 20);
            if (raw !== norm) fUser.value = norm;
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (fUser) {
            var u = fUser.value.trim();
            if (u.length < 4) {
                Swal.fire({ title: 'Usuario muy corto', text: 'El nombre de usuario debe tener MÍN 4 caracteres.', icon: 'error', confirmButtonColor: '#DC2626' });
                return;
            }
            if (u.length > 20) {
                Swal.fire({ title: 'Usuario muy largo', text: 'El nombre de usuario debe tener MÁX 20 caracteres.', icon: 'error', confirmButtonColor: '#DC2626' });
                return;
            }
            if (!/^[a-zA-Z0-9_]+$/.test(u)) {
                Swal.fire({ title: 'Usuario inválido', text: 'Solo se permiten letras, números y guiones bajos.', icon: 'error', confirmButtonColor: '#DC2626' });
                return;
            }
        }

        Swal.fire({
            title: '¿Está seguro de estos cambios?',
            text: 'Se actualizarán los datos de tu cuenta y pregunta de seguridad.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4F46E5',
            cancelButtonColor: '#CED4DA',
            confirmButtonText: 'SÍ, GUARDAR',
            cancelButtonText: 'NO, VOLVER',
            background: '#fff',
            color: '#212529'
        }).then(function(r) {
            if (r.isConfirmed) {
                form.submit();
            }
        });
    });
})();

// Auto-ocultar alertas flash (gestionado en diseno.js vía .flash-auto)
const observer = new MutationObserver(function() {
    if (typeof mainWrapper === 'undefined' || !mainWrapper) return;
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
