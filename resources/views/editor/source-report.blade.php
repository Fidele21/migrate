{{-- Starting content for a report, rendered once then freely editable. --}}
@php
  $e = $inspection->entity;
  [$verdict] = $inspection->deliberation();
  $yes = $answers->where('status','yes')->count();
  $no  = $answers->where('status','no')->count();
  $na  = $answers->where('status','na')->count();
  $zone = collect(config('zoning'))->flatMap(fn($g) => $g)->get($e->zoning);
@endphp
<div class="page">
<p style="font-size:9pt;letter-spacing:2pt;color:#666"><strong>REPUBLIC OF RWANDA</strong></p>
<p style="font-size:17pt;color:#34A8DB;margin:0"><strong>CITY OF KIGALI</strong></p>
<p style="font-size:10pt;color:#666">Inspection Unit</p>
<hr>

<h2 style="text-align:center;color:#34A8DB">INSPECTION REPORT</h2>

<table style="width:100%">
  <tr><td style="width:22%;background:#f2f4f7"><strong>Premises</strong></td><td colspan="3">{{ $e->name }}</td></tr>
  <tr>
    <td style="background:#f2f4f7"><strong>UPI</strong></td>
    <td>@forelse($e->upis as $u){{ $u->upi }}@if(!$loop->last)<br>@endif @empty {{ $e->upi ?: '—' }} @endforelse</td>
    <td style="width:16%;background:#f2f4f7"><strong>Zoning</strong></td>
    <td>{{ $e->zoning ?: '—' }}@if($zone)<br><em style="font-size:9pt">{{ $zone }}</em>@endif</td>
  </tr>
  <tr>
    <td style="background:#f2f4f7"><strong>Owner</strong></td><td>{{ $e->owner ?: '—' }}</td>
    <td style="background:#f2f4f7"><strong>Use</strong></td><td>{{ $e->use_type ?: '—' }}</td>
  </tr>
  <tr>
    <td style="background:#f2f4f7"><strong>District</strong></td><td>{{ $e->district ?: '—' }}</td>
    <td style="background:#f2f4f7"><strong>Sector</strong></td><td>{{ $e->sector ?: '—' }}</td>
  </tr>
  <tr>
    <td style="background:#f2f4f7"><strong>Date</strong></td><td>{{ $inspection->inspection_date->format('j F Y') }}</td>
    <td style="background:#f2f4f7"><strong>Visit</strong></td><td>{{ $inspection->visit_number }}</td>
  </tr>
</table>

<p>&nbsp;</p>
<p style="background:#E8F4FA;padding:10pt"><strong>Overall compliance: {{ round((float) $inspection->compliance, 1) }}%</strong>
&nbsp;&nbsp;|&nbsp;&nbsp; Deliberation: <strong>{{ $verdict }}</strong><br>
<span style="font-size:10pt">{{ $yes }} compliant &middot; {{ $no }} not compliant &middot; {{ $na }} not applicable</span></p>

<h3 style="color:#34A8DB">Detailed findings</h3>

@foreach($inspection->template->sections as $section)
<p style="background:#34A8DB;color:#fff;padding:5pt 9pt;margin:12pt 0 0"><strong>{{ $section->section_number }}. {{ $section->title }}</strong></p>
<table style="width:100%">
@foreach($section->items as $item)
  @php $a = $answers->get($item->id); @endphp
  <tr>
    <td>{{ rtrim($item->label, '. ') }}@if(optional($a)->comment)<br><em style="font-size:9pt;color:#666">{{ $a->comment }}</em>@endif</td>
    <td style="width:100pt;text-align:right">
      @if(optional($a)->status === 'yes')<span style="color:#2E7D32"><strong>Compliant</strong></span>
      @elseif(optional($a)->status === 'no')<span style="color:#C62828"><strong>Not compliant</strong></span>
      @elseif(optional($a)->status === 'na')<span style="color:#888">N/A</span>
      @else — @endif
    </td>
  </tr>
@endforeach
</table>
@endforeach
@include('partials.report-faults')
@if($inspection->observations)
<h3 style="color:#34A8DB">Observations</h3>
<p style="text-align:justify">{!! nl2br(e($inspection->observations)) !!}</p>
@endif

@if($inspection->recommendations)
<h3 style="color:#34A8DB">Recommendations</h3>
<p style="text-align:justify">{!! nl2br(e($inspection->recommendations)) !!}</p>
@endif

@if($inspection->photos->count())
<h3 style="color:#34A8DB">Photographs</h3>
<p>
@foreach($inspection->photos as $p)<img src="{{ $p->url() }}" style="width:250px;margin:6px">@endforeach
</p>
@endif

<h3 style="color:#34A8DB">Signatures</h3>
<p style="font-size:10pt">This inspection was conducted by the following officers.</p>

<table style="width:100%;border:none">
@php $team = $inspection->team->count() ? $inspection->team : collect([(object)['name' => $inspection->inspector_name, 'position' => 'Inspector', 'institution' => 'City of Kigali']]); @endphp
@foreach($team->chunk(3) as $chunk)
<tr>
@foreach($chunk as $m)
  <td style="border:none;width:33%">
    <p style="border-bottom:1px solid #888;height:34pt;margin:0"></p>
    <p style="margin:3pt 0 0"><strong>{{ $m->name }}</strong><br>
    <span style="font-size:9pt;color:#34A8DB"><strong>{{ mb_strtoupper($m->position ?: 'Inspector') }}</strong></span><br>
    <span style="font-size:8pt;color:#888">{{ $m->institution ?? 'City of Kigali' }}</span></p>
  </td>
@endforeach
@for($i = $chunk->count(); $i < 3; $i++)<td style="border:none"></td>@endfor
</tr>
@endforeach
</table>

<p style="font-size:10pt;margin-top:16pt">Verified and approved:</p>
<table style="width:100%;border:none">
<tr>
  <td style="border:none;width:33%">
    <p style="border-bottom:1px solid #888;height:34pt;margin:0"></p>
    <p style="margin:3pt 0 0"><span style="font-size:9pt;color:#34A8DB"><strong>DIRECTOR OF INSPECTION UNIT</strong></span><br>
    <span style="font-size:8pt;color:#888">{{ $e->district }} District</span></p>
  </td>
  <td style="border:none"></td><td style="border:none"></td>
</tr>
</table>
</div>
