// ==========================================
// DEVOLUCIONES — Lógica del módulo
// ==========================================

let ventaSeleccionada = null;

function seleccionarVenta(idSalida) {
    document.getElementById('dev_id_salida').value = idSalida;
    document.getElementById('panelDevolucion').style.display = 'block';
    document.getElementById('devVentaTitulo').textContent = 'Cargando venta...';

    fetch('index.php?url=devoluciones/detalle&id_salida=' + idSalida)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.venta) {
                alert(data.error || 'Error al cargar la venta');
                cancelarDevolucion();
                return;
            }

            ventaSeleccionada = data.venta;
            document.getElementById('devVentaTitulo').textContent =
                'Devolución de Venta ' + (ventaSeleccionada.nro_factura_manual || '') +
                ' — ' + (ventaSeleccionada.cliente || 'Sin cliente');

            renderProductos(ventaSeleccionada.detalles);

            // Scroll al panel
            document.getElementById('panelDevolucion').scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error de conexión al cargar la venta.');
            cancelarDevolucion();
        });
}

function renderProductos(detalles) {
    const tbody = document.getElementById('devProductosBody');
    tbody.innerHTML = '';

    if (!detalles || detalles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--jv-text-muted);padding:24px;">Esta venta no tiene productos.</td></tr>';
        return;
    }

    detalles.forEach((det, idx) => {
        const lotes = det.lotes || [];
        let optionsLotes = '<option value="0">Sin lote específico</option>';
        lotes.forEach(l => {
            optionsLotes += `<option value="${l.id_lote}">Lote #${l.id_lote} — ${l.cantidad_restante} uds${l.fecha_vencimiento ? ' — vence: ' + l.fecha_vencimiento : ''}</option>`;
        });

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div style="font-weight:600;">${escHtml(det.nombre_producto)}</div>
                <div style="font-size:.75rem;color:var(--jv-text-muted);">${escHtml(det.sku)}</div>
            </td>
            <td class="text-center">${parseInt(det.cantidad)}</td>
            <td class="text-center">${parseInt(det.stock_actual)}</td>
            <td>
                <input type="number" min="0" max="${parseInt(det.cantidad)}" value="0"
                       class="form-control input-jv dev-cantidad"
                       data-idx="${idx}" data-max="${parseInt(det.cantidad)}"
                       style="width:80px;text-align:center;"
                       oninput="validarCantidad(this)">
            </td>
            <td>
                <select class="form-select input-jv dev-lote" data-idx="${idx}" style="font-size:.85rem;">
                    ${optionsLotes}
                </select>
            </td>
            <td class="text-end">$${parseFloat(det.precio_venta).toFixed(2)}</td>
        `;
        tbody.appendChild(tr);
    });

    actualizarResumen();
}

function validarCantidad(el) {
    const max = parseInt(el.dataset.max);
    let val = parseInt(el.value) || 0;
    if (val < 0) val = 0;
    if (val > max) val = max;
    el.value = val;
    actualizarResumen();
}

function actualizarResumen() {
    const cantidades = document.querySelectorAll('.dev-cantidad');
    let total = 0;
    cantidades.forEach(el => {
        total += parseInt(el.value) || 0;
    });
    document.getElementById('devResumen').textContent = total > 0
        ? `${total} unidad(es) seleccionada(s) para devolver`
        : '';
}

function cancelarDevolucion() {
    document.getElementById('panelDevolucion').style.display = 'none';
    document.getElementById('dev_id_salida').value = '';
    document.getElementById('dev_productos_data').value = '';
    document.getElementById('dev_motivo').value = '';
    document.getElementById('devProductosBody').innerHTML = '';
    ventaSeleccionada = null;
    document.getElementById('devResumen').textContent = '';
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formDevolucion');
    if (form) {
        form.addEventListener('submit', function(e) {
            const cantidades = document.querySelectorAll('.dev-cantidad');
            const lotes = document.querySelectorAll('.dev-lote');
            const productos = [];

            cantidades.forEach((el, i) => {
                const cant = parseInt(el.value) || 0;
                if (cant > 0 && ventaSeleccionada && ventaSeleccionada.detalles) {
                    const det = ventaSeleccionada.detalles[i];
                    const loteSelect = lotes[i];
                    productos.push({
                        id_producto: parseInt(det.id_producto),
                        cantidad: cant,
                        id_lote: parseInt(loteSelect ? loteSelect.value : 0),
                        precio_venta: parseFloat(det.precio_venta)
                    });
                }
            });

            if (productos.length === 0) {
                e.preventDefault();
                alert('Seleccione al menos un producto con cantidad > 0.');
                return;
            }

            document.getElementById('dev_productos_data').value = JSON.stringify(productos);

            const btn = document.getElementById('btnDevolver');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>REGISTRANDO...';
        });
    }
});
