@extends('layouts.app')
@section('title', 'Registry')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Registry</div>
    <h2>Letter Registry</h2>
    <p>Signed letters pass through here to be referenced, printed, stamped, scanned and dispatched to the owner.</p>
  </div>
  <div class="head-actions">
    <div class="next-ref">
      Last number used this year
      <strong>{{ $lastSerial ?: 'none yet' }}</strong>
    </div>
  </div>
</div>

@if(session('status'))
  <div class="note" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif
@if($errors->any())
  <div class="callout"><b>Cannot record that number</b>
    <p style="font-size:14px;font-weight:400">{{ $errors->first() }}</p></div>
@endif

<div class="stats">
  <div class="stat r"><b>{{ $awaiting->count() }}</b><span>Awaiting reference</span></div>
  <div class="stat y"><b>{{ $toPrint->count() }}</b><span>To print</span></div>
  <div class="stat y"><b>{{ $toScan->count() }}</b><span>Awaiting scan</span></div>
  <div class="stat t"><b>{{ $toDispatch->count() }}</b><span>Ready to dispatch</span></div>
  <div class="stat g"><b>{{ $issued->count() }}</b><span>Issued</span></div>
</div>

{{-- ---------- 1. Reference ---------- --}}
@if($awaiting->count())
<section class="step-card">
  <div class="sc-head"><span class="sc-n">1</span> Record the reference</div>
  <div class="desc">Signed by the Chief Inspector. Enter the number taken from the registry book.</div>

  @if($lastSerial)
    <div class="mini" style="margin-bottom:14px">
      The last number recorded in this platform for {{ now()->format('Y') }} was
      <strong>{{ $lastSerial }}</strong>. The registry is shared across the City,
      so the next number is not necessarily {{ $lastSerial + 1 }}.
    </div>
  @endif

  @foreach($awaiting as $l)
    <form method="post" action="{{ route('secretary.reference', $l) }}" class="ref-row">
      @csrf
      <div class="rr-info">
        <strong>{{ $l->title }}</strong><br>
        <span class="mini">{{ Str::limit($l->subject, 60) }} &middot;
          signed {{ $l->approved_at?->format('j M Y') }}
          @if($l->signatures->first()) by {{ $l->signatures->first()->signer_name }}@endif</span>
      </div>
      
      @php
        $fine = \App\Models\Fine::where('inspection_id', $l->inspection_id)
            ->where('status', \App\Models\Fine::PROPOSED)->first();
      @endphp

      @if($fine)
        <div class="rr-fine" title="Recording the reference will confirm this fine">
          <span>Fine</span>
          <b>FRW {{ number_format((float) $fine->amount, 0) }}</b>
          <em>confirmed on recording</em>
        </div>
      @endif
      

      <div class="rr-ref">
        <input type="number" name="serial" required min="1" max="999999"
               placeholder="8211" value="{{ old('serial') }}"
               aria-label="Registry number">
        <span class="rr-fixed">/{{ config('letters.reference.registry_code') }}/{{ now()->format('y') }}</span>
      </div>

      <div class="rr-acts">
        <a href="{{ route('letter.show', $l) }}" class="btn btn-ghost btn-sm">View</a>
        <button type="submit" class="btn btn-primary btn-sm">Record</button>
      </div>
    </form>
  @endforeach
</section>
@endif

{{-- ---------- 2. Print ---------- --}}
@if($toPrint->count())
<section class="step-card">
  <div class="sc-head"><span class="sc-n">2</span> Print and stamp</div>
  <div class="desc">Print, obtain the stamp, then record it here</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Premises</th><th></th></tr></thead>
      <tbody>
        @foreach($toPrint as $l)
          <tr>
            <td><strong>{{ $l->reference_number }}</strong></td>
            <td>{{ $l->title }}</td>
            <td class="num">
              <a href="{{ route('letter.docx', $l) }}" class="btn btn-ghost btn-sm">Download</a>
              <form method="post" action="{{ route('secretary.printed', $l) }}" style="display:inline">
                @csrf<button type="submit" class="btn btn-primary btn-sm">Mark Printed</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- ---------- 3. Scan ---------- --}}
@if($toScan->count())
<section class="step-card">
  <div class="sc-head"><span class="sc-n">3</span> Upload the stamped copy</div>
  <div class="desc">PDF or photograph of the signed and stamped letter</div>
  @foreach($toScan as $l)
    <form method="post" action="{{ route('secretary.scan', $l) }}" enctype="multipart/form-data" class="scan-row">
      @csrf
      <div class="sr-info"><strong>{{ $l->reference_number }}</strong><br>
        <span class="mini">{{ $l->title }}</span></div>
      <input type="file" name="scan" accept=".pdf,image/*" required>
      <button type="submit" class="btn btn-primary btn-sm">Upload</button>
    </form>
  @endforeach
</section>
@endif

{{-- ---------- 4. Dispatch ---------- --}}
@if($toDispatch->count())
<section class="step-card">
  <div class="sc-head"><span class="sc-n">4</span> Dispatch to the owner</div>
  <div class="desc">Send the stamped copy and record how it was delivered</div>
  @foreach($toDispatch as $l)
    @php $entity = \App\Models\Inspection::find($l->inspection_id)?->entity; @endphp
    <form method="post" action="{{ route('secretary.dispatch', $l) }}" class="disp">
      @csrf
      <div class="d-head">
        <strong>{{ $l->reference_number }}</strong> &middot; {{ $l->title }}
        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($l->scan_path) }}"
           target="_blank" class="btn btn-ghost btn-sm">View scan</a>
      </div>
      <div class="d-grid">
        <div><label>Send to</label>
          <input type="email" name="to" required value="{{ $entity?->email }}"
                 placeholder="{{ $entity?->email ? '' : 'No email on record' }}"></div>
        <div><label>Copy to</label>
          <input type="text" name="cc" placeholder="Optional, comma separated"></div>
        <div><label>Method</label>
          <select name="method">
            <option value="email">Email</option>
            <option value="hand">By hand</option>
            <option value="post">By post</option>
            <option value="both">Email and by hand</option>
          </select></div>
        <div><label>Note</label><input type="text" name="note" placeholder="Optional"></div>
      </div>
      <div style="text-align:right;margin-top:12px">
        <button type="submit" class="btn btn-success">Dispatch</button>
      </div>
    </form>
  @endforeach
</section>
@endif

{{-- ---------- Issued ---------- --}}
@if($issued->count())
<section>
  <h3>Issued</h3>
  <div class="desc">Delivered to the owner</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Premises</th><th>Sent to</th><th>Dispatched</th><th></th></tr></thead>
      <tbody>
        @foreach($issued as $l)
          <tr>
            <td><strong>{{ $l->reference_number }}</strong></td>
            <td>{{ $l->title }}</td>
            <td style="font-size:12.5px">{{ $l->dispatched_to }}</td>
            <td style="font-size:12.5px">{{ $l->dispatched_at?->format('j M Y, H:i') }}</td>
            <td class="num"><a href="{{ route('letter.show', $l) }}" class="btn btn-ghost btn-sm">Open</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@if(! $awaiting->count() && ! $toPrint->count() && ! $toScan->count() && ! $toDispatch->count())
  <section>
    <div class="empty"><b>Nothing awaiting handling</b>
      <span>Letters appear here once the Chief Inspector has signed them.</span></div>
  </section>
@endif



@endsection

@push('styles')
<style>
  .next-ref{background:var(--white);padding:12px 18px;box-shadow:var(--shadow-sm);
            border-left:4px solid var(--primary);font-family:var(--f-head);font-size:11px;
            font-weight:600;letter-spacing:.7px;text-transform:uppercase;color:var(--tertiary)}
  .next-ref strong{display:block;font-size:17px;color:var(--primary);letter-spacing:0;
                   text-transform:none;margin-top:3px}
  .step-card{border-left:4px solid var(--primary)}
  .sc-head{font-family:var(--f-head);font-size:16px;font-weight:600;color:var(--ink);
           display:flex;align-items:center;gap:11px;margin-bottom:4px}
  .sc-n{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;
        background:var(--primary);color:#fff;font-size:13px;font-weight:700}
  .mini{font-size:11.5px;color:var(--tertiary)}
  .scan-row{display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:14px 0;
            border-top:1px solid var(--border)}
  .sr-info{flex:1;min-width:200px}
  .disp{padding:18px 0;border-top:1px solid var(--border)}
  .d-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;font-size:14px}
  .d-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
  .ref-row{display:flex;gap:16px;align-items:center;flex-wrap:wrap;
           padding:16px 0;border-top:1px solid var(--border)}
  .rr-info{flex:1;min-width:220px}
  .rr-ref{display:flex;align-items:center;gap:2px}
  .rr-ref input{width:110px;padding:10px 12px;border:2px solid var(--primary);
                font-family:var(--f-head);font-size:16px;font-weight:700;
                text-align:right;color:var(--primary)}
  .rr-fixed{font-family:var(--f-head);font-size:16px;font-weight:700;color:var(--tertiary)}
  .rr-acts{display:flex;gap:8px}
</style>
@endpush
