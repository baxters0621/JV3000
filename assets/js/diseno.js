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
