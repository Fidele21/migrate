<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>Inspection Report — {{ $inspection->entity->name }}</title>
<!--[if gte mso 9]><xml>
  <w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument>
</xml><![endif]-->
<style>
  @page { size: A4 portrait; margin: 2cm 1.8cm; }
  body { font-family: "Calibri", sans-serif; font-size: 10.5pt; color: #1a1a1a; line-height: 1.4; }

  .lh { border-bottom: 2.5pt solid #0033A0; padding-bottom: 8pt; margin-bottom: 16pt; }
  .lh .rep { font-size: 8pt; letter-spacing: 2pt; color: #606874; font-weight: bold; }
  .lh .city { font-size: 17pt; font-weight: bold; color: #0033A0; }
  .lh .unit { font-size: 9.5pt; color: #606874; }

  h1 { font-size: 15pt; color: #0033A0; text-align: center; margin: 0 0 3pt; }
  .ref { text-align: center; font-size: 8.5pt; color: #606874; margin-bottom: 14pt; }

  h2 { font-size: 11pt; color: #0033A0; border-bottom: 0.75pt solid #d5d9e0;
       padding-bottom: 3pt; margin: 18pt 0 8pt; }

  table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
  td, th { border: 0.5pt solid #c9ced6; padding: 4.5pt 6pt; vertical-align: top; }
  th { background: #f2f4f7; text-align: left; font-size: 8pt; color: #606874; }
  td.k { background: #f2f4f7; width: 22%; font-size: 8.5pt; color: #606874; }

  .verdict { border: 1.5pt solid #0033A0; background: #eef2fb; padding: 10pt 14pt; margin: 12pt 0 16pt; }
  .verdict .score { font-size: 24pt; font-weight: bold; color: #0033A0; }
  .verdict .lbl { font-size: 8pt; letter-spacing: 1.2pt; color: #606874; font-weight: bold; }
  .verdict .verd { font-size: 13pt; font-weight: bold; color: #0033A0; }

  .sec { background: #0033A0; color: #ffffff; padding: 5pt 9pt; font-size: 9.5pt;
         font-weight: bold; margin-top: 12pt; }
  table.chk td { font-size: 9pt; }
  table.chk td.stat { width: 90pt; text-align: right; font-weight: bold; }
  .ok   { color: #00722f; }
  .bad  { color: #b32d22; }
  .na   { color: #707784; }
  .cmt  { color: #606874; font-size: 8pt; font-style: italic; }

  .prose { font-size: 10pt; line-height: 1.55; }

  .sigs { width: 100%; margin-top: 34pt; }
  .sigs td { border: none; width: 25%; padding-right: 12pt; vertical-align: bottom; }
  .sigline { border-bottom: 0.75pt solid #7d848f; height: 34pt; }
  .sigrole { font-size: 8pt; font-weight: bold; color: #0033A0; letter-spacing: 0.5pt;
             text-transform: uppercase; padding-top: 4pt; }
  .signame { font-size: 8pt; color: #606874; }

  .foot { margin-top: 22pt; padding-top: 7pt; border-top: 0.5pt solid #d5d9e0;
          font-size: 8pt; color: #606874; text-align: center; }
</style>
</head>
<body>

@php
  [$verdict] = $inspection->deliberation();
  $e = $inspection->entity;
  $yes = $answers->where('status','yes')->count();
  $no  = $answers->where('status','no')->count();
  $na  = $answers->where('status','na')->count();
  $zoneLabel = collect(config('zoning'))->flatMap(fn($g) => $g)->get($e->zoning);
@endphp

<div class="lh">
  <div class="rep">REPUBLIC OF RWANDA</div>
  <div class="city">CITY OF KIGALI</div>
  <div class="unit">Inspection Unit &mdash; {{ $inspection->type_code === 'petrol' ? 'Petrol Station' : 'Building' }} Inspection</div>
</div>

<h1>INSPECTION REPORT</h1>
<div class="ref">Reference to be assigned upon approval &middot; Visit {{ $inspection->visit_number }} of {{ count($history) }}</div>

<h2>1. Particulars of the premises</h2>
<table>
  <tr><td class="k">Premises</td><td colspan="3"><b>{{ $e->name }}</b></td></tr>
  <tr>
    <td class="k">Parcel (UPI)</td>
    <td>@forelse($e->upis as $u){{ $u->upi }}@if(!$loop->last)<br>@endif @empty {{ $e->upi ?: '—' }} @endforelse</td>
    <td class="k">Zoning</td>
    <td>{{ $e->zoning ?: '—' }}@if($zoneLabel)<br><span class="cmt">{{ $zoneLabel }}</span>@endif</td>
  </tr>
  <tr>
    <td class="k">Owner</td><td>{{ $e->owner ?: '—' }}</td>
    <td class="k">Use</td><td>{{ $e->use_type ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">Telephone</td><td>{{ $e->telephone ?: '—' }}</td>
    <td class="k">Email</td><td>{{ $e->email ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">District</td><td>{{ $e->district ?: '—' }}</td>
    <td class="k">Sector</td><td>{{ $e->sector ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">Cell</td><td>{{ $e->cell ?: '—' }}</td>
    <td class="k">Coordinates</td>
    <td>@if($e->latitude){{ $e->latitude }}, {{ $e->longitude }}@else—@endif</td>
  </tr>
  <tr>
    <td class="k">Date of inspection</td><td>{{ $inspection->inspection_date->format('j F Y') }}</td>
    <td class="k">Inspector</td><td>{{ $inspection->inspector_name }}</td>
  </tr>
</table>

<div class="verdict">
  <table style="border:none">
    <tr>
      <td style="border:none;width:50%">
        <div class="lbl">OVERALL COMPLIANCE</div>
        <div class="score">{{ round((float) $inspection->compliance, 1) }}%</div>
        <div class="cmt">{{ $inspection->earned_score }} of {{ $inspection->possible_score }} points</div>
      </td>
      <td style="border:none;text-align:right">
        <div class="lbl">DELIBERATION</div>
        <div class="verd">{{ $verdict }}</div>
      </td>
    </tr>
  </table>
</div>

<h2>2. Assessment summary</h2>
<table>
  <tr>
    <td class="k">Compliant</td><td>{{ $yes }}</td>
    <td class="k">Not compliant</td><td>{{ $no }}</td>
  </tr>
  <tr>
    <td class="k">Not applicable</td><td>{{ $na }}</td>
    <td class="k">Checklist</td><td>{{ $inspection->template->name }}, version {{ $inspection->template->version }}</td>
  </tr>
</table>

<h2>3. Detailed findings</h2>
@foreach($inspection->template->sections as $section)
  @php
    $earned = 0; $possible = 0;
    foreach ($section->items as $it) {
      $a = $answers->get($it->id);
      if (! $a || ! in_array($a->status, ['yes','no'], true)) continue;
      $possible += (float) $it->max_score;
      if ($a->status === 'yes') $earned += (float) $it->max_score;
    }
    $pct = $possible > 0 ? round(100 * $earned / $possible) : null;
  @endphp

  <div class="sec">{{ $section->section_number }}. {{ $section->title }}@if($pct !== null) &mdash; {{ $pct }}%@endif</div>
  <table class="chk">
    @foreach($section->items as $item)
      @php $a = $answers->get($item->id); @endphp
      <tr>
        <td>{{ rtrim($item->label, '. ') }}
          @if(optional($a)->comment)<br><span class="cmt">{{ $a->comment }}</span>@endif
        </td>
        <td class="stat">
          @if(optional($a)->status === 'yes')<span class="ok">Compliant</span>
          @elseif(optional($a)->status === 'no')<span class="bad">Not compliant</span>
          @elseif(optional($a)->status === 'na')<span class="na">N/A</span>
          @else <span class="na">—</span>@endif
        </td>
      </tr>
    @endforeach
  </table>
@endforeach

@if($inspection->observations)
  <h2>4. Observations</h2>
  <div class="prose">{!! nl2br(e($inspection->observations)) !!}</div>
@endif

@if($inspection->recommendations)
  <h2>{{ $inspection->observations ? '5' : '4' }}. Recommendations</h2>
  <div class="prose">{!! nl2br(e($inspection->recommendations)) !!}</div>
@endif

@if(count($history) > 1)
  <h2>Inspection history</h2>
  <table>
    <tr><th>Visit</th><th>Date</th><th>Inspector</th><th>Compliance</th></tr>
    @foreach($history as $h)
      <tr>
        <td>{{ $h->visit_number }}</td>
        <td>{{ $h->inspection_date->format('j M Y') }}</td>
        <td>{{ $h->inspector_name }}</td>
        <td>{{ round((float) $h->compliance, 1) }}%</td>
      </tr>
    @endforeach
  </table>
@endif

@if($inspection->team->count())
  <h2>Inspection team</h2>
  <table>
    <tr><th>Name</th><th>Institution</th></tr>
    @foreach($inspection->team as $m)
      <tr><td>{{ $m->name }}</td><td>{{ $m->institution ?: '—' }}</td></tr>
    @endforeach
  </table>
@endif

<table class="sigs">
  <tr>
    <td><div class="sigline"></div><div class="sigrole">Inspector</div><div class="signame">{{ $inspection->inspector_name }}</div></td>
    <td><div class="sigline"></div><div class="sigrole">Director of Inspection</div><div class="signame">{{ $e->district }} District</div></td>
    <td><div class="sigline"></div><div class="sigrole">Senior Inspector</div><div class="signame">City of Kigali</div></td>
    <td><div class="sigline"></div><div class="sigrole">Chief Inspector</div><div class="signame">City of Kigali</div></td>
  </tr>
</table>

<div class="foot">
  Generated by the City of Kigali Digital Inspection Platform on {{ now()->format('j F Y \a\t H:i') }}.<br>
  This report remains a draft until signed by the Chief Inspector.
</div>

</body>
</html>
