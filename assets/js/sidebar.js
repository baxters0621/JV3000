// JAVASCRIPT: TOGGLE DEL SIDEBAR
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const backdrop = document.getElementById('sidebarBackdrop');
const mainWrapper = document.getElementById('mainWrapper');
let sidebarOpen = false;

function abrirSidebar() {
    sidebarOpen = true;
    sidebar.classList.add('open');
    document.body.classList.add('sidebar-open');
    if (backdrop) backdrop.classList.toggle('visible', window.innerWidth <= 768);
    if (mainWrapper) mainWrapper.style.marginLeft = '260px';
}

function cerrarSidebar() {
    sidebarOpen = false;
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    if (backdrop) backdrop.classList.remove('visible');
    if (mainWrapper) mainWrapper.style.marginLeft = '0';
}

// Abrir por defecto en escritorio
if (window.innerWidth > 768) abrirSidebar();

// Evento del botón toggle
toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebarOpen ? cerrarSidebar() : abrirSidebar();
});

// Cerrar al hacer clic fuera (móvil) o en backdrop
if (backdrop) backdrop.addEventListener('click', cerrarSidebar);

window.addEventListener('resize', () => {
    if (backdrop && sidebarOpen) {
        backdrop.classList.toggle('visible', window.innerWidth <= 768);
    }
});

// Cerrar sidebar en móvil al hacer clic en un enlace
document.querySelectorAll('.sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) cerrarSidebar();
    });
});

// ══════════════════════════════════════════════
// GRUPOS COLAPSABLES DEL MENÚ (ramas principales)
// ══════════════════════════════════════════════
// Cada rama (Compras / Inventario / Salidas / Análisis / Control) se puede
// expandir o contraer con su flechita. La preferencia del usuario se guarda
// en localStorage; además, la rama que contenga el módulo activo se abre
// automáticamente al cargar la página.
(function () {
    const STORAGE_KEY = 'jv_sidebar_grupos';
    const grupos = Array.prototype.slice.call(document.querySelectorAll('.sidebar .nav-group'));

    if (!grupos.length) return;

    // Restaurar preferencias guardadas. La rama que contiene el módulo activo
    // SIEMPRE se muestra abierta (el PHP ya le puso .open al servir la página);
    // la preferencia guardada solo se aplica a las ramas sin módulo activo.
    let guardados = {};
    try {
        guardados = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch (e) {
        guardados = {};
    }
    grupos.forEach(grupo => {
        const tieneActivo = grupo.querySelector('.nav-item.active') !== null;
        if (tieneActivo) return; // respeta el .open que marcó el servidor
        const clave = grupo.querySelector('.nav-group-toggle')?.innerText.trim() || '';
        if (clave && Object.prototype.hasOwnProperty.call(guardados, clave)) {
            grupo.classList.toggle('open', guardados[clave]);
        }
    });

    // Toggle con la flechita (o clic en toda la cabecera)
    grupos.forEach(grupo => {
        const toggle = grupo.querySelector('.nav-group-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const abrir = !grupo.classList.contains('open');
            grupo.classList.toggle('open', abrir);
            toggle.setAttribute('aria-expanded', abrir ? 'true' : 'false');
            // Persistir preferencia (marca explícita: usuario abrió o cerró la rama)
            const clave = toggle.innerText.trim();
            if (clave) {
                guardados[clave] = abrir;
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(guardados));
                } catch (err) { /* almacenamiento no disponible */ }
            }
        });
    });
})();

// Imprimir reporte
function imprimirReporte(e) {
    e.preventDefault();
    const prefijo = window.JV_BASE || (window.JV_CONFIG && window.JV_CONFIG.prefijo) || '';
    window.location.href = prefijo + 'index.php?url=reporte_inventario';
}
