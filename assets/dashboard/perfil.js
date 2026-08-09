
// ==========================================
// PERFIL — JS DEL MÓDULO
// ==========================================

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
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
