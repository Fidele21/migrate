@extends('layouts.app')
@section('title', $type['name'] . ' Register')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['group'] }} &middot; {{$type['name']}}</div>
     {{-- ---------------- <h4>{{ $type['name'] }}</h4>------------ --}}
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

<form method="get" class="fbar" id="fpanel">
 

  <div class="fb-fields">

    {{-- District (existing) --}}
    <div class="fb-group">
      <label for="district">District</label>
      <select name="district" id="district">
        <option value="">All districts</option>
        @foreach($districts as $d)
          <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    {{-- Sector (new) --}}
    <div class="fb-group">
      <label for="sector">Sector</label>
      <input type="text" name="sector" id="sector" value="{{ $filters['sector'] ?? '' }}"
             placeholder="Sector name">
    </div>

    {{-- Cell (new) --}}
    <div class="fb-group">
      <label for="cell">Cell</label>
      <input type="text" name="cell" id="cell" value="{{ $filters['cell'] ?? '' }}"
             placeholder="Cell name">
    </div>

    
    
    {{-- UPI --}}
    <div class="fb-group fb-wide">
      <label for="upi">UPI</label>
      <input type="text" name="upi" id="upi" value="{{ $filters['upi'] ?? '' }}"
             placeholder="Any part of the UPI">
    </div>
    
     {{-- Owner name (new) --}}
    <div class="fb-group fb-wide">
      <label for="owner">Owner</label>
      <input type="text" name="owner" id="owner" value="{{ $filters['owner'] ?? '' }}"
             placeholder="Owner or operator">
    </div>


    {{-- Entity name (previously "Name or owner") --}}
    <div class="fb-group fb-wide">
      <label for="name">Entity name</label>
      <input type="text" name="name" id="name" value="{{ $filters['name'] ?? '' }}"
             placeholder="Premises or entity name">
    </div>

   
    
    {{-- Date from --}}
    <div class="fb-group">
      <label for="date_from">Inspected from</label>
      <input type="date" name="date_from" id="date_from" max="{{ date('Y-m-d') }}"
             value="{{ $filters['date_from'] ?? '' }}">
    </div>

    {{-- Date to --}}
    <div class="fb-group">
      <label for="date_to">To</label>
      <input type="date" name="date_to" id="date_to" max="{{ date('Y-m-d') }}"
             value="{{ $filters['date_to'] ?? '' }}">
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

<section>
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
          
        @php [$verdict, $tone] = \App\Http\Controllers\RegisterController::deliberationFor((float) $r->compliance, (bool) $r->siting_violation); @endphp
          <tr>
            <td style="color:var(--muted)">{{ $n + 1 }}</td>
            <td style="font-size:12px">{{ $r->upi ?: '—' }}</td>
            <td style="font-size:12.5px">{{ $r->zoning ?: '—' }}</td>
            <td>{{ $r->owner ?: '—' }}</td>
            <td>
              <a href="{{ route('inspection.show', $r->last_inspection) }}"
                 style="font-weight:700;color:var(--blue);text-decoration:none">{{ $r->name }}</a>
              @if($r->sector)<br><span style="color:var(--muted);font-size:11.5px">{{ $r->sector }}</span>@endif
            </td>
            <td>{{ $r->district ?: '—' }}</td>
            <td>{{ $r->sector ?: '—' }}</td>
            <td>{{ $r->cell ?: '—' }}</td>
            <td style="white-space:nowrap;font-size:12.5px">{{ $r->last_date }}</td>
            <td class="num"><span class="pill {{ $tone }}">{{ round((float) $r->compliance, 1) }}%</span></td>
            <td style="text-align:center">
              <span class="count-chip {{ $r->inspections_count > 1 ? 'multi' : '' }}">{{ $r->inspections_count }}</span>
            </td>
            <td><span class="pill {{ $tone }}">{{ $verdict }}</span></td>
            <td class="num">
              <a href="{{ route('inspection.show', $r->last_inspection) }}" class="btn btn-ghost btn-sm">View Report</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="13" style="text-align:center;color:var(--muted);padding:34px">
            No premises match these filters.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
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