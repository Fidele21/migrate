@extends('layouts.app')
@section('title', 'My Box')

@section('content')

@php
  $total = $myDrafts->count() + $awaiting->count() + $returned->count();
@endphp

@if($toSign->count())
<section class="sign-due">
  <div class="sd-head">
    <div>
      <h3>
        {{ $toSign->count() }} {{ Str::plural('report', $toSign->count()) }}
        awaiting your signature
      </h3>
      <div class="sd-desc">
        Nothing further can happen on these cases until they are signed —
        no letter can be transmitted without a sealed report.
      </div>
    </div>
  </div>

  <div class="sd-list">
    @foreach($toSign as $d)
      @php
        $i        = $d->inspection;
        $progress = $d->signatureProgress();
        $mine     = $i?->conductedBy(auth()->user());
      @endphp

      <a href="{{ route('inspection.show', $i->id) }}" class="sd-item">
        <div class="sd-main">
          <b>{{ $i->entity->name ?? $d->title }}</b>
          <span>
            {{ $i->locationLabel() }} ·
            inspected {{ $i->inspection_date?->format('j M Y') }} ·
            {{ round((float) $i->compliance, 1) }}%
          </span>
        </div>

        <div class="sd-state">
          @if($mine)
            <span class="sd-tag officer">Your signature</span>
          @else
            <span class="sd-tag director">As Director</span>
          @endif

          @if($progress['awaiting'] > 0 && ! $mine)
            <span class="sd-note">{{ $progress['awaiting'] }} officer(s) still to sign</span>
          @else
            <span class="sd-note">
              Open since {{ $d->signatures_opened_at?->diffForHumans() }}
            </span>
          @endif
        </div>

        <span class="sd-go">Sign &rsaquo;</span>
      </a>
    @endforeach
  </div>
</section>
@endif

<div class="page-head">
  <div>
    <div class="crumb">{{ $role }}</div>
    <h2>My Box</h2>
    <p>Everything on your desk &mdash; inspections in progress, reports and letters awaiting
       your action, and work you have sent onward.</p>
  </div>
  <div class="head-actions">
    @can('inspection.create')
      @include('partials.inspect-button')
    @endcan
    <a href="{{ route('dashboard') }}" class="btn btn-ghost">Dashboard</a>
  </div>
</div>

@if(session('status'))
  <div class="note" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif

{{-- ---------- Summary ----------
     Assignments lead the row when there are any: work given to you by
     someone else is the one thing here you did not choose to start, and
     the one most easily forgotten. --}}
<div class="stats">
  @if(($assignmentCount ?? 0) > 0)
    <a href="{{ route('assignment.index') }}" class="stat asg-stat">
      <b>{{ $assignmentCount }}</b><span>{{ Str::plural('Assignment', $assignmentCount) }} to work</span>
    </a>
  @endif
  <div class="stat y"><b>{{ $myDrafts->count() }}</b><span>My drafts</span></div>
  @if($canReview)
    <div class="stat r"><b>{{ $awaiting->count() }}</b><span>Awaiting my action</span></div>
  @endif
  <div class="stat"><b>{{ $sentOn->count() }}</b><span>Sent onward</span></div>
  <div class="stat r"><b>{{ $returned->count() }}</b><span>Returned to me</span></div>
  <div class="stat g"><b>{{ $myCompleted->count() }}</b><span>Completed by me</span></div>
</div>

@if($total === 0 && $myCompleted->count() === 0)
  <section>
    <div class="empty">
      <b>Your box is empty</b>
      @if(($assignmentCount ?? 0) > 0)
        <span>
          Nothing is in progress, but
          {{ $assignmentCount }} {{ Str::plural('assignment', $assignmentCount) }}
          {{ $assignmentCount === 1 ? 'is' : 'are' }} waiting on you.
          <a href="{{ route('assignment.index') }}">See what was assigned</a>.
        </span>
      @else
        <span>Nothing is waiting on you. Start an inspection and it will appear here while in progress.</span>
      @endif
      @can('inspection.create')
        {{-- The header already carries Inspect with its panel. A second
             include would put two panels in the page with the same ids,
             and only the first would respond. --}}
        <div style="margin-top:24px">
          <button type="button" class="btn btn-success"
                  onclick="document.getElementById('inspect-btn').click()">Inspect</button>
        </div>
      @endcan
    </div>
  </section>
@endif

{{-- ---------- Awaiting my action ---------- --}}
@if($canReview && $awaiting->count())
<section class="urgent">
  <h3>Awaiting your action</h3>
  <div class="desc">These cannot move forward until you act on them</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Document</th><th>Premises</th><th>Stage</th><th>From</th><th>Waiting</th><th></th></tr></thead>
      <tbody>
        @foreach($awaiting as $d)
          <tr>
            <td><strong>{{ ucfirst($d->type) }}</strong>@if($d->reference_number)<br><span class="ref">{{ $d->reference_number }}</span>@endif</td>
            <td>{{ $d->title }}</td>
            <td><span class="pill weak">{{ Str::headline(str_replace('pending_', 'With ', $d->status)) }}</span></td>
            <td style="font-size:12.5px">{{ $d->creator?->name ?? '—' }}</td>
            <td style="font-size:12.5px;color:var(--body-text)">{{ $d->submitted_at?->diffForHumans() ?? '—' }}</td>
            <td class="num"><a href="{{ route('letter.review', $d) }}" class="btn btn-primary btn-sm">Open</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- ---------- Returned to me ---------- --}}
@if($returned->count())
<section>
  <h3>Returned for revision</h3>
  <div class="desc">Sent back with comments &mdash; correct and resubmit</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Document</th><th>Premises</th><th>Returned</th><th></th></tr></thead>
      <tbody>
        @foreach($returned as $d)
          <tr>
            <td><strong>{{ ucfirst($d->type) }}</strong></td>
            <td>{{ $d->title }}</td>
            <td style="font-size:12.5px;color:var(--body-text)">{{ $d->updated_at->diffForHumans() }}</td>
            <td class="num"><a href="{{ route('editor.edit', $d) }}" class="btn btn-primary btn-sm">Revise</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- ---------- My drafts ---------- --}}
@if($myDrafts->count())
<section>
  <h3>Inspections in progress</h3>
  <div class="desc">Drafts you started but have not yet completed</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Premises</th><th>Location</th><th>Date</th><th style="text-align:center">Progress</th><th>Last edited</th><th></th></tr></thead>
      <tbody>
        @foreach($myDrafts as $d)
          @php
            $answered = $d->answers()->whereNotNull('status')->count();
            $totalItems = $d->template?->sections->sum(fn($s) => $s->items->count()) ?? 0;
            $pct = $totalItems ? round(100 * $answered / $totalItems) : 0;
          @endphp
          <tr>
            <td><strong>{{ $d->entity->name }}</strong>
              @if($d->visit_type === 'followup')<br><span class="pill weak">Follow-up</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $d->entity->district }}@if($d->entity->sector)<br><span style="color:var(--body-text);font-size:11.5px">{{ $d->entity->sector }}</span>@endif</td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $d->inspection_date->format('j M Y') }}</td>
            <td style="text-align:center;min-width:120px">
              <div class="mini"><div class="mini-f" style="width:{{ $pct }}%"></div></div>
              <span style="font-size:11.5px;color:var(--body-text)">{{ $answered }} / {{ $totalItems }}</span>
            </td>
            <td style="font-size:12px;color:var(--body-text);white-space:nowrap">{{ $d->updated_at->diffForHumans() }}</td>
            <td class="num"><a href="{{ route('inspection.edit', $d) }}" class="btn btn-primary btn-sm">Continue</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- ---------- Sent onward ---------- --}}
@if($sentOn->count())
<section>
  <h3>Sent onward</h3>
  <div class="desc">Submitted and now with someone else in the chain</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Document</th><th>Premises</th><th>Now with</th><th>Submitted</th></tr></thead>
      <tbody>
        @foreach($sentOn as $d)
          <tr>
            <td><strong>{{ ucfirst($d->type) }}</strong></td>
            <td>{{ $d->title }}</td>
            <td><span class="pill fair">{{ Str::headline(str_replace('pending_', '', $d->status)) }}</span></td>
            <td style="font-size:12.5px;color:var(--body-text)">{{ $d->submitted_at?->diffForHumans() ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- ---------- Completed ---------- --}}
@if($myCompleted->count())
<section>
  <h3>Completed by me</h3>
  <div class="desc">Your most recent {{ $myCompleted->count() }} inspections</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Premises</th><th>Location</th><th>Date</th><th style="text-align:right">Compliance</th><th></th></tr></thead>
      <tbody>
        @foreach($myCompleted as $i)
          <tr>
            <td><strong>{{ $i->entity->name }}</strong>
              @if($i->visit_number > 1)<br><span style="color:var(--body-text);font-size:11.5px">Visit {{ $i->visit_number }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $i->entity->district }}</td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $i->inspection_date->format('j M Y') }}</td>
            <td class="num">
              <span class="pill {{ \App\Services\InspectionStats::band((float) $i->compliance) }}">{{ round((float) $i->compliance, 1) }}%</span>
            </td>
            <td class="num">
              <a href="{{ route('inspection.show', $i) }}" class="btn btn-ghost btn-sm">Report</a>
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
  section.urgent{border-left:4px solid var(--danger)}
  .ref{font-family:var(--f-head);font-size:11px;color:var(--tertiary);letter-spacing:.5px}
  .mini{height:7px;background:var(--canvas);overflow:hidden;margin-bottom:4px}
  .mini-f{height:100%;background:var(--warning)}

  /* Work given to you by someone else — the one figure here you did not
     choose to start, so it carries a mark of its own rather than sitting
     as another quiet number in the row. */
  .asg-stat{text-decoration:none;position:relative;padding-left:15px}
  .asg-stat b{color:var(--danger)}
  .asg-stat::before{content:'';position:absolute;left:0;top:7px;width:8px;height:8px;
                    border-radius:50%;background:var(--danger)}
  .asg-stat:hover span{text-decoration:underline}

  .sign-due{border-left:4px solid var(--warning);margin-bottom:22px}
  .sd-head{margin-bottom:14px}
  .sd-head h3{color:var(--ink)}
  .sd-desc{font-size:13px;color:var(--body-text);margin-top:3px;max-width:620px;line-height:1.6}
  .sd-list{display:flex;flex-direction:column;gap:1px;background:var(--border)}
  .sd-item{display:flex;align-items:center;gap:16px;padding:13px 15px;background:var(--white);
           text-decoration:none;transition:background .18s}
  .sd-item:hover{background:var(--canvas)}
  .sd-main{flex:1;min-width:0}
  .sd-main b{display:block;font-family:var(--f-head);font-size:14px;color:var(--ink)}
  .sd-main span{display:block;font-size:12px;color:var(--body-text);margin-top:2px}
  .sd-state{text-align:right;flex-shrink:0}
  .sd-tag{display:inline-block;padding:2px 9px;font-family:var(--f-head);font-size:9.5px;
          font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:#fff}
  .sd-tag.officer{background:var(--primary)}
  .sd-tag.director{background:var(--tertiary)}
  .sd-note{display:block;font-size:11px;color:var(--tertiary);margin-top:3px}
  .sd-go{font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.7px;
         text-transform:uppercase;color:var(--primary);flex-shrink:0}

  @media(max-width:640px){
    .sd-item{flex-wrap:wrap;gap:8px}
    .sd-state{text-align:left;width:100%}
    .sd-go{width:100%}
  }
</style>
@endpush