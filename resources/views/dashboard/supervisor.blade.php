@extends('layouts.app')
@section('title', 'Overview')

@section('content')

@php
  $waiting = array_sum($queue);
  $active  = collect($filters)->filter()->count();

  $live = collect($byType)->filter(fn ($t) => $t->inspections > 0);
  $pendingSetup = collect($activities)->flatMap(fn ($g) => $g)
                    ->filter(fn ($t) => ! $t['live'])->count();
@endphp

{{-- ══════════ Filter panel ══════════ --}}
<form method="get" class="fpanel" id="fpanel">
  <div class="fp-head">
    <div>
      <div class="fp-title">Overview</div>
    </div>
    <button type="button" class="fp-toggle" id="fp-toggle" aria-expanded="true">
      <span>Filters</span>
      @if($active)<i class="fp-dot">{{ $active }}</i>@endif
    </button>
  </div>

  <div class="fp-body" id="fp-body">
    <div class="fp-grid">

      <div class="fp-field">
        <label for="activity">Activity</label>
        <select name="activity" id="activity">
          <option value="">All activities</option>
          @foreach($activities as $group => $types)
            <optgroup label="{{ $group }}">
              @foreach($types as $t)
                <option value="{{ $t['code'] }}">
                  {{ $t['name'] }}@if(! $t['live']) — not configured @endif
                </option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>

      <div class="fp-field">
        <label for="district">District</label>
        <select name="district" id="district" {{ $cityWide ? '' : 'disabled' }}>
          @if($cityWide)
            <option value="">All districts</option>
            @foreach(array_keys(config('kigali')) as $d)
              <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
            @endforeach
          @else
            <option>{{ $district }}</option>
          @endif
        </select>
      </div>

      <div class="fp-field">
        <label for="sector">Sector</label>
        <select name="sector" id="sector"><option value="">All sectors</option></select>
      </div>

      <div class="fp-field">
        <label for="cell">Cell</label>
        <select name="cell" id="cell"><option value="">All cells</option></select>
      </div>

      {{-- One button for everything to do with time --}}
      <div class="fp-field date-field">
        <label>Date range</label>
        <button type="button" class="date-btn" id="date-btn" aria-expanded="false"
                onclick="event.stopPropagation();document.getElementById('date-pop').classList.toggle('on')">
          <span>{{ $periodLabel }}</span>
          <i>&#9662;</i>
        </button>

        <div class="date-pop" id="date-pop">
          <div class="dp-custom">
            <div class="dp-group">Custom range</div>
            <div class="dp-row">
              <label for="from">From</label>
              <input type="date" name="from" id="from" max="{{ date('Y-m-d') }}"
                     value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="dp-row">
              <label for="to">To</label>
              <input type="date" name="to" id="to" max="{{ date('Y-m-d') }}"
                     value="{{ $filters['to'] ?? '' }}">
            </div>
            <button type="button" class="btn btn-primary btn-sm dp-apply" id="date-apply">Apply range</button>
          </div>

          <div class="dp-list">
            <button type="button" class="dp-item {{ blank($filters['period'] ?? null) ? 'on' : '' }}"
                    data-period="">All time</button>
            @foreach($periodOptions as $group => $opts)
              @if($group !== 'Choose dates')
                <div class="dp-group">{{ $group }}</div>
                @foreach($opts as $k => $label)
                  <button type="button" class="dp-item {{ ($filters['period'] ?? '') === $k ? 'on' : '' }}"
                          data-period="{{ $k }}">{{ $label }}</button>
                @endforeach
              @endif
            @endforeach
          </div>
        </div>

        <input type="hidden" name="period" id="period" value="{{ $filters['period'] ?? '' }}">
      </div>

      <div class="fp-actions">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost">Reset filters</a>
        <noscript><button type="submit" class="btn btn-secondary">Apply</button></noscript>
      </div>
    </div>
  </div>
</form>

{{-- ══════════ Work queue ══════════ --}}
@if($waiting)
<div class="queue-strip">
  @foreach($queue as $label => $n)
    <a href="{{ route('mybox') }}" class="q-chip {{ $n > 0 ? 'live' : '' }}">
      <span class="q-n">{{ $n }}</span>
      <span class="q-l">{{ $label }}</span>
    </a>
  @endforeach
</div>
@endif

{{-- ══════════ Activities ══════════ --}}
<section class="card">

  {{-- Says plainly that something is hidden, so nobody watching concludes
       the unit's remit is narrower than it is. --}}
  <div class="disp-note" id="disp-note" style="display:none">
    <span id="disp-count"></span>
    <button type="button" id="disp-reset">Show all</button>
  </div>

  @foreach($activities as $group => $types)
    <div class="act-group" data-group="{{ Str::slug($group) }}">{{ $group }}</div>
    <div class="act-grid" data-grid="{{ Str::slug($group) }}">
      @foreach($types as $t)
        @php
          $data = collect($byType)->firstWhere('code', $t['code']);
          $n    = $data->inspections ?? 0;
          $pct  = $data->compliance ?? null;
          $tone = $pct !== null ? \App\Services\InspectionStats::band((float) $pct) : 'na';
        @endphp

        <a href="{{ route('type.show', array_merge(['code' => $t['code']], array_filter([
              'district' => $filters['district'] ?? null,
              'sector'   => $filters['sector'] ?? null,
              'cell'     => $filters['cell'] ?? null,
              'period'   => $filters['period'] ?? null,
              'from'     => $filters['from'] ?? null,
              'to'       => $filters['to'] ?? null,
           ]))) }}"
           data-activity="{{ $t['code'] }}"
           class="act-box {{ $t['live'] ? '' : 'planned' }} {{ $tone }}">

          <div class="ab-name">{{ $t['name'] }}</div>

          @if($t['live'])
            <div class="ab-figure">
              <span class="ab-n">{{ $n }}</span>
              <span class="ab-unit">premises</span>
            </div>
            @if($pct !== null)
              <div class="ab-bar"><div class="ab-fill {{ $tone }}" style="width:{{ $pct }}%"></div></div>
              <div class="ab-pct">{{ $pct }}% mean compliance</div>
            @else
              <div class="ab-pct">Nothing recorded in this period</div>
            @endif
          @else
            <div class="ab-pending">Checklist not yet published</div>
          @endif

          <span class="ab-more">&rsaquo;</span>
        </a>
      @endforeach
    </div>
  @endforeach

    {{-- Presentation only. Hides boxes from view; changes no figure. --}}
    <div class="disp">
      <button type="button" class="disp-btn" id="disp-btn" aria-expanded="false">
        Display <i>&#9662;</i>
      </button>

      <div class="disp-pop" id="disp-pop">
        <div class="disp-quick">
          <button type="button" data-preset="all">All activities</button>
          <button type="button" data-preset="data">With data only</button>
          <button type="button" data-preset="none">Clear</button>
        </div>

        @foreach($activities as $group => $types)
          <div class="disp-group">{{ $group }}</div>
          @foreach($types as $t)
            @php $d = collect($byType)->firstWhere('code', $t['code']); @endphp
            <label class="disp-item">
              <input type="checkbox" class="disp-check" value="{{ $t['code'] }}"
                     data-has="{{ ($d->inspections ?? 0) > 0 ? 1 : 0 }}" checked>
              <span>{{ $t['name'] }}</span>
              @if(($d->inspections ?? 0) > 0)
                <em>{{ $d->inspections }}</em>
              @endif
            </label>
          @endforeach
        @endforeach
      </div>
    </div>

</section>

{{-- ══════════ Approval queue ══════════ --}}
@if($pending->count())
<section class="card urgent">
  <div class="card-head">
    <div>
      <h3>In the approval chain</h3>
      <div class="desc">Oldest first — these cannot move until someone acts</div>
    </div>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Premises</th><th>Stage</th><th>From</th><th>Waiting</th><th></th></tr></thead>
      <tbody>
        @foreach($pending as $d)
          <tr>
            <td><strong>{{ $d->title }}</strong><br><span class="mini">{{ ucfirst($d->type) }}</span></td>
            <td><span class="pill weak">{{ Str::headline(str_replace('pending_', '', $d->status)) }}</span></td>
            <td style="font-size:12.5px">{{ $d->creator?->name }}</td>
            <td style="font-size:12.5px">{{ $d->submitted_at?->diffForHumans() ?? '—' }}</td>
            <td class="num">
              <a href="{{ $d->type === 'letter' ? route('letter.review', $d) : route('letter.show', $d) }}"
                 class="btn btn-primary btn-sm">Review</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <a href="{{ route('mybox') }}" class="card-more"><span>My Box</span> &rsaquo;</a>
</section>
@endif

@endsection

@section('scripts')
<script>
const KIGALI = @json(config('kigali'));

/* ═══════ Filters ═══════ */
(function () {
  var panel = document.getElementById('fpanel');
  if (!panel) return;

  function apply() {
    panel.classList.add('fp-busy');
    panel.submit();
  }

  var toggle = document.getElementById('fp-toggle');
  var body   = document.getElementById('fp-body');
  if (toggle && body) {
    toggle.addEventListener('click', function () {
      toggle.setAttribute('aria-expanded', body.classList.toggle('open'));
    });
  }

  /* ---- Cascading district, sector, cell ---- */
  var dEl = document.getElementById('district');
  var sEl = document.getElementById('sector');
  var cEl = document.getElementById('cell');

  var savedSector = @json($filters['sector'] ?? '');
  var savedCell   = @json($filters['cell'] ?? '');

  function key() {
    var opt = dEl.options[dEl.selectedIndex];
    return dEl.value || (opt ? opt.textContent.trim() : '');
  }

  function fill(select, values, selected, placeholder) {
    select.innerHTML = '<option value="">' + placeholder + '</option>';
    values.forEach(function (v) {
      var o = document.createElement('option');
      o.value = v; o.textContent = v;
      if (v === selected) o.selected = true;
      select.appendChild(o);
    });
    select.disabled = values.length === 0;
  }

  function onDistrict(keep) {
    var sectors = KIGALI[key()] ? Object.keys(KIGALI[key()]) : [];
    fill(sEl, sectors, keep ? savedSector : '', 'All sectors');
    onSector(keep);
  }

  function onSector(keep) {
    var cells = (KIGALI[key()] && KIGALI[key()][sEl.value]) || [];
    fill(cEl, cells, keep ? savedCell : '', 'All cells');
  }

  onDistrict(true);

  var activity = document.getElementById('activity');
  if (activity) activity.addEventListener('change', apply);

  dEl.addEventListener('change', function () { onDistrict(false); apply(); });
  sEl.addEventListener('change', function () { onSector(false); apply(); });
  cEl.addEventListener('change', apply);

  /* ---- The date button ---- */
  var btn    = document.getElementById('date-btn');
  var pop    = document.getElementById('date-pop');
  var period = document.getElementById('period');
  var fromEl = document.getElementById('from');
  var toEl   = document.getElementById('to');

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    btn.setAttribute('aria-expanded', pop.classList.toggle('on'));
  });

  pop.addEventListener('click', function (e) { e.stopPropagation(); });

  document.addEventListener('click', function () {
    pop.classList.remove('on');
    btn.setAttribute('aria-expanded', false);
  });

  /* A preset clears any custom dates, so the two cannot disagree. */
  pop.querySelectorAll('.dp-item').forEach(function (item) {
    item.addEventListener('click', function () {
      period.value = item.dataset.period;
      fromEl.value = '';
      toEl.value = '';
      apply();
    });
  });

  document.getElementById('date-apply').addEventListener('click', function () {
    if (!fromEl.value && !toEl.value) { pop.classList.remove('on'); return; }
    period.value = 'custom';
    apply();
  });
})();

/* ═══════ Display — presentation only ═══════
   Hides activity boxes from view. Changes no figure: the totals still
   cover everything in scope, so nothing said from this screen becomes
   untrue because a box is hidden. */
(function () {
  var btn   = document.getElementById('disp-btn');
  var pop   = document.getElementById('disp-pop');
  var note  = document.getElementById('disp-note');
  var count = document.getElementById('disp-count');
  var reset = document.getElementById('disp-reset');

  if (!btn || !pop) return;

  var KEY = 'cok.display.activities';
  var checks = Array.prototype.slice.call(pop.querySelectorAll('.disp-check'));

  function apply() {
    var on = checks.filter(function (c) { return c.checked; })
                   .map(function (c) { return c.value; });

    document.querySelectorAll('.act-box').forEach(function (box) {
      box.style.display = on.indexOf(box.dataset.activity) === -1 ? 'none' : '';
    });

    /* A group whose boxes are all hidden goes too, heading included. */
    document.querySelectorAll('.act-grid').forEach(function (grid) {
      var visible = Array.prototype.slice.call(grid.querySelectorAll('.act-box'))
                      .some(function (b) { return b.style.display !== 'none'; });
      grid.style.display = visible ? '' : 'none';
      var head = document.querySelector('.act-group[data-group="' + grid.dataset.grid + '"]');
      if (head) head.style.display = visible ? '' : 'none';
    });

    var hidden = checks.length - on.length;
    try { localStorage.setItem(KEY, JSON.stringify(on)); } catch (e) {}
  }

  /* Restore whatever was chosen before the meeting. */
  try {
    var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (Array.isArray(saved) && saved.length) {
      checks.forEach(function (c) { c.checked = saved.indexOf(c.value) !== -1; });
    }
  } catch (e) {}

  apply();

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    btn.setAttribute('aria-expanded', pop.classList.toggle('on'));
  });

  pop.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', function () { pop.classList.remove('on'); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') pop.classList.remove('on');
  });

  checks.forEach(function (c) { c.addEventListener('change', apply); });

  pop.querySelectorAll('.disp-quick button').forEach(function (b) {
    b.addEventListener('click', function () {
      checks.forEach(function (c) {
        if (b.dataset.preset === 'all')       c.checked = true;
        else if (b.dataset.preset === 'none') c.checked = false;
        else                                  c.checked = c.dataset.has === '1';
      });
      apply();
    });
  });

  reset.addEventListener('click', function () {
    checks.forEach(function (c) { c.checked = true; });
    apply();
  });
})();

/* ═══════ Charts ═══════ */
(function () {
  if (!window.Chart) return;
  var C = window.CoK;
  var band = function (v) { return v >= 85 ? C.success : v >= 70 ? '#8BC34A' : v >= 50 ? C.warning : C.danger; };

  var acts = @json($byType ?? []);
  var withData = acts.filter(function (a) { return a.inspections > 0; });
  var aEl = document.getElementById('cActivity');

  if (aEl && withData.length) {
    new Chart(aEl, {
      type: 'bar',
      data: { labels: withData.map(function (a) { return a.name; }),
        datasets: [{ data: withData.map(function (a) { return a.compliance; }),
          backgroundColor: withData.map(function (a) { return band(a.compliance); }),
          maxBarThickness: 34 }] },
      options: { indexAxis: 'y', maintainAspectRatio: false,
        plugins: { legend: { display: false },
          tooltip: { callbacks: { afterLabel: function (ctx) {
            return withData[ctx.dataIndex].inspections + ' premises';
          } } } },
        scales: { x: { beginAtZero: true, max: 100, grid: { color: C.grid },
                       ticks: { callback: function (v) { return v + '%'; } } },
                  y: { grid: { display: false }, ticks: { font: { size: 11 } } } } }
    });
  }

  var districts = @json($districts ?? []);
  if (document.getElementById('cDistrict') && districts.length) {
    new Chart(document.getElementById('cDistrict'), {
      type: 'bar',
      data: { labels: districts.map(function (d) { return d.district; }),
        datasets: [
          { label: 'Compliance', data: districts.map(function (d) { return d.compliance; }),
            backgroundColor: districts.map(function (d) { return band(d.compliance); }),
            yAxisID: 'y', maxBarThickness: 48, order: 2 },
          { label: 'Inspections', data: districts.map(function (d) { return d.inspections; }),
            type: 'line', borderColor: C.primaryDark, backgroundColor: C.primaryDark,
            borderWidth: 2.5, pointRadius: 4, tension: .3, yAxisID: 'y1', order: 1 }
        ] },
      options: { maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { padding: 12, font: { size: 10.5 } } } },
        scales: { y:  { beginAtZero: true, max: 100, grid: { color: C.grid },
                        ticks: { callback: function (v) { return v + '%'; } } },
                  y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0} },
                  x:  { grid: { display: false } } } }
    });
  }
})();

/* Date range — its own closure, so a fault anywhere else on the page
   cannot stop it. This control failed silently because a script above
   it threw and everything after stopped. */
(function () {
  var pop    = document.getElementById('date-pop');
  var panel  = document.getElementById('fpanel');
  var period = document.getElementById('period');
  var fromEl = document.getElementById('from');
  var toEl   = document.getElementById('to');
  if (!pop || !panel) return;

  pop.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', function () { pop.classList.remove('on'); });

  /* The browser's date picker is drawn outside the popover, so a click
     in it would otherwise close the panel before a date is chosen. */
  pop.querySelectorAll('input[type=date]').forEach(function (el) {
    ['click', 'mousedown', 'focus'].forEach(function (ev) {
      el.addEventListener(ev, function (e) { e.stopPropagation(); });
    });
  });

  function go() { panel.classList.add('fp-busy'); panel.submit(); }

  pop.querySelectorAll('.dp-item').forEach(function (i) {
    i.addEventListener('click', function () {
      period.value = i.dataset.period;
      if (fromEl) fromEl.value = '';
      if (toEl) toEl.value = '';
      go();
    });
  });

  var ap = document.getElementById('date-apply');
  if (ap) ap.addEventListener('click', function () {
    if (!fromEl.value && !toEl.value) { pop.classList.remove('on'); return; }
    period.value = 'custom';
    go();
  });
})();
</script>
@endsection

@push('styles')
<style>
  /* ═══════ Filter panel ═══════ */
  .fpanel{background:var(--white);margin-bottom:22px;box-shadow:var(--shadow-sm);
          border-top:3px solid var(--primary)}
  .fp-head{display:flex;justify-content:space-between;align-items:center;gap:16px;
           padding:18px 22px;flex-wrap:wrap}
  .fp-title{font-family:var(--f-head);font-size:19px;font-weight:700;color:var(--ink)}
  .fp-sub{font-size:13px;color:var(--body-text);margin-top:2px}
  .fp-toggle{display:none;align-items:center;gap:9px;background:transparent;
             border:1px solid var(--primary);color:var(--primary);padding:9px 15px;cursor:pointer;
             font-family:var(--f-head);font-size:11.5px;font-weight:600;letter-spacing:.8px;
             text-transform:uppercase}
  .fp-dot{display:inline-flex;align-items:center;justify-content:center;min-width:19px;height:19px;
          background:var(--primary);color:#fff;font-size:11px;font-style:normal;font-weight:700}
  .fp-body{border-top:1px solid var(--border);padding:18px 22px}
  .fp-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));align-items:end}
  .fp-field label{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .fp-field select{width:100%;padding:10px 12px;border:1px solid var(--border);
                   font-family:var(--f-body);font-size:14px;background:var(--white);color:var(--ink)}
  .fp-field select:focus{outline:none;border-color:var(--primary)}
  .fp-field select:disabled{background:var(--canvas);color:var(--body-text)}
  .fp-actions{display:flex;gap:9px}
  .fp-actions .btn{flex:1}
  .fp-busy{opacity:.45;pointer-events:none;transition:opacity .15s}

  /* ═══════ The one date button ═══════ */
  .date-field{position:relative}
  .date-btn{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;
            padding:10px 12px;border:1px solid var(--border);background:var(--white);
            font-family:var(--f-body);font-size:14px;color:var(--ink);cursor:pointer;text-align:left}
  .date-btn:hover{border-color:var(--primary)}
  .date-btn span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .date-btn i{font-style:normal;color:var(--tertiary);font-size:11px;flex-shrink:0}
  .date-pop{display:none;position:absolute;z-index:90;top:100%;right:0;width:300px;
            background:var(--white);border:1px solid var(--border);box-shadow:var(--shadow);
            margin-top:4px;max-height:420px;overflow-y:auto}
  .date-pop.on{display:block}
  .dp-list{padding:6px 0}
  .dp-group{font-family:var(--f-head);font-size:9.5px;font-weight:600;letter-spacing:1px;
            text-transform:uppercase;color:var(--tertiary);padding:10px 16px 5px}
  .dp-item{display:block;width:100%;text-align:left;padding:8px 16px;border:0;background:none;
           font-family:var(--f-body);font-size:13.5px;color:var(--ink);cursor:pointer}
  .dp-item:hover{background:var(--canvas)}
  .dp-item.on{background:var(--primary);color:#fff;font-weight:600}
  .dp-custom{border-bottom:1px solid var(--border);padding:6px 16px 16px}
  .dp-row{margin-bottom:10px}
  .dp-row label{display:block;font-family:var(--f-head);font-size:9.5px;font-weight:600;
                letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:4px}
  .dp-row input[type=date]{width:100%;padding:8px 10px;border:1px solid var(--border);
                           font-family:var(--f-body);font-size:13.5px;color:var(--ink)}
  .dp-row input[type=date]:focus{outline:none;border-color:var(--primary)}
  .dp-note{font-size:11px;color:var(--tertiary);margin-bottom:10px}
  .dp-apply{width:100%}

  /* ═══════ Display control — presentation only ═══════ */
  /* The card would otherwise clip a popover opening upward from
     its foot, so the card is allowed to overflow. */
  .card{overflow:visible}
  .disp{position:absolute;right:14px;bottom:10px;z-index:200}
  .disp-btn{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;
            border:1px solid var(--border);background:var(--white);color:var(--body-text);
            font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.7px;
            text-transform:uppercase;cursor:pointer;transition:all .2s}
  .disp-btn:hover{border-color:var(--primary);color:var(--primary)}
  .disp-btn i{font-style:normal;font-size:9px}
  .disp-pop{display:none;position:fixed;z-index:400;right:24px;bottom:70px;width:290px;
            max-height:400px;overflow-y:auto;background:var(--white);
            border:1px solid var(--border);box-shadow:var(--shadow);margin-top:4px}
  .disp-pop.on{display:block}
  .disp-quick{display:flex;gap:1px;background:var(--border);border-bottom:1px solid var(--border)}
  .disp-quick button{flex:1;padding:9px 4px;border:0;background:var(--white);
                     font-family:var(--f-head);font-size:9.5px;font-weight:600;
                     letter-spacing:.5px;text-transform:uppercase;color:var(--primary);cursor:pointer}
  .disp-quick button:hover{background:var(--canvas)}
  .disp-group{font-family:var(--f-head);font-size:9px;font-weight:600;letter-spacing:1px;
              text-transform:uppercase;color:var(--tertiary);padding:11px 15px 4px}
  .disp-item{display:flex;align-items:center;gap:9px;padding:6px 15px;cursor:pointer;
             font-size:13px;color:var(--ink)}
  .disp-item:hover{background:var(--canvas)}
  .disp-item input{accent-color:var(--primary);cursor:pointer}
  .disp-item span{flex:1}
  .disp-item em{font-family:var(--f-head);font-size:10.5px;font-style:normal;color:var(--tertiary)}

  .disp-note{display:flex;align-items:center;gap:12px;padding:9px 13px;margin-bottom:14px;
             background:#FFF8E5;border-left:3px solid var(--warning);font-size:12.5px;
             color:#6B4A00}
  .disp-note button{border:0;background:none;color:var(--primary);cursor:pointer;
                    font-family:var(--f-head);font-size:10.5px;font-weight:600;
                    letter-spacing:.6px;text-transform:uppercase}
  .disp-note button:hover{text-decoration:underline}

  /* ═══════ Queue ═══════ */
  .queue-strip{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:22px}
  .q-chip{background:var(--white);padding:16px 20px;box-shadow:var(--shadow-sm);
          border-left:4px solid var(--border);text-decoration:none;transition:all .2s}
  .q-chip.live{border-left-color:var(--danger)}
  .q-chip:hover{box-shadow:var(--shadow)}
  .q-n{display:block;font-family:var(--f-head);font-size:26px;font-weight:800;color:var(--ink);line-height:1.1}
  .q-l{display:block;font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.7px;
       text-transform:uppercase;color:var(--tertiary);margin-top:4px}

  /* ═══════ Cards ═══════ */
  .card{position:relative;background:var(--white);padding:22px 24px 40px;margin:0 0 20px;
        box-shadow:var(--shadow-sm)}
  .card.urgent{border-left:4px solid var(--danger)}
  .card-more{position:absolute;right:14px;bottom:10px;display:inline-flex;align-items:center;gap:5px;
             padding:5px 11px;background:var(--canvas);color:var(--tertiary);text-decoration:none;
             font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.7px;
             text-transform:uppercase;transition:all .2s}
  .card-more:hover{background:var(--primary);color:#fff}
  .card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;
             flex-wrap:wrap;margin-bottom:16px}
  .card-head h3{margin-bottom:2px}
  .card-head .desc{margin-bottom:0}

  /* ═══════ Activity boxes ═══════ */
  .act-group{font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:1px;
             text-transform:uppercase;color:var(--tertiary);margin:20px 0 12px;
             padding-bottom:7px;border-bottom:1px solid var(--border)}
  .act-group:first-of-type{margin-top:0}
  .act-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(215px,1fr))}
  .act-box{position:relative;display:block;background:var(--canvas);padding:18px 18px 20px;
           text-decoration:none;border-left:4px solid var(--border);transition:all .2s}
  .act-box:hover{background:var(--white);box-shadow:var(--shadow-sm);transform:translateY(-2px)}
  .act-box.good{border-left-color:var(--success)}
  .act-box.fair{border-left-color:#8BC34A}
  .act-box.weak{border-left-color:var(--warning)}
  .act-box.poor{border-left-color:var(--danger)}
  .act-box.planned{opacity:.62}
  .ab-name{font-family:var(--f-head);font-size:14px;font-weight:600;color:var(--ink);
           line-height:1.35;margin-bottom:10px;padding-right:18px}
  .ab-figure{display:flex;align-items:baseline;gap:6px;margin-bottom:9px}
  .ab-n{font-family:var(--f-head);font-size:27px;font-weight:800;color:var(--primary);line-height:1}
  .ab-unit{font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.6px;
           text-transform:uppercase;color:var(--tertiary)}
  .ab-bar{height:6px;background:var(--border);overflow:hidden;margin-bottom:6px}
  .ab-fill{height:100%}
  .ab-fill.good{background:var(--success)} .ab-fill.fair{background:#8BC34A}
  .ab-fill.weak{background:var(--warning)} .ab-fill.poor{background:var(--danger)}
  .ab-pct{font-size:11.5px;color:var(--body-text)}
  .ab-pending{font-size:11.5px;color:var(--tertiary);font-style:italic;padding:8px 0}
  .ab-more{position:absolute;right:12px;top:16px;font-size:19px;color:var(--tertiary);line-height:1}
  .act-box:hover .ab-more{color:var(--primary)}

  .rowlink{text-decoration:none;color:inherit;transition:background .15s}
  .rowlink:hover{background:var(--canvas)}
  .mini{font-size:11.5px;color:var(--tertiary)}

  /* ═══════ Responsive ═══════ */
  .chart-grid{display:grid;gap:20px;grid-template-columns:1fr}
  @media(min-width:700px){.chart-grid{grid-template-columns:repeat(2,1fr)}}
  @media(min-width:1100px){
    .chart-box{height:250px}
    .act-grid{grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
  }
  @media(min-width:1600px){.container{max-width:1560px}.chart-box{height:270px}}
  @media(max-width:699px){
    .fp-toggle{display:inline-flex}
    .fp-body{display:none}
    .fp-body.open{display:block}
    .fp-head{padding:15px 16px}
    .fp-body{padding:16px}
    .fp-title{font-size:16px}
    .card{padding:18px 16px 38px}
    .chart-box{height:230px}
    .act-grid{grid-template-columns:1fr}
    .date-pop{right:auto;left:0;width:100%;min-width:260px}
    .disp-pop{right:0;width:260px}
  }

  /* Nothing about the display control belongs on paper. */
  @media print{.disp,.disp-note,.fpanel{display:none!important}}
</style>
@endpush