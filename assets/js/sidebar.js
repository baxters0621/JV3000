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
    if (backdrop) backdrop.classList.add('visible');
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

// Cerrar sidebar en móvil al hacer clic en un enlace
document.querySelectorAll('.sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) cerrarSidebar();
    });
});

// Imprimir reporte
function imprimirReporte(e) {
    e.preventDefault();
    window.location.href = (window.JV_CONFIG && window.JV_CONFIG.prefijo ? window.JV_CONFIG.prefijo : '') + 'modules/reporte_inventario.php';
}
