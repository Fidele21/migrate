{{--
  Faults found, and what the schedule sets against them.

  Distinct from the checklist above. A checklist asks whether a
  requirement was met and scores the answer; a fault is a specific
  contravention with a penalty. A premises can score well and still carry
  one — good safety practice, built without a permit.

  The fine follows the faults: completing the inspection raises the
  proposal, and editing it adjusts the figure. Nothing is owed until the
  report is signed and a letter served.
--}}

@php
  $faults = $inspection->faults ?? collect();
  $fine   = $inspection->fines->first();
  $total  = (float) $faults->sum('amount');
  $noFine = $faults->filter(fn ($f) => ! (float) $f->amount);
@endphp

@if($faults->count())
<section class="ff-block">
  <div class="ff-head">
    <div>
      <h3>Faults observed</h3>
      <div class="desc">
        Contraventions under the Urban Planning Code, separate from the
        checklist above
      </div>
    </div>

    <div class="ff-total {{ $total > 0 ? 'live' : '' }}">
      <span>{{ $fine ? 'Proposed fine' : 'Schedule sets' }}</span>
      <b>FRW {{ number_format($total, 0) }}</b>
      @if($fine)
        <a href="{{ route('fine.show', $fine) }}">
          {{ $fine->reference ?: 'Open the fine' }} &rsaquo;
        </a>
      @else
        <em>no fine raised yet</em>
      @endif
    </div>
  </div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:26px">No</th>
          <th>Fault</th>
          <th>Sanction</th>
          <th class="num" style="width:110px">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($faults as $n => $f)
          <tr>
            <td class="num">{{ $n + 1 }}</td>
            <td>
              <strong>{{ $f->fault?->title ?? '—' }}</strong>
              @if($f->scope)<br><span class="ff-scope">{{ $f->scope }}</span>@endif
              @if($f->note)<div class="ff-note">{{ $f->note }}</div>@endif
            </td>
            <td style="font-size:12.5px">{{ $f->action ?: '—' }}</td>
            <td class="num">
              @if((float) $f->amount)
                {{ number_format((float) $f->amount, 0) }}
              @else
                <span class="ff-none">Not sanctioned<br>for this category</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="num ff-sum-l">Total under the schedule</td>
          <td class="num ff-sum">FRW {{ number_format($total, 0) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  @if($noFine->count())
    <div class="ff-warn">
      {{ $noFine->count() }} {{ Str::plural('fault', $noFine->count()) }}
      above {{ $noFine->count() === 1 ? 'carries' : 'carry' }} no fine.
      Either the sanction is the action stated rather than a payment, or
      the schedule sets no penalty for this building category. Neither is
      an omission, and neither is counted in the total.
    </div>
  @endif

  @if($fine)
    <div class="ff-state">
      <span class="pill {{ $fine->status === 'proposed' ? 'na' : ($fine->status === 'paid' ? 'good' : 'weak') }}">
        {{ $fine->status === 'confirmed' ? 'Approved' : Str::headline($fine->status) }}
      </span>
      @if($fine->status === 'proposed')
        Proposed on {{ $fine->proposed_at?->format('j F Y') }}. Nothing is
        owed until the report is signed and a letter served.
      @else
        Confirmed {{ $fine->confirmed_at?->format('j F Y') }}
        @if($fine->amount_paid > 0)
          &middot; FRW {{ number_format((float) $fine->amount_paid, 0) }} paid
        @endif
      @endif
    </div>
  @endif
</section>
@endif

@push('styles')
<style>
  .ff-block{border-left:4px solid var(--danger)}
  .ff-head{display:flex;justify-content:space-between;align-items:flex-start;
           gap:20px;flex-wrap:wrap;margin-bottom:14px}
  .ff-head .desc{margin-bottom:0;max-width:520px}

  .ff-total{text-align:right;flex-shrink:0;padding:10px 16px;background:var(--canvas);
            border-left:3px solid var(--border)}
  .ff-total.live{border-left-color:var(--danger);background:#FDECEA}
  .ff-total span{display:block;font-family:var(--f-head);font-size:9px;font-weight:600;
                 letter-spacing:1px;text-transform:uppercase;color:var(--tertiary)}
  .ff-total b{display:block;font-family:var(--f-head);font-size:20px;font-weight:800;
              color:var(--ink);line-height:1.2;margin-top:2px}
  .ff-total.live b{color:var(--danger)}
  .ff-total a{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
              letter-spacing:.5px;text-transform:uppercase;color:var(--primary);
              margin-top:4px;text-decoration:none}
  .ff-total a:hover{text-decoration:underline}
  .ff-total em{display:block;font-style:normal;font-size:11px;color:var(--tertiary);margin-top:4px}

  .ff-scope{font-size:11.5px;color:var(--tertiary)}
  .ff-note{font-size:11.5px;color:var(--body-text);font-style:italic;margin-top:3px}
  .ff-none{font-size:10.5px;color:var(--tertiary);font-style:italic;line-height:1.4}
  .ff-sum-l{font-weight:700;background:var(--canvas)}
  .ff-sum{font-weight:800;color:var(--danger);background:var(--canvas)}

  .ff-warn{margin-top:12px;padding:10px 14px;background:#FFF8E5;
           border-left:3px solid var(--warning);font-size:12.5px;color:#6B4A00;line-height:1.6}
  .ff-state{margin-top:14px;padding-top:12px;border-top:1px solid var(--border);
            font-size:12.5px;color:var(--body-text);line-height:1.6}
  .ff-state .pill{margin-right:8px}

  @media print{.ff-total a{display:none}}
</style>
@endpush