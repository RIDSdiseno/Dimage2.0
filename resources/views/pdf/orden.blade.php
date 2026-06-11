@php
if (!function_exists('pdfDienteTable')) {
    function pdfDienteTable(array $teeth, array $r): string {
        $filtered = array_values(array_filter($teeth, fn($d) => !empty($r["diente_{$d}"])));
        if (!$filtered) return '';
        $html = '<table style="width:100%;border-collapse:collapse;font-size:10px;margin-top:2px;">';
        foreach (array_chunk($filtered, 4) as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as $d) {
                $lbl = floor($d/10).'.'.($d%10);
                $val = htmlspecialchars($r["diente_{$d}"] ?? '');
                $html .= "<td style=\"border:1px solid #e2e8f0;padding:3px 5px;vertical-align:top;width:25%;\"><strong>{$lbl}</strong><br>{$val}</td>";
            }
            for ($f = count($chunk); $f < 4; $f++)
                $html .= '<td style="width:25%;border:1px solid #f1f5f9;"></td>';
            $html .= '</tr>';
        }
        return $html . '</table>';
    }
}
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }
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
  @php
    $r  = $ex['respuesta'] ?? null;
    $d  = strtolower($ex['descripcion'] ?? '');
    $isPano   = $ex['kind_id'] == 15;
    $isRetro  = str_contains($d, 'retroalveolar');
    $isBWBi   = str_contains($d, 'bite wing bilateral');
    $isBWUD   = !$isBWBi && str_contains($d, 'bite wing') && (str_contains($d, 'derecha') || (!str_contains($d, 'izquierda') && str_contains($d, 'unilateral')));
    $isBWUI   = !$isBWBi && str_contains($d, 'bite wing') && str_contains($d, 'izquierda');
    $isCefalo = str_contains($d, 'cefalom');
    $isOclusal= str_contains($d, 'oclusal') || str_contains($d, 'telerradiograf') || str_contains($d, 'carpo') || str_contains($d, 'atm');
    // Tooth arrays
    $pmx = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28];
    $tmx = [51,52,53,54,55,61,62,63,64,65];
    $pmn = [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
    $tmn = [71,72,73,74,75,81,82,83,84,85];
    $bwDP = [13,14,15,16,17,18,43,44,45,46,47,48];
    $bwDT = [53,54,55,83,84,85];
    $bwIP = [23,24,25,26,27,28,33,34,35,36,37,38];
    $bwIT = [63,64,65,73,74,75];
    $cefaloMap = [
        'Análisis Rickets'=>'campo_4','Análisis Roth'=>'campo_5',
        'Análisis Jaraback'=>'campo_6','Análisis Steiner'=>'campo_7',
        'Análisis Mcnamara'=>'campo_8','Otros'=>'campo_9',
    ];
  @endphp
  <div class="exam-box">
    <div class="exam-header">{{ $ex['descripcion'] }}</div>
    <div class="exam-body">
      @if($r)

        {{-- ══ PANORÁMICA ══ --}}
        @if($isPano)
          @if(!empty($r['informe_examen'])||!empty($r['informe_libre'])||!empty($r['informe_impresion']))
            @if(!empty($r['informe_examen']))  <p><strong>Examen:</strong></p><p>{{ $r['informe_examen'] }}</p> @endif
            @if(!empty($r['informe_libre']))   <p style="margin-top:4px;"><strong>Informe:</strong></p><p>{{ $r['informe_libre'] }}</p> @endif
            @if(!empty($r['informe_impresion']))<p style="margin-top:4px;"><strong>Impresión Diagnóstica:</strong></p><p>{{ $r['informe_impresion'] }}</p> @endif
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:8px 0;">
          @endif
          @php $hasPanoMax = !empty($r['campo_2'])||!empty($r['campo_3'])||!empty($r['campo_5'])||!empty($r['campo_8'])||collect(array_merge($pmx,$tmx))->contains(fn($d)=>!empty($r["diente_{$d}"]));
               $hasPanoMan = !empty($r['campo_6'])||!empty($r['campo_7'])||!empty($r['campo_9'])||!empty($r['campo_4'])||collect(array_merge($pmn,$tmn))->contains(fn($d)=>!empty($r["diente_{$d}"])); @endphp
          @if($hasPanoMax)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;">Maxilar</p>
            @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
            @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
            @if(!empty($r['campo_5'])) <p><strong>Dientes Ausentes:</strong> {{ $r['campo_5'] }}</p> @endif
            @if(!empty($r['campo_8'])) <p><strong>Observaciones:</strong> {{ $r['campo_8'] }}</p> @endif
            @php $t=pdfDienteTable($pmx,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($tmx,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if($hasPanoMan)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;margin-top:8px;">Mandíbula</p>
            @if(!empty($r['campo_6'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_6'] }}</p> @endif
            @if(!empty($r['campo_7'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_7'] }}</p> @endif
            @if(!empty($r['campo_9'])) <p><strong>Dientes Ausentes:</strong> {{ $r['campo_9'] }}</p> @endif
            @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
            @php $t=pdfDienteTable($pmn,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($tmn,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if(!$hasPanoMax && !$hasPanoMan && empty($r['informe_examen'])) <p style="color:#94a3b8;font-style:italic;">Sin informe.</p> @endif

        {{-- ══ RETROALVEOLAR ══ --}}
        @elseif($isRetro)
          @php
            $hMax = !empty($r['campo_2'])||!empty($r['campo_3'])||!empty($r['campo_4'])||collect(array_merge($pmx,$tmx))->contains(fn($d)=>!empty($r["diente_{$d}"]));
            $hMan = !empty($r['campo_5'])||!empty($r['campo_6'])||!empty($r['campo_7'])||collect(array_merge($pmn,$tmn))->contains(fn($d)=>!empty($r["diente_{$d}"]));
          @endphp
          @if($hMax)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;">Maxilar</p>
            @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
            @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
            @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
            @php $t=pdfDienteTable($pmx,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($tmx,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if($hMan)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;margin-top:8px;">Mandíbula</p>
            @if(!empty($r['campo_5'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_5'] }}</p> @endif
            @if(!empty($r['campo_6'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_6'] }}</p> @endif
            @if(!empty($r['campo_7'])) <p><strong>Observaciones:</strong> {{ $r['campo_7'] }}</p> @endif
            @php $t=pdfDienteTable($pmn,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($tmn,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if(!empty($r['campo_1'])) <p style="margin-top:6px;"><strong>Observaciones generales:</strong> {{ $r['campo_1'] }}</p> @endif
          @if(!$hMax && !$hMan && empty($r['campo_1'])) <p style="color:#94a3b8;font-style:italic;">Sin informe.</p> @endif

        {{-- ══ BITE WING BILATERAL ══ --}}
        @elseif($isBWBi)
          @php $hD=!empty($r['campo_2'])||!empty($r['campo_3'])||!empty($r['campo_4'])||collect(array_merge($bwDP,$bwDT))->contains(fn($d)=>!empty($r["diente_{$d}"]));
               $hI=!empty($r['campo_5'])||!empty($r['campo_6'])||!empty($r['campo_7'])||collect(array_merge($bwIP,$bwIT))->contains(fn($d)=>!empty($r["diente_{$d}"])); @endphp
          @if($hD)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;">Lado Derecho</p>
            @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
            @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
            @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
            @php $t=pdfDienteTable($bwDP,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($bwDT,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if($hI)
            <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;margin-top:8px;">Lado Izquierdo</p>
            @if(!empty($r['campo_5'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_5'] }}</p> @endif
            @if(!empty($r['campo_6'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_6'] }}</p> @endif
            @if(!empty($r['campo_7'])) <p><strong>Observaciones:</strong> {{ $r['campo_7'] }}</p> @endif
            @php $t=pdfDienteTable($bwIP,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
            @php $t=pdfDienteTable($bwIT,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @endif
          @if(!empty($r['campo_1'])) <p style="margin-top:6px;"><strong>Observaciones generales:</strong> {{ $r['campo_1'] }}</p> @endif

        {{-- ══ BITE WING UNILATERAL DERECHA ══ --}}
        @elseif($isBWUD)
          <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;">Lado Derecho</p>
          @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
          @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
          @php $t=pdfDienteTable($bwDP,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
          @php $t=pdfDienteTable($bwDT,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @if(!empty($r['campo_1'])) <p style="margin-top:6px;"><strong>Observaciones generales:</strong> {{ $r['campo_1'] }}</p> @endif

        {{-- ══ BITE WING UNILATERAL IZQUIERDA ══ --}}
        @elseif($isBWUI)
          <p style="font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:2px;margin-bottom:5px;">Lado Izquierdo</p>
          @if(!empty($r['campo_2'])) <p><strong>Nivel Óseo Marginal:</strong> {{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p><strong>Cálculo dentario marginal:</strong> {{ $r['campo_3'] }}</p> @endif
          @if(!empty($r['campo_4'])) <p><strong>Observaciones:</strong> {{ $r['campo_4'] }}</p> @endif
          @php $t=pdfDienteTable($bwIP,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Permanentes:</p>{!! $t !!}@endif
          @php $t=pdfDienteTable($bwIT,(array)$r); @endphp @if($t)<p style="margin-top:5px;font-weight:600;font-size:10px;color:#475569;">Temporales:</p>{!! $t !!}@endif
          @if(!empty($r['campo_1'])) <p style="margin-top:6px;"><strong>Observaciones generales:</strong> {{ $r['campo_1'] }}</p> @endif

        {{-- ══ CEFALOMÉTRICO ══ --}}
        @elseif($isCefalo)
          @if(!empty($r['campo_1'])) <p><strong>Examen radiográfico:</strong></p><p>{{ $r['campo_1'] }}</p> @endif
          @if(!empty($r['campo_2'])) <p style="margin-top:4px;"><strong>Informe Radiográfico:</strong></p><p>{{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p style="margin-top:4px;"><strong>Observaciones:</strong></p><p>{{ $r['campo_3'] }}</p> @endif
          @foreach($cefaloMap as $label => $campo)
            @if(!empty($r[$campo])) <p style="margin-top:4px;"><strong>{{ $label }}:</strong></p><p>{{ $r[$campo] }}</p> @endif
          @endforeach
          @php $anyC = array_filter(array_keys($cefaloMap), fn($k) => !empty($r[$cefaloMap[$k]]));
               if (!$anyC && empty($r['campo_1']) && empty($r['campo_2']) && empty($r['campo_3'])) echo '<p style="color:#94a3b8;font-style:italic;">Sin informe.</p>'; @endphp

        {{-- ══ OCLUSAL / TELERRADIOGRAFÍA / ATM / CARPO ══ --}}
        @elseif($isOclusal)
          @if(!empty($r['campo_1'])) <p><strong>Examen radiográfico:</strong></p><p>{{ $r['campo_1'] }}</p> @endif
          @if(!empty($r['campo_2'])) <p style="margin-top:4px;"><strong>Informe Radiográfico:</strong></p><p>{{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p style="margin-top:4px;"><strong>Observaciones:</strong></p><p>{{ $r['campo_3'] }}</p> @endif
          @if(empty($r['campo_1']) && empty($r['campo_2']) && empty($r['campo_3'])) <p style="color:#94a3b8;font-style:italic;">Sin informe.</p> @endif

        {{-- ══ ESTÁNDAR (Cone Beam, Periapical, otros) ══ --}}
        @else
          @if(!empty($r['campo_1'])) <p><strong>Examen:</strong></p><p>{{ $r['campo_1'] }}</p> @endif
          @if(!empty($r['campo_2'])) <p style="margin-top:4px;"><strong>Informe:</strong></p><p>{{ $r['campo_2'] }}</p> @endif
          @if(!empty($r['campo_3'])) <p style="margin-top:4px;"><strong>Impresión Diagnóstica:</strong></p><p>{{ $r['campo_3'] }}</p> @endif
          @if(empty($r['campo_1']) && empty($r['campo_2']) && empty($r['campo_3'])) <p style="color:#94a3b8;font-style:italic;">Sin informe.</p> @endif
        @endif

      @else
        <p style="color:#94a3b8;font-style:italic;">Sin informe.</p>
      @endif
    </div>
  </div>
  @endforeach

  @if($radiologos->isNotEmpty())
  <div style="margin-top:28px; border-top:1px solid #e2e8f0; padding-top:16px;">
    <h2>Firma del Radiólogo</h2>
    <div style="margin-top:12px; display:flex; gap:40px; flex-wrap:wrap;">
      @foreach($radiologos as $rad)
      <div style="text-align:center;">
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
  </div>
  @endif

  <div class="footer">
    Documento generado por DIMAGE · {{ now()->format('d/m/Y H:i') }}
  </div>
</div>
</body>
</html>
