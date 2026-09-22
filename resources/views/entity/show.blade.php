@extends('layouts.app')
@section('title', $entity->name)

@section('content')

{{-- latestScore, verdict and tone come from the controller. A block
     here recomputed them from a "score" property Eloquent does not have,
     so a premises scoring 34% showed 0. --}}

<div class="page-head">
  <div>
    <div class="crumb">{{ $entity->type_name }} &middot; {{ $entity->district }}</div>
    <h2>{{ $entity->name }}</h2>
    <p>{{ $entity->owner ? 'Owner: ' . $entity->owner : 'Owner not recorded' }}
       @if($entity->upi) &middot; UPI {{ $entity->upi }} @endif</p>
  </div>
  <div class="head-actions">
    @if($latest)
      <a href="{{ route('inspection.show', $latest->id) }}" class="btn btn-primary">Full Report</a>
    @endif
    <a href="{{ url()->previous() }}" class="btn btn-ghost">Back</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><b>{{ count($inspections) }}</b><span>Inspections carried out</span></div>
  <div class="stat {{ \App\Support\ComplianceBand::tone((float) $latestScore) === 'poor' ? 'r' : 'g' }}">
    <b>{{ $latestScore }}%</b><span>Latest compliance</span></div>
  <div class="stat t"><b>{{ $latest?->inspection_date ?? '—' }}</b><span>Last inspected</span></div>
  <div class="stat y"><b>{{ $latest?->no_count ?? 0 }}</b><span>Non-compliant items</span></div>
</div>

<div class="grid2">
  <section>
    <h3>Premises record</h3>
    <div class="desc">As captured during inspection</div>
    <table>
      <tbody>
        <tr><td style="color:var(--muted);width:38%">UPI</td><td><strong>{{ $entity->upi ?: '—' }}</strong></td></tr>
        <tr><td style="color:var(--muted)">Zoning</td><td>{{ $entity->zoning ?: '—' }}</td></tr>
        <tr><td style="color:var(--muted)">Use</td><td>{{ $entity->use_type ?: '—' }}</td></tr>
        <tr><td style="color:var(--muted)">Owner</td><td>{{ $entity->owner ?: '—' }}</td></tr>
        <tr><td style="color:var(--muted)">Telephone</td><td>{{ $entity->telephone ?: '—' }}</td></tr>
        <tr><td style="color:var(--muted)">Email</td><td>{{ $entity->email ?: '—' }}</td></tr>
        <tr><td style="color:var(--muted)">Location</td>
            <td>{{ collect([$entity->district, $entity->sector, $entity->cell])->filter()->implode(' · ') ?: '—' }}</td></tr>
        @if($entity->latitude)
          <tr><td style="color:var(--muted)">Coordinates</td>
              <td style="font-variant-numeric:tabular-nums">{{ $entity->latitude }}, {{ $entity->longitude }}</td></tr>
        @endif
      </tbody>
    </table>
  </section>

  <section>
    <h3>Inspection history</h3>
    <div class="desc">{{ count($inspections) }} {{ Str::plural('visit', count($inspections)) }} recorded</div>
    <table>
      <thead><tr><th>Date</th><th>Inspector</th><th style="text-align:right">Score</th><th></th></tr></thead>
      <tbody>
        @foreach($inspections as $n => $i)
          <tr>
            <td>{{ $i->inspection_date }}
              @if($n === 0)<br><span class="pill good" style="font-size:9.5px">Latest</span>
              @else<br><span style="color:var(--muted);font-size:11px">Visit {{ count($inspections) - $n }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $i->inspector_name ?: '—' }}</td>
            <td class="num"><span class="pill {{ \App\Services\LegacyStats::band((float) $i->score) }}">{{ $i->score }}%</span></td>
            <td class="num"><a href="{{ route('inspection.show', $i->id) }}" class="btn btn-ghost btn-sm">Report</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
</div>

@if($latest)
<section>
  <h3>Latest checklist &mdash; {{ $latest->inspection_date->format('j F Y') }}</h3>
  <div class="desc">
    {{ $counts['yes'] }} compliant &middot; {{ $counts['no'] }} non-compliant
    &middot; {{ $counts['na'] }} not applicable &middot;
    <span class="pill {{ $tone }}">{{ $verdict }}</span>
  </div>

  @foreach($checklist as $section)
    @php $pct = $section['possible'] > 0 ? round(100 * $section['earned'] / $section['possible']) : null; @endphp
    <div class="sec-head">
      <div><span class="sec-no">{{ $section['number'] }}</span> {{ $section['title'] }}</div>
      @if($pct !== null)<span class="pill {{ \App\Support\ComplianceBand::tone((float) $pct) }}">{{ $pct }}%</span>@endif
    </div>
    <table class="chk">
      <tbody>
        @foreach($section['items'] as $item)
          <tr>
            <td>{{ rtrim($item->label, '. ') }}
              @if($item->comment)<br><span style="color:var(--muted);font-size:11.5px">{{ $item->comment }}</span>@endif
            </td>
            <td style="width:110px;text-align:right">
              @if($item->status === 'yes')   <span class="pill good">Compliant</span>
              @elseif($item->status === 'no')<span class="pill poor">Not compliant</span>
              @elseif($item->status === 'na')<span class="pill" style="background:#EEF1F5;color:#606874">N/A</span>
              @else <span style="color:var(--muted)">—</span>@endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
</section>
@endif



<footer>City of Kigali &mdash; Premises record &middot; Generated {{ now()->format('j F Y, H:i') }}</footer>

@endsection

@push('styles')
<style>
  .sec-head{display:flex;justify-content:space-between;align-items:center;gap:12px;
    background:var(--blue);color:#fff;padding:9px 14px;border-radius:7px;margin:16px 0 0;
    font-size:13px;font-weight:700}
  .sec-no{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
    border-radius:5px;background:rgba(255,255,255,.2);font-size:11px;margin-right:7px}
  table.chk td{font-size:13px;padding:8px 10px}
  table.chk tr:last-child td{border-bottom:none}
</style>
@endpush
