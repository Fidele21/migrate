@extends('layouts.app')
@section('title', 'Assignment')

@php
  $type = \App\Http\Controllers\InspectionTypeController::resolve($assignment->type_code);
  [$verdict, $tone] = $assignment->standing();
  $isTeam = $assignment->sharing === \App\Models\Assignment::TEAM;
@endphp

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">
      Inspection &middot; Assignments
      @if($assignment->parent)
        &middot; <a href="{{ route('assignment.show', $assignment->parent) }}">from {{ $assignment->parent->assigner->name ?? 'above' }}</a>
      @endif
    </div>
    <h2>{{ $type['name'] }}</h2>
  </div>
  <div class="head-actions">
    @if($canDelegate)
      <a href="{{ route('assignment.create', ['from' => $assignment->id]) }}" class="btn btn-primary">Pass on a share</a>
    @endif
    <a href="{{ route('inspection.create', $assignment->type_code) }}" class="btn btn-ghost">Record an inspection</a>
    @if($canManage && $assignment->status === 'active')
      <form method="post" action="{{ route('assignment.cancel', $assignment) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-ghost"
                onclick="return confirm('Cancel this assignment? The inspections already done stay on record.')">Cancel</button>
      </form>
    @endif
  </div>
</div>

<div class="asg-headline">
  <div class="asg-big">
    <span class="asg-n">{{ $assignment->done() }}</span>
    <span class="asg-of">of {{ $assignment->quantity }}</span>
  </div>
  <div class="asg-meta">
    <span class="pill {{ $tone }}">{{ $verdict }}</span>
    <span class="asg-pct">{{ $assignment->progress() }}%</span>
  </div>
</div>

<div class="asg-track">
  <div class="asg-fill" style="width:{{ min(100, $assignment->progress()) }}%"></div>
</div>

@if($myShare !== null && ! $isTeam)
  <div class="my-share">
    <div>
      <b>Your share: {{ $myDone }} of {{ $myShare }}</b>
      @if($myRemaining > 0 && $canDelegate)
        <span class="mini">&mdash; {{ $myRemaining }} still yours to do or pass on</span>
      @elseif($myRemaining === 0 && $assignment->delegatedBy(auth()->id()) > 0)
        <span class="mini">&mdash; all passed on to your inspectors</span>
      @endif
    </div>
    <span class="asg-pct">{{ $assignment->progressFor(auth()->id()) }}%</span>
  </div>
@endif

<div class="stats">
  <div class="stat"><b>{{ $assignment->starts_on->format('j M Y') }}</b><span>Starts</span></div>
  <div class="stat {{ $assignment->isOverdue() ? 'r' : '' }}"><b>{{ $assignment->due_on->format('j M Y') }}</b><span>Due</span></div>
  <div class="stat t"><b>{{ $assignment->members->count() }}</b><span>{{ Str::plural('Officer', $assignment->members->count()) }}</span></div>
  <div class="stat"><b>{{ $assignment->assigner->name ?? '—' }}</b><span>Assigned by</span></div>
</div>

@if($assignment->instructions)
  <section>
    <h3>What to do</h3>
    <div class="asg-instructions">{!! nl2br(e($assignment->instructions)) !!}</div>
  </section>
@endif

<section>
  <h3>{{ $assignment->members->count() > 1 ? ($isTeam ? 'The team' : 'Who holds it') : 'Assigned to' }}</h3>
  <div class="desc">
    @if($isTeam)
      The target is theirs to meet between them, and each is credited with
      what they achieve together &mdash; not a share of it.
    @else
      Each holds {{ $assignment->shareEach() }} of the {{ $assignment->quantity }},
      and answers for their own.
    @endif
  </div>

  <div class="tbl-wrap">
    <table class="asg">
      <thead>
        <tr>
          <th>Officer</th>
          <th>Role</th>
          <th style="text-align:center">Own</th>
          @if(! $isTeam)<th style="text-align:center">Passed on</th>@endif
          <th style="text-align:center">{{ $isTeam ? 'Toward target' : 'Their total' }}</th>
          <th style="text-align:center">Credited</th>
        </tr>
      </thead>
      <tbody>
        @foreach($perMember as $row)
          <tr>
            <td><b>{{ $row['user']->name }}</b></td>
            <td style="font-size:12.5px;color:var(--tertiary)">{{ $row['user']->roles->pluck('name')->implode(', ') }}</td>
            <td style="text-align:center">{{ $row['own'] }}</td>
            @if(! $isTeam)
              <td style="text-align:center">{{ $row['delegated'] ?: '—' }}</td>
            @endif
            <td style="text-align:center">
              <b>{{ $row['total'] }}</b>@if(! $isTeam) <span class="mini">of {{ $row['share'] }}</span>@endif
            </td>
            <td style="text-align:center"><b>{{ $row['progress'] }}%</b></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

@if($assignment->children->where('status', '!=', 'cancelled')->isNotEmpty())
<section>
  <h3>Passed on</h3>
  <div class="desc">Shares of this assignment given to officers further down</div>

  <div class="tbl-wrap">
    <table class="asg">
      <thead>
        <tr>
          <th>Given to</th>
          <th>By</th>
          <th style="text-align:center">Share</th>
          <th style="text-align:center">Done</th>
          <th>Standing</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($assignment->children->where('status', '!=', 'cancelled') as $child)
          @php [$cv, $ct] = $child->standing(); @endphp
          <tr>
            <td>
              @foreach($child->members as $m)
                <span class="who">{{ $m->name }}</span>@if(! $loop->last), @endif
              @endforeach
            </td>
            <td style="font-size:12.5px;color:var(--tertiary)">{{ $child->assigner->name ?? '—' }}</td>
            <td style="text-align:center">{{ $child->quantity }}</td>
            <td style="text-align:center"><b>{{ $child->done() }}</b></td>
            <td><span class="pill {{ $ct }}">{{ $cv }}</span></td>
            <td style="text-align:right">
              <a href="{{ route('assignment.show', $child) }}" class="btn btn-ghost btn-sm">Open</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@endsection

@push('styles')
<style>
  .asg-headline{display:flex;align-items:baseline;gap:18px;margin:18px 0 10px;flex-wrap:wrap}
  .asg-big{display:flex;align-items:baseline;gap:8px}
  .asg-n{font-family:var(--f-head);font-size:46px;font-weight:800;color:var(--ink);line-height:1}
  .asg-of{font-size:16px;color:var(--tertiary)}
  .asg-meta{display:flex;align-items:center;gap:12px;margin-left:auto}
  .asg-pct{font-family:var(--f-head);font-size:20px;font-weight:700;color:var(--tertiary)}

  .asg-track{height:12px;background:var(--canvas);border-radius:6px;overflow:hidden;margin-bottom:16px}
  .asg-fill{height:100%;background:var(--primary);border-radius:6px;transition:width .3s}

  .my-share{display:flex;align-items:center;justify-content:space-between;gap:14px;
            background:#f0f7ff;border-left:4px solid var(--primary);
            padding:12px 16px;border-radius:6px;margin-bottom:18px;font-size:13.5px}

  .asg-instructions{background:var(--white);padding:18px 20px;border-radius:8px;
                    border-left:4px solid var(--primary);font-size:13.5px;line-height:1.7}

  .asg th{font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:var(--tertiary);
          padding:8px 6px;border-bottom:2px solid var(--border)}
  .asg td{padding:9px 6px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
  .who{font-size:12.5px}
  .mini{color:var(--tertiary);font-size:11.5px;font-weight:400}

  .pill{display:inline-block;padding:0 12px;border-radius:12px;font-weight:600;
        font-size:12.5px;line-height:26px;white-space:nowrap}
  .pill.good{background:#e3f5e9;color:#1a7a3a}
  .pill.weak{background:#fff3d6;color:#8a6d00}
  .pill.poor{background:#fde8e8;color:#b71c1c}
  .pill.muted{background:var(--canvas);color:var(--tertiary)}

  .stats{display:flex;flex-wrap:wrap;gap:6px 22px;margin:14px 0 20px;font-size:14px;color:var(--tertiary)}
  .stat{display:flex;align-items:baseline;gap:6px}
  .stat b{font-family:var(--f-head);font-size:16px;font-weight:700;color:var(--ink)}
  .stat.t b{color:var(--blue)}
  .stat.r b{color:var(--danger)}
</style>
@endpush