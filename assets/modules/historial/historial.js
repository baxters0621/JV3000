
// Historial de movimientos: filtros y paginación (sin lógica propia).
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

