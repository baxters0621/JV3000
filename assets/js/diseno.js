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
        navigator.sendBeacon('logout.php?action=tab_closed', '1');
        window.location.replace('login.php?error=expired');
        return;
    }
    sessionStorage.setItem('jv_tab', marker);
})();

function jvPost(params) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.pathname;
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
