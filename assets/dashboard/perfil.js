
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

    form.addEventListener('submit', function(e) {
        e.preventDefault();
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

// Auto-ocultar alertas flash
(function() {
    var alerts = document.querySelectorAll('.alert-jv');
    for (var i = 0; i < alerts.length; i++) {
        (function(a) {
            setTimeout(function() {
                a.style.transition = 'opacity 0.6s';
                a.style.opacity = '0';
                setTimeout(function() { a.remove(); }, 600);
            }, 4000);
        })(alerts[i]);
    }
})();
const observer = new MutationObserver(function() {
    if (typeof mainWrapper === 'undefined' || !mainWrapper) return;
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
