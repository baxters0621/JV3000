<?php

/** @var string $manual_html Contenido del manual convertido a HTML. */
?>
<!-- GUÍA DE USO -->
<div class="manual-container">

    <a href="<?php echo BASE_PATH; ?>dashboard/index.php" class="manual-back">
        <i class="bi bi-arrow-left"></i> VOLVER AL PANEL
    </a>

    <div class="manual-header">
        <div class="manual-header-icon">
            <i class="bi bi-book-half"></i>
        </div>
        <div>
            <h1>GUÍA DE USO</h1>
            <p>Paso a paso para que todo funcione correctamente</p>
        </div>
    </div>

    <?php echo $manual_html; ?>

</div>