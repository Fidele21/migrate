@extends('layouts.app')
@section('title', 'Compliance Analysis')

@section('content')

<div class="page-head">
  <h2>Petrol Station Compliance Analysis</h2>
  <p>{{ $overview['inspections'] }} inspections &middot; {{ $overview['first_date'] }} to {{ $overview['last_date'] }}</p>
</div>

<div class="stats">
  <div class="stat"><b>{{ $overview['entities'] }}</b><span>Stations assessed</span></div>
  <div class="stat"><b class="green">{{ $overview['mean'] }}%</b><span>Mean compliance</span></div>
  <div class="stat"><b class="red">{{ $overview['below70'] }}</b><span>Below 70%</span></div>
  <div class="stat"><b class="gold">{{ $overview['lowest'] }}%</b><span>Lowest</span></div>
  <div class="stat"><b class="green">{{ $overview['highest'] }}%</b><span>Highest</span></div>
</div>

@if($top)
<div class="callout">
  <b>Principal finding</b>
  <p>{{ rtrim($top->label, '. ') }} &mdash; absent at {{ $top->failures }} of {{ $top->assessed }} stations ({{ $top->fail_pct }}%).</p>
  <small>This is the most frequently failed requirement across all stations inspected, and it
    applies to facilities storing bulk flammable fuel in populated areas.</small>
</div>
@endif

<div class="split">
  <div class="pill-card a">
    <div class="big">{{ $gap['siting'] }}%</div>
    <div class="cap">Siting &amp; separation</div>
    <div class="exp">Plot size, road safety, distance from residences, power lines,
      sensitive areas, tank capacity</div>
  </div>
  <div class="pill-card b">
    <div class="big">{{ $gap['operations'] }}%</div>
    <div class="cap">Operations &amp; documentation</div>
    <div class="exp">Permits, licences, insurance, firefighting systems, electrical
      certification, sanitation, zoning</div>
  </div>
</div>

<div class="note">
  <strong>What this indicates</strong>
  Siting compliance averages {{ $gap['siting'] }}% while operational and documentary compliance
  averages {{ $gap['operations'] }}% &mdash; a gap of {{ abs($gap['siting'] - $gap['operations']) }} percentage points.
  Siting requirements are verified once, at planning approval, and cannot easily lapse.
  Operational requirements must be maintained continuously and only surface as lapsed when
  someone inspects. The pattern suggests approval controls are working and that the gap lies
  in periodic verification &mdash; which is the function this platform exists to support.
</div>

<section>
  <h3>Compliance by district</h3>
  <div class="desc">Weighted across all assessed criteria</div>
  @foreach($districts as $d)
    <div class="row">
      <div class="nm">{{ $d->district }}<small>{{ $d->inspections }} {{ Str::plural('inspection', $d->inspections) }}</small></div>
      <div class="track"><div class="fill {{ \App\Services\LegacyStats::band((float) $d->compliance) }}" style="width:{{ $d->compliance }}%"></div></div>
      <div class="v">{{ $d->compliance }}%</div>
    </div>
  @endforeach
</section>

<section>
  <h3>Compliance by requirement area</h3>
  <div class="desc">All assessed sections, weakest first</div>
  @foreach($sections as $s)
    @continue($s->compliance === null)
    <div class="row">
      <div class="nm">{{ $s->title }}<small>Section {{ $s->section_number }}</small></div>
      <div class="track"><div class="fill {{ \App\Services\LegacyStats::band((float) $s->compliance) }}" style="width:{{ $s->compliance }}%"></div></div>
      <div class="v">{{ $s->compliance }}%</div>
    </div>
  @endforeach
</section>

<section>
  <h3>Station ranking</h3>
  <div class="desc">Lowest scoring first &mdash; suggested order of follow-up</div>
  @foreach($stations as $s)
    <div class="row">
      <div class="nm">{{ $s->name }}<small>{{ $s->district }}@if($s->sector) &middot; {{ $s->sector }}@endif</small></div>
      <div class="track"><div class="fill {{ \App\Services\LegacyStats::band((float) $s->score) }}" style="width:{{ $s->score }}%"></div></div>
      <div class="v">{{ $s->score }}%</div>
    </div>
  @endforeach
</section>

<section>
  <h3>Most frequently failed requirements</h3>
  <div class="desc">Ranked by number of stations in non-compliance</div>
  <table>
    <thead>
      <tr><th>Requirement</th><th style="text-align:right">Failed</th>
          <th style="text-align:right">Assessed</th><th style="text-align:right">Rate</th></tr>
    </thead>
    <tbody>
      @foreach($failures as $f)
        <tr>
          <td>{{ rtrim($f->label, '. ') }}</td>
          <td class="num">{{ $f->failures }}</td>
          <td class="num">{{ $f->assessed }}</td>
          <td class="num"><span class="pill {{ $f->fail_pct >= 50 ? 'poor' : 'weak' }}">{{ $f->fail_pct }}%</span></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</section>

<footer>
  City of Kigali &mdash; Digital Inspection Platform<br>
  Figures computed from live inspection records at {{ now()->format('j F Y, H:i') }}
</footer>

@endsection
