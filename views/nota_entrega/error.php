<?php
// ==========================================
// VISTA: Error de Preview / Nota
// ==========================================
// Se muestra cuando el token del preview no existe o ha
// caducado, o cuando la nota solicitada no fue encontrada.
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota no disponible | JV3000 C.A.</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f1f5f9;
            font-family: system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 48px 40px;
            text-align: center;
            max-width: 440px;
            width: 100%;
        }
        .error-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        .error-box h1 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin: 0 0 10px;
        }
        .error-box p {
            color: #475569;
            font-size: .95rem;
            line-height: 1.6;
            margin: 0 0 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .9rem;
            text-decoration: none;
            background: #dc2626;
            color: #fff;
            transition: .2s;
        }
        .btn:hover {
            background: #b91c1c;
        }
    </style>
</head>

<body>
    <div class="error-box">
        <div class="error-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <h1>Nota no disponible</h1>
        <p>El preview o la nota solicitada no existe o ya fue procesada. Cierre esta ventana y genere la venta nuevamente.</p>
        <a class="btn" href="<?php echo BASE_PATH; ?>index.php?url=salidas">← IR A VENTAS</a>
    </div>
</body>

</html>
