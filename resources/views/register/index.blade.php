@extends('layouts.app')
@section('title', $type['name'] . ' Register')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['group'] }} &middot; {{$type['name']}}</div>
  </div>
  <div class="head-actions">
    @can('export.excel')
      <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-ghost">Export Excel</a>
    @endcan
    @can('export.pdf')
      <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" target="_blank" class="btn btn-ghost">Export PDF</a>
    @endcan
  </div>
</div>

{{-- ---------------- Filters ---------------- --}}

<form method="get" class="fbar" id="fpanel" action="{{ route('register.index', $type['code']) }}">
 
  <div class="fb-fields">

    {{-- District --}}
    <div class="fb-group">
      <label for="district">District</label>
      <select name="district" id="district">
        <option value="">All districts</option>
        @foreach($districts as $d)
          <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    {{-- Sector – text input becomes select when district chosen --}}
    <div class="fb-group" id="sector-group">
      <label for="sector">Sector</label>
      <input type="text" name="sector" id="sector-input" value="{{ $filters['sector'] ?? '' }}" placeholder="Sector name">
      <select name="sector" id="sector-select" style="display:none">
        <option value="">All sectors</option>
      </select>
    </div>

    {{-- Cell – text input becomes select when district + sector chosen --}}
    <div class="fb-group" id="cell-group">
      <label for="cell">Cell</label>
      <input type="text" name="cell" id="cell-input" value="{{ $filters['cell'] ?? '' }}" placeholder="Cell name">
      <select name="cell" id="cell-select" style="display:none">
        <option value="">All cells</option>
      </select>
    </div>

    {{-- UPI --}}
    <div class="fb-group fb-wide">
      <label for="upi">UPI</label>
      <input type="text" name="upi" id="upi" value="{{ $filters['upi'] ?? '' }}" placeholder="Any part of the UPI">
    </div>

    {{-- Owner --}}
    <div class="fb-group fb-wide">
      <label for="owner">Owner</label>
      <input type="text" name="owner" id="owner" value="{{ $filters['owner'] ?? '' }}" placeholder="Owner or operator">
    </div>

    {{-- Entity name --}}
    <div class="fb-group fb-wide">
      <label for="name">Entity name</label>
      <input type="text" name="name" id="name" value="{{ $filters['name'] ?? '' }}" placeholder="Premises or entity name">
    </div>

    {{-- Date range – two date inputs in one cell --}}
    <div class="fb-group">
      <label for="date_from">Date range</label>
      <div style="display:flex;gap:4px;align-items:center;">
        <input type="date" name="date_from" id="date_from" max="{{ date('Y-m-d') }}" value="{{ $filters['date_from'] ?? '' }}" style="flex:1;min-width:0;">
        <span style="color:var(--tertiary);font-size:12px;">to</span>
        <input type="date" name="date_to" id="date_to" max="{{ date('Y-m-d') }}" value="{{ $filters['date_to'] ?? '' }}" style="flex:1;min-width:0;">
      </div>
    </div>

    @php $active = collect($filters ?? [])->filter()->count(); @endphp

    {{-- Reset button --}}
    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ route('register.index', $type['code']) }}" class="fb-reset {{ $active ? 'live' : '' }}">
        Reset @if($active)<i>{{ $active }}</i>@endif
      </a>
    </div>

    <noscript>
      <div class="fb-group fb-end">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
      </div>
    </noscript>

  </div> <!-- /.fb-fields -->
</form>

{{-- ---------------- TABLE SECTION ---------------- --}}
<section>
  @if(isset($type['code']) && $type['code'] === 'waste_water')
    {{-- ========== WASTEWATER: Excel-style table ========== --}}
    <div class="desc">{{ count($rows) }} wastewater inspections found.</div>
    <div class="tbl-wrap" style="overflow-x:auto">
      <table class="register" style="min-width:1400px;font-size:12px">
        <thead>
          <tr>
            <th>N0</th>
            <th>District</th>
            <th>Sector</th>
            <th>Cell</th>
            <th>Name of the Building</th>
            <th>Owner</th>
            <th>Building use</th>
            <th>Type of waste water management</th>
            <th>Maintenance Company</th>
            <th>Valid test results</th>
            <th>Compliance</th>
            <th>Status</th>
            <th>Decision</th>
            <th>Inspection date</th>
            <th>Report</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ $row->district ?? '—' }}</td>
              <td>{{ $row->sector ?? '—' }}</td>
              <td>{{ $row->cell ?? '—' }}</td>
              <td>{{ $row->building_name ?? $row->name ?? '—' }}</td>
              <td>{{ $row->owner ?? '—' }}</td>
              <td>{{ $row->building_use ?? '—' }}</td>
              <td>{{ $row->ww_type ?? '—' }}</td>
              <td>{{ $row->maintenance_co ?? '—' }}</td>
              <td>{{ $row->test_results ?? '—' }}</td>
              <td>{{ $row->compliance ?? 0 }}%</td>
              <td>
                <span class="pill {{ ($row->ww_status ?? '') === 'Compliant' ? 'good' : 'bad' }}">
                  {{ $row->ww_status ?? '—' }}
                </span>
              </td>
              <td>{{ $row->decision ?? '—' }}</td>
              <td>{{ $row->timeline ?? '—' }}</td>
              <td>
                <a href="{{ route('inspection.show', $row->last_inspection ?? $row->inspection_id) }}" class="btn btn-ghost btn-sm">View Report</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="15" style="text-align:center;padding:30px;color:var(--muted)">No wastewater inspections found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @else
    {{-- ========== NORMAL REGISTER TABLE ========== --}}
    
    <div class="tbl-wrap">
      <table class="register">
        <thead>
          <tr>
            <th style="width:38px">#</th>
            <th>UPI</th>
            <th>Zoning</th>
            <th>Owner</th>
            <th>{{ $type['name'] }} Name</th>
            <th>District</th>
            <th>Sector</th>
            <th>Cell</th>
            <th>Date of Inspection</th>
            <th style="text-align:right">Compliance</th>
            <th style="text-align:center">Inspections</th>
            <th>Deliberation</th>
            <th style="text-align:right">Report</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $n => $r)
            @php [$verdict, $tone] = \App\Http\Controllers\RegisterController::deliberationFor((float) ($r->compliance ?? 0), (bool) ($r->siting_violation ?? false)); @endphp
            <tr>
              <td style="color:var(--muted)">{{ $n + 1 }}</td>
              <td style="font-size:12px">{{ $r->upi ?? '—' }}</td>
              <td style="font-size:12.5px">{{ $r->zoning ?? '—' }}</td>
              <td>{{ $r->owner ?? '—' }}</td>
              <td>
                <a href="{{ route('inspection.show', $r->last_inspection ?? '') }}" style="font-weight:700;color:var(--blue);text-decoration:none">{{ $r->name ?? '—' }}</a>
                @if(($r->sector ?? false))<br><span style="color:var(--muted);font-size:11.5px">{{ $r->sector }}</span>@endif
              </td>
              <td>{{ $r->district ?? '—' }}</td>
              <td>{{ $r->sector ?? '—' }}</td>
              <td>{{ $r->cell ?? '—' }}</td>
              <td style="white-space:nowrap;font-size:12.5px">{{ $r->last_date ?? '—' }}</td>
              <td class="num"><span class="pill {{ $tone }}">{{ round((float) ($r->compliance ?? 0), 1) }}%</span></td>
              <td style="text-align:center"><span class="count-chip {{ ($r->inspections_count ?? 0) > 1 ? 'multi' : '' }}">{{ $r->inspections_count ?? 0 }}</span></td>
              <td><span class="pill {{ $tone }}">{{ $verdict }}</span></td>
              <td class="num"><a href="{{ route('inspection.show', $r->last_inspection ?? '') }}" class="btn btn-ghost btn-sm">View Report</a></td>
            </tr>
          @empty
            <tr><td colspan="13" style="text-align:center;padding:34px">No premises match these filters.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endif
</section>

@endsection

@push('styles')
<style>
  /* ---------- Filter bar tweaks ---------- */
  .fb-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
    align-items: flex-end;
    margin-top: 8px;
  }
  .fb-group {
    display: flex;
    flex-direction: column;
    min-width: 130px;
    flex: 1 0 auto;
  }
  .fb-group.fb-wide {
    flex: 2 0 180px;
  }
  .fb-group.fb-end {
    flex: 0 0 auto;
    justify-content: flex-end;
  }
  .fb-group label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--tertiary);
    margin-bottom: 3px;
  }
  .fb-group input[type="text"],
  .fb-group input[type="search"],
  .fb-group input[type="date"],
  .fb-group select {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-family: var(--f-body);
    font-size: 13px;
    background: var(--white);
    color: var(--ink);
    height: 35px;
    transition: border-color 0.2s;
  }
  .fb-group input:focus,
  .fb-group select:focus {
    outline: none;
    border-color: var(--primary);
  }
  .fb-reset {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0 12px;
    height: 35px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--white);
    color: var(--tertiary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
  }
  .fb-reset.live {
    border-color: var(--primary);
    color: var(--primary);
    background: #f0f7ff;
  }
  .fb-reset:hover {
    background: var(--canvas);
    border-color: var(--primary);
    color: var(--primary);
  }
  .fb-reset i {
    font-style: normal;
    background: var(--primary);
    color: #fff;
    border-radius: 10px;
    padding: 0 7px;
    font-size: 11px;
    line-height: 18px;
  }

  /* ---------- Stats ---------- */
  .stats {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    margin: 14px 0 10px;
    font-size: 14px;
    color: var(--tertiary);
  }
  .stat {
    display: flex;
    align-items: baseline;
    gap: 6px;
  }
  .stat b {
    font-family: var(--f-head);
    font-size: 21px;
    font-weight: 700;
    color: var(--ink);
  }
  .stat.t b { color: var(--blue); }
  .stat.g b { color: var(--success); }
  .stat.y b { color: #b8860b; }
  .stat.r b { color: var(--danger); }

  /* ---------- Table ---------- */
  .register th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--tertiary);
    padding: 8px 6px;
    border-bottom: 2px solid var(--border);
  }
  .register td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
  }
  .register .num {
    text-align: right;
  }
  .pill {
    display: inline-block;
    padding: 0 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 12px;
    line-height: 24px;
    white-space: nowrap;
  }
  .pill.good { background: #e3f5e9; color: #1a7a3a; }
  .pill.warn { background: #fff3d6; color: #8a6d00; }
  .pill.bad  { background: #fde8e8; color: #b71c1c; }
  .count-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 26px;
    height: 24px;
    padding: 0 7px;
    border-radius: 12px;
    background: #EDF2FC;
    color: var(--blue);
    font-weight: 800;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
  }
  .count-chip.multi {
    background: #FFF4D6;
    color: #8A6D00;
  }

  /* Responsive */
  @media (max-width: 760px) {
    .fb-fields {
      flex-direction: column;
      gap: 8px;
    }
    .fb-group {
      min-width: 100%;
    }
    .fb-group.fb-wide {
      flex: 1 0 auto;
    }
    .fb-group.fb-end {
      align-items: stretch;
    }
    .stats {
      font-size: 13px;
    }
    .stat b {
      font-size: 18px;
    }
    .register td,
    .register th {
      font-size: 12px;
      padding: 6px 4px;
    }
  }
</style>
@endpush

@push('scripts')
<script>
(function() {
  var districtEl = document.getElementById('district');
  var sectorInput = document.getElementById('sector-input');
  var sectorSelect = document.getElementById('sector-select');
  var cellInput = document.getElementById('cell-input');
  var cellSelect = document.getElementById('cell-select');
  var form = document.getElementById('fpanel');

  var allSectors = @json($allSectors ?? []);
  var allCells = @json($allCells ?? []);
  var currentDistrict = districtEl.value || '';
  var currentSector = '{{ $filters['sector'] ?? '' }}';
  var currentCell = '{{ $filters['cell'] ?? '' }}';

  // Helper: populate sector select
  function populateSectors(district) {
    var sectors = allSectors[district] || [];
    sectorSelect.innerHTML = '<option value="">All sectors</option>';
    sectors.forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s;
      sectorSelect.appendChild(opt);
    });

    // If the current filter has a sector, select it
    if (currentSector && sectors.includes(currentSector)) {
      sectorSelect.value = currentSector;
    }

    // Toggle visibility: if district is empty (All), show text input; else show select
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

  // Helper: populate cell select
  function populateCells(district, sector) {
    var cells = (allCells[district] && allCells[district][sector]) || [];
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

    // Toggle visibility: if district is empty (All) OR sector is empty, show text input; else show select
    if (district === '' || sector === '') {
      cellInput.style.display = '';
      cellSelect.style.display = 'none';
      cellInput.value = currentCell || '';
    } else {
      cellInput.style.display = 'none';
      cellSelect.style.display = '';
    }
  }

  // Function to prepare form and submit (auto-apply)
  function applyFilters() {
    // Set the correct field names before submit
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

    // Submit the form
    form.submit();
  }

  // ---- On district change - auto apply ----
  districtEl.addEventListener('change', function() {
    var district = this.value;
    populateSectors(district);
    // Auto-submit after a tiny delay to let the DOM update
    setTimeout(applyFilters, 50);
  });

  // ---- On sector change (select) - auto apply ----
  sectorSelect.addEventListener('change', function() {
    var district = districtEl.value;
    var sector = this.value;
    populateCells(district, sector);
    setTimeout(applyFilters, 50);
  });

  // ---- On cell change (select) - auto apply ----
  cellSelect.addEventListener('change', function() {
    setTimeout(applyFilters, 50);
  });

  // ---- On text input changes - don't auto-apply (user must press Enter or click Reset) ----
  // But we do need to capture Enter key on text inputs
  var textInputs = [sectorInput, cellInput];
  textInputs.forEach(function(input) {
    if (input) {
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyFilters();
        }
      });
    }
  });

  // ---- Date inputs - auto-apply when either date changes ----
  var dateFrom = document.getElementById('date_from');
  var dateTo = document.getElementById('date_to');
  if (dateFrom) {
    dateFrom.addEventListener('change', function() {
      setTimeout(applyFilters, 50);
    });
  }
  if (dateTo) {
    dateTo.addEventListener('change', function() {
      setTimeout(applyFilters, 50);
    });
  }

  // ---- Initial load ----
  if (currentDistrict !== '') {
    populateSectors(currentDistrict);
  } else {
    // Show text inputs
    sectorInput.style.display = '';
    sectorSelect.style.display = 'none';
    cellInput.style.display = '';
    cellSelect.style.display = 'none';
  }

  // ---- Also auto-apply when user types in UPI, Owner, Name (with Enter key) ----
  ['upi', 'owner', 'name'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyFilters();
        }
      });
    }
  });

})();
</script>
@endpush