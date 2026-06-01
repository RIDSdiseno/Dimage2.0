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
      <div class="value">{{ $radiologos->pluck('name')->implode(', ') }}</div>
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
  @php $r = $ex['respuesta'] ?? null; @endphp
  <div class="exam-box">
    <div class="exam-header">{{ $ex['descripcion'] }}</div>
    <div class="exam-body">
      @php
        $isRetro  = stripos($ex['descripcion'] ?? '', 'retroalveolar') !== false;
        $maxilarN = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,51,52,53,54,55,61,62,63,64,65];
        $mandibN  = [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48,71,72,73,74,75,81,82,83,84,85];
      @endphp
      @if($r)
        {{-- Panorámica: informe_examen/libre/impresion --}}
        @if(!empty($r['informe_examen']) || !empty($r['informe_libre']) || !empty($r['informe_impresion']))
          @if(!empty($r['informe_examen']))  <p><strong>Examen:</strong></p><p>{{ $r['informe_examen'] }}</p> @endif
          @if(!empty($r['informe_libre']))   <p style="margin-top:6px;"><strong>Informe:</strong></p><p>{{ $r['informe_libre'] }}</p> @endif
          @if(!empty($r['informe_impresion']))<p style="margin-top:6px;"><strong>Impresión Diagnóstica:</strong></p><p>{{ $r['informe_impresion'] }}</p> @endif

        {{-- Retroalveolar: secciones Maxilar / Mandíbula + dientes --}}
        @elseif($isRetro)
          @if(!empty($r['campo_2']) || !empty($r['campo_3']) || !empty($r['campo_4']))
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:3px;margin-bottom:6px;">Maxilar</p>
            @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
            @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
            @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
            @php $maxDientes = collect($maxilarN)->filter(fn($d) => !empty($r["diente_{$d}"]))->values(); @endphp
            @if($maxDientes->isNotEmpty())
              <p style="margin-top:6px;font-weight:600;font-size:10px;">Dientes:</p>
              <table style="width:100%;border-collapse:collapse;font-size:10px;margin-top:2px;">
                @foreach($maxDientes->chunk(4) as $chunk)
                <tr>
                  @foreach($chunk as $d)
                  <td style="border:1px solid #e2e8f0;padding:3px 5px;vertical-align:top;width:25%;">
                    <strong>{{ floor($d/10).'.'.($d%10) }}</strong><br>{{ $r["diente_{$d}"] }}
                  </td>
                  @endforeach
                </tr>
                @endforeach
              </table>
            @endif
          @endif
          @if(!empty($r['campo_5']) || !empty($r['campo_6']) || !empty($r['campo_7']))
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:3px;margin-bottom:6px;margin-top:10px;">Mandíbula</p>
            @if(!empty($r['campo_5'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_5'] }}</p> @endif
            @if(!empty($r['campo_6'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_6'] }}</p> @endif
            @if(!empty($r['campo_7'])) <p><strong>Observaciones:</strong> {{ $r['campo_7'] }}</p> @endif
            @php $mandDientes = collect($mandibN)->filter(fn($d) => !empty($r["diente_{$d}"]))->values(); @endphp
            @if($mandDientes->isNotEmpty())
              <p style="margin-top:6px;font-weight:600;font-size:10px;">Dientes:</p>
              <table style="width:100%;border-collapse:collapse;font-size:10px;margin-top:2px;">
                @foreach($mandDientes->chunk(4) as $chunk)
                <tr>
                  @foreach($chunk as $d)
                  <td style="border:1px solid #e2e8f0;padding:3px 5px;vertical-align:top;width:25%;">
                    <strong>{{ floor($d/10).'.'.($d%10) }}</strong><br>{{ $r["diente_{$d}"] }}
                  </td>
                  @endforeach
                </tr>
                @endforeach
              </table>
            @endif
          @endif
          @if(!empty($r['campo_1'])) <p style="margin-top:8px;"><strong>Observaciones generales:</strong> {{ $r['campo_1'] }}</p> @endif
          @if(!empty($r['campo_1']) && !$isRetro && empty($r['campo_2'])) <p>Sin informe de secciones.</p> @endif

        {{-- Examen estándar: campo_1/2/3 --}}
        @else
          @if(!empty($r['campo_1'])) <p><strong>Examen:</strong></p><p>{{ $r['campo_1'] }}</p> @endif
          @if(!empty($r['campo_2'])) <p style="margin-top:6px;"><strong>Informe:</strong></p><p>{{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p style="margin-top:6px;"><strong>Impresión Diagnóstica:</strong></p><p>{{ $r['campo_3'] }}</p> @endif
        @endif
      @else
        <p style="color:#94a3b8;font-style:italic;">Sin informe.</p>
      @endif
    </div>
  </div>
  @endforeach

  <div style="margin-top:28px; border-top:1px solid #e2e8f0; padding-top:16px;">
    <h2>Firmas</h2>
    <div style="display:table; width:100%; margin-top:12px;">

      {{-- Radiólogo(s) --}}
      <div style="display:table-cell; width:50%; vertical-align:bottom;">
        @foreach($radiologos as $rad)
        <div style="text-align:center; margin-bottom:16px;">
          @if(!empty($rad->firma_b64))
            <img src="{{ $rad->firma_b64 }}" style="height:60px; max-width:180px; object-fit:contain; display:block; margin:0 auto 4px;" />
          @else
            <div style="height:60px; width:160px; border-bottom:1px solid #94a3b8; margin:0 auto 4px;"></div>
          @endif
          <p style="font-size:10px; color:#1e293b; font-weight:600;">{{ $rad->name }}</p>
          <p style="font-size:9px; color:#64748b;">Radiólogo</p>
        </div>
        @endforeach
      </div>

      {{-- Paciente --}}
      <div style="display:table-cell; width:50%; vertical-align:bottom; text-align:center;">
        <div style="height:60px; width:160px; border-bottom:1px solid #94a3b8; margin:0 auto 4px;"></div>
        <p style="font-size:10px; color:#1e293b; font-weight:600;">{{ $paciente->name ?? '' }}</p>
        <p style="font-size:9px; color:#64748b;">Firma del Paciente</p>
      </div>

    </div>
  </div>

  <div class="footer">
    Documento generado por DIMAGE · {{ now()->format('d/m/Y H:i') }}
  </div>
</div>
</body>
</html>
