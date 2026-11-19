<?php
// ==========================================
// VISTA: 403 — Acceso denegado
// ==========================================
// Página autónoma: no usa el layout para no
// depender de la sesión ni de la base de datos.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo htmlspecialchars($titulo ?? '403 — Acceso denegado', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #0f1a2e;
        }
        main {
            width: 100%;
            max-width: 460px;
            padding: 40px 32px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 48px rgba(15, 26, 46, 0.12);
            text-align: center;
        }
        .codigo {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1;
            color: #dc3545;
            letter-spacing: -2px;
        }
        h1 { margin: 12px 0 8px; font-size: 1.4rem; }
        p { margin: 0 0 24px; color: #475569; line-height: 1.5; }
        a {
            display: inline-block;
            padding: 11px 22px;
            border-radius: 10px;
            background: #0f1a2e;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover { background: #1e293b; }
        a:focus-visible { outline: 3px solid #0f766e; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <div class="codigo">403</div>
        <h1>Acceso denegado</h1>
        <p>Esta sección es exclusiva del Administrador de JV3000.</p>
        <a href="<?php echo BASE_PATH; ?>dashboard/index.php">Volver al panel</a>
    </main>
</body>
</html>
