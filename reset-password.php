<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a, #111827);
            font-family: Arial, sans-serif;
            color: #e5e7eb;
        }
        .reset-card {
            width: min(92vw, 420px);
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.45);
            padding: 28px 24px;
        }
        h2 {
            margin: 0 0 18px;
            text-align: center;
            font-size: 1.7rem;
        }
        .field {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
            font-weight: 600;
        }
        input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #475569;
            background: #0b1120;
            color: #f8fafc;
            padding: 12px 14px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #22c55e);
            color: white;
            font-weight: 700;
            padding: 12px 14px;
            cursor: pointer;
            margin-top: 8px;
        }
        .message {
            margin-top: 16px;
            min-height: 24px;
            font-size: 0.95rem;
            text-align: center;
        }
        .message.error { color: #fca5a5; }
        .message.success { color: #86efac; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: #93c5fd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <h2>Restablecer contraseña</h2>

        <form id="formResetPassword">
            <div class="field">
                <label for="newPassword">Nueva contraseña</label>
                <input type="password" id="newPassword" name="newPassword" placeholder="Mínimo 6 caracteres" required>
            </div>

            <div class="field">
                <label for="confirmPassword">Confirmar contraseña</label>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Repetir contraseña" required>
            </div>

            <button type="submit">Guardar nueva contraseña</button>
        </form>

        <div id="resetMessage" class="message"></div>
        <a href="index.html" class="back-link">Volver al login</a>
    </div>

    <script src="js/reset-password.js"></script>
</body>
</html>
