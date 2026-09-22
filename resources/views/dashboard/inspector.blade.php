@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

@php
  $hour = (int) now()->format('H');
  $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<div class="page-head">
  <div>
    <div class="crumb">{{ $role }}@if($district) &middot; {{ $district }} @endif</div>
    <h2>{{ $greet }}, {{ Str::before(auth()->user()->name, ' ') }}</h2>
    <p>
      @if($mine['drafts'])
        You have <strong>{{ $mine['drafts'] }}</strong> {{ Str::plural('inspection', $mine['drafts']) }} in progress.
      @elseif($returned)
        <strong>{{ $returned }}</strong> {{ Str::plural('document', $returned) }} returned to you for revision.
      @else
        Nothing outstanding. {{ $mine['this_month'] }} {{ Str::plural('inspection', $mine['this_month']) }} completed this month.
      @endif
    </p>
  </div>
  <div class="head-actions">
    @can('inspection.create')
      @include('partials.inspect-button')
    @endcan
    <a href="{{ route('mybox') }}" class="btn btn-ghost">My Box</a>
  </div>
</div>

<div class="stats">
  <div class="stat y"><b>{{ $mine['drafts'] }}</b><span>My drafts</span></div>
  <div class="stat r"><b>{{ $returned }}</b><span>Returned to me</span></div>
  <div class="stat t"><b>{{ $inReview }}</b><span>With reviewers</span></div>
  <div class="stat"><b>{{ $mine['this_month'] }}</b><span>Completed this month</span></div>
  <div class="stat g"><b>{{ $mine['completed'] }}</b><span>Completed in total</span></div>
  <div class="stat g"><b>{{ $mine['mean'] }}%</b><span>Mean compliance found</span></div>
</div>

@if($drafts->count())
<section class="urgent">
  <h3>Continue where you left off</h3>
  <div class="desc">Inspections you started but have not completed</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Premises</th><th>Location</th><th>Started</th><th></th></tr></thead>
      <tbody>
        @foreach($drafts as $d)
          <tr>
            <td><strong>{{ $d->entity->name }}</strong>
              @if($d->visit_type === 'followup')<br><span class="pill weak">Follow-up</span>@endif</td>
            <td style="font-size:12.5px">{{ $d->entity->district }}@if($d->entity->sector) &middot; {{ $d->entity->sector }}@endif</td>
            <td style="font-size:12.5px">{{ $d->updated_at->diffForHumans() }}</td>
            <td class="num"><a href="{{ route('inspection.edit', $d) }}" class="btn btn-primary btn-sm">Continue</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<div class="grid2">
  <section>
    <h3>My recent inspections</h3>
    <div class="desc">Completed and on record</div>
    @forelse($recent as $i)
      <div class="row">
        <div class="nm">
          <a href="{{ route('inspection.show', $i) }}" style="text-decoration:none;color:inherit">{{ $i->entity->name }}</a>
          <small>{{ $i->inspection_date->format('j M Y') }}@if($i->visit_number > 1) &middot; visit {{ $i->visit_number }}@endif</small>
        </div>
        <div class="track"><div class="fill {{ \App\Services\InspectionStats::band((float) $i->compliance) }}"
             style="width:{{ $i->compliance }}%"></div></div>
        <div class="v">{{ round((float) $i->compliance, 1) }}%</div>
      </div>
    @empty
      <div class="empty" style="padding:30px"><b>No completed inspections yet</b>
        <span>Your work will appear here.</span></div>
    @endforelse
  </section>

  <section>
    <h3>Compliance in {{ $district ?? 'the city' }}</h3>
    <div class="desc">All inspections in your area</div>
    <div class="chart-box"><canvas id="cDist"></canvas></div>
  </section>
</div>

@if(count($failures))
<section>
  <h3>Most frequently failed requirements</h3>
  <div class="desc">Worth checking closely on your next visit</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Requirement</th><th style="text-align:right">Failed</th><th style="text-align:right">Rate</th></tr></thead>
      <tbody>
        @foreach($failures as $f)
          <tr>
            <td>{{ rtrim($f->label, '. ') }}</td>
            <td class="num">{{ $f->failures }} / {{ $f->assessed }}</td>
            <td class="num"><span class="pill {{ $f->fail_pct >= 50 ? 'poor' : 'weak' }}">{{ $f->fail_pct }}%</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif


@endsection

@section('scripts')
<script>
(function () {
  if (!window.Chart) return;
  var C = window.CoK;
  var dist = @json($distribution ?? []);
  if (!Object.keys(dist).length) return;

  new Chart(document.getElementById('cDist'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(dist),
      datasets: [{ data: Object.values(dist),
        backgroundColor: [C.danger, C.warning, '#8BC34A', C.success],
        borderWidth: 4, borderColor: '#fff', hoverOffset: 12 }]
    },
    options: { maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'bottom' } } }
  });
})();
</script>
@endsection

@push('styles')
<style>
  section.urgent{border-left:4px solid var(--warning)}
</style>
@endpush
