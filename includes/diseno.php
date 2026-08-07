<?php
// ==========================================
// CONFIGURACIÓN DE DISEÑO
// ==========================================
$base_assets = BASE_PATH . 'assets/';
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
    window.JV_CONFIG = window.JV_CONFIG || {};
    window.JV_CONFIG.tab = { marker: <?php echo json_encode($marker); ?>, fresh: <?php echo $fresh ? 'true' : 'false'; ?>, base: <?php echo json_encode(BASE_PATH); ?> };
</script>
<?php endif; ?>

<!-- ACCIONES POST SEGURAS -->
<script src="<?php echo $base_assets; ?>js/diseno.js"></script>

<!-- FUENTES -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Estilos base -->
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/bootstrap.min.css?v=4">
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/bootstrap-icons.css?v=4">

<link rel="stylesheet" href="<?php echo $base_assets; ?>css/diseno.css?v=4">

<!-- FAVICON -->
<link rel="icon" type="image/svg+xml" href="<?php echo $base_assets; ?>img/favicon.svg?v=1">
