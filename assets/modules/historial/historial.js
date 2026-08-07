
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

// ==========================================
// TOOLTIP GRANDE (texto completo del detalle)
// ==========================================
(function() {
    var tip = null;
    var tipTimer = null;

    function mostrarTip(e, texto) {
        if (!texto) return;
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'jv-tooltip';
            document.body.appendChild(tip);
        }
        tip.textContent = texto;
        tip.classList.add('jv-tooltip-visible');
        posicionarTip(e);
    }

    function posicionarTip(e) {
        if (!tip) return;
        var pad = 16;
        var x = e.clientX + pad;
        var y = e.clientY + pad;
        var r = tip.getBoundingClientRect();
        if (x + r.width > window.innerWidth - 8) x = e.clientX - r.width - pad;
        if (y + r.height > window.innerHeight - 8) y = e.clientY - r.height - pad;
        tip.style.left = Math.max(8, x) + 'px';
        tip.style.top = Math.max(8, y) + 'px';
    }

    function ocultarTip() {
        if (tipTimer) window.clearTimeout(tipTimer);
        tipTimer = window.setTimeout(function() {
            if (tip) tip.classList.remove('jv-tooltip-visible');
        }, 80);
    }

    document.addEventListener('mouseover', function(e) {
        var t = e.target.closest('[data-tooltip]');
        if (t) {
            window.clearTimeout(tipTimer);
            mostrarTip(e, t.dataset.tooltip);
        }
    });
    document.addEventListener('mousemove', function(e) {
        if (tip && tip.classList.contains('jv-tooltip-visible')) posicionarTip(e);
    });
    document.addEventListener('mouseout', function(e) {
        if (e.target.closest('[data-tooltip]')) ocultarTip();
    });
})();

const observer = new MutationObserver(function() {
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });

