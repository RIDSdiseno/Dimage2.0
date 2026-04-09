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
  .exam-box { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
  .exam-header { background: #0b2a4a; color: #fff; padding: 6px 12px; font-weight: 700; font-size: 11px; }
  .exam-body { padding: 10px 12px; background: #fff; }
  .exam-body p { margin-bottom: 4px; line-height: 1.5; }
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
      <div class="badge">INFORME #{{ $order->id }}</div>
      <div style="font-size:9px;color:#64748b;margin-top:4px;text-align:right;">
        {{ $order->respondida ? \Carbon\Carbon::parse($order->respondida)->format('d/m/Y H:i') : '' }}
      </div>
    </div>
  </div>

  <h2>Datos del Paciente</h2>
  <div class="grid-2">
    <div class="col">
      <div class="label">Nombre</div>
      <div class="value">{{ $paciente->name ?? '-' }}</div>
    </div>
    <div class="col">
      <div class="label">RUT</div>
      <div class="value">{{ $paciente->rut ?? '-' }}</div>
    </div>
  </div>

  <h2>Datos de la Orden</h2>
  <div class="grid-2">
    <div class="col">
      <div class="label">Clínica</div>
      <div class="value">{{ $clinica }}</div>
    </div>
    <div class="col">
      <div class="label">Radiólogo(s)</div>
      <div class="value">{{ implode(', ', $radiologos->toArray()) }}</div>
    </div>
  </div>
  <div class="grid-2" style="margin-top:-1px;">
    <div class="col">
      <div class="label">Diagnóstico clínico</div>
      <div class="value" style="font-weight:400;">{{ $order->diagnostico }}</div>
    </div>
    <div class="col">
      <div class="label">Prioridad</div>
      <div class="value">{{ $order->prioridad }}</div>
    </div>
  </div>

  <h2 style="margin-top:16px;">Resultados por Examen</h2>
  @foreach($examenes as $ex)
  <div class="exam-box">
    <div class="exam-header">{{ $ex['descripcion'] }}</div>
    <div class="exam-body">
      @if($ex['respuesta'])
        <p>{{ $ex['respuesta'] }}</p>
      @else
        <p style="color:#94a3b8;font-style:italic;">Sin informe.</p>
      @endif
    </div>
  </div>
  @endforeach

  <div class="footer">
    Documento generado por DIMAGE · {{ now()->format('d/m/Y H:i') }}
  </div>
</div>
</body>
</html>
