<?php

/** @var string $manual_html Contenido del manual convertido a HTML. */
/** @var array<int, array{nivel:int, titulo:string, id:string, num:?int}> $manual_toc Índice de contenidos. */
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
        <div class="manual-header-tx">
            <h1>GU&Iacute;A DE USO</h1>
            <p>Paso a paso para que todo funcione correctamente</p>
        </div>
        <span class="manual-header-badge">TODOS LOS ROLES</span>
    </div>

    <div class="manual-layout">
        <?php if (!empty($manual_toc)): ?>
            <aside class="manual-toc-wrap">
                <nav class="manual-toc" id="manualToc" aria-label="Índice de la guía">
                    <div class="manual-toc-titulo"><i class="bi bi-list-ul me-1"></i>CONTENIDO</div>
                    <?php foreach ($manual_toc as $item): ?>
                        <a class="manual-toc-item manual-toc-nivel-<?php echo $item['nivel']; ?>" href="#<?php echo $item['id']; ?>" data-ancla="<?php echo $item['id']; ?>">
                            <?php if ($item['num'] !== null): ?>
                                <span class="manual-toc-num"><?php echo $item['num']; ?></span>
                            <?php endif; ?>
                            <span class="manual-toc-tx"><?php echo htmlspecialchars($item['titulo']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
        <?php endif; ?>

        <article class="manual-body">
            <?php echo $manual_html; ?>
        </article>
    </div>

</div>