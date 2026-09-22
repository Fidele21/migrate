@extends('layouts.app')
@section('title', 'Recovery')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Recovery Officer</div>
    <h2>Good day, {{ Str::before(auth()->user()->name, ' ') }}</h2>
    <p>
      @if(! $empty && $summary['overdue'])
        <strong>{{ $summary['overdue'] }}</strong> {{ Str::plural('fine', $summary['overdue']) }}
        overdue, {{ number_format($summary['overdue_sum']) }} RWF outstanding.
      @else
        Fines confirmed by the Chief or Senior Inspector appear here for collection.
      @endif
    </p>
  </div>
  <div class="head-actions">
    <a href="{{ route('fine.index') }}" class="btn btn-success">Fines Register</a>
    <a href="{{ route('entity.search') }}" class="btn btn-ghost">Search</a>
  </div>
</div>

@if($empty)
  <section><div class="empty"><b>No fines recorded yet</b>
    <span>They will appear here once inspectors propose them and a Senior or Chief Inspector confirms.</span></div></section>
@else

<div class="money-band">
  <div class="mb-main">
    <span>Outstanding</span>
    <strong>{{ number_format($summary['outstanding']) }}</strong>
    <em>RWF across {{ $summary['payable'] }} {{ Str::plural('fine', $summary['payable']) }}</em>
  </div>
  <div class="mb-side">
    <div><span>Collected this month</span><strong>{{ number_format($summary['this_month']) }}</strong></div>
    <div><span>Collected in total</span><strong>{{ number_format($summary['collected']) }}</strong></div>
    <div><span>Settled in full</span><strong>{{ $summary['settled'] }}</strong></div>
  </div>
</div>

<div class="grid2">
  <section>
    <h3>Collection over time</h3>
    <div class="desc">Payments received by month</div>
    <div class="chart-box"><canvas id="cMonths"></canvas></div>
  </section>

  <section>
    <h3>Outstanding by district</h3>
    <div class="desc">Where the money is owed</div>
    @forelse($byDistrict as $name => $d)
      @php $max = $byDistrict->max('sum') ?: 1; @endphp
      <div class="row">
        <div class="nm">{{ $name }}<small>{{ $d['count'] }} {{ Str::plural('fine', $d['count']) }}</small></div>
        <div class="track"><div class="fill poor" style="width:{{ round(100 * $d['sum'] / $max) }}%"></div></div>
        <div class="v">{{ number_format($d['sum'] / 1000, 0) }}k</div>
      </div>
    @empty
      <div class="empty" style="padding:26px"><b>Nothing outstanding</b></div>
    @endforelse
  </section>
</div>

@if($overdue->count())
<section class="urgent">
  <h3>Overdue</h3>
  <div class="desc">Past the due date and still unpaid</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Premises</th><th>Due</th><th>Days late</th>
                 <th style="text-align:right">Outstanding</th><th></th></tr></thead>
      <tbody>
        @foreach($overdue as $f)
          <tr class="late">
            <td><strong>{{ $f->reference }}</strong></td>
            <td>{{ $f->entity->name }}<br><span class="mini">{{ $f->entity->district }}</span></td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $f->due_date->format('j M Y') }}</td>
            <td><span class="pill poor">{{ $f->due_date->diffInDays(now()) }}</span></td>
            <td class="num">{{ number_format($f->outstanding()) }}</td>
            <td class="num"><a href="{{ route('fine.show', $f) }}" class="btn btn-primary btn-sm">Record Payment</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@if($dueSoon->count())
<section>
  <h3>Due within a fortnight</h3>
  <div class="desc">Worth a reminder before they fall overdue</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Premises</th><th>Due</th>
                 <th style="text-align:right">Outstanding</th><th></th></tr></thead>
      <tbody>
        @foreach($dueSoon as $f)
          <tr>
            <td><strong>{{ $f->reference }}</strong></td>
            <td>{{ $f->entity->name }}</td>
            <td style="font-size:12.5px">{{ $f->due_date->format('j M Y') }}
              <span class="mini">in {{ now()->diffInDays($f->due_date) }} days</span></td>
            <td class="num">{{ number_format($f->outstanding()) }}</td>
            <td class="num"><a href="{{ route('fine.show', $f) }}" class="btn btn-ghost btn-sm">Open</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@endif


@endsection

@section('scripts')
@if(! $empty)
<script>
(function () {
  if (!window.Chart) return;
  var C = window.CoK;
  new Chart(document.getElementById('cMonths'), {
    type: 'bar',
    data: {
      labels: @json($months->pluck('month')),
      datasets: [{ label: 'RWF received', data: @json($months->pluck('total')),
                   backgroundColor: C.success, maxBarThickness: 40 }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: C.grid },
             ticks: { callback: function (v) { return (v / 1000) + 'k'; } } },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>
@endif
@endsection

@push('styles')
<style>
  .money-band{display:grid;grid-template-columns:1.2fr 2fr;gap:1px;background:var(--border);
              margin-bottom:22px;box-shadow:var(--shadow-sm)}
  @media(max-width:760px){.money-band{grid-template-columns:1fr}}
  .mb-main{background:var(--danger);color:#fff;padding:26px 28px}
  .mb-main span{font-family:var(--f-head);font-size:11px;letter-spacing:1.2px;
                text-transform:uppercase;opacity:.9;font-weight:600}
  .mb-main strong{display:block;font-family:var(--f-head);font-size:40px;font-weight:800;line-height:1.1}
  .mb-main em{font-style:normal;font-size:12.5px;opacity:.92}
  .mb-side{background:var(--white);display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
           gap:1px;background:var(--border)}
  .mb-side > div{background:var(--white);padding:20px 22px}
  .mb-side span{display:block;font-family:var(--f-head);font-size:10.5px;letter-spacing:.8px;
                text-transform:uppercase;color:var(--tertiary);font-weight:600}
  .mb-side strong{display:block;font-family:var(--f-head);font-size:23px;color:var(--ink);margin-top:4px}
  section.urgent{border-left:4px solid var(--danger)}
  tr.late td{background:#FDECEA}
  .mini{font-size:11.5px;color:var(--tertiary)}
</style>
@endpush
