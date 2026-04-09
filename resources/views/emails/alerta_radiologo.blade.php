<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <style>
        body { font-family: Arial, sans-serif; background:#f0f2f5; margin:0; padding:20px; color:#374151; }
        .card { background:#fff; border-radius:12px; max-width:600px; margin:0 auto; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .header { background:#0b2a4a; padding:24px 32px; }
        .header h1 { color:#fff; margin:0; font-size:20px; }
        .header p  { color:rgba(255,255,255,.6); margin:4px 0 0; font-size:13px; }
        .body { padding:28px 32px; }
        .greeting { font-size:15px; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; margin-top:16px; font-size:13px; }
        th { background:#f8fafc; text-align:left; padding:8px 12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb; }
        td { padding:9px 12px; border-bottom:1px solid #f1f5f9; }
        tr:last-child td { border-bottom:none; }
        .badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-warn { background:#fef3c7; color:#92400e; }
        .badge-danger { background:#fee2e2; color:#991b1b; }
        .footer { background:#f8fafc; border-top:1px solid #e5e7eb; padding:16px 32px; font-size:12px; color:#9ca3af; text-align:center; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>🏥 Dimage — Telediagnóstico Imagenológico</h1>
        <p>Recordatorio de órdenes pendientes</p>
    </div>
    <div class="body">
        <p class="greeting">Estimado/a <strong>{{ $radiologoNombre }}</strong>,</p>
        <p>Tienes las siguientes órdenes radiográficas <strong>pendientes de informe</strong>:</p>

        <table>
            <thead>
                <tr>
                    <th>N° Orden</th>
                    <th>Paciente</th>
                    <th>Fecha Envío</th>
                    <th>Días pendiente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ordenes as $o)
                <tr>
                    <td><strong>#{{ $o['id'] }}</strong></td>
                    <td>{{ $o['paciente'] }}</td>
                    <td>{{ $o['enviada'] }}</td>
                    <td>
                        <span class="badge {{ $o['dias'] >= 3 ? 'badge-danger' : 'badge-warn' }}">
                            {{ $o['dias'] }} día(s)
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-top:20px; font-size:13px; color:#6b7280;">
            Por favor ingresa a la plataforma para revisar y responder estas órdenes.
        </p>
    </div>
    <div class="footer">
        Este correo fue generado automáticamente por Dimage. No responder a este mensaje.
    </div>
</div>
</body>
</html>
