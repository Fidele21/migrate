@extends('layouts.app')
@section('title', $type['name'])

@php
  use App\Support\ComplianceBand;

  $active = collect($filters)->filter()->count();
  $q      = collect($filters)->filter()->all();

  /* Which two upper panels this category shows. Declared in config so a
     category is not stuck with something designed for another: siting
     against operations decides a petrol station's fate, while an
     occupied building is better read by district and by ranking. */
  $panels = $panels ?? ['districts', 'ranking'];
@endphp

@section('content')

{{-- ══════════ Filter bar ══════════ --}}
<form method="get" class="fbar" id="fpanel">
  <div class="fb-id">
    <span class="fb-crumb">{{ $type['group'] }}</span>
    <span class="fb-name">{{ $type['name'] }}</span>
    <span class="fb-scope">{{ $scopeName }} &middot; {{ $periodLabel }}</span>
  </div>

  <div class="fb-fields">

    <div class="fb-group">
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

    <div class="fb-group">
      <label for="sector">Sector</label>
      <select name="sector" id="sector"><option value="">All sectors</option></select>
    </div>

    <div class="fb-group">
      <label for="cell">Cell</label>
      <select name="cell" id="cell"><option value="">All cells</option></select>
    </div>

    <div class="fb-group date-field">
      <label for="date-btn">Period</label>
      <button type="button" class="date-btn" id="date-btn" aria-expanded="false"
              onclick="event.stopPropagation();document.getElementById('date-pop').classList.toggle('on')">
        <span>{{ $periodLabel }}</span><i>&#9662;</i>
      </button>

      <div class="date-pop" id="date-pop">
        <div class="dp-custom" style="border-top:none">
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

    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ route('type.show', $type['code']) }}" class="fb-reset {{ $active ? 'live' : '' }}">
        Reset @if($active)<i>{{ $active }}</i>@endif
      </a>
    </div>

    <noscript>
      <div class="fb-group fb-end">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
      </div>
    </noscript>
  </div>
</form>

@if($figures['premises'] === 0)

<section class="panel" style="padding:60px 24px">
  <div class="empty">
    <b>No premises inspected in this selection</b>
    <span>Nothing recorded for {{ $type['name'] }} in {{ $scopeName }} during {{ strtolower($periodLabel) }}.</span>
    <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      @can('inspection.create')
        <a href="{{ route('entity.add', $type['code']) }}" class="btn btn-success">Add File</a>
      @endcan
      <a href="{{ route('type.show', $type['code']) }}" class="btn btn-ghost">Reset filters</a>
    </div>
  </div>
</section>

@else

{{-- ══════════ Figures ══════════ --}}
<div class="kpi-row">
  <a href="{{ route('register.index', array_merge(['code' => $type['code']], $q)) }}" class="kpi">
    <span class="k-label">Premises</span>
    <b class="k-value">{{ $figures['premises'] }}</b>
    <span class="k-note">inspected</span>
  </a>

  <a href="{{ route('register.index', array_merge(['code' => $type['code']], $q)) }}" class="kpi">
    <span class="k-label">Inspections</span>
    <b class="k-value">{{ $figures['inspections'] }}</b>
    <span class="k-note">{{ $figures['frequency'] }} per premises</span>
  </a>

  <a href="{{ route('register.index', array_merge(['code' => $type['code'], 'visits' => 'repeat'], $q)) }}" class="kpi">
    <span class="k-label">Visited more than once</span>
    <b class="k-value">{{ $figures['repeat'] }}</b>
    <span class="k-note">{{ $figures['premises'] ? round(100 * $figures['repeat'] / $figures['premises']) : 0 }}% of premises</span>
  </a>

  <a href="{{ route('register.index', array_merge(['code' => $type['code'], 'band' => 'compliant'], $q)) }}" class="kpi good">
    <span class="k-label">Compliant</span>
    <b class="k-value">{{ $figures['compliant'] }}</b>
    <span class="k-note">98% and above</span>
  </a>

  <a href="{{ route('register.index', array_merge(['code' => $type['code'], 'band' => 'uncompliant'], $q)) }}" class="kpi bad">
    <span class="k-label">Non-compliant</span>
    <b class="k-value">{{ $figures['uncompliant'] }}</b>
    <span class="k-note">below 98%</span>
  </a>

  <a href="{{ route('analysis.group', array_merge(['code' => $type['code'], 'group' => 'all'], $q)) }}" class="kpi accent">
    <span class="k-label">Mean compliance</span>
    <b class="k-value">{{ $figures['mean'] }}<i>%</i></b>
    <span class="k-note">across premises</span>
  </a>
</div>

{{-- ══════════ Main grid ══════════ --}}
<div class="main-grid">

  {{-- The map, across the centre --}}
  <section class="panel map-panel" id="map-panel">
    <div class="p-head">
      <div>
        <h3>Deliberation Map</h3>
        <div class="p-desc">{{ count($mapped) }} of {{ $figures['premises'] }} premises located</div>
      </div>
      <button type="button" class="icon-btn" id="map-full" title="Full view">&#10530;</button>
    </div>

    @if(count($mapped))
      <div id="cok-map"></div>
    @else
      <div class="empty map-empty">
        <b>No coordinates recorded</b>
        <span>Use <em>Capture Location</em> on the premises record.</span>
      </div>
    @endif

    <div class="band-row">
      @foreach($tally as $key => $b)
        <a href="{{ route('register.index', array_merge(['code' => $type['code'], 'band' => $key], $q)) }}"
           class="band" style="--c:{{ $b['colour'] }}" title="{{ $b['note'] }}">
          <b>{{ $b['count'] }}</b>
          <span>{{ $b['short'] }}</span>
        </a>
      @endforeach
    </div>
  </section>

  {{-- The two panels this category chooses --}}
  @include('partials.panels', ['panel' => $panels[0] ?? 'districts', 'slot' => 'siting'])
  @include('partials.panels', ['panel' => $panels[1] ?? 'ranking',   'slot' => 'ops'])

  {{-- Analysis by criteria (petrol: permanent closure list instead) --}}
  <section class="panel area-analysis">
      @if($type['code'] === 'petrol')
      <div class="p-head compact">
        <div>
          <h3>Permanent Closure</h3>
          
        </div>
        <div class="p-tools">
          <button type="button" class="icon-btn" id="pc-toggle" title="Why">&#9432;</button>
          <a href="{{ route('register.index', $type['code']) }}?band=permanent"
             class="icon-btn" title="Full register">&rsaquo;</a>
        </div>
      </div>

            <button type="button" class="pc-headline-btn" id="pc-toggle">
        <span class="pc-headline-n">{{ $permanentStations->count() }}</span>
        <span class="pc-headline-label">{{ Str::plural('station', $permanentStations->count()) }} must be permanently closed</span>
        
      </button>

      <div class="pc-modal-overlay" id="pc-modal-overlay" style="display:none">
        <div class="pc-modal" role="dialog" aria-modal="true">
          <div class="pc-modal-head">
            <h3>Why stations must be permanently closure</h3>
            <button type="button" class="pc-modal-close" id="pc-modal-close" aria-label="Close">&times;</button>
          </div>

          <div class="pc-modal-body">
            <div class="pc-modal-total">
              <span class="pc-modal-total-n">{{ $permanentStations->count() }}</span>
              <span>petrol {{ Str::plural('station', $permanentStations->count()) }} must be permanently closed</span>
            </div>

            <h4>By reason</h4>
            <div class="pc-bars">
              @foreach($permanentBreakdown['bars'] as $b)
                <div class="pc-bar-row">
                  <div class="pc-bar-label">{{ $b['label'] }}</div>
                  <div class="pc-bar-track">
                    <div class="pc-bar-fill" style="width:{{ $b['pct'] }}%;background:{{ $b['colour'] }}"></div>
                  </div>
                  <div class="pc-bar-stats"><b>{{ $b['failed'] }}</b> of {{ $b['assessed'] }} <span>({{ $b['rate'] }}%)</span></div>
                </div>
              @endforeach
              <div class="pc-bar-note">A station can fail more than one reason — bars are independent, not parts of one total.</div>
            </div>

            <h4>How many reasons at once</h4>
            <div class="pc-combos">
              @foreach($permanentBreakdown['combinations'] as $n => $count)
                <div class="pc-combo">
                  <span class="pc-combo-n">{{ $count }}</span>
                  <span class="pc-combo-label">{{ $count === 1 ? 'station fails' : 'stations fail' }} exactly {{ $n }} {{ Str::plural('reason', $n) }} at once</span>
                </div>
              @endforeach
            </div>

            <a href="{{ route('register.index', $type['code']) }}?band=permanent" class="btn btn-primary pc-modal-cta">
              Open the full register &rsaquo;
            </a>
          </div>
        </div>
      </div>
    @else
      <div class="p-head compact">
        <div>
          <h3>Analysis by Criteria</h3>
          <div class="p-desc">Premises failing each requirement</div>
        </div>
        <div class="p-tools">
          <button type="button" class="icon-btn scroll-up" data-target="crit-scroll" title="Up">&#9650;</button>
          <button type="button" class="icon-btn scroll-down" data-target="crit-scroll" title="Down">&#9660;</button>
          <a href="{{ route('analysis.group', array_merge(['code' => $type['code'], 'group' => 'all'], $q)) }}"
             class="icon-btn" title="Detail">&rsaquo;</a>
        </div>
      </div>
      <div class="scroller" id="crit-scroll">
        @foreach($criteria as $c)
          <a href="{{ route('analysis.item', array_merge(['code' => $type['code'], 'item' => $c->item_id], $q)) }}"
             class="crit">
            <div class="c-n">{{ $c->premises_failed }}</div>
            <div class="c-mid">
              <div class="c-label">{{ Str::limit(rtrim($c->label, '. '), 62) }}</div>
              <div class="c-track">
                <div class="c-fill" style="width:{{ $c->premises_assessed ? round(100 * $c->premises_failed / $c->premises_assessed) : 0 }}%"></div>
              </div>
            </div>
            <div class="c-pct">{{ $c->compliance }}%</div>
          </a>
        @endforeach
      </div>
    @endif
  </section>
  
  {{-- Criteria compliance --}}
  <section class="panel area-criteria">
    <div class="p-head compact">
      <div>
        <h3>Criteria Compliance</h3>
        <div class="p-desc">Sections, weakest first</div>
      </div>
      <div class="p-tools">
        <div class="seg" id="sec-toggle">
          <button type="button" class="on" data-view="bar" title="Bars">&#9636;</button>
          <button type="button" data-view="radar" title="Radar">&#9673;</button>
        </div>
        <a href="{{ route('analysis.group', array_merge(['code' => $type['code'], 'group' => 'all'], $q)) }}"
           class="icon-btn" title="Detail">&rsaquo;</a>
      </div>
    </div>
    <div class="chart-box"><canvas id="cSections"></canvas></div>
  </section>

</div>

@endif
@endsection

@push('scripts')
<script>
(function () {
  var btn     = document.getElementById('pc-toggle');
  var overlay = document.getElementById('pc-modal-overlay');
  var close   = document.getElementById('pc-modal-close');
  if (!btn || !overlay) return;

  function open() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  function shut() { overlay.style.display = 'none'; document.body.style.overflow = ''; }

  btn.addEventListener('click', open);
  if (close) close.addEventListener('click', shut);

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) shut();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.style.display !== 'none') shut();
  });
})();
</script>
@endpush

@push('styles')
<style>
  /* ═══════ One screen, no page scroll ═══════ */
  .content{padding-bottom:16px}

  /* ═══════ Filter bar additions ═══════ */
  .fb-add{display:inline-flex;align-items:center;height:35px;padding:0 15px;
          background:var(--success);color:#fff;text-decoration:none;
          font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.8px;
          text-transform:uppercase;transition:background .2s;white-space:nowrap}
  .fb-add:hover{background:var(--success-hover)}

  /* ═══════ Figures ═══════ */
  .kpi-row{display:grid;gap:10px;grid-template-columns:repeat(2,1fr);margin-bottom:12px}
  .kpi{background:var(--white);box-shadow:var(--shadow-sm);padding:10px 13px;
       border-top:3px solid var(--primary);text-decoration:none;display:block;transition:all .18s}
  .kpi:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
  .kpi.good{border-top-color:var(--success)}
  .kpi.bad{border-top-color:var(--danger)}
  .kpi.accent{border-top-color:var(--secondary)}
  .k-label{display:block;font-family:var(--f-head);font-size:8.5px;font-weight:600;
           letter-spacing:.9px;text-transform:uppercase;color:var(--tertiary);line-height:1.3}
  .k-value{display:block;font-family:var(--f-head);font-size:23px;font-weight:800;
           color:var(--ink);line-height:1.1;margin-top:2px}
  .k-value i{font-style:normal;font-size:13px;color:var(--tertiary)}
  .kpi.good .k-value{color:var(--success)}
  .kpi.bad .k-value{color:var(--danger)}
  .kpi.accent .k-value{color:var(--primary)}
  .k-note{display:block;font-size:10px;color:var(--body-text);margin-top:1px}

  /* ═══════ Panels ═══════ */
  .panel{background:var(--white);box-shadow:var(--shadow-sm);padding:12px 14px;
         display:flex;flex-direction:column;min-height:0;overflow:hidden}
  .p-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;
          margin-bottom:8px;flex-shrink:0}
  .p-head.compact{align-items:center}
  .p-head h3{font-family:var(--f-head);font-size:13px;font-weight:600;color:var(--ink);margin:0}
  .p-desc{font-size:10.5px;color:var(--body-text);margin-top:1px}
  .p-tools{display:flex;gap:5px;align-items:center;flex-shrink:0}
  .icon-btn{width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;
            border:1px solid var(--border);background:var(--white);color:var(--tertiary);
            font-size:12px;line-height:1;cursor:pointer;text-decoration:none;transition:all .18s}
  .icon-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff}
  .seg{display:inline-flex;border:1px solid var(--border)}
  .seg button{width:24px;height:24px;border:0;background:var(--white);color:var(--tertiary);
              font-size:11px;cursor:pointer}
  .seg button.on{background:var(--primary);color:#fff}
  .p-empty{flex:1;display:flex;align-items:center;justify-content:center;
           font-size:12px;color:var(--tertiary);text-align:center;padding:20px}

  /* ═══════ Map ═══════ */
  #cok-map{flex:1;min-height:200px;width:100%;background:var(--canvas)}
  .map-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center}

  .band-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);
            margin-top:10px;flex-shrink:0}
  .band{background:var(--white);padding:8px 6px;text-align:center;border-top:3px solid var(--c);
        text-decoration:none;transition:background .18s}
  .band:hover{background:var(--canvas)}
  .band b{display:block;font-family:var(--f-head);font-size:19px;font-weight:800;color:var(--c);line-height:1}
  .band span{display:block;font-family:var(--f-head);font-size:8.5px;font-weight:600;
             letter-spacing:.4px;text-transform:uppercase;color:var(--tertiary);margin-top:2px}

  body.map-open .map-panel{position:fixed;inset:0;z-index:400;margin:0;padding:14px 18px}
  body.map-open #cok-map{min-height:0}

  /* ═══════ Gauges ═══════ */
  .gauge{position:relative;flex:1;min-height:96px}
  .gauge-centre{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                pointer-events:none;padding-top:18px}
  .gauge-centre b{font-family:var(--f-head);font-size:27px;font-weight:800;color:var(--ink);line-height:1}
  .gauge-centre i{font-style:normal;font-size:14px;color:var(--tertiary)}
  .g-note{font-size:10px;color:var(--tertiary);text-align:center;flex-shrink:0;margin-top:4px}

  /* ═══════ District and sector comparison ═══════ */
  .area-list{flex:1;min-height:0;overflow-y:auto}
  .area-row{display:grid;grid-template-columns:1fr 60px 38px;gap:9px;align-items:center;
            padding:7px 2px;border-bottom:1px solid var(--border);
            text-decoration:none;transition:background .15s}
  .area-row:last-child{border-bottom:none}
  .area-row:hover{background:var(--canvas)}
  .ar-name{font-family:var(--f-head);font-size:12.5px;font-weight:600;color:var(--ink);
           overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ar-name span{display:block;font-family:var(--f-body);font-weight:400;
                font-size:10.5px;color:var(--tertiary)}
  .ar-track{height:6px;background:var(--canvas);overflow:hidden}
  .ar-fill{height:100%}
  .ar-fill.good{background:var(--success)} .ar-fill.fair{background:#8BC34A}
  .ar-fill.weak{background:var(--warning)} .ar-fill.poor{background:var(--danger)}
  .ar-pct{font-family:var(--f-head);font-size:11.5px;font-weight:700;
          color:var(--body-text);text-align:right}

  /* ═══════ Premises ranking ═══════ */
  .rank-row{display:grid;grid-template-columns:24px 1fr 42px;gap:9px;align-items:center;
            padding:7px 2px;border-bottom:1px solid var(--border);
            text-decoration:none;transition:background .15s}
  .rank-row:last-child{border-bottom:none}
  .rank-row:hover{background:var(--canvas)}
  .rk-n{font-family:var(--f-head);font-size:11px;font-weight:700;color:var(--tertiary);text-align:center}
  .rk-name{font-size:12px;color:var(--ink);line-height:1.35;margin-bottom:4px;
           overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rk-track{height:5px;background:var(--canvas);overflow:hidden}
  .rk-fill{height:100%}
  .rk-fill.good{background:var(--success)} .rk-fill.fair{background:#8BC34A}
  .rk-fill.weak{background:var(--warning)} .rk-fill.poor{background:var(--danger)}
  .rk-pct{font-family:var(--f-head);font-size:11px;font-weight:700;text-align:right}
  .rk-pct.good{color:var(--success)} .rk-pct.fair{color:#8BC34A}
  .rk-pct.weak{color:var(--warning)} .rk-pct.poor{color:var(--danger)}

  /* ═══════ Criteria list ═══════ */
  .scroller{flex:1;min-height:0;overflow-y:auto;scroll-behavior:smooth}
  .scroller::-webkit-scrollbar{width:5px}
  .scroller::-webkit-scrollbar-thumb{background:var(--border)}
  .crit{display:grid;grid-template-columns:30px 1fr 38px;gap:9px;align-items:center;
        padding:6px 2px;border-bottom:1px solid var(--border);text-decoration:none;transition:background .15s}
  .crit:last-child{border-bottom:none}
  .crit:hover{background:var(--canvas)}
  .crit-permanent{border-left:3px solid #B71C1C}
  .crit-permanent .c-n{color:#B71C1C;font-weight:800}
   .crit-headline{display:flex;align-items:baseline;gap:9px;padding:6px 4px 12px}
  .crit-headline-n{font-family:var(--f-head);font-size:32px;font-weight:800;color:#B71C1C;line-height:1}
  .crit-headline-label{font-size:12.5px;color:var(--tertiary);text-transform:uppercase;letter-spacing:.5px;font-weight:600}
  .crit-permanent{border-left:3px solid #B71C1C}
  .crit-permanent .c-n{color:#B71C1C;font-weight:800;font-variant-numeric:tabular-nums}
  .crit-empty{padding:24px 8px;text-align:center;color:var(--tertiary);font-size:13px}
  .c-n{font-family:var(--f-head);font-size:15px;font-weight:800;color:var(--danger);text-align:center}
  .c-label{font-size:11.5px;color:var(--ink);line-height:1.35;margin-bottom:3px}
  .c-track{height:4px;background:var(--canvas);overflow:hidden}
  .c-fill{height:100%;background:var(--danger)}
  .c-pct{font-family:var(--f-head);font-size:11px;font-weight:700;color:var(--body-text);text-align:right}

  /* ═══════ Layout ═══════ */
  .main-grid{display:grid;gap:12px;grid-template-columns:1fr}
  .main-grid .panel{min-height:220px}

  @media(min-width:700px){
    .kpi-row{grid-template-columns:repeat(3,1fr)}
    .main-grid{grid-template-columns:repeat(2,1fr)}
    .map-panel{grid-column:1 / -1;order:-1;min-height:320px}
  }

  /* One screen from here up */
  @media(min-width:1100px){
    .kpi-row{grid-template-columns:repeat(6,1fr)}
    .main-grid{
      grid-template-columns:1fr 2fr 1fr;
      grid-template-rows:1fr 1fr;
      grid-template-areas:
        "siting   map  ops"
        "analysis map  criteria";
      height:calc(100vh - var(--topbar-h) - 218px);
      min-height:420px;
    }
    .map-panel{grid-area:map;order:0;min-height:0}
    .area-siting{grid-area:siting;min-height:0}
    .area-ops{grid-area:ops;min-height:0}
    .area-analysis{grid-area:analysis;min-height:0}
    .area-criteria{grid-area:criteria;min-height:0}
    .main-grid .panel{min-height:0}
  }

  @media(min-width:1600px){
    .container{max-width:1680px}
    .main-grid{grid-template-columns:1fr 2.3fr 1fr}
    .k-value{font-size:26px}
  }

  @media(max-width:720px){
    .fbar{flex-direction:column;align-items:stretch}
    .fb-fields{justify-content:stretch}
    .main-grid .panel{min-height:260px}
    .band-row{grid-template-columns:repeat(2,1fr)}
  }

  /* ---- Premises ranked, as columns (scrollable) ---- */
  .rank-scroll{flex:1 1 auto;min-height:0;overflow:auto;height:100%}
  .rank-scroll::-webkit-scrollbar{height:6px;width:6px}
  .rank-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
  .rank-scroll::-webkit-scrollbar-track{background:var(--canvas)}
  .rank-canvas{height:100%;min-height:150px;position:relative}

  .rank-legend{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;flex-shrink:0;
               font-family:var(--f-head);font-size:9px;font-weight:600;
               letter-spacing:.4px;text-transform:uppercase;color:var(--tertiary)}
  .rank-legend i{display:inline-block;width:9px;height:9px;margin-right:4px;vertical-align:-1px}

  /* Full screen: the columns need width more than anything else here. */
  body.rank-open #rank-panel{position:fixed;inset:0;z-index:400;margin:0;padding:16px 20px}
  body.rank-open .rank-scroll{min-height:0}

  /* ---- District and sector performance ---- */
  .area-wrap{flex:1;min-height:130px;position:relative}
  .area-legend{display:flex;gap:14px;margin-top:8px;flex-shrink:0;
               font-family:var(--f-head);font-size:9px;font-weight:600;
               letter-spacing:.4px;text-transform:uppercase;color:var(--tertiary)}
  .area-legend i{display:inline-block;margin-right:5px;vertical-align:1px}
  .lg-bar{width:9px;height:9px;background:var(--primary)}
  .lg-line{width:14px;height:2px;background:var(--danger);vertical-align:4px!important}

  /* ---- Permanent closure modal and breakdown ---- */
  .pc-headline{display:flex;align-items:baseline;gap:10px;padding:8px 4px 6px}
  .pc-headline-n{font-family:var(--f-head);font-size:40px;font-weight:800;color:#B71C1C;line-height:1}
  .pc-headline-label{font-size:13px;color:var(--tertiary);text-transform:uppercase;letter-spacing:.5px;font-weight:600}

  .pc-headline-btn{display:flex;align-items:baseline;gap:10px;padding:8px 4px 6px;
    background:none;border:0;cursor:pointer;width:100%;text-align:left}
  .pc-headline-n{font-family:var(--f-head);font-size:40px;font-weight:800;color:#B71C1C;line-height:1}
  .pc-headline-label{font-size:13px;color:var(--tertiary);text-transform:uppercase;letter-spacing:.5px;font-weight:600}
  .pc-headline-more{margin-left:auto;font-size:12px;color:var(--primary);font-weight:600}
  .pc-headline-btn:hover .pc-headline-more{text-decoration:underline}

  .pc-modal-overlay{position:fixed;inset:0;background:rgba(20,24,32,.55);z-index:1000;
    display:flex;align-items:center;justify-content:center;padding:20px}
  .pc-modal{background:#fff;border-radius:12px;max-width:560px;width:100%;
    max-height:86vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
  .pc-modal-head{display:flex;justify-content:space-between;align-items:center;
    padding:18px 22px;border-bottom:1px solid var(--border)}
  .pc-modal-head h3{font-size:16px;margin:0}
  .pc-modal-close{background:none;border:0;font-size:24px;line-height:1;cursor:pointer;
    color:var(--tertiary);padding:0 4px}
  .pc-modal-close:hover{color:var(--ink)}
  .pc-modal-body{padding:20px 22px}
  .pc-modal-body h4{font-size:11px;text-transform:uppercase;letter-spacing:.5px;
    color:var(--tertiary);margin:20px 0 10px;font-weight:700}
  .pc-modal-body h4:first-child{margin-top:0}

  .pc-modal-total{display:flex;align-items:baseline;gap:8px;padding-bottom:14px;
    border-bottom:1px solid var(--border);margin-bottom:4px}
  .pc-modal-total-n{font-family:var(--f-head);font-size:30px;font-weight:800;color:#B71C1C}
  .pc-modal-total span:last-child{font-size:13px;color:var(--body-text)}

  .pc-bars{padding:10px 4px 4px;border-top:1px solid var(--border);margin-top:8px}
  .pc-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:12px}
  .pc-bar-label{width:150px;font-size:12.5px;color:var(--body-text);flex-shrink:0}
  .pc-bar-track{flex:1;height:10px;background:var(--canvas);border-radius:5px;overflow:hidden}
  .pc-bar-fill{height:100%;border-radius:5px}
  .pc-bar-stats{width:110px;text-align:right;font-size:12px;color:var(--tertiary);flex-shrink:0}
  .pc-bar-stats b{color:var(--ink);font-family:var(--f-head)}
  .pc-bar-note{font-size:10.5px;color:var(--tertiary);font-style:italic;margin-top:4px}

  .pc-combos{display:flex;flex-direction:column;gap:7px}
  .pc-combo{display:flex;align-items:center;gap:10px;font-size:13px}
  .pc-combo-n{font-family:var(--f-head);font-weight:800;font-size:16px;color:var(--ink);width:26px;text-align:right}
  .pc-combo-label{color:var(--body-text)}

  .pc-modal-cta{display:block;text-align:center;margin-top:20px}

  /* ---- Criteria Compliance chart (scrollable) ---- */
  .chart-box{flex:1 1 auto;min-height:0;overflow:auto;height:100%}
  .chart-box::-webkit-scrollbar{width:6px;height:6px}
  .chart-box::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
  .chart-box::-webkit-scrollbar-track{background:var(--canvas)}

  /* Full‑screen for area chart */
  body.area-open #area-panel{position:fixed;inset:0;z-index:400;margin:0;padding:16px 20px}
</style>
@endpush

@section('scripts')
<script>
/* ═══════ Scroll buttons ═══════ */
document.querySelectorAll('.scroll-up, .scroll-down').forEach(function (b) {
  b.addEventListener('click', function () {
    var box = document.getElementById(b.dataset.target);
    if (box) box.scrollBy({ top: b.classList.contains('scroll-up') ? -140 : 140, behavior: 'smooth' });
  });
});

/* ═══════ Map and charts ═══════ */
(function () {
  var C = window.CoK;

  function bandColour(v) {
    if (v >= 98) return '#4CAF50';
    if (v >= 70) return '#F39C12';
    if (v >= 50) return '#E74C3C';
    return '#B71C1C';
  }

  function bandLabel(v) {
    if (v >= 98) return 'Compliant';
    if (v >= 70) return 'Improvement notice and fine';
    if (v >= 50) return 'Temporary closure';
    return 'Permanent closure';
  }

  var mapped = @json($mapped ?? []);
  var map = null;

  if (window.L && document.getElementById('cok-map') && mapped.length) {
    map = L.map('cok-map', { scrollWheelZoom: false, zoomControl: true });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
      { attribution: '&copy; OpenStreetMap &copy; CARTO', maxZoom: 19 }).addTo(map);

    var bounds = [];
    mapped.forEach(function (p) {
      var lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
      if (isNaN(lat) || isNaN(lng)) return;
      var pct = parseFloat(p.compliance) || 0;

      L.circleMarker([lat, lng], { radius: 9, weight: 2.5, color: '#fff',
        fillColor: bandColour(pct), fillOpacity: .95 })
        .bindPopup('<b>' + p.name + '</b>' + (p.sector ? p.sector + ', ' : '') + (p.district || '') +
                   '<br>Compliance: <strong>' + pct.toFixed(1) + '%</strong><br>' + bandLabel(pct) +
                   '<br>Inspected ' + p.inspection_date +
                   '<br><a href="/inspect/inspections/' + p.inspection_id + '">Open report</a>')
        .addTo(map);
      bounds.push([lat, lng]);
    });

    if (bounds.length > 1) {
      var b = L.latLngBounds(bounds).pad(0.15);
      L.rectangle(b, { color: C.primary, weight: 1.5, dashArray: '6 5',
                       fillColor: C.primary, fillOpacity: .04 }).addTo(map);
      map.fitBounds(b, { padding: [15, 15] });
    } else if (bounds.length === 1) {
      map.setView(bounds[0], 15);
    } else {
      map.setView([-1.9441, 30.0619], 12);
    }

    map.on('click', function () { map.scrollWheelZoom.enable(); });
    map.on('mouseout', function () { map.scrollWheelZoom.disable(); });
  }

  var full = document.getElementById('map-full');
  if (full) {
    full.addEventListener('click', function () {
      var open = document.body.classList.toggle('map-open');
      full.innerHTML = open ? '&#10005;' : '&#10530;';
      if (map) setTimeout(function () { map.invalidateSize(); }, 250);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('map-open')) full.click();
    });
  }

  if (!window.Chart) return;
  /* ---- Districts, then sectors ---- */
  (function () {
    var holder = document.getElementById('area-data');
    var el     = document.getElementById('cArea');
    if (!holder || !el) return;

    var rows  = JSON.parse(holder.textContent);
    var level = JSON.parse(document.getElementById('area-level').textContent);
    if (!rows.length) return;

    var chart = new Chart(el, {
      data: {
        labels: rows.map(function (r) { return r.name; }),
        datasets: [
          {
            type: 'bar',
            label: 'Premises',
            data: rows.map(function (r) { return r.n; }),
            backgroundColor: 'rgba(52,168,219,.75)',
            borderColor: C.primary,
            borderWidth: 1,
            yAxisID: 'y',
            maxBarThickness: 46,
            order: 2
          },
          {
            type: 'line',
            label: 'Compliance',
            data: rows.map(function (r) { return r.pct; }),
            borderColor: C.danger,
            backgroundColor: C.danger,
            pointBackgroundColor: rows.map(function (r) { return bandColour(r.pct); }),
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 2,
            tension: .25,
            yAxisID: 'y1',
            order: 1
          }
        ]
      },
      options: {
        maintainAspectRatio: false,
        onClick: function (e, hit) {
          if (!hit.length) return;
          var name = rows[hit[0].index].name;
          var u = new URL(window.location.href);
          if (level === 'district') {
            u.searchParams.set('district', name);
            u.searchParams.delete('sector');
            u.searchParams.delete('cell');
          } else {
            u.searchParams.set('sector', name);
            u.searchParams.delete('cell');
          }
          window.location = u.toString();
        },
        onHover: function (e, hit) {
          e.native.target.style.cursor = hit.length ? 'pointer' : 'default';
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (c) {
                var r = rows[c.dataIndex];
                return c.dataset.type === 'bar'
                  ? r.n + ' premises inspected'
                  : r.pct + '% mean compliance';
              },
              afterBody: function (c) {
                var r = rows[c[0].dataIndex];
                return [bandLabel(r.pct),
                        level === 'district' ? 'Click to see its sectors'
                                             : 'Click to narrow further'];
              }
            }
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 9.5 } } },
          y: {
            beginAtZero: true,
            position: 'left',
            grid: { color: '#E0E0E0' },
            ticks: { font: { size: 9 }, precision: 0 },
            title: { display: false }
          },
          y1: {
            beginAtZero: true,
            max: 100,
            position: 'right',
            grid: { display: false },
            ticks: { font: { size: 9 }, callback: function (v) { return v + '%'; } }
          }
        }
      }
    });

    var full = document.getElementById('area-full');
    if (full) {
      full.addEventListener('click', function () {
        var open = document.body.classList.toggle('area-open');
        full.innerHTML = open ? '&#10005;' : '&#10530;';
        setTimeout(function () { chart.resize(); }, 260);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('area-open')) full.click();
      });
    }
  })();

  /* ---- The gauges, only where the category shows them ---- */
  function gauge(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
      type: 'doughnut',
      data: { datasets: [{ data: [value, Math.max(0, 100 - value)],
        backgroundColor: [bandColour(value), '#EEF1F5'], borderWidth: 0 }] },
      options: { maintainAspectRatio: false, rotation: -90, circumference: 180, cutout: '72%',
        animation: { animateRotate: true, duration: 900 },
        plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });
  }

  gauge('cSiting', {{ $gap['siting'] ?? 0 }});
  gauge('cOps',    {{ $gap['operations'] ?? 0 }});

  /* ---- Criteria compliance ---- */
  var sections = @json($sections ?? []);
  var secEl = document.getElementById('cSections');
  var secChart = null;

  function drawSections(view) {
    if (!secEl || !sections.length) return;
    if (secChart) secChart.destroy();

    var labels = sections.map(function (s) { return s.title; });
    var data = sections.map(function (s) { return s.compliance; });

    secChart = new Chart(secEl, view === 'radar' ? {
      type: 'radar',
      data: { labels: labels, datasets: [{ data: data,
        backgroundColor: 'rgba(52,168,219,.16)', borderColor: C.primary, borderWidth: 2,
        pointBackgroundColor: data.map(bandColour), pointRadius: 3 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { r: { beginAtZero: true, max: 100, grid: { color: '#E0E0E0' },
          angleLines: { color: '#E0E0E0' }, pointLabels: { font: { size: 8 } },
          ticks: { display: false } } } }
    } : {
      type: 'bar',
      data: { labels: labels, datasets: [{ data: data,
        backgroundColor: data.map(bandColour), maxBarThickness: 13 }] },
      options: { indexAxis: 'y', maintainAspectRatio: false,
        plugins: { legend: { display: false },
          tooltip: { callbacks: { label: function (ctx) {
            return ctx.parsed.x + '% of premises complying';
          } } } },
        scales: { x: { beginAtZero: true, max: 100, grid: { color: '#E0E0E0' },
                       ticks: { font: { size: 9 }, callback: function (v) { return v + '%'; } } },
                  y: { grid: { display: false }, ticks: { font: { size: 8.5 },
                       callback: function (v) {
                         var l = this.getLabelForValue(v);
                         return l.length > 26 ? l.slice(0, 24) + '…' : l;
                       } } } } }
    });
  }

  drawSections('bar');
  /* ---- Premises ranked ---- */
  (function () {
    var holder = document.getElementById('rank-data');
    var el     = document.getElementById('cRank');
    if (!holder || !el) return;

    var rows = JSON.parse(holder.textContent);
    if (!rows.length) return;

    var scroll = document.getElementById('rank-scroll');
    var canvas = document.getElementById('rank-canvas');

    /* Columns need room. Below about 34px each they stop being readable,
       so the canvas grows and the panel scrolls rather than squeezing. */
    function size() {
      var need = Math.max(rows.length * 34, scroll.clientWidth);
      canvas.style.width = need + 'px';
    }
    size();

    var chart = new Chart(el, {
      type: 'bar',
      data: {
        labels: rows.map(function (r) { return r.name; }),
        datasets: [
          {
            label: 'Achieved',
            data: rows.map(function (r) { return r.pct; }),
            backgroundColor: rows.map(function (r) { return bandColour(r.pct); }),
            stack: 'a',
            maxBarThickness: 26
          },
          {
            label: 'Shortfall',
            data: rows.map(function (r) { return Math.max(0, 100 - r.pct); }),
            backgroundColor: '#EEF1F5',
            stack: 'a',
            maxBarThickness: 26
          }
        ]
      },
      options: {
        maintainAspectRatio: false,
        onClick: function (e, hit) {
          if (hit.length) window.location = '/inspect/inspections/' + rows[hit[0].index].id;
        },
        onHover: function (e, hit) {
          e.native.target.style.cursor = hit.length ? 'pointer' : 'default';
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: function (c) { return rows[c[0].dataIndex].name; },
              label: function (c) {
                var r = rows[c.dataIndex];
                return c.datasetIndex === 0
                  ? r.pct + '% achieved'
                  : (100 - r.pct).toFixed(1) + '% short';
              },
              afterBody: function (c) {
                var r = rows[c[0].dataIndex];
                return [r.where, bandLabel(r.pct), 'Click to open the report'];
              }
            }
          }
        },
        scales: {
          x: {
            stacked: true,
            grid: { display: false },
            ticks: {
              font: { size: 8.5 },
              maxRotation: 90,
              minRotation: 90,
              autoSkip: false,
              callback: function (v) {
                var l = this.getLabelForValue(v);
                return l.length > 18 ? l.slice(0, 16) + '…' : l;
              }
            }
          },
          y: {
            stacked: true,
            beginAtZero: true,
            max: 100,
            grid: { color: '#E0E0E0' },
            ticks: { font: { size: 9 }, callback: function (v) { return v + '%'; } }
          }
        }
      }
    });

    /* Left and right */
    var step = 240;
    var l = document.getElementById('rank-left');
    var r = document.getElementById('rank-right');
    if (l) l.addEventListener('click', function () { scroll.scrollBy({ left: -step, behavior: 'smooth' }); });
    if (r) r.addEventListener('click', function () { scroll.scrollBy({ left:  step, behavior: 'smooth' }); });

    /* Full screen */
    var full = document.getElementById('rank-full');
    if (full) {
      full.addEventListener('click', function () {
        var open = document.body.classList.toggle('rank-open');
        full.innerHTML = open ? '&#10005;' : '&#10530;';
        setTimeout(function () { size(); chart.resize(); }, 260);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('rank-open')) full.click();
      });
    }

    window.addEventListener('resize', function () { size(); chart.resize(); });
  })();

  var secToggle = document.getElementById('sec-toggle');
  if (secToggle) {
    secToggle.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (!b) return;
      secToggle.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
      drawSections(b.dataset.view);
    });
  }
})();

/* ═══════ Date range — its own closure ═══════ */
(function () {
  var pop    = document.getElementById('date-pop');
  var panel  = document.getElementById('fpanel');
  var period = document.getElementById('period');
  var fromEl = document.getElementById('from');
  var toEl   = document.getElementById('to');
  if (!pop || !panel) return;

  pop.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('click', function () { pop.classList.remove('on'); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') pop.classList.remove('on');
  });

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