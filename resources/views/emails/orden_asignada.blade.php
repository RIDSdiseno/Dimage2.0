<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <style>
        body  { font-family: Arial, sans-serif; background:#f0f2f5; margin:0; padding:20px; color:#374151; }
        .card { background:#fff; border-radius:12px; max-width:600px; margin:0 auto; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .header { background:#0b2a4a; padding:24px 32px; }
        .header h1 { color:#fff; margin:0; font-size:20px; }
        .header p  { color:rgba(255,255,255,.6); margin:4px 0 0; font-size:13px; }
        .body { padding:28px 32px; }
        .greeting { font-size:15px; margin-bottom:20px; }
        .detail-box { background:#f8fafc; border-radius:10px; padding:18px 20px; margin:16px 0; }
        .detail-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e5e7eb; font-size:13px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:#6b7280; font-weight:600; }
        .detail-value { color:#111827; }
        .badge-urgente { display:inline-block; background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
        .badge-normal  { display:inline-block; background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
        .btn { display:inline-block; background:#1b96cc; color:#fff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:600; font-size:14px; margin-top:20px; }
        .footer { background:#f8fafc; border-top:1px solid #e5e7eb; padding:16px 32px; font-size:12px; color:#9ca3af; text-align:center; }
    </style>
</head>
<body>
<div class="card">

    <div class="header">
        <h1>🏥 Dimage — Telediagnóstico Imagenológico</h1>
        <p>Nueva orden radiográfica asignada</p>
    </div>

    <div class="body">
        <p class="greeting">Estimado/a <strong>{{ $radiologoNombre }}</strong>,</p>
        <p>Se te ha asignado una nueva orden radiográfica que requiere tu atención:</p>

        <div class="detail-box">
            <div class="detail-row">
                <span class="detail-label">N° Orden</span>
                <span class="detail-value"><strong>#{{ $ordenId }}</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Paciente</span>
                <span class="detail-value">{{ $paciente }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Clínica</span>
                <span class="detail-value">{{ $clinica }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Examen</span>
                <span class="detail-value">{{ $examen }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Prioridad</span>
                <span class="detail-value">
                    @if($prioridad === 'Urgente')
                        <span class="badge-urgente">🚨 URGENTE</span>
                    @else
                        <span class="badge-normal">Normal</span>
                    @endif
                </span>
            </div>
        </div>

        <p style="font-size:13px; color:#6b7280;">
            Ingresa a la plataforma para revisar las imágenes y emitir el informe radiológico.
        </p>

        <a href="{{ config('app.url') }}/ordenes/{{ $ordenId }}" class="btn">
            Ver Orden en MorfoX →
        </a>
    </div>

    <div class="footer">
        Este correo fue generado automáticamente por Dimage. No responder a este mensaje.
    </div>

</div>
</body>
</html>
