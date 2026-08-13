(function(){
    var cfg = window.JV_CONFIG && window.JV_CONFIG.tab;
    if (!cfg) return;
    var marker = cfg.marker;
    var stored = sessionStorage.getItem('jv_tab');
    if (cfg.fresh) {
        sessionStorage.setItem('jv_tab', marker);
        return;
    }
    if (stored !== marker) {
        navigator.sendBeacon((cfg.base || '') + 'login/logout.php?action=tab_closed', '1');
        window.location.replace((cfg.base || '') + 'login/login.php?error=expired');
        return;
    }
    sessionStorage.setItem('jv_tab', marker);
})();

function jvPost(params, url) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = url || window.location.href;
    form.style.display = 'none';
    var keys = Object.keys(params);
    for (var i = 0; i < keys.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = keys[i];
        input.value = params[keys[i]];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// ==========================================
// TOOLBOX AJAX (búsqueda de productos/clientes)
// ==========================================

function jvBasePath() {
    var seg = window.location.pathname.split('/');
    var last = seg[seg.length - 2] || '';
    return (last === 'modules' || last === 'dashboard' || last === 'login') ? '../' : '';
}

function jvApiGet(endpoint, params, cb) {
    var qs = [];
    var k;
    for (k in (params || {})) {
        if (Object.prototype.hasOwnProperty.call(params, k) && params[k] !== '' && params[k] !== null && params[k] !== undefined) {
            qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
        }
    }
    fetch(jvBasePath() + endpoint + (qs.length ? '?' + qs.join('&') : ''), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { cb(d); })
    .catch(function() { cb({ success: false, items: [] }); });
}

function jvBuscarProductos(params, cb) {
    jvApiGet('includes/ajax/productos_buscar.php', params || {}, cb);
}

function jvBuscarClientes(params, cb) {
    jvApiGet('includes/ajax/clientes_buscar.php', params || {}, cb);
}

// ==========================================
// VALIDACIÓN DE FORMULARIOS (helpers globales)
// ==========================================
// ANTES: cada módulo (categorias, compras, productos,
// proveedores, salidas) traía su propia copia idéntica de
// limpiarErrores()/marcarError(). Se unificaron aquí.
// Uso: marcarError(elemento, 'MENSAJE') marca el campo con
// borde rojo y añade un <small> de error debajo. limpiarErrores()
// borra todas las marcas previas antes de validar de nuevo.

function limpiarErrores() {
    document.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
    document.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
}

function marcarError(el, msg) {
    el.classList.add('input-error');
    if (msg && el.id) {
        var errEl = document.getElementById(el.id + '_err');
        if (!errEl) {
            errEl = document.createElement('small');
            errEl.id = el.id + '_err';
            errEl.className = 'field-error';
            errEl.style.cssText = 'color:#DC2626;font-size:.7rem;margin-top:2px;display:block;';
            el.parentNode.appendChild(errEl);
        }
        errEl.textContent = msg;
    }
}
