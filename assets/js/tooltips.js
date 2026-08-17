// ==========================================
// TOOLTIP GLOBAL COMPARTIDO (jv-tooltip)
// ==========================================
// Un único tooltip reutilizable para toda la app.
// ANTES: cada módulo JS traía su propia copia del trío
//   mostrarTip / posicionarTip / ocultarTip (+ listeners),
//   duplicada en salidas, historial, recepcion, compras,
//   categorias, productos, proveedores y nota_entrega.
// AHORA: este bloque se carga una sola vez (layout MVC +
// nota imprimible) y cualquier elemento con el atributo
// [data-tooltip] recibe el tooltip automáticamente.
// ==========================================

// Estado compartido del tooltip (un solo <div> por página).
let jvTip = null;
let jvTipTimer = null;

/**
 * Muestra el tooltip con el texto dado, creándolo la primera vez.
 * @param {Event} e     Evento (para posicionar junto al cursor).
 * @param {string} texto Contenido del tooltip.
 */
function mostrarTip(e, texto) {
    if (!texto) return;
    if (!jvTip) {
        jvTip = document.createElement('div');
        jvTip.className = 'jv-tooltip';
        document.body.appendChild(jvTip);
    }
    jvTip.textContent = texto;
    jvTip.classList.add('jv-tooltip-visible');
    posicionarTip(e);
}

/**
 * Ubica el tooltip junto al cursor evitando que se salga
 * de la ventana (rota a la izquierda/arriba si no cabe).
 * @param {Event} e Evento con clientX/clientY del cursor.
 */
function posicionarTip(e) {
    if (!jvTip) return;
    const pad = 16; // separación respecto al cursor
    let x = e.clientX + pad;
    let y = e.clientY + pad;
    const r = jvTip.getBoundingClientRect();
    // Si se desborda a la derecha, colócalo a la izquierda del cursor.
    if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
    // Si se desborda abajo, colócalo arriba del cursor.
    if (y + r.height > window.innerHeight - 8) y = e.clientY - r.height - pad;
    jvTip.style.left = Math.max(8, x) + 'px';
    jvTip.style.top = Math.max(8, y) + 'px';
}

/**
 * Oculta el tooltip tras un breve retardo (permite pasar el
 * cursor entre elementos sin parpadeos).
 */
function ocultarTip() {
    if (jvTipTimer) window.clearTimeout(jvTipTimer);
    jvTipTimer = window.setTimeout(function () {
        if (jvTip) jvTip.classList.remove('jv-tooltip-visible');
    }, 80);
}

// Listeners globales: atienden a CUALQUIER [data-tooltip] de la página.
document.addEventListener('mouseover', function (e) {
    const t = e.target.closest('[data-tooltip]');
    if (t) {
        window.clearTimeout(jvTipTimer);
        mostrarTip(e, t.dataset.tooltip);
    }
});
document.addEventListener('mousemove', function (e) {
    if (jvTip && jvTip.classList.contains('jv-tooltip-visible')) posicionarTip(e);
});
document.addEventListener('mouseout', function (e) {
    if (e.target.closest('[data-tooltip]')) ocultarTip();
});