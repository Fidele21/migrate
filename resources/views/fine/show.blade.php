@extends('layouts.app')
@section('title', 'Fine ' . ($fine->reference ?: ''))

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Enforcement &middot; {{ Str::headline($fine->status) }}</div>
    <h2>{{ $fine->entity->name }}</h2>
    <p>{{ $fine->reference ?: 'Reference assigned on confirmation' }}
       @if($fine->inspection) &middot; from the inspection of {{ $fine->inspection->inspection_date->format('j F Y') }}@endif</p>
  </div>
  <div class="head-actions">
    @if($fine->inspection)
      <a href="{{ route('inspection.show', $fine->inspection) }}" class="btn btn-ghost">Inspection</a>
    @endif
    <a href="{{ route('fine.index') }}" class="btn btn-ghost">Register</a>
  </div>
</div>

@if(session('status'))
  <div class="note" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif
@if($errors->any())
  <div class="callout"><b>Cannot proceed</b><p style="font-size:14px;font-weight:400">{{ $errors->first() }}</p></div>
@endif

<div class="money {{ \App\Models\Fine::statusTone($fine->status) }}">
  <div>
    <div class="m-label">Amount</div>
    <div class="m-big">{{ number_format((float) $fine->amount) }} <span>RWF</span></div>
  </div>
  <div>
    <div class="m-label">Paid</div>
    <div class="m-mid">{{ number_format((float) $fine->amount_paid) }}</div>
  </div>
  <div>
    <div class="m-label">Outstanding</div>
    <div class="m-mid">{{ $fine->isPayable() ? number_format($fine->outstanding()) : '—' }}</div>
  </div>
  <div style="text-align:right">
    <div class="m-label">Status</div>
    <div class="m-status">{{ Str::headline($fine->status) }}</div>
    @if($fine->isOverdue())<div class="m-over">Overdue since {{ $fine->due_date->format('j M Y') }}</div>@endif
  </div>
</div>

<div class="grid2">
  <section>
    <h3>The penalty</h3>
    <div class="desc">As proposed and decided</div>
    <table>
      <tbody>
        <tr><td class="k">Premises</td><td><strong>{{ $fine->entity->name }}</strong><br>
          <span class="mini">{{ collect([$fine->entity->district, $fine->entity->sector])->filter()->implode(' · ') }}</span></td></tr>
        <tr><td class="k">Owner</td><td>{{ $fine->entity->owner ?: '—' }}</td></tr>
        <tr><td class="k">Reason</td><td>{{ $fine->reason }}</td></tr>
        <tr><td class="k">Legal basis</td><td>{{ $fine->legal_basis ?: '—' }}</td></tr>
        <tr><td class="k">Due</td><td>{{ $fine->due_date?->format('j F Y') ?: 'Not set' }}</td></tr>
        <tr><td class="k">Proposed by</td><td>{{ $fine->proposer?->name }}<br>
          <span class="mini">{{ $fine->proposed_at->format('j M Y, H:i') }}</span></td></tr>
        @if($fine->confirmed_at)
          <tr><td class="k">Decided by</td><td>{{ $fine->confirmer?->name }}<br>
            <span class="mini">{{ $fine->confirmed_at->format('j M Y, H:i') }}</span>
            @if($fine->confirm_note)<br>{{ $fine->confirm_note }}@endif</td></tr>
        @endif
      </tbody>
    </table>
  </section>

  <section>
    @if($canConfirm)
      <h3>Confirm this fine</h3>
      <div class="desc">Confirming makes it payable. The amount may be adjusted.</div>
      <form method="post" action="{{ route('fine.confirm', $fine) }}">
        @csrf
        <div style="display:grid;gap:14px">
          <div><label>Decision</label>
            <select name="decision" id="decision">
              <option value="confirm">Confirm the fine</option>
              <option value="waive">Waive it</option>
              <option value="cancel">Cancel it</option>
            </select></div>
          <div id="amt"><label>Amount (RWF)</label>
            <input type="number" name="amount" step="1" min="1" value="{{ (int) $fine->amount }}"></div>
          <div id="due"><label>Due date</label>
            <input type="date" name="due_date" value="{{ $fine->due_date?->format('Y-m-d') ?: now()->addDays(30)->format('Y-m-d') }}"></div>
          <div><label>Note</label><textarea name="note" rows="3"></textarea></div>
          <div><button type="submit" class="btn btn-success">Record Decision</button></div>
        </div>
      </form>
      <script>
        document.getElementById('decision').addEventListener('change', function () {
          var show = this.value === 'confirm';
          document.getElementById('amt').style.display = show ? '' : 'none';
          document.getElementById('due').style.display = show ? '' : 'none';
        });
      </script>

    @elseif($canRecord)
      <h3>Record a payment</h3>
      <div class="desc">{{ number_format($fine->outstanding()) }} RWF outstanding</div>
      <form method="post" action="{{ route('fine.pay', $fine) }}">
        @csrf
        <div style="display:grid;gap:14px">
          <div><label>Amount received (RWF)</label>
            <input type="number" name="amount" step="1" min="1" max="{{ (int) $fine->outstanding() }}"
                   value="{{ (int) $fine->outstanding() }}" required></div>
          <div><label>Date received</label>
            <input type="date" name="paid_on" max="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" required></div>
          <div><label>Method</label>
            <select name="method" required>
              <option>Bank transfer</option><option>Mobile money</option>
              <option>Cash</option><option>Cheque</option>
            </select></div>
          <div><label>Payment reference</label><input type="text" name="reference"></div>
          <div><label>Note</label><textarea name="note" rows="2"></textarea></div>
          <div><button type="submit" class="btn btn-success">Record Payment</button></div>
        </div>
      </form>

    @else
      <h3>Status</h3>
      <div class="desc">No action is available to you on this fine</div>
      <div class="empty" style="padding:30px">
        <b>{{ Str::headline($fine->status) }}</b>
        <span>
          @switch($fine->status)
            @case('proposed') Awaiting confirmation by a Senior or Chief Inspector. @break
            @case('paid')     Settled in full. @break
            @case('waived')   Waived and no longer payable. @break
            @case('cancelled')Cancelled. @break
            @default          Payable. The Recovery Officer records settlement.
          @endswitch
        </span>
      </div>
    @endif
  </section>
</div>

@if($fine->payments->count())
<section>
  <h3>Payments received</h3>
  <div class="desc">{{ $fine->payments->count() }} recorded</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Date</th><th style="text-align:right">Amount</th><th>Method</th>
                 <th>Reference</th><th>Recorded by</th><th>Note</th></tr></thead>
      <tbody>
        @foreach($fine->payments as $p)
          <tr>
            <td style="white-space:nowrap">{{ $p->paid_on->format('j M Y') }}</td>
            <td class="num">{{ number_format((float) $p->amount) }}</td>
            <td style="font-size:12.5px">{{ $p->method }}</td>
            <td style="font-size:12.5px">{{ $p->reference ?: '—' }}</td>
            <td style="font-size:12.5px">{{ $p->recorder?->name }}</td>
            <td style="font-size:12.5px">{{ $p->note ?: '—' }}</td>
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
  .money{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px;
         padding:24px 26px;margin-bottom:22px;color:#fff;align-items:center}
  .money.good{background:var(--success)} .money.weak{background:var(--warning)}
  .money.poor{background:var(--danger)}  .money.fair{background:var(--primary)}
  .money.na{background:#9E9E9E}
  .m-label{font-family:var(--f-head);font-size:10px;letter-spacing:1.2px;text-transform:uppercase;
           opacity:.9;font-weight:600}
  .m-big{font-family:var(--f-head);font-size:34px;font-weight:800;line-height:1.1}
  .m-big span{font-size:15px;font-weight:600}
  .m-mid{font-family:var(--f-head);font-size:23px;font-weight:700}
  .m-status{font-family:var(--f-head);font-size:19px;font-weight:700}
  .m-over{font-size:11.5px;opacity:.95;margin-top:3px}
  td.k{color:var(--tertiary);width:30%;font-family:var(--f-head);font-size:11.5px;
       text-transform:uppercase;letter-spacing:.5px}
  .mini{font-size:11.5px;color:var(--tertiary)}
</style>
@endpush
