@extends('layouts.app')
@section('title', 'Search')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Search</div>
    <h2>Find anything</h2>
    <p>Case reference, UPI, premises name, owner, telephone or email. A case reference
       returns the report, the letters and the fines together.</p>
  </div>
</div>

<section class="search-box">
  <form method="get">
    <input type="text" name="q" value="{{ $term }}" autofocus autocomplete="off"
           placeholder="8211/07.01.14/26   ·   1/02/05/03/4745   ·   ENGEN KABUGA   ·   0788…">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
  @if($term === '')
    <div class="hints">
      <span><strong>Case reference</strong> 8211/07.01.14/26</span>
      <span><strong>Parcel</strong> 1/02/05/03/4745</span>
      <span><strong>Premises or owner</strong> ENGEN KABUGA</span>
      <span><strong>Contact</strong> telephone or email</span>
    </div>
  @endif
</section>

@if($term !== '')

  <div class="result-head">
    <strong>{{ $total }}</strong> {{ Str::plural('result', $total) }} for &ldquo;{{ $term }}&rdquo;
  </div>

  @if($total === 0)
    <section>
      <div class="empty">
        <b>Nothing found</b>
        <span>No case, premises, letter or fine matches that. Check the spelling, or try
          part of the reference rather than all of it.</span>
      </div>
    </section>
  @endif

  {{-- ---------- Cases ---------- --}}
  @if($cases->count())
  <section>
    <h3>Inspections</h3>
    <div class="desc">{{ $cases->count() }} found</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Case</th><th>Premises</th><th>Date</th><th>Inspector</th>
                   <th style="text-align:right">Compliance</th><th></th></tr></thead>
        <tbody>
          @foreach($cases as $c)
            <tr>
              <td><strong>{{ $c->case_reference ?: '—' }}</strong>
                @if($c->status === 'draft')<br><span class="pill weak">Draft</span>@endif</td>
              <td>{{ $c->entity->name }}<br><span class="mini">{{ $c->entity->district }}
                @if($c->entity->upi) &middot; {{ $c->entity->upi }}@endif</span></td>
              <td style="white-space:nowrap;font-size:12.5px">{{ $c->inspection_date->format('j M Y') }}
                @if($c->visit_number > 1)<br><span class="mini">Visit {{ $c->visit_number }}</span>@endif</td>
              <td style="font-size:12.5px">{{ $c->inspector_name }}</td>
              <td class="num">
                <span class="pill {{ \App\Services\InspectionStats::band((float) $c->compliance) }}">{{ round((float) $c->compliance, 1) }}%</span>
              </td>
              <td class="num"><a href="{{ route('inspection.show', $c) }}" class="btn btn-ghost btn-sm">Report</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @endif

  {{-- ---------- Letters ---------- --}}
  @if($letters->count())
  <section>
    <h3>Letters</h3>
    <div class="desc">{{ $letters->count() }} found</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Case</th><th>Premises</th><th>Subject</th>
                   <th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($letters as $l)
            <tr>
              <td><strong>{{ $l->reference_number ?: 'Not referenced' }}</strong></td>
              <td style="font-size:12.5px">{{ $l->case_reference ?: '—' }}</td>
              <td>{{ $l->title }}</td>
              <td style="font-size:12.5px">{{ Str::limit($l->subject, 44) }}</td>
              <td><span class="pill {{ $l->status === 'issued' ? 'good' : ($l->status === 'returned' ? 'poor' : 'fair') }}">
                {{ Str::headline($l->status) }}</span></td>
              <td class="num"><a href="{{ route('letter.show', $l) }}" class="btn btn-ghost btn-sm">Open</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @endif

  {{-- ---------- Fines ---------- --}}
  @if($fines->count())
  <section>
    <h3>Fines</h3>
    <div class="desc">{{ $fines->count() }} found</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Premises</th><th style="text-align:right">Amount</th>
                   <th style="text-align:right">Outstanding</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach($fines as $f)
            <tr class="{{ $f->isOverdue() ? 'overdue' : '' }}">
              <td><strong>{{ $f->reference ?: '—' }}</strong>
                @if($f->case_reference && $f->case_reference !== $f->reference)
                  <br><span class="mini">{{ $f->case_reference }}</span>@endif</td>
              <td>{{ $f->entity->name }}</td>
              <td class="num">{{ number_format((float) $f->amount) }}</td>
              <td class="num">{{ $f->isPayable() ? number_format($f->outstanding()) : '—' }}</td>
              <td><span class="pill {{ \App\Models\Fine::statusTone($f->status) }}">{{ Str::headline($f->status) }}</span></td>
              <td class="num"><a href="{{ route('fine.show', $f) }}" class="btn btn-ghost btn-sm">Open</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @endif

  {{-- ---------- Premises ---------- --}}
  @if($premises->count())
  <section>
    <h3>Premises</h3>
    <div class="desc">{{ $premises->count() }} found</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Name</th><th>UPI</th><th>Owner</th><th>Contact</th><th>Location</th>
                   <th style="text-align:center">Inspections</th></tr></thead>
        <tbody>
          @foreach($premises as $p)
            <tr>
              <td><strong>{{ $p->name }}</strong></td>
              <td style="font-size:12px">{{ $p->upi ?: '—' }}</td>
              <td>{{ $p->owner ?: '—' }}</td>
              <td style="font-size:12px">
                {{ $p->telephone }}@if($p->telephone && $p->email)<br>@endif{{ $p->email }}
                @if(! $p->telephone && ! $p->email)—@endif
              </td>
              <td style="font-size:12.5px">{{ collect([$p->district, $p->sector])->filter()->implode(' · ') }}</td>
              <td style="text-align:center"><strong>{{ $p->inspections_count }}</strong></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @endif

@endif



@endsection

@push('styles')
<style>
  .search-box form{display:flex;gap:12px;flex-wrap:wrap}
  .search-box input{flex:1;min-width:260px;padding:15px 18px;font-size:16px;
                    border:2px solid var(--border);font-family:var(--f-body)}
  .search-box input:focus{border-color:var(--primary);outline:none}
  .search-box .btn{padding:15px 28px}
  .hints{display:flex;gap:26px;flex-wrap:wrap;margin-top:18px;font-size:12.5px;color:var(--body-text)}
  .hints strong{display:block;font-family:var(--f-head);font-size:10.5px;letter-spacing:.7px;
                text-transform:uppercase;color:var(--tertiary);margin-bottom:2px}
  .result-head{font-family:var(--f-head);font-size:13px;color:var(--body-text);margin-bottom:18px}
  .result-head strong{color:var(--primary);font-size:16px}
  .mini{font-size:11.5px;color:var(--tertiary)}
  tr.overdue td{background:#FDECEA}
</style>
@endpush
