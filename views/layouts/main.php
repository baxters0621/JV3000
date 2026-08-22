<?php
// ==========================================
// LAYOUT PRINCIPAL MVC
// ==========================================
// Estructura común a todas las vistas.
// La vista específica se inyecta en $__contenido.
if (!isset($__view)) {
    die('Vista no definida.');
}
$__base_assets = BASE_PATH . 'assets/';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include APP_ROOT . '/includes/diseno.php'; ?>
    <title><?php echo htmlspecialchars($titulo ?? 'JV3000 C.A.'); ?></title>
    <?php if (!empty($css_extra)): foreach ((array)$css_extra as $css):
            $cssUrl = preg_match('#^(https?:)?//#i', $css) ? $css : $__base_assets . ltrim($css, '/');
    ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach;
    endif; ?>
</head>

<body>
    <?php include APP_ROOT . '/includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 <?php echo $wrapper_class ?? ''; ?>">
            <?php $this->renderRaw($__view, $this->viewData); ?>
        </div>
    </div>

    <script src="<?php echo $__base_assets; ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $__base_assets; ?>js/sweetalert2.all.min.js"></script>
    <script>
        window.JV_CONFIG = window.JV_CONFIG || {};
        window.JV_CONFIG.csrfToken = '<?php echo $csrf ?? ''; ?>';
        <?php if (!empty($js_config) && is_array($js_config)): foreach ($js_config as $k => $v): ?>
                window.JV_CONFIG.<?php echo preg_replace('/[^A-Za-z0-9_]/', '', (string)$k); ?> = <?php echo json_encode($v, JSON_UNESCAPED_UNICODE); ?>;
        <?php endforeach;
        endif; ?>
    </script>
    <?php if (!empty($js_extra)): foreach ((array)$js_extra as $js):
            $jsUrl = preg_match('#^(https?:)?//#i', $js) ? $js : $__base_assets . ltrim($js, '/');
    ?>
            <script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach;
    endif; ?>
</body>

</html>