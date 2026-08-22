
// ==========================================
// COLABORADORES — JS DEL MÓDULO
// ==========================================

// Suspender / Reactivar usuario
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
        if (result.isConfirmed) jvPost({ toggle_status: id, csrf_token: window.JV_CONFIG.csrfToken });
    });
}

// Sincronizar main-wrapper con sidebar
const observer = new MutationObserver(() => {
    if (typeof mainWrapper === 'undefined' || !mainWrapper) return;
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
