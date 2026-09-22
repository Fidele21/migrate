@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

@php
  $hour = (int) now()->format('H');
  $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
  $first = Str::before(auth()->user()->name, ' ');
  $hasData = $overview['inspections'] > 0;
@endphp

{{-- ══════════ Header ══════════ --}}
<div class="page-head">
  <div>
    <div class="crumb">{{ $role }} &middot; {{ $scopeName }}</div>
    <h2>{{ $greet }}, {{ $first }}</h2>
    <p>
      @if($district)
        Inspection activity across {{ $district }} district.
      @else
        Inspection activity across all three districts of Kigali.
      @endif
      @if($overview['drafts'] > 0)
        <strong>{{ $overview['drafts'] }}</strong> {{ Str::plural('draft', $overview['drafts']) }} still open.
      @endif
    </p>
  </div>
  <div class="head-actions">
    @if($primary)
      <a href="{{ $primary['href'] }}" class="btn btn-success">{{ $primary['label'] }}</a>
    @endif
    <a href="{{ route('inspection.drafts') }}" class="btn btn-ghost">Drafts</a>
    <a href="{{ route('register.index', 'petrol') }}" class="btn btn-ghost">Register</a>
    @if($showAdmin)<a href="{{ route('users.index') }}" class="btn btn-ghost">Accounts</a>@endif
  </div>
</div>

@if(session('status'))
  <div class="note" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif

{{-- ══════════ Work queue ══════════ --}}
@if(count($queues))
<div class="queue-strip">
  @foreach($queues as $label => [$count, $tone])
    <div class="q-chip {{ $tone }}">
      <span class="q-n" data-count="{{ $count }}">0</span>
      <span class="q-l">{{ $label }}</span>
    </div>
  @endforeach
</div>
@endif

@if(! $hasData)

<section>
  <div class="empty">
    <b>No completed inspections in your scope yet</b>
    <span>Figures appear here once inspections are completed@if($district) in {{ $district }} district@endif.</span>
    @if($primary)
      <div style="margin-top:24px"><a href="{{ $primary['href'] }}" class="btn btn-success">{{ $primary['label'] }}</a></div>
    @endif
  </div>
</section>

@else

{{-- ══════════ Key figures ══════════ --}}
<div class="stats">
  <div class="stat"><b data-count="{{ $overview['inspections'] }}">0</b><span>Inspections</span></div>
  <div class="stat t"><b data-count="{{ $overview['entities'] }}">0</b><span>Premises</span></div>
  <div class="stat g"><b data-count="{{ $overview['mean'] }}" data-suffix="%">0</b><span>Mean compliance</span></div>
  <div class="stat g"><b data-count="{{ $overview['compliant'] }}">0</b><span>At or above 70%</span></div>
  <div class="stat r"><b data-count="{{ $overview['below70'] }}">0</b><span>Below 70%</span></div>
  <div class="stat y"><b data-count="{{ $overview['drafts'] }}">0</b><span>Open drafts</span></div>
</div>

{{-- ══════════ Distribution + District ══════════ --}}
<div class="grid2">
  <section>
    <h3>Compliance distribution</h3>
    <div class="desc">How inspected premises are spread across bands</div>
    <div class="chart-box donut-wrap">
      <canvas id="cDist"></canvas>
      <div class="donut-centre">
        <b>{{ $overview['mean'] }}%</b>
        <span>Mean</span>
      </div>
    </div>
  </section>

  <section>
    <h3>District performance</h3>
    <div class="desc">Mean compliance and inspection volume</div>
    <div class="chart-box"><canvas id="cDistrict"></canvas></div>
  </section>
</div>

{{-- ══════════ Requirement areas ══════════ --}}
<section>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
    <div>
      <h3>Requirement areas</h3>
      <div class="desc">Where premises most often fall short &mdash; weakest first</div>
    </div>
    <div class="seg" id="sec-toggle">
      <button type="button" class="on" data-view="bar">Bars</button>
      <button type="button" data-view="radar">Radar</button>
    </div>
  </div>
  <div class="chart-box tall"><canvas id="cSections"></canvas></div>
</section>

{{-- ══════════ Map ══════════ --}}
@if(count($mapped))
<section>
  <h3>Inspected premises</h3>
  <div class="desc">{{ count($mapped) }} of {{ $overview['entities'] }} premises have recorded coordinates. Colour shows the latest compliance band.</div>
  <div id="cok-map"></div>
  <div class="legend">
    <span><i style="background:#4CAF50"></i>85% and above</span>
    <span><i style="background:#8BC34A"></i>70 to 84%</span>
    <span><i style="background:#F39C12"></i>50 to 69%</span>
    <span><i style="background:#E74C3C"></i>Below 50%</span>
  </div>
</section>
@else
<section>
  <h3>Inspected premises</h3>
  <div class="empty" style="padding:34px 20px">
    <b>No coordinates recorded yet</b>
    <span>Use <em>Capture Location</em> on the inspection form and premises will appear on a map here.</span>
  </div>
</section>
@endif

{{-- ══════════ Categories + Follow-up ══════════ --}}
<div class="grid2">
  <section>
    <h3>Inspection categories</h3>
    <div class="desc">Coverage and mean compliance</div>
    @foreach($types as $t)
      <div class="row">
        <div class="nm">{{ $t->name }}<small>{{ $t->inspections }} {{ Str::plural('inspection', $t->inspections) }}</small></div>
        <div class="track"><div class="fill {{ \App\Services\InspectionStats::band((float) ($t->compliance ?? 0)) }}"
             data-w="{{ $t->compliance ?? 0 }}" style="width:0"></div></div>
        <div class="v">{{ $t->compliance !== null ? $t->compliance.'%' : '—' }}</div>
      </div>
    @endforeach
  </section>

  <section>
    <h3>Requiring follow-up</h3>
    <div class="desc">Lowest scoring premises in your scope</div>
    @foreach($records as $r)
      <div class="row">
        <div class="nm">
          <a href="{{ route('inspection.show', $r->id) }}" style="text-decoration:none;color:inherit">{{ $r->name }}</a>
          <small>{{ $r->district }}@if($r->sector) &middot; {{ $r->sector }}@endif</small>
        </div>
        <div class="track"><div class="fill {{ \App\Services\InspectionStats::band((float) $r->compliance) }}"
             data-w="{{ $r->compliance }}" style="width:0"></div></div>
        <div class="v">{{ round((float) $r->compliance, 1) }}%</div>
      </div>
    @endforeach
  </section>
</div>

{{-- ══════════ Failures ══════════ --}}
<section>
  <h3>Most frequently failed requirements</h3>
  <div class="desc">Ranked by the number of premises found non-compliant</div>
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

<footer>
  City of Kigali &mdash; Digital Inspection Platform &middot; {{ $role }}<br>
  Figures computed {{ now()->format('j F Y, H:i') }}
</footer>


@endsection

@section('scripts')
<script>
(function () {
  var C = window.CoK;

  /* ---------- Animated counters ---------- */
  function countUp(el) {
    var target = parseFloat(el.dataset.count) || 0;
    var suffix = el.dataset.suffix || '';
    var decimals = (String(target).split('.')[1] || '').length;
    var start = performance.now(), dur = 900;

    function step(now) {
      var p = Math.min((now - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = (target * eased).toFixed(decimals) + suffix;
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target.toFixed(decimals) + suffix;
    }
    requestAnimationFrame(step);
  }

  document.querySelectorAll('[data-count]').forEach(countUp);

  /* ---------- Bars grow on reveal ---------- */
  document.querySelectorAll('.fill[data-w]').forEach(function (el, i) {
    setTimeout(function () { el.style.width = el.dataset.w + '%'; }, 120 + i * 55);
  });

  if (!window.Chart) return;

  var bandColour = function (v) {
    return v >= 85 ? C.success : v >= 70 ? '#8BC34A' : v >= 50 ? C.warning : C.danger;
  };

  /* ---------- Compliance distribution ---------- */
  var dist = @json($distribution ?? []);
  var distEl = document.getElementById('cDist');
  if (distEl && Object.keys(dist).length) {
    new Chart(distEl, {
      type: 'doughnut',
      data: {
        labels: Object.keys(dist),
        datasets: [{
          data: Object.values(dist),
          backgroundColor: [C.danger, C.warning, '#8BC34A', C.success],
          borderWidth: 4, borderColor: '#fff', hoverOffset: 14, hoverBorderWidth: 0
        }]
      },
      options: {
        maintainAspectRatio: false, cutout: '68%',
        animation: { animateRotate: true, duration: 1100, easing: 'easeOutQuart' },
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var pct = total ? Math.round(100 * ctx.parsed / total) : 0;
                return ' ' + ctx.label + ': ' + ctx.parsed + ' premises (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

  /* ---------- District performance ---------- */
  var districts = @json($districts ?? []);
  var dEl = document.getElementById('cDistrict');
  if (dEl && districts.length) {
    new Chart(dEl, {
      type: 'bar',
      data: {
        labels: districts.map(function (d) { return d.district; }),
        datasets: [
          {
            label: 'Mean compliance',
            data: districts.map(function (d) { return d.compliance; }),
            backgroundColor: districts.map(function (d) { return bandColour(d.compliance); }),
            yAxisID: 'y', maxBarThickness: 52, order: 2
          },
          {
            label: 'Inspections',
            data: districts.map(function (d) { return d.inspections; }),
            type: 'line', borderColor: C.primaryDark, backgroundColor: C.primaryDark,
            borderWidth: 2.5, pointRadius: 5, pointHoverRadius: 7, tension: .3,
            yAxisID: 'y1', order: 1
          }
        ]
      },
      options: {
        maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: { legend: { position: 'bottom' } },
        scales: {
          y:  { beginAtZero: true, max: 100, grid: { color: C.grid },
                ticks: { callback: function (v) { return v + '%'; } } },
          y1: { beginAtZero: true, position: 'right', grid: { display: false },
                ticks: { precision: 0 } },
          x:  { grid: { display: false } }
        }
      }
    });
  }

  /* ---------- Requirement areas ---------- */
  var sections = @json($sections ?? []);
  var sEl = document.getElementById('cSections');
  var secChart = null;

  function drawSections(view) {
    if (!sEl || !sections.length) return;
    if (secChart) secChart.destroy();

    var rows = view === 'radar' ? sections.slice(0, 12) : sections.slice(0, 12);
    var labels = rows.map(function (s) { return s.title; });
    var data = rows.map(function (s) { return s.compliance; });

    if (view === 'radar') {
      secChart = new Chart(sEl, {
        type: 'radar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Compliance %', data: data,
            backgroundColor: 'rgba(52,168,219,.18)', borderColor: C.primary, borderWidth: 2,
            pointBackgroundColor: data.map(bandColour), pointRadius: 4, pointHoverRadius: 6
          }]
        },
        options: {
          maintainAspectRatio: false,
          animation: { duration: 900, easing: 'easeOutQuart' },
          plugins: { legend: { display: false } },
          scales: {
            r: { beginAtZero: true, max: 100, grid: { color: C.grid },
                 angleLines: { color: C.grid },
                 pointLabels: { font: { size: 10 } },
                 ticks: { stepSize: 25, backdropColor: 'transparent',
                          callback: function (v) { return v + '%'; } } }
          }
        }
      });
    } else {
      secChart = new Chart(sEl, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{ label: 'Compliance %', data: data,
                       backgroundColor: data.map(bandColour), maxBarThickness: 22 }]
        },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          animation: { duration: 900, easing: 'easeOutQuart' },
          plugins: { legend: { display: false } },
          scales: {
            x: { beginAtZero: true, max: 100, grid: { color: C.grid },
                 ticks: { callback: function (v) { return v + '%'; } } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
          }
        }
      });
    }
  }

  drawSections('bar');

  var toggle = document.getElementById('sec-toggle');
  if (toggle) {
    toggle.addEventListener('click', function (e) {
      if (e.target.tagName !== 'BUTTON') return;
      toggle.querySelectorAll('button').forEach(function (b) { b.classList.remove('on'); });
      e.target.classList.add('on');
      drawSections(e.target.dataset.view);
    });
  }

  /* ---------- Map ---------- */
  var mapped = @json($mapped ?? []);
  if (window.L && document.getElementById('cok-map') && mapped.length) {
    var map = L.map('cok-map', { scrollWheelZoom: false });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 19
    }).addTo(map);

    var bounds = [];

    mapped.forEach(function (p) {
      var lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
      if (isNaN(lat) || isNaN(lng)) return;

      var pct = parseFloat(p.compliance) || 0;

      L.circleMarker([lat, lng], {
        radius: 9, weight: 2.5, color: '#fff',
        fillColor: bandColour(pct), fillOpacity: .92
      })
      .bindPopup(
        '<b>' + p.name + '</b>' +
        (p.sector ? p.sector + ', ' : '') + (p.district || '') + '<br>' +
        'Compliance: <strong>' + pct.toFixed(1) + '%</strong><br>' +
        'Inspected ' + p.inspection_date + '<br>' +
        '<a href="/inspect/inspections/' + p.inspection_id + '">Open report</a>'
      )
      .addTo(map);

      bounds.push([lat, lng]);
    });

    if (bounds.length === 1) map.setView(bounds[0], 15);
    else if (bounds.length) map.fitBounds(bounds, { padding: [40, 40] });
    else map.setView([-1.9441, 30.0619], 12);

    map.on('click', function () { map.scrollWheelZoom.enable(); });
    map.on('mouseout', function () { map.scrollWheelZoom.disable(); });
  }
})();
</script>
@endsection

@push('styles')
<style>
  /* ---- Work queue ---- */
  .queue-strip{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:26px}
  .q-chip{background:var(--white);padding:16px 20px;box-shadow:var(--shadow-sm);
          border-left:4px solid var(--primary);min-width:150px;flex:1}
  .q-chip.gold{border-left-color:var(--warning)}
  .q-chip.red{border-left-color:var(--danger)}
  .q-chip.green{border-left-color:var(--success)}
  .q-chip.blue{border-left-color:var(--primary)}
  .q-n{display:block;font-family:var(--f-head);font-size:26px;font-weight:800;
       color:var(--ink);line-height:1.1;font-variant-numeric:tabular-nums}
  .q-l{display:block;font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.7px;
       text-transform:uppercase;color:var(--tertiary);margin-top:4px}

  /* ---- Donut centre ---- */
  .donut-wrap{position:relative}
  .donut-centre{position:absolute;inset:0;display:flex;flex-direction:column;
                align-items:center;justify-content:center;pointer-events:none;padding-bottom:38px}
  .donut-centre b{font-family:var(--f-head);font-size:34px;font-weight:800;color:var(--primary);line-height:1}
  .donut-centre span{font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:1px;
                     text-transform:uppercase;color:var(--tertiary);margin-top:3px}

  /* ---- Segmented toggle ---- */
  .seg{display:inline-flex;border:1px solid var(--primary)}
  .seg button{font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.8px;
              text-transform:uppercase;padding:8px 16px;border:0;background:transparent;
              color:var(--primary);cursor:pointer;transition:all .2s}
  .seg button.on{background:var(--primary);color:var(--white)}

  /* ---- Map ---- */
  #cok-map{height:420px;width:100%;background:var(--canvas)}
  .legend{display:flex;gap:20px;flex-wrap:wrap;margin-top:16px;font-family:var(--f-head);
          font-size:11.5px;font-weight:600;color:var(--body-text)}
  .legend i{display:inline-block;width:12px;height:12px;margin-right:7px;vertical-align:-1px}
  .leaflet-popup-content{font-family:var(--f-body);font-size:13px;margin:12px 14px}
  .leaflet-popup-content b{font-family:var(--f-head);display:block;font-size:14px;color:var(--ink);margin-bottom:3px}
  .leaflet-popup-content-wrapper{border-radius:0}
</style>
@endpush
