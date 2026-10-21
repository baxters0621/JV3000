
// ==========================================
// PERFIL — JS DEL MÓDULO
// ==========================================

document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
    button.addEventListener('click', function() {
        var inputId = button.getAttribute('aria-controls');
        var input = inputId ? document.getElementById(inputId) : null;
        var icon = button.querySelector('i');
        if (!input || !icon) return;

        // Ojo abierto = texto visible; ojo tachado = oculto.
        var mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(mostrar));
        button.setAttribute('aria-label', (mostrar ? 'Ocultar ' : 'Mostrar ') + button.dataset.passwordLabel);
        icon.className = mostrar ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

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

        var passActual = document.getElementById('perfil_password_actual');
        var passNueva = document.getElementById('perfil_password_nueva');
        if (passNueva && passActual && passNueva.value.trim() !== '' && passActual.value.trim() === '') {
            Swal.fire({
                title: 'Falta la contraseña actual',
                text: 'Para cambiar la contraseña debes escribir tu contraseña actual.',
                icon: 'warning',
                confirmButtonColor: '#4F46E5'
            });
            passActual.focus();
            return;
        }

        Swal.fire({
            title: '¿Está seguro de estos cambios?',
            text: 'Se actualizarán los datos de tu cuenta.',
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
