@extends('layouts.app')
@section('title', 'Assignments')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Inspection &middot; Assignments</div>
    <h2>Assignments</h2>
  </div>
  @if($canAssign)
    <div class="head-actions">
      <a href="{{ route('assignment.create') }}" class="btn btn-primary">Assign work</a>
    </div>
  @endif
</div>

@if($canAssign)
<section>
  <h3>Given out</h3>
  <div class="desc">Work you have assigned, and how it stands</div>

  <div class="tbl-wrap">
    <table class="asg">
      <thead>
        <tr>
          <th>Category</th>
          <th>Assigned to</th>
          <th style="text-align:center">Target</th>
          <th style="text-align:center">Done</th>
          <th>Period</th>
          <th>Standing</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($given as $a)
          @php [$verdict, $tone] = $a->standing(); @endphp
          <tr>
            <td><a href="{{ route('assignment.show', $a) }}" class="asg-link">{{ \App\Http\Controllers\InspectionTypeController::resolve($a->type_code)['name'] }}</a></td>
            <td>
              @foreach($a->members as $m)
                <span class="who">{{ $m->name }}</span>@if(! $loop->last), @endif
              @endforeach
            </td>
            <td style="text-align:center">{{ $a->quantity }}</td>
            <td style="text-align:center"><b>{{ $a->done() }}</b></td>
            <td style="white-space:nowrap;font-size:12.5px">
              {{ $a->starts_on->format('j M') }} &ndash; {{ $a->due_on->format('j M Y') }}
            </td>
            <td><span class="pill {{ $tone }}">{{ $verdict }}</span></td>
            <td style="text-align:right">
              <a href="{{ route('assignment.show', $a) }}" class="btn btn-ghost btn-sm">Open</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="empty">You have not assigned any work yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endif

<section>
  <h3>Held by you</h3>
  <div class="desc">Work assigned to you, alone or with others</div>

  <div class="tbl-wrap">
    <table class="asg">
      <thead>
        <tr>
          <th>Category</th>
          <th>Assigned by</th>
          <th style="text-align:center">Target</th>
          <th style="text-align:center">Done</th>
          <th>Due</th>
          <th>Standing</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($held as $a)
          @php [$verdict, $tone] = $a->standing(); @endphp
          <tr>
            <td>
              <a href="{{ route('assignment.show', $a) }}" class="asg-link">{{ \App\Http\Controllers\InspectionTypeController::resolve($a->type_code)['name'] }}</a>
              @if($a->members->count() > 1)
                <br><span class="mini">With {{ $a->members->count() - 1 }} other{{ $a->members->count() > 2 ? 's' : '' }}</span>
              @endif
            </td>
            <td>{{ $a->assigner->name ?? '—' }}</td>
            <td style="text-align:center">{{ $a->quantity }}</td>
            <td style="text-align:center"><b>{{ $a->done() }}</b></td>
            <td style="white-space:nowrap;font-size:12.5px">
              {{ $a->due_on->format('j M Y') }}
              @if($a->status === 'active' && ! $a->isMet())
                <br><span class="mini {{ $a->isOverdue() ? 'late' : '' }}">
                  {{ $a->isOverdue() ? 'Overdue' : $a->daysLeft() . ' day' . ($a->daysLeft() === 1 ? '' : 's') . ' left' }}
                </span>
              @endif
            </td>
            <td><span class="pill {{ $tone }}">{{ $verdict }}</span></td>
            <td style="text-align:right">
              <a href="{{ route('assignment.show', $a) }}" class="btn btn-ghost btn-sm">Open</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="empty">No work is assigned to you.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@endsection

@push('styles')
<style>
  .asg th{font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:var(--tertiary);
          padding:8px 6px;border-bottom:2px solid var(--border)}
  .asg td{padding:9px 6px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
  .asg-link{font-weight:700;color:var(--blue);text-decoration:none}
  .asg-link:hover{text-decoration:underline}
  .who{font-size:12.5px}
  .mini{color:var(--tertiary);font-size:11.5px}
  .mini.late{color:var(--danger);font-weight:600}
  .empty{text-align:center;color:var(--tertiary);padding:30px}
  .pill{display:inline-block;padding:0 10px;border-radius:12px;font-weight:600;
        font-size:12px;line-height:24px;white-space:nowrap}
  .pill.good{background:#e3f5e9;color:#1a7a3a}
  .pill.weak{background:#fff3d6;color:#8a6d00}
  .pill.poor{background:#fde8e8;color:#b71c1c}
  .pill.muted{background:var(--canvas);color:var(--tertiary)}
</style>
@endpush
