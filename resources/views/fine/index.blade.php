@extends('layouts.app')
@section('title', 'Fines')

@section('content')

@php
  $q = collect($filters)->filter()->all();

  $money = fn ($n) => number_format((float) $n, 0);

  /* Each figure links to the list filtered by its status. Overdue and
     pending are both confirmed fines, distinguished by whether the due
     date has passed, so they share a status and differ by a flag. */
  $link = fn (array $extra = []) => route('fine.index',
      array_merge(collect($filters)->except('status')->filter()->all(), $extra));
@endphp

{{-- ══════════ Filter bar ══════════ --}}
<form method="get" class="fbar" id="fpanel">
  <div class="fb-id">
    <span class="fb-crumb">Enforcement</span>
    <span class="fb-name">Fines</span>
    <span class="fb-scope">
      {{ count($fines) }} {{ Str::plural('fine', count($fines)) }} in this selection
    </span>
  </div>

  <div class="fb-fields">
    <div class="fb-group">
      <label for="district">District</label>
      <select name="district" id="district">
        <option value="">All districts</option>
        @foreach(array_keys(config('kigali')) as $d)
          <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    <div class="fb-group">
      <label for="status">Status</label>
      <select name="status" id="status">
        <option value="">Any status</option>
        @foreach([
            'proposed'  => 'Proposed',
            'confirmed' => 'Approved',
            'part_paid' => 'Part paid',
            'paid'      => 'Paid',
            'waived'    => 'Waived',
            'cancelled' => 'Cancelled',
        ] as $k => $label)
          <option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="fb-group">
      <label for="from">Proposed from</label>
      <input type="date" name="from" id="from" max="{{ date('Y-m-d') }}"
             value="{{ $filters['from'] }}">
    </div>

    <div class="fb-group">
      <label for="to">To</label>
      <input type="date" name="to" id="to" max="{{ date('Y-m-d') }}"
             value="{{ $filters['to'] }}">
    </div>

    <div class="fb-group">
      <label for="q">Search</label>
      <input type="text" name="q" id="q" value="{{ $filters['q'] }}"
             placeholder="Reference, owner, premises or UPI">
    </div>

    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ route('fine.export', $q) }}" class="fb-export">Excel</a>
    </div>

    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ route('fine.index') }}" class="fb-reset {{ $active ? 'live' : '' }}">
        Reset @if($active)<i>{{ $active }}</i>@endif
      </a>
    </div>
  </div>
</form>

{{-- ══════════ What is owed, at each stage ══════════ --}}
<div class="fig-row">
  <a href="{{ $link(['status' => 'proposed']) }}" class="fig">
    <span class="f-label">Proposed</span>
    <b class="f-value">{{ $money($figures['proposed']) }}</b>
    <span class="f-note">
      {{ $counts['proposed'] }} {{ Str::plural('fine', $counts['proposed']) }} awaiting confirmation
    </span>
  </a>

  <a href="{{ $link(['status' => 'confirmed']) }}" class="fig ok">
    <span class="f-label">Approved</span>
    <b class="f-value">{{ $money($figures['approved']) }}</b>
    <span class="f-note">
      {{ $counts['approved'] }} confirmed against the letter
    </span>
  </a>

  <a href="{{ $link(['status' => 'paid']) }}" class="fig paid">
    <span class="f-label">Paid</span>
    <b class="f-value">{{ $money($figures['paid']) }}</b>
    <span class="f-note">{{ $counts['paid'] }} with a payment recorded</span>
  </a>

  <a href="{{ $link(['status' => 'confirmed']) }}" class="fig warn">
    <span class="f-label">Pending</span>
    <b class="f-value">{{ $money($figures['pending']) }}</b>
    <span class="f-note">{{ $counts['pending'] }} owed, not yet due</span>
  </a>

  <a href="{{ $link(['status' => 'confirmed', 'overdue' => 1]) }}" class="fig bad">
    <span class="f-label">Overdue</span>
    <b class="f-value">{{ $money($figures['overdue']) }}</b>
    <span class="f-note">{{ $counts['overdue'] }} past the due date</span>
  </a>

  
</div>


{{-- ══════════ The register ══════════ --}}
<section>
  <h3>Fines on record</h3>
  
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Premises</th>
          <th>District</th>
          <th class="num">Amount</th>
          <th class="num">Paid</th>
          <th class="num">Outstanding</th>
          <th>Due</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($fines as $f)
          @php
            $over = $f->isOverdue();
            $tone = match ($f->status) {
                'paid'      => 'good',
                'part_paid' => 'weak',
                'proposed'  => 'na',
                'waived', 'cancelled' => 'na',
                default     => $over ? 'poor' : 'weak',
            };
          @endphp
          <tr class="{{ $over ? 'overdue' : '' }}">
            <td>
              <strong>{{ $f->reference ?: '—' }}</strong>
              @if($f->case_reference)<br><span class="mini">{{ $f->case_reference }}</span>@endif
            </td>
            <td>
              {{ $f->entity->name ?? '—' }}
              @if($f->entity?->owner)<br><span class="mini">{{ $f->entity->owner }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $f->entity->district ?? '—' }}</td>
            <td class="num">{{ $money($f->amount) }}</td>
            <td class="num">{{ $f->amount_paid ? $money($f->amount_paid) : '—' }}</td>
            <td class="num">
              {{ $f->isPayable() ? $money($f->outstanding()) : '—' }}
            </td>
            <td style="font-size:12.5px">
              {{ $f->due_date?->format('j M Y') ?: '—' }}
              @if($over)<br><span class="late">{{ $f->due_date->diffForHumans() }}</span>@endif
            </td>
            <td>
              <span class="pill {{ $tone }}">
                {{ $f->status === 'confirmed' ? 'Approved' : Str::headline($f->status) }}
              </span>
            </td>
            <td class="num">
              <a href="{{ route('fine.show', $f) }}" class="btn btn-ghost btn-sm">Open</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" style="text-align:center;color:var(--tertiary);padding:34px">
              @if($active)
                No fine matches this selection.
              @else
                No fines on record. A fine is proposed from the faults found at
                an inspection, once its report has been signed.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@endsection

@push('styles')
<style>
  /* ---- What is owed at each stage ---- */
  .fig-row{display:grid;gap:10px;grid-template-columns:repeat(2,1fr);margin-bottom:16px}
  @media(min-width:700px){.fig-row{grid-template-columns:repeat(3,1fr)}}
  @media(min-width:1100px){.fig-row{grid-template-columns:repeat(6,1fr)}}

  .fig{background:var(--white);box-shadow:var(--shadow-sm);padding:12px 15px;
       border-top:3px solid var(--tertiary);text-decoration:none;display:block;
       transition:all .18s}
  .fig:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
  .fig.ok{border-top-color:var(--primary)}
  .fig.paid{border-top-color:var(--success)}
  .fig.warn{border-top-color:var(--warning)}
  .fig.bad{border-top-color:var(--danger)}
  .fig.quiet{border-top-color:var(--border);opacity:.9;cursor:default}
  .fig.quiet:hover{box-shadow:var(--shadow-sm);transform:none}

  .f-label{display:block;font-family:var(--f-head);font-size:8.5px;font-weight:600;
           letter-spacing:.9px;text-transform:uppercase;color:var(--tertiary);line-height:1.3}
  .f-value{display:block;font-family:var(--f-head);font-size:20px;font-weight:800;
           color:var(--ink);line-height:1.15;margin-top:3px;font-variant-numeric:tabular-nums}
  .fig.ok .f-value{color:var(--primary)}
  .fig.paid .f-value{color:var(--success)}
  .fig.warn .f-value{color:#B8860B}
  .fig.bad .f-value{color:var(--danger)}
  .f-note{display:block;font-size:10px;color:var(--body-text);margin-top:2px;line-height:1.4}

  tr.overdue td{background:#FFF8F7}
  .late{font-size:10.5px;color:var(--danger);font-family:var(--f-head);font-weight:600}
  .mini{font-size:11px;color:var(--tertiary)}

  .fb-export{display:inline-flex;align-items:center;height:35px;padding:0 15px;
             background:var(--success);color:#fff;text-decoration:none;
             font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.8px;
             text-transform:uppercase;transition:background .2s;white-space:nowrap}
  .fb-export:hover{background:var(--success-hover)}
  .fb-group input[type=text],.fb-group input[type=date]{width:100%;padding:8px 11px;
             border:1px solid var(--border);font-family:var(--f-body);font-size:13px;height:35px}
  .fb-group input:focus{outline:none;border-color:var(--primary)}

  .note{padding:14px 18px;margin-bottom:18px;font-size:14px;line-height:1.65;
        border-left:4px solid var(--primary);background:#E8F4FA;max-width:900px}
  .note strong{display:block;font-family:var(--f-head);font-size:11px;font-weight:600;
               letter-spacing:.9px;text-transform:uppercase;margin-bottom:5px;color:var(--primary)}
  .note.warn{border-left-color:var(--warning);background:#FFF8E5;color:#6B4A00}
  .note.warn strong{color:#8A6D00}
</style>
@endpush