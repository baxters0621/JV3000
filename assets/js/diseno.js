// ==========================================
// CONTROL DE PESTAÑA ÚNICA (marcador de sesión)
// ==========================================
// Al cargar cada página MVC se compara el marcador de pestaña guardado en
// sessionStorage con el que genera el layout. Si no coincide, la pestaña fue
// duplicada/reabierta y se cierra la sesión (sendBeacon + redirect al login).
(function(){
    var tabConfiguration = window.JV_CONFIG && window.JV_CONFIG.tab;
    if (!tabConfiguration) return;
    var tabMarker = tabConfiguration.marker;
    var stored = sessionStorage.getItem('jv_tab');
    if (tabConfiguration.fresh) {
        sessionStorage.setItem('jv_tab', tabMarker);
        return;
    }
    if (stored !== tabMarker) {
        navigator.sendBeacon((tabConfiguration.base || '') + 'login/logout.php?action=tab_closed', '1');
        window.location.replace((tabConfiguration.base || '') + 'login/login.php?error=expired');
        return;
    }
    sessionStorage.setItem('jv_tab', tabMarker);
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
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function(character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
}

// ==========================================
// TOOLBOX AJAX (búsqueda de productos/clientes)
// ==========================================

// Calcula la ruta base relativa hacia la raíz del sistema: los módulos,
// dashboard y login viven en subcarpetas y necesitan subir un nivel.
function jvBasePath() {
    var pathSegments = window.location.pathname.split('/');
    var parentFolder = pathSegments[pathSegments.length - 2] || '';
    return (parentFolder === 'modules' || parentFolder === 'dashboard' || parentFolder === 'login') ? '../' : '';
}

// Petición GET AJAX contra un endpoint del sistema (marca X-Requested-With).
// Ignora parámetros vacíos y devuelve un JSON vacío si la petición falla.
function jvApiGet(endpoint, params, cb) {
    var queryParts = [];
    var parameterName;
    for (parameterName in (params || {})) {
        if (Object.prototype.hasOwnProperty.call(params, parameterName) && params[parameterName] !== '' && params[parameterName] !== null && params[parameterName] !== undefined) {
            queryParts.push(encodeURIComponent(parameterName) + '=' + encodeURIComponent(params[parameterName]));
        }
    }
    fetch(jvBasePath() + endpoint + (queryParts.length ? '?' + queryParts.join('&') : ''), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(response) { return response.json(); })
    .then(function(responseData) { cb(responseData); })
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
    document.querySelectorAll('.input-error').forEach(function(inputElement) { inputElement.classList.remove('input-error'); });
    document.querySelectorAll('.field-error').forEach(function(errorElement) { errorElement.remove(); });
}

// Marca un campo como inválido: añade borde rojo y, si hay mensaje,
// inserta un <small> con el texto del error debajo del campo.
function marcarError(inputElement, errorMessage) {
    inputElement.classList.add('input-error');
    if (errorMessage && inputElement.id) {
        var errorElement = document.getElementById(inputElement.id + '_err');
        if (!errorElement) {
            errorElement = document.createElement('small');
            errorElement.id = inputElement.id + '_err';
            errorElement.className = 'field-error';
            errorElement.style.cssText = 'color:#DC2626;font-size:.7rem;margin-top:2px;display:block;';
            inputElement.parentNode.appendChild(errorElement);
        }
        errorElement.textContent = errorMessage;
    }
}

// ==========================================
// MENSAJES FLASH AUTO-CERRABLES
// ==========================================
// Cualquier .flash-auto se cierra solo a los 4 segundos, sincronizado con la
// barra de tiempo que dibuja el CSS (::after con animación jvFlashBarra).
// Un MutationObserver vigila el DOM: si algún módulo inyecta una alerta nueva
// en runtime (fetch, innerHTML), también le programa su cierre automático.
(function() {
    function jvProgramarCierre(alerta) {
        if (alerta.dataset.flashListo) return;
        alerta.dataset.flashListo = '1';
        window.setTimeout(function() {
            alerta.classList.add('jv-flash-out');
            window.setTimeout(function() { alerta.remove(); }, 550);
        }, 4000);
    }
    function jvAutoCerrarAlertas(raiz) {
        (raiz || document).querySelectorAll('.flash-auto').forEach(jvProgramarCierre);
    }
    function jvObservarNodos(mutaciones) {
        mutaciones.forEach(function(m) {
            m.addedNodes.forEach(function(nodo) {
                if (nodo.nodeType !== 1) return;
                if (nodo.classList && nodo.classList.contains('flash-auto')) jvProgramarCierre(nodo);
                jvAutoCerrarAlertas(nodo);
            });
        });
    }
    var observador = new MutationObserver(jvObservarNodos);
    observador.observe(document.body || document.documentElement, { childList: true, subtree: true });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { jvAutoCerrarAlertas(); });
    } else {
        jvAutoCerrarAlertas();
    }
})();
