@extends('layouts.app')
@section('title', 'Registry')

@section('content')

@php $pending = $awaiting->count() + $toPrint->count() + $toScan->count() + $toDispatch->count(); @endphp

<div class="page-head">
  <div>
    <div class="crumb">Secretary</div>
    <h2>Good day, {{ Str::before(auth()->user()->name, ' ') }}</h2>
    <p>
      @if($pending)
        <strong>{{ $pending }}</strong> {{ Str::plural('letter', $pending) }} awaiting handling.
      @else
        Nothing is waiting on you. Letters appear here once the Chief Inspector has signed them.
      @endif
    </p>
  </div>
  <div class="head-actions">
    <a href="{{ route('secretary.desk') }}" class="btn btn-success">Open the Registry</a>
    <a href="{{ route('archive.index') }}" class="btn btn-ghost">E-Archive</a>
  </div>
</div>

<div class="next-ref-band">
  <div><span>Next reference</span><strong>{{ $nextRef }}</strong></div>
  <div><span>Issued this month</span><strong>{{ $issuedMonth }}</strong></div>
  <div><span>Issued in total</span><strong>{{ $issuedTotal }}</strong></div>
</div>

<div class="stats">
  <div class="stat r"><b>{{ $awaiting->count() }}</b><span>Awaiting reference</span></div>
  <div class="stat y"><b>{{ $toPrint->count() }}</b><span>To print and stamp</span></div>
  <div class="stat y"><b>{{ $toScan->count() }}</b><span>Awaiting scan</span></div>
  <div class="stat t"><b>{{ $toDispatch->count() }}</b><span>Ready to dispatch</span></div>
</div>

@if($pending)
<section>
  <h3>What is waiting</h3>
  <div class="desc">In the order it must be handled</div>

  @foreach([
    ['Assign a reference', $awaiting,   'r', 'Signed and waiting for a registry number'],
    ['Print and stamp',    $toPrint,    'y', 'Referenced and ready to print'],
    ['Upload the stamped copy', $toScan,'y', 'Printed and awaiting the scan'],
    ['Dispatch to the owner', $toDispatch, 't', 'Scanned and ready to send'],
  ] as [$title, $set, $tone, $note])
    @if($set->count())
      <div class="q-block {{ $tone }}">
        <div class="q-head">
          <strong>{{ $title }}</strong>
          <span class="q-count">{{ $set->count() }}</span>
        </div>
        <div class="q-note">{{ $note }}</div>
        <ul class="q-list">
          @foreach($set->take(5) as $l)
            <li>
              <a href="{{ route('letter.show', $l) }}">
                {{ $l->reference_number ?: 'Not referenced' }} — {{ $l->title }}
              </a>
              <span class="mini">signed {{ $l->approved_at?->diffForHumans() }}</span>
            </li>
          @endforeach
        </ul>
        @if($set->count() > 5)
          <div class="mini">and {{ $set->count() - 5 }} more</div>
        @endif
      </div>
    @endif
  @endforeach

  <div style="margin-top:20px">
    <a href="{{ route('secretary.desk') }}" class="btn btn-primary">Handle these in the Registry</a>
  </div>
</section>
@endif

@if($recent->count())
<section>
  <h3>Recently issued</h3>
  <div class="desc">Letters served on owners</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Premises</th><th>Sent to</th><th>Dispatched</th><th></th></tr></thead>
      <tbody>
        @foreach($recent as $l)
          <tr>
            <td><strong>{{ $l->reference_number }}</strong></td>
            <td>{{ $l->title }}</td>
            <td style="font-size:12.5px">{{ $l->dispatched_to }}</td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $l->dispatched_at?->format('j M Y') }}</td>
            <td class="num"><a href="{{ route('letter.show', $l) }}" class="btn btn-ghost btn-sm">Open</a></td>
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
  .next-ref-band{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1px;
                 background:var(--border);margin-bottom:22px;box-shadow:var(--shadow-sm)}
  .next-ref-band > div{background:var(--white);padding:18px 22px}
  .next-ref-band span{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                      letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary)}
  .next-ref-band strong{display:block;font-family:var(--f-head);font-size:22px;
                        color:var(--primary);margin-top:4px}
  .q-block{padding:16px 18px;margin-bottom:14px;background:var(--canvas);border-left:4px solid var(--primary)}
  .q-block.r{border-left-color:var(--danger)} .q-block.y{border-left-color:var(--warning)}
  .q-block.t{border-left-color:var(--tertiary)}
  .q-head{display:flex;justify-content:space-between;align-items:center;
          font-family:var(--f-head);font-size:15px}
  .q-count{background:var(--primary);color:#fff;padding:2px 11px;font-size:13px;font-weight:700}
  .q-note{font-size:12.5px;color:var(--body-text);margin:3px 0 10px}
  .q-list{list-style:none;margin:0;padding:0}
  .q-list li{padding:5px 0;font-size:13.5px;border-top:1px solid var(--border)}
  .q-list a{color:var(--primary);text-decoration:none;font-weight:600}
  .mini{font-size:11.5px;color:var(--tertiary);margin-left:8px}
</style>
@endpush
