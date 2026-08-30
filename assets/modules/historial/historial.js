
// Historial de movimientos: filtros y paginación (sin lógica propia).
// Las alertas flash se auto-ocultan desde diseno.js (clase .flash-auto).

const observer = new MutationObserver(function() {
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });

