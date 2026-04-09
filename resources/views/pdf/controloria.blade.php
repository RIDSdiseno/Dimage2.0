<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
  .page { padding: 28px 32px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #3452ff; padding-bottom: 12px; margin-bottom: 18px; }
  .logo-text { font-size: 22px; font-weight: 700; color: #3452ff; letter-spacing: -0.5px; }
  .logo-sub  { font-size: 10px; color: #64748b; }
  .badge { background: #3452ff; color: #fff; border-radius: 4px; padding: 2px 8px; font-size: 10px; font-weight: 700; }
  h2 { font-size: 13px; font-weight: 700; color: #0b2a4a; margin-bottom: 10px; border-left: 3px solid #3452ff; padding-left: 8px; }
  .grid-2 { display: table; width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  .grid-2 .col { display: table-cell; width: 50%; vertical-align: top; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
  .label { font-size: 9px; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
  .value { font-size: 11px; font-weight: 600; }
  .text-block { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
  .text-block-header { background: #0b2a4a; color: #fff; padding: 6px 12px; font-weight: 700; font-size: 11px; }
  .text-block-body { padding: 10px 12px; background: #fff; line-height: 1.6; }
  .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; }
  .status-pending  { background: #fef3c7; color: #92400e; }
  .status-answered { background: #d1fae5; color: #065f46; }
  .footer { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      <img src="{{ public_path('images/dimage_logo.png') }}" style="height:44px;object-fit:contain;" />
      <div class="logo-sub" style="margin-top:4px;">Diagnóstico por Imagen Digital</div>
    </div>
    <div>
      <div class="badge">CONTRALORÍA #{{ $account->id }}</div>
      <div style="font-size:9px;color:#64748b;margin-top:4px;text-align:right;">
        {{ \Carbon\Carbon::parse($account->created_at)->format('d/m/Y H:i') }}
      </div>
    </div>
  </div>

  <h2>Datos del Paciente</h2>
  <div class="grid-2">
    <div class="col">
      <div class="label">Nombre</div>
      <div class="value">{{ $paciente }}</div>
    </div>
    <div class="col">
      <div class="label">RUT</div>
      <div class="value">{{ $rut }}</div>
    </div>
  </div>

  <h2>Datos de la Cuenta</h2>
  <div class="grid-2">
    <div class="col">
      <div class="label">Clínica</div>
      <div class="value">{{ $clinica }}</div>
    </div>
    <div class="col">
      <div class="label">Contralor</div>
      <div class="value">{{ $contralor }}</div>
    </div>
  </div>
  <div class="grid-2" style="margin-top:-1px;">
    <div class="col">
      <div class="label">Estado</div>
      <div class="value">
        <span class="status-badge {{ $estado ? 'status-answered' : 'status-pending' }}">
          {{ $estado ? 'Respondida' : 'Pendiente' }}
        </span>
      </div>
    </div>
    <div class="col">
      <div class="label">Fecha Creación</div>
      <div class="value">{{ \Carbon\Carbon::parse($account->created_at)->format('d/m/Y H:i') }}</div>
    </div>
  </div>

  <h2 style="margin-top:16px;">Diagnóstico</h2>
  <div class="text-block">
    <div class="text-block-header">Diagnóstico Clínico</div>
    <div class="text-block-body">{{ $diagnostico }}</div>
  </div>

  @if($observaciones)
  <div class="text-block">
    <div class="text-block-header">Observaciones</div>
    <div class="text-block-body">{{ $observaciones }}</div>
  </div>
  @endif

  @if($detalle)
  <div class="text-block">
    <div class="text-block-header">Detalle</div>
    <div class="text-block-body">{{ $detalle }}</div>
  </div>
  @endif

  @if($respuesta)
  <div class="text-block">
    <div class="text-block-header" style="background:#065f46;">Respuesta del Contralor</div>
    <div class="text-block-body" style="background:#f0fdf4;">{{ $respuesta }}</div>
  </div>
  @endif

  <div class="footer">
    Documento generado por DIMAGE · {{ now()->format('d/m/Y H:i') }}
  </div>

</div>
</body>
</html>
