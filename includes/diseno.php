<?php
// ==========================================
// CONFIGURACIÓN DE DISEÑO
// ==========================================
$base_assets = (basename(dirname($_SERVER['PHP_SELF'])) === 'modules') ? '../assets/' : 'assets/';
?>
<!-- META TAGS -->
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- GESTIÓN DE SESIÓN POR PESTAÑA -->
<?php if (isset($_SESSION['id_usuario']) && defined('_TAB_FRESH_LOGIN')):
$marker = strval($_SESSION['tab_marker'] ?? '');
$fresh = constant('_TAB_FRESH_LOGIN');
?>
<script>
(function(){
    var marker = <?php echo json_encode($marker); ?>;
    var stored = sessionStorage.getItem('jv_tab');
    if (<?php echo $fresh ? 'true' : 'false'; ?>) {
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
</script>
<?php endif; ?>

<!-- FUENTES Y ESTILOS BASE -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">

<!-- Estilos base -->
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/bootstrap.min.css?v=2">
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/bootstrap-icons.css?v=2">

<style>
/* ==========================================
   SISTEMA DE DISEÑO BASE
   ========================================== */

/* VARIABLES CSS */
:root {
    /* Colores principales */
    --jv-cyan: #06b6d4;
    --jv-cyan-light: #22d3ee;
    --jv-cyan-dark: #0891b2;
    --jv-verde: #14b8a6;
    --jv-verde-light: #2dd4bf;
    
    /* Colores de fondo */
    --jv-bg-primary: #020617;
    --jv-bg-secondary: #0f172a;
    --jv-bg-card: rgba(15, 23, 42, 0.8);
    --jv-bg-card-light: rgba(30, 41, 59, 0.5);
    
    /* Colores de texto */
    --jv-text-primary: #f8fafc;
    --jv-text-secondary: #94a3b8;
    --jv-text-muted: #64748b;
    
    /* Colores de estado */
    --jv-success: #22c55e;
    --jv-warning: #f59e0b;
    --jv-danger: #ef4444;
    --jv-info: #3b82f6;
    
    /* Bordes */
    --jv-border: rgba(56, 189, 248, 0.15);
    --jv-border-hover: rgba(56, 189, 248, 0.4);
    
    /* Sombras */
    --jv-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.5);
    --jv-shadow-sm: 0 4px 15px -5px rgba(0, 0, 0, 0.3);

    /* Tipografía */
    --jv-font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --jv-font-brand: 'Orbitron', 'Courier New', monospace;

    /* Border radius */
    --jv-radius-sm: 8px;
    --jv-radius: 12px;
    --jv-radius-lg: 16px;
    --jv-radius-xl: 24px;
}

/* text-secondary de Bootstrap muy oscuro (#6c757d) para fondo oscuro */
.text-secondary { color: var(--jv-text-secondary) !important; }

/* RESET Y BASE */
*, *::before, *::after {
    box-sizing: border-box;
}

body {
    background: radial-gradient(circle at center, var(--jv-bg-secondary) 0%, var(--jv-bg-primary) 100%);
    color: var(--jv-text-primary);
    font-family: var(--jv-font-main);
    min-height: 100vh;
    margin: 0;
}

/* BOTONES */
/* ==========================================
   CLASES REUTILIZABLES - BOTONES
   ========================================== */

/* Primario (Cyan) */
.btn-jv-primary {
    background: linear-gradient(135deg, var(--jv-cyan) 0%, var(--jv-cyan-dark) 100%);
    color: var(--jv-bg-primary);
    border: none;
    border-radius: var(--jv-radius);
    padding: 10px 24px;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: var(--jv-shadow-sm);
}

.btn-jv-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(6, 182, 212, 0.4);
    color: var(--jv-bg-primary);
}

.btn-jv-primary:active {
    transform: translateY(0);
}

/* Éxito (Verde) */
.btn-jv-success {
    background: linear-gradient(135deg, var(--jv-success) 0%, #16a34a 100%);
    color: #fff;
    border: none;
    border-radius: var(--jv-radius);
    padding: 10px 24px;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: var(--jv-shadow-sm);
}

.btn-jv-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(34, 197, 94, 0.4);
    color: #fff;
}

/* Peligro (Rojo) */
.btn-jv-danger {
    background: linear-gradient(135deg, var(--jv-danger) 0%, #dc2626 100%);
    color: #fff;
    border: none;
    border-radius: var(--jv-radius);
    padding: 10px 24px;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: var(--jv-shadow-sm);
}

.btn-jv-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(239, 68, 68, 0.4);
    color: #fff;
}

/* Warning (Amarillo) */
.btn-jv-warning {
    background: linear-gradient(135deg, var(--jv-warning) 0%, #d97706 100%);
    color: var(--jv-bg-primary);
    border: none;
    border-radius: var(--jv-radius);
    padding: 10px 24px;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: var(--jv-shadow-sm);
}

.btn-jv-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(245, 158, 11, 0.4);
    color: var(--jv-bg-primary);
}

/* Outline */
.btn-jv-outline {
    background: transparent;
    color: var(--jv-cyan);
    border: 1px solid var(--jv-cyan);
    border-radius: var(--jv-radius);
    padding: 10px 24px;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-jv-outline:hover {
    background: rgba(6, 182, 212, 0.1);
    color: var(--jv-cyan);
    transform: translateY(-2px);
}

/* CARDS */
/* ==========================================
   CLASES REUTILIZABLES - CARDS
   ========================================== */

/* Card base */
.card-jv {
    background: var(--jv-bg-card);
    border: 1px solid var(--jv-border);
    border-radius: var(--jv-radius-lg);
    padding: 24px;
    box-shadow: var(--jv-shadow);
}

.card-jv:hover {
    border-color: var(--jv-border-hover);
}

/* Card widget (para el dashboard) */
.card-widget {
    background: var(--jv-bg-card);
    border: 1px solid var(--jv-border);
    border-radius: var(--jv-radius);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.card-widget:hover {
    border-color: var(--jv-border-hover);
}

/* INPUTS */
/* ==========================================
   CLASES REUTILIZABLES - INPUTS
   ========================================== */

/* Input base dark */
.input-jv {
    background: var(--jv-bg-primary);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--jv-text-primary);
    border-radius: var(--jv-radius);
    padding: 12px 16px;
    transition: all 0.3s ease;
    width: 100%;
}

.input-group .input-jv {
    width: auto;
    flex: 1;
    min-width: 0;
}

.input-jv:focus {
    outline: none;
    border-color: var(--jv-cyan);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
    background: var(--jv-bg-primary);
    color: var(--jv-text-primary);
}

.input-jv::placeholder {
    color: var(--jv-text-muted);
    opacity: 1;
}

input[type="date"].input-jv {
    color-scheme: dark;
}

/* ALERTAS */
/* ==========================================
   CLASES REUTILIZABLES - ALERTAS
   ========================================== */

/* Alerta base */
.alert-jv {
    border-radius: var(--jv-radius);
    padding: 16px 20px;
    border: 1px solid;
    font-weight: 600;
}

/* Éxito */
.alert-jv-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: var(--jv-success);
}

/* Error */
.alert-jv-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: var(--jv-danger);
}

/* Warning */
.alert-jv-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: var(--jv-warning);
}

/* Info */
.alert-jv-info {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.3);
    color: var(--jv-cyan);
}

/* BADGES */
/* ==========================================
   CLASES REUTILIZABLES - BADGES
   ========================================== */

.badge-jv {
    background: rgba(6, 182, 212, 0.1);
    color: var(--jv-cyan);
    border: 1px solid rgba(6, 182, 212, 0.3);
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* Success */
.badge-success {
    background: rgba(34, 197, 94, 0.1);
    color: var(--jv-success);
    border-color: rgba(34, 197, 94, 0.3);
}

/* Warning */
.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--jv-warning);
    border-color: rgba(245, 158, 11, 0.3);
}

/* Info */
.badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
    border-color: rgba(59, 130, 246, 0.3);
}

/* Danger */
.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--jv-danger);
    border-color: rgba(239, 68, 68, 0.3);
}

/* Secondary */
.badge-secondary {
    background: rgba(148, 163, 184, 0.1);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.3);
}

/* SPINNERS / LOADING */
/* ==========================================
   CLASES REUTILIZABLES - SPINNER / LOADING
   ========================================== */

.spinner-jv {
    width: 24px;
    height: 24px;
    border: 3px solid rgba(6, 182, 212, 0.2);
    border-top-color: var(--jv-cyan);
    border-radius: 50%;
    animation: spin-jv 0.8s linear infinite;
}

.spinner-jv-sm {
    width: 16px;
    height: 16px;
    border-width: 2px;
}

.spinner-jv-lg {
    width: 40px;
    height: 40px;
    border-width: 4px;
}

@keyframes spin-jv {
    to { transform: rotate(360deg); }
}

/* TABLAS */
/* ==========================================
   CLASES REUTILIZABLES - TABLAS
   ========================================== */

.table-jv {
    color: var(--jv-text-primary);
    width: 100%;
}

.table-jv thead th {
    color: var(--jv-cyan);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    padding: 18px 16px;
    background: rgba(6, 182, 212, 0.05);
    border: none;
    font-weight: 700;
}

.table-jv tbody td {
    padding: 16px;
    border-bottom: 1px solid rgba(96, 165, 250, 0.08);
    vertical-align: middle;
}

.table-jv tbody tr:hover {
    background: rgba(6, 182, 212, 0.03);
}

/* UTILIDADES */
/* ==========================================
   UTILIDADES
   ========================================== */

.text-jv-cyan { color: var(--jv-cyan); }
.text-jv-success { color: var(--jv-success); }
.text-jv-danger { color: var(--jv-danger); }
.text-jv-warning { color: var(--jv-warning); }
.text-jv-muted { color: var(--jv-text-muted); }
.text-jv-verde { color: var(--jv-verde); }
.text-jv-info { color: var(--jv-info); }

.bg-jv-primary { background-color: var(--jv-bg-primary); }
.bg-jv-card { background-color: var(--jv-bg-card); }

.font-brand {
    font-family: var(--jv-font-brand);
}

.font-bold { font-weight: 700; }
.font-bolder { font-weight: 900; }

.icon-jv {
    font-size: 3rem;
    line-height: 1;
}

.dropdown-menu-jv {
    background: var(--jv-bg-secondary);
    border: 1px solid var(--jv-border);
    border-radius: var(--jv-radius);
}

.navbar-jv {
    background: var(--jv-bg-secondary);
    border-bottom: 1px solid var(--jv-border);
}

.navbar-brand-jv {
    font-size: 1.4rem;
    color: var(--jv-cyan);
}

.navbar-brand-jv-accent {
    color: var(--jv-verde);
}

.user-role-label {
    color: var(--jv-text-muted);
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.modal-content-jv {
    background: var(--jv-bg-secondary);
    border: 1px solid var(--jv-border);
    border-radius: var(--jv-radius-xl);
}

.glass-login {
    background: #0f172a;
    border: 1px solid var(--jv-border);
    border-left: 4px solid var(--jv-cyan);
    border-radius: var(--jv-radius-xl);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
    padding: 40px 36px;
}

.glass-login:hover {
    border-color: var(--jv-border-hover);
}

.label-muted {
    color: var(--jv-text-muted);
}

.navbar-dropdown-inventario .dropdown-toggle { color: var(--jv-cyan); }
.navbar-dropdown-inventario .dropdown-toggle::after { border-top-color: var(--jv-cyan); }
.navbar-dropdown-inventario .dropdown-menu { border-top: 3px solid var(--jv-cyan); }
.navbar-dropdown-inventario .dropdown-item:hover { color: var(--jv-cyan); background: rgba(6,182,212,0.08); }

.navbar-dropdown-operaciones .dropdown-toggle { color: var(--jv-cyan-dark); }
.navbar-dropdown-operaciones .dropdown-toggle::after { border-top-color: var(--jv-cyan-dark); }
.navbar-dropdown-operaciones .dropdown-menu { border-top: 3px solid var(--jv-cyan-dark); }
.navbar-dropdown-operaciones .dropdown-item:hover { color: var(--jv-cyan-dark); background: rgba(8,145,178,0.08); }

.navbar-dropdown-herramientas .dropdown-toggle { color: var(--jv-verde); }
.navbar-dropdown-herramientas .dropdown-toggle::after { border-top-color: var(--jv-verde); }
.navbar-dropdown-herramientas .dropdown-menu { border-top: 3px solid var(--jv-verde); }
.navbar-dropdown-herramientas .dropdown-item:hover { color: var(--jv-verde); background: rgba(20,184,166,0.08); }

.navbar-colaboradores .nav-link { color: var(--jv-info); }
.navbar-colaboradores .nav-link:hover { color: var(--jv-info); }

.navbar-user-name { color: var(--jv-cyan); font-size: 0.8rem; font-weight: 700; }
.navbar-user-role { color: var(--jv-text-muted); font-size: 0.65rem; text-transform: capitalize; }

/* Pulse */
.pulse-jv {
    animation: pulse-animation 2s ease-in-out infinite;
}

@keyframes pulse-animation {
    0% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.5); }
    70% { box-shadow: 0 0 0 10px rgba(6, 182, 212, 0); }
    100% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0); }
}

/* RESPONSIVE */
@media (max-width: 1200px) {
    .kpi-grid { grid-template-columns: repeat(2,1fr); }
    .charts-grid { grid-template-columns: 1fr; }
    .tables-grid { grid-template-columns: 1fr; }
}

/* Medium (max 992px) */
@media (max-width: 992px) {
    body { padding-left: 0 !important; }
    .container-fluid.px-4 { padding-left: 16px !important; padding-right: 16px !important; }
    .row.g-3 > .col-md-3,
    .row.g-3 > .col-md-4,
    .row.g-3 > .col-md-6 { width: 100%; }
    .card-jv { padding: 12px 14px !important; }
    .widget-card { padding: 14px; }
}

/* Tablets (max 768px) */
@media (max-width: 768px) {
    #sidebar { width: 100% !important; }
    body.sidebar-open .main-wrapper { margin-left: 0 !important; }
    .sidebar-toggle-btn { display: flex !important; }
    .page { padding: 16px !important; }
    h1.font-brand { font-size: 1.1rem !important; }
    .input-jv { font-size: 0.8rem; padding: 8px 10px; }
    .btn-jv-primary, .btn-jv-danger, .btn-jv-success,
    .btn-jv-secondary, .btn-jv-warning { padding: 8px 14px; font-size: 0.75rem; }
    .table-jv { font-size: 0.7rem; }
    .table-jv th, .table-jv td { padding: 6px 4px !important; }
    .table-responsive { overflow-x: auto; }
    .codigo-badge { font-size: 0.6rem; padding: 3px 8px; }
    .cant-badge { min-width: 28px; height: 28px; font-size: 0.7rem; }
    .d-flex.align-items-end.gap-3 { flex-direction: column; align-items: stretch !important; }
    .row.g-2 > [class*="col-"] { width: 100%; }
    .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .kpi-card { padding: 14px; }
    .kpi-value { font-size: 1.2rem; }
    .shortcuts-grid { grid-template-columns: 1fr; }
    .filtro-box { padding: 10px; }
    .d-print-flex { flex-direction: column; gap: 16px; }
    .info-row { flex-direction: column; gap: 8px; }
    .totals { width: 100%; }
    .header { flex-direction: column; gap: 12px; }
    .header-right { text-align: left; }
    .firma { flex-direction: column; gap: 16px; align-items: center; }
}

/* Small (max 480px) */
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .table-card { max-height: none; }
    .data-table td { font-size: 0.75rem; padding: 10px 4px !important; }
    .data-table th { font-size: 0.6rem; padding: 8px 4px !important; }
    .stock-badge { font-size: 0.65rem; padding: 3px 8px; }
}
</style>