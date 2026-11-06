
// Historial de movimientos: filtros y paginación (sin lógica propia).
// Las alertas flash se auto-ocultan desde diseno.js (clase .flash-auto).

const observer = new MutationObserver(function() {
    if (typeof mainWrapper === 'undefined' || !mainWrapper) return;
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });

// Detalle de la operación: completa el modal con los datos visibles de la fila.
const modalDetalleOperacion = document.getElementById('modalDetalleOperacion');
if (modalDetalleOperacion) {
    modalDetalleOperacion.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        const fila = boton ? boton.closest('tr') : null;
        if (!fila) return;

        const leer = function (selector) {
            const nodo = fila.querySelector(selector);
            return nodo ? nodo.textContent.trim().replace(/\s+/g, ' ') : '';
        };
        const productos = Array.from(fila.querySelectorAll('.historial-lista-productos li'))
            .map(function (item) { return item.textContent.trim(); })
            .filter(Boolean)
            .join('\n');
        const estados = Array.from(fila.querySelectorAll('.historial-estado-grupo .historial-estado, .historial-estado-grupo .historial-estado-secundario'))
            .map(function (badge) { return badge.textContent.trim().replace(/\s+/g, ' '); })
            .filter(Boolean)
            .join(' · ');
        const usuario = leer('.historial-usuario');
        const fecha = leer('.historial-fecha');

        const asignar = function (clave, valor) {
            const destino = modalDetalleOperacion.querySelector('[data-detalle="' + clave + '"]');
            if (destino) destino.textContent = valor;
        };

        asignar('operacion', [leer('.historial-tipo'), leer('.historial-referencia')].filter(Boolean).join(' · '));
        asignar('contraparte', leer('.historial-contraparte'));
        asignar('productos', productos);
        asignar('registro', [usuario, fecha].filter(Boolean).join(' · '));
        asignar('estado', estados);
        asignar('total', leer('.historial-total'));
    });
}

// Presentación del modal: icono y color del total según compra o venta.
const modalDetalleVisual = document.getElementById('modalDetalleOperacion');
if (modalDetalleVisual) {
    modalDetalleVisual.addEventListener('show.bs.modal', function (evento) {
        const fila = evento.relatedTarget ? evento.relatedTarget.closest('tr') : null;
        const esCompra = fila ? fila.classList.contains('historial-tipo-compra') : false;
        const icono = document.getElementById('modalDetalleIcono');
        const total = modalDetalleVisual.querySelector('[data-detalle="total"]');

        if (icono) {
            icono.className = esCompra ? 'bi bi-box-seam' : 'bi bi-bag-check';
        }
        if (total) {
            total.classList.toggle('text-success', esCompra);
            total.classList.toggle('text-danger', !esCompra);
        }
    });
}
