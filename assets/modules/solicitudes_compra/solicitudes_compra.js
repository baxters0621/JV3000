// ==========================================
// SOLICITUDES DE REPOSICIÓN — JS DEL MÓDULO
// ==========================================

// Cancelar solicitud pendiente
// Pide confirmación para anular una solicitud de compra y la envía al servidor; recarga la página al terminar.
function confirmarCancelar(id) {
    Swal.fire({
        title: '¿CANCELAR SOLICITUD?',
        text: 'La solicitud quedará anulada.',
        icon: 'warning',
        showCancelButton: true,
        background: '#fff',
        color: '#212529',
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'SÍ, CANCELAR',
        cancelButtonText: 'VOLVER'
    }).then(r => {
        if (r.isConfirmed) {
            const fd = new FormData();
            fd.append('csrf_token', window.JV_CONFIG.c0);
            fd.append('accion_cancelar_solicitud', '1');
            fd.append('id_solicitud', id);
            fetch((window.JV_BASE || '') + 'index.php?url=solicitudes/cancelar', { method: 'POST', body: fd })
                .then(() => { window.location.reload(); })
                .catch(() => { window.location.reload(); });
        }
    });
}
