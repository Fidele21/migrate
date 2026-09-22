@extends('layouts.app')
@section('title', 'E-Archive')

@section('content')

<div class="page-head">
  <div>
    <h2>E-Archive</h2>
  </div>
  <div class="head-actions">
@if(!$showSummary)
      <a href="{{ route('archive.index', array_merge(request()->query(), ['summary' => 1])) }}" class="btn btn-ghost btn-sm" title="Back to summary">
        &#8962; Home
      </a>
    @endif
  </div>
</div>

<form method="get" class="filters" id="filterForm">
  @if(!$showSummary)
    <input type="hidden" name="summary" value="0">
  @endif

  {{-- District --}}
  <div class="f">
    <label>District</label>
    <select name="district" id="district" onchange="this.form.submit()">
      <option value="">All districts</option>
      @foreach($districts as $d)
        <option value="{{ $d }}" @selected($district === $d)>{{ $d }}</option>
      @endforeach
    </select>
  </div>

  {{-- Sector --}}
  <div class="f" id="sector-group">
    <label>Sector</label>
    <input type="text" name="sector" id="sector-input" value="{{ $sector }}" placeholder="Sector" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}">
    <select name="sector" id="sector-select" style="display:none" onchange="this.form.submit()">
      <option value="">All sectors</option>
    </select>
  </div>

  {{-- Cell --}}
  <div class="f" id="cell-group">
    <label>Cell</label>
    <input type="text" name="cell" id="cell-input" value="{{ $cell }}" placeholder="Cell" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}">
    <select name="cell" id="cell-select" style="display:none" onchange="this.form.submit()">
      <option value="">All cells</option>
    </select>
  </div>

  {{-- Zoning --}}
  <div class="f">
    <label>Zoning</label>
    <select name="zoning" onchange="this.form.submit()">
      <option value="">All zonings</option>
      @foreach($zonings as $z)
        <option value="{{ $z }}" @selected($zoning === $z)>{{ $z }}</option>
      @endforeach
    </select>
  </div>

  {{-- Activity --}}
  <div class="f">
    <label>Activity</label>
    <select name="activity" onchange="this.form.submit()">
      <option value="">All activities</option>
      @foreach($activities as $a)
        <option value="{{ $a }}" @selected($activity === $a)>{{ $a }}</option>
      @endforeach
    </select>
  </div>

  {{-- Date range --}}
  <div class="f">
    <label>From</label>
    <input type="date" name="date_from" value="{{ $date_from }}" onchange="this.form.submit()">
  </div>
  <div class="f">
    <label>To</label>
    <input type="date" name="date_to" value="{{ $date_to }}" onchange="this.form.submit()">
  </div>

  {{-- Search --}}
  <div class="f" style="flex:2;">
    <label>Search</label>
    <input type="text" name="q" value="{{ $term }}" placeholder="Reference, case, premises, UPI or owner" onkeydown="if(event.key==='Enter'){event.preventDefault();this.form.submit();}">
  </div>

  {{-- Reset --}}
  <div class="acts">
    <a href="{{ route('archive.index') }}" class="btn btn-ghost">Reset</a>
  </div>
</form>

{{-- ═══════ HOME / SUMMARY VIEW ═══════ --}}
@if($showSummary)
<div class="summary-grid">
  {{-- Letters summary --}}
  <div class="summary-section">
    <h4>Letters</h4>
    <div class="summary-cards">
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'letters', 'letter_status' => 'draft']) }}" class="summary-card">
        <span class="s-count">{{ $letterCounts['draft'] ?? 0 }}</span>
        <span class="s-label">Draft</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'letters', 'letter_status' => 'revision']) }}" class="summary-card">
        <span class="s-count">{{ $letterCounts['revision'] ?? 0 }}</span>
        <span class="s-label">Revision</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'letters', 'letter_status' => 'signed']) }}" class="summary-card">
        <span class="s-count">{{ $letterCounts['signed'] ?? 0 }}</span>
        <span class="s-label">Signed</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'letters', 'letter_status' => 'stamped']) }}" class="summary-card">
        <span class="s-count">{{ $letterCounts['stamped'] ?? 0 }}</span>
        <span class="s-label">Stamped</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'letters', 'letter_status' => 'issued']) }}" class="summary-card">
        <span class="s-count">{{ $letterCounts['issued'] ?? 0 }}</span>
        <span class="s-label">Issued</span>
      </a>
    </div>
  </div>

  {{-- Reports summary --}}
  <div class="summary-section">
    <h4>Reports</h4>
    <div class="summary-cards">
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'reports', 'report_status' => 'draft']) }}" class="summary-card">
        <span class="s-count">{{ $reportCounts['draft'] ?? 0 }}</span>
        <span class="s-label">Draft</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'reports', 'report_status' => 'completed']) }}" class="summary-card">
        <span class="s-count">{{ $reportCounts['completed'] ?? 0 }}</span>
        <span class="s-label">Completed</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'reports', 'report_status' => 'submitted']) }}" class="summary-card">
        <span class="s-count">{{ $reportCounts['submitted'] ?? 0 }}</span>
        <span class="s-label">Submitted</span>
      </a>
      <a href="{{ request()->fullUrlWithQuery(['summary' => 0, 'pane' => 'reports', 'report_status' => 'signed']) }}" class="summary-card">
        <span class="s-count">{{ $reportCounts['signed'] ?? 0 }}</span>
        <span class="s-label">Signed</span>
      </a>
    </div>
  </div>
</div>
@else

{{-- ═══════ TABS & LIST VIEW ═══════ --}}
<div class="tabs">
  {{-- Letters tabs --}}
  <button class="tab {{ $letterStatusFilter === 'draft' ? 'on' : '' }}" data-pane="pane-letters" data-status="draft">Draft Letter ({{ $letterCounts['draft'] ?? 0 }})</button>
  <button class="tab {{ $letterStatusFilter === 'revision' ? 'on' : '' }}" data-pane="pane-letters" data-status="revision">Revision Letter ({{ $letterCounts['revision'] ?? 0 }})</button>
  <button class="tab {{ $letterStatusFilter === 'signed' ? 'on' : '' }}" data-pane="pane-letters" data-status="signed">Signed Letter ({{ $letterCounts['signed'] ?? 0 }})</button>
  <button class="tab {{ $letterStatusFilter === 'stamped' ? 'on' : '' }}" data-pane="pane-letters" data-status="stamped">Stamped Letter ({{ $letterCounts['stamped'] ?? 0 }})</button>
  <button class="tab {{ $letterStatusFilter === 'issued' ? 'on' : '' }}" data-pane="pane-letters" data-status="issued">Issued Letter ({{ $letterCounts['issued'] ?? 0 }})</button>

  {{-- Reports tabs --}}
  <button class="tab {{ $reportStatusFilter === 'draft' ? 'on' : '' }}" data-pane="pane-reports" data-status="draft">Draft Report ({{ $reportCounts['draft'] ?? 0 }})</button>
  <button class="tab {{ $reportStatusFilter === 'completed' ? 'on' : '' }}" data-pane="pane-reports" data-status="completed">Completed Report ({{ $reportCounts['completed'] ?? 0 }})</button>
  <button class="tab {{ $reportStatusFilter === 'submitted' ? 'on' : '' }}" data-pane="pane-reports" data-status="submitted">Submitted Report ({{ $reportCounts['submitted'] ?? 0 }})</button>
  <button class="tab {{ $reportStatusFilter === 'signed' ? 'on' : '' }}" data-pane="pane-reports" data-status="signed">Signed Report ({{ $reportCounts['signed'] ?? 0 }})</button>
</div>

{{-- ═══════ LETTERS PANE (Stamped copy moved to first column) ═══════ --}}
<section id="pane-letters" class="pane" @if($activePane !== 'letters') style="display:none" @endif>
  <div class="desc">{{ $letters->count() }} records found</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>Stamped copy</th>
        <th>Reference</th>
        <th>Case</th>
        <th>Premises</th>
        <th>Issued</th>
        <th>Sent to</th>
      </tr></thead>
      <tbody>
        @forelse($letters as $l)
          <tr>
            <td>
              @if($l->scan_path)
                <a href="{{ Storage::disk('public')->url($l->scan_path) }}" target="_blank" class="pill good">View scan</a>
              @else
                <span class="pill weak">Not uploaded</span>
              @endif
            </td>
            <td><strong>{{ $l->reference_number }}</strong></td>
            <td style="font-size:12.5px">
              @if($l->case_reference)
                <a href="{{ route('archive.case', $l->case_reference) }}">{{ $l->case_reference }}</a>
              @else — @endif
            </td>
            <td>{{ $l->title }}<br><span class="mini">{{ Str::limit($l->subject, 42) }}</span></td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $l->issued_at?->format('j M Y') }}</td>
            <td style="font-size:12px">{{ $l->dispatched_to ?: '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;color:var(--tertiary);padding:34px">
            No letters match the filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- ═══════ REPORTS PAN  ═══════ --}}
<section id="pane-reports" class="pane" @if($activePane !== 'reports') style="display:none" @endif>
 
  <div class="desc">{{ $reports->count() }} records found</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>Case</th><th>Premises</th><th>Date</th><th>Inspector</th>
        <th style="text-align:right">Compliance</th>
      </tr></thead>
      <tbody>
        @forelse($reports as $r)
          <tr>
            <td style="font-size:12.5px">
              @if($r->case_reference)
                <a href="{{ route('archive.case', $r->case_reference) }}"><strong>{{ $r->case_reference }}</strong></a>
              @else <span class="mini">—</span> @endif
            </td>
            <td>{{ $r->entity->name }}<br><span class="mini">{{ $r->entity->district }}
              @if($r->entity->upi) &middot; {{ $r->entity->upi }}@endif</span></td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $r->inspection_date->format('j M Y') }}
              @if($r->visit_number > 1)<br><span class="mini">Visit {{ $r->visit_number }}</span>@endif</td>
            <td style="font-size:12.5px">{{ $r->inspector_name }}</td>
            <td class="num">
              <span class="pill {{ \App\Services\InspectionStats::band((float) $r->compliance) }}">{{ round((float) $r->compliance, 1) }}%</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center;color:var(--tertiary);padding:34px">
            No reports match the filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endif

<script>
// Tab switching
document.querySelectorAll('.tab').forEach(function (t) {
  t.addEventListener('click', function () {
    document.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('on'); });
    t.classList.add('on');
    var pane = t.dataset.pane;
    var status = t.dataset.status;
    // Remove old hidden inputs
    document.querySelectorAll('input[name="letter_status"], input[name="report_status"]').forEach(function(el){el.remove();});
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = (pane === 'pane-letters') ? 'letter_status' : 'report_status';
    input.value = status;
    document.getElementById('filterForm').appendChild(input);
    // Also set the pane
    var paneInput = document.createElement('input');
    paneInput.type = 'hidden';
    paneInput.name = 'pane';
    paneInput.value = (pane === 'pane-letters') ? 'letters' : 'reports';
    document.getElementById('filterForm').appendChild(paneInput);
    // Submit the form to apply the status filter
    document.getElementById('filterForm').submit();
  });
});

// Dynamic Sector / Cell dropdowns
(function() {
  var districtEl = document.getElementById('district');
  var sectorInput = document.getElementById('sector-input');
  var sectorSelect = document.getElementById('sector-select');
  var cellInput = document.getElementById('cell-input');
  var cellSelect = document.getElementById('cell-select');

  var sectorsByDistrict = @json($sectorsByDistrict ?? []);
  var cellsByDistrictSector = @json($cellsByDistrictSector ?? []);
  var currentDistrict = districtEl.value || '';
  var currentSector = '{{ $sector }}';
  var currentCell = '{{ $cell }}';

  function populateSectors(district) {
    var sectors = sectorsByDistrict[district] || [];
    sectorSelect.innerHTML = '<option value="">All sectors</option>';
    sectors.forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s;
      sectorSelect.appendChild(opt);
    });

    if (currentSector && sectors.includes(currentSector)) {
      sectorSelect.value = currentSector;
    }

    if (district === '') {
      sectorInput.style.display = '';
      sectorSelect.style.display = 'none';
      sectorInput.value = currentSector || '';
    } else {
      sectorInput.style.display = 'none';
      sectorSelect.style.display = '';
      populateCells(district, sectorSelect.value);
    }
  }

  function populateCells(district, sector) {
    var cells = (cellsByDistrictSector[district] && cellsByDistrictSector[district][sector]) || [];
    cellSelect.innerHTML = '<option value="">All cells</option>';
    cells.forEach(function(c) {
      var opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c;
      cellSelect.appendChild(opt);
    });

    if (currentCell && cells.includes(currentCell)) {
      cellSelect.value = currentCell;
    }

    if (district === '' || sector === '') {
      cellInput.style.display = '';
      cellSelect.style.display = 'none';
      cellInput.value = currentCell || '';
    } else {
      cellInput.style.display = 'none';
      cellSelect.style.display = '';
    }
  }

  districtEl.addEventListener('change', function() {
    populateSectors(this.value);
    this.form.submit();
  });

  sectorSelect.addEventListener('change', function() {
    populateCells(districtEl.value, this.value);
    this.form.submit();
  });

  if (currentDistrict !== '') {
    populateSectors(currentDistrict);
  } else {
    sectorInput.style.display = '';
    sectorSelect.style.display = 'none';
    cellInput.style.display = '';
    cellSelect.style.display = 'none';
  }

  document.getElementById('filterForm').addEventListener('submit', function() {
    if (sectorInput.style.display !== 'none') {
      sectorSelect.removeAttribute('name');
      sectorInput.setAttribute('name', 'sector');
    } else {
      sectorInput.removeAttribute('name');
      sectorSelect.setAttribute('name', 'sector');
    }

    if (cellInput.style.display !== 'none') {
      cellSelect.removeAttribute('name');
      cellInput.setAttribute('name', 'cell');
    } else {
      cellInput.removeAttribute('name');
      cellSelect.setAttribute('name', 'cell');
    }
  });
})();
</script>

@endsection

@push('styles')
<style>
  .tabs{display:flex;margin-bottom:22px;border-bottom:2px solid var(--border);flex-wrap:wrap}
  .tab{font-family:var(--f-head);font-size:12.5px;font-weight:600;letter-spacing:.8px;
       text-transform:uppercase;padding:13px 22px;border:0;background:transparent;
       color:var(--tertiary);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
  .tab.on{color:var(--primary);border-bottom-color:var(--primary)}
  .mini{font-size:11.5px;color:var(--tertiary)}
  .filters { display:flex; flex-wrap:wrap; gap:8px 12px; align-items:flex-end; background:var(--white); padding:12px 16px; border-radius:8px; margin-bottom:20px; box-shadow:var(--shadow-sm); }
  .filters .f { display:flex; flex-direction:column; min-width:120px; flex:1 0 auto; }
  .filters .acts { display:flex; gap:8px; flex:0 0 auto; align-items:center; }
  .filters label { font-size:10px; font-weight:600; text-transform:uppercase; color:var(--tertiary); margin-bottom:2px; letter-spacing:0.3px; }
  .filters input, .filters select { padding:5px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; height:32px; }
  .btn-ghost { border:1px solid var(--border); background:transparent; }
  .pill { display:inline-block; padding:0 10px; border-radius:12px; font-weight:600; font-size:12px; line-height:24px; white-space:nowrap; }
  .pill.good { background:#e3f5e9; color:#1a7a3a; }
  .pill.weak { background:#fff3d6; color:#8a6d00; }

  .summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:20px; }
  .summary-section h4 { font-family:var(--f-head); font-size:13px; font-weight:600; color:var(--tertiary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px; }
  .summary-cards { display:flex; flex-wrap:wrap; gap:10px; }
  .summary-card { display:flex; flex-direction:column; align-items:center; background:var(--white); padding:14px 22px; border-radius:8px; box-shadow:var(--shadow-sm); text-decoration:none; min-width:70px; transition:all .18s; border:2px solid transparent; }
  .summary-card:hover { transform:translateY(-2px); box-shadow:var(--shadow); border-color:var(--primary); }
  .summary-card .s-count { font-family:var(--f-head); font-size:28px; font-weight:800; color:var(--ink); line-height:1.2; }
  .summary-card .s-label { font-family:var(--f-head); font-size:9px; font-weight:600; text-transform:uppercase; color:var(--tertiary); letter-spacing:0.5px; margin-top:4px; }

  @media (max-width: 768px) {
    .filters .f { min-width:100%; }
    .summary-grid { grid-template-columns:1fr; }
    .summary-cards { justify-content:center; }
  }
</style>
@endpush