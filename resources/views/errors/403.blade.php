<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>403 — Sin acceso · DIMAGE</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(52, 82, 255, 0.08);
            padding: 48px 56px;
            max-width: 480px;
            width: 90%;
            text-align: center;
        }

        .logo {
            font-size: 26px;
            font-weight: 800;
            color: #3452ff;
            letter-spacing: -0.5px;
            margin-bottom: 32px;
        }

        .icon-wrap {
            width: 72px;
            height: 72px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-wrap svg {
            width: 36px;
            height: 36px;
            color: #ef4444;
        }

        .code {
            font-size: 56px;
            font-weight: 800;
            color: #3452ff;
            line-height: 1;
            margin-bottom: 8px;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #3452ff;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn:hover { background: #2540d4; }

        .divider {
            border: none;
            border-top: 1px solid #f1f5f9;
            margin: 32px 0 24px;
        }

        .footer {
            font-size: 11px;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <img src="/images/dimage_logo.png" alt="DIMAGE" style="height:56px; object-fit:contain; display:inline-block;" />
        </div>

        <div class="icon-wrap">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>

        <div class="code">403</div>
        <h1>Acceso no autorizado</h1>
        <p>No tienes permiso para acceder a esta sección.<br>Si crees que esto es un error, contacta al administrador.</p>

        <button onclick="window.location.href='{{ url('/') }}'" class="btn">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Volver al inicio
        </button>

        <hr class="divider" />
        <p class="footer">DIMAGE · Telediagnóstico Imagenológico</p>
    </div>
</body>
</html>
