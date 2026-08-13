// ==========================================
// CONTROL DE PESTAÑA ÚNICA (marcador de sesión)
// ==========================================
// Al cargar cada página MVC se compara el marcador de pestaña guardado en
// sessionStorage con el que genera el layout. Si no coincide, la pestaña fue
// duplicada/reabierta y se cierra la sesión (sendBeacon + redirect al login).
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

// ==========================================
// POST DINÁMICO — envía parámetros como formulario oculto
// ==========================================
// Crea un <form> oculto con inputs para cada parámetro y lo envía con POST.
// Se usa para acciones que requieren método POST desde código JavaScript.
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

// ==========================================
// ESCAPADO HTML — evita inyección en el DOM
// ==========================================
// Convierte los caracteres especiales (&, <, >, comillas) de un texto en sus
// entidades HTML para poder insertarlo de forma segura en la página.
function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// ==========================================
// TOOLBOX AJAX (búsqueda de productos/clientes)
// ==========================================

// Calcula la ruta base relativa hacia la raíz del sistema: los módulos,
// dashboard y login viven en subcarpetas y necesitan subir un nivel.
function jvBasePath() {
    var seg = window.location.pathname.split('/');
    var last = seg[seg.length - 2] || '';
    return (last === 'modules' || last === 'dashboard' || last === 'login') ? '../' : '';
}

// Petición GET AJAX contra un endpoint del sistema (marca X-Requested-With).
// Ignora parámetros vacíos y devuelve un JSON vacío si la petición falla.
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

// Busca productos por nombre/código vía el endpoint AJAX de productos.
function jvBuscarProductos(params, cb) {
    jvApiGet('includes/ajax/productos_buscar.php', params || {}, cb);
}

// Busca clientes vía el endpoint AJAX de clientes.
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

// Limpia todas las marcas de error (bordes rojos y mensajes) de la página.
function limpiarErrores() {
    document.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
    document.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
}

// Marca un campo como inválido: añade borde rojo y, si hay mensaje,
// inserta un <small> con el texto del error debajo del campo.
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
