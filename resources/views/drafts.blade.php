@extends('layouts.app')
@section('title', 'Draft Inspections')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Inspections &middot; {{ $scope }}</div>
    <h2>Drafts</h2>
    <p>Inspections started but not yet completed. A draft can be reopened and finished at any time.</p>
  </div>
  <div class="head-actions">
    @can('inspection.create')
      <a href="{{ route('entity.add', 'petrol') }}" class="btn btn-success">Add File</a>
    @endcan
  </div>
</div>

<div class="stats">
  <div class="stat y"><b>{{ $drafts->count() }}</b><span>Drafts open</span></div>
  <div class="stat"><b>{{ $drafts->where('inspector_id', auth()->id())->count() }}</b><span>Started by you</span></div>
  <div class="stat t"><b>{{ $drafts->unique('entity_id')->count() }}</b><span>Premises</span></div>
</div>

<section>
  <h3>Open drafts</h3>
  <div class="desc">Most recently edited first</div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Premises</th><th>Location</th><th>Date</th><th>Inspector</th>
            <th style="text-align:center">Answered</th><th>Last edited</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($drafts as $d)
          @php
            $answered = $d->answers()->whereNotNull('status')->count();
            $total    = $d->template?->sections->sum(fn($s) => $s->items->count()) ?? 0;
            $pct      = $total ? round(100 * $answered / $total) : 0;
          @endphp
          <tr>
            <td><strong>{{ $d->entity->name }}</strong>
              @if($d->visit_type === 'followup')<br><span class="pill weak" style="font-size:9.5px">Follow-up</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $d->entity->district }}@if($d->entity->sector)<br><span style="color:var(--muted);font-size:11px">{{ $d->entity->sector }}</span>@endif</td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $d->inspection_date->format('j M Y') }}</td>
            <td style="font-size:12.5px">{{ $d->inspector_name }}</td>
            <td style="text-align:center;min-width:110px">
              <div class="mini-track"><div class="mini-fill" style="width:{{ $pct }}%"></div></div>
              <span style="font-size:11px;color:var(--muted)">{{ $answered }} / {{ $total }}</span>
            </td>
            <td style="font-size:12px;color:var(--muted);white-space:nowrap">{{ $d->updated_at->diffForHumans() }}</td>
            <td class="num">
              <a href="{{ route('inspection.edit', $d) }}" class="btn btn-primary btn-sm">Continue</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:34px">
            No open drafts. Every inspection in your scope has been completed.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>



@endsection

@push('styles')
<style>
  .mini-track{height:6px;background:#EEF1F5;border-radius:4px;overflow:hidden;margin-bottom:3px}
  .mini-fill{height:100%;background:var(--gold);border-radius:4px}
</style>
@endpush
