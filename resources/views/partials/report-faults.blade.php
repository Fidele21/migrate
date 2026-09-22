{{--
  Faults found at the inspection.

  Included by both the editable report and the Word export, so the two
  cannot drift apart — which is what happened with the letter, where the
  screen said one thing and the document another.

  A fault is not a checklist finding. The checklist scores whether
  requirements were met; a fault is a contravention with a sanction from
  the schedule in the Urban Planning Code. A site can score well and still
  carry one.

  Expects: $inspection, and optionally $heading (the section number).
--}}

@php
  $faults = $inspection->faults ?? collect();
  $total  = (float) $faults->sum('amount');
  $unpriced = $faults->whereNull('amount');
@endphp

@if($faults->count())

<h2>@if(!empty($heading)){{ $heading }}. @endif Faults observed</h2>

<p class="rf-lead">
  The following contraventions were found. The amounts are those set by
  the administrative sanctions schedule; nothing is owed until this report
  is signed and an enforcement letter issued.
</p>

<table class="rf-table">
  <thead>
    <tr>
      <th style="width:26pt">No</th>
      <th>Fault</th>
      <th style="width:96pt">Sanction</th>
      <th style="width:78pt;text-align:right">Amount</th>
    </tr>
  </thead>
  <tbody>
    @foreach($faults as $n => $f)
      <tr>
        <td class="rf-n">{{ $n + 1 }}</td>
        <td>
          <strong>{{ $f->fault?->title ?? '—' }}</strong>
          @if($f->scope)
            <span class="rf-scope">{{ $f->scope }}</span>
          @endif
          @if($f->note)
            <div class="rf-note">{{ $f->note }}</div>
          @endif
        </td>
        <td class="rf-action">{{ $f->action ?: '—' }}</td>
        <td class="rf-amt">
          @if($f->amount)
            {{ number_format((float) $f->amount, 0) }}
          @else
            <span class="rf-none">No fine</span>
          @endif
        </td>
      </tr>
    @endforeach
  </tbody>

  <tfoot>
    <tr>
      <td colspan="3" class="rf-total-l">Total proposed under the schedule</td>
      <td class="rf-total">FRW {{ number_format($total, 0) }}</td>
    </tr>
  </tfoot>
</table>

@if($unpriced->count())
  <p class="rf-warn">
    {{ $unpriced->count() }}
    {{ Str::plural('fault', $unpriced->count()) }}
    above {{ $unpriced->count() === 1 ? 'carries' : 'carry' }} no fine.
    The sanction is the action stated, not a payment, and is not included
    in the total.
  </p>
@endif

@endif