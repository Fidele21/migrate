@extends('layouts.app')
@section('title', 'Review — ' . $letter->title)

@section('content')

@php
  $cfg  = config('letters');
  $type = $cfg['types'][$letter->letter_type] ?? $cfg['types']['enforcement'];
  $e    = $inspection->entity;
  [$verdict, $tone] = $inspection->deliberation();

  $stageName = [
    \App\Models\Document::DRAFT            => 'Draft',
    \App\Models\Document::RETURNED         => 'Returned for revision',
    \App\Models\Document::PENDING_DIRECTOR => 'With the Director of Inspection',
    \App\Models\Document::PENDING_SENIOR   => 'With the Senior Inspector',
    \App\Models\Document::PENDING_CHIEF    => 'Awaiting the Chief Inspector',
    \App\Models\Document::APPROVED         => 'Approved and signed',
    \App\Models\Document::ISSUED           => 'Issued',
  ][$letter->status] ?? $letter->status;

  $actionLabel = match($letter->status) {
    \App\Models\Document::DRAFT, \App\Models\Document::RETURNED => 'Submit for Verification',
    \App\Models\Document::PENDING_DIRECTOR => 'Verify and Send to CoK Office',
    \App\Models\Document::PENDING_SENIOR   => 'Verify and Send to Chief Inspector',
    \App\Models\Document::PENDING_CHIEF    => 'Approve and Sign',
    \App\Models\Document::APPROVED         => 'Record as Issued',
    default => null,
  };

  $failed = $inspection->answers->where('status', 'no')
      ->map(fn ($a) => $a->item?->label)->filter()->values();

  $sub = fn (string $t) => strtr($t, [
      ':authority'       => $cfg['authority'][$inspection->type_code] ?? $cfg['authority']['building'],
      ':inspection_date' => $inspection->inspection_date->translatedFormat('j F Y'),
      ':premises'        => mb_strtoupper($e->name),
      ':sector'          => $e->sector,
      ':district'        => $e->district,
      ':upi'             => $e->upis->first()->upi ?? $e->upi ?? '—',
      ':deadline'        => $letter->deadline_days ?? $type['deadline'],
      ':prior_ref'       => $letter->prior_reference ?? '—',
      ':prior_date'      => $letter->prior_date?->format('d/m/Y') ?? '—',
      ':reply_date'      => $letter->reply_date?->format('d/m/Y') ?? '—',
  ]);

  /* The same values the Word export is filled with — the one source
     both this review page and show.blade.php read, so a reviewer sees
     exactly the letter that will be signed and served, not a separate
     reconstruction of it. */
  $f = \App\Http\Controllers\LetterTemplateController::fields($letter);

  /* The signing role, not a person — matches show.blade.php exactly,
     so the signature block here shows the same image and name it will
     show once approved. */
  $signatory = \App\Models\User::whereHas('roles',
        fn ($q) => $q->where('name', $cfg['signatory']['role'] ?? 'Chief Inspector'))
      ->where('is_active', true)
      ->first();
@endphp

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['name'] }} &middot; {{ $stageName }}</div>
    <h2>{{ $e->name }}</h2>
    <p>
      Inspected {{ $inspection->inspection_date->format('j F Y') }} by {{ $inspection->inspector_name }}
      &middot; {{ round((float) $inspection->compliance, 1) }}% compliance
      &middot; drafted by {{ $letter->creator?->name }}
    </p>
  </div>
    <div class="head-actions">
    <a href="{{ route('letter.docx', $letter) }}"
       class="btn btn-primary btn-lg"
       style="font-weight:700">
      &#128196; Open Official Letter (Word)
    </a>
    <a href="{{ route('inspection.word', $inspection) }}" class="btn btn-ghost">Report (Word)</a>
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

@if($letter->status === \App\Models\Document::RETURNED)
  <div class="callout">
    <b>Returned for revision</b>
    <p style="font-size:14.5px">{{ $letter->transitionLog->last()?->comment }}</p>
    <small>Returned by {{ $letter->transitionLog->last()?->actor?->name }},
      {{ $letter->transitionLog->last()?->created_at->diffForHumans() }}</small>
  </div>
@endif

{{-- ══════════ Action panel ══════════ --}}
@if($canAct || $canReturn)
<section class="action-panel no-print">
  <div class="ap-left">
    <div class="ap-stage">{{ $stageName }}</div>
    <div class="ap-note">
      @switch($letter->status)
        @case(\App\Models\Document::PENDING_DIRECTOR)
          Verify the findings and the wording, then send to the CoK office.
          @break
        @case(\App\Models\Document::PENDING_SENIOR)
          Verify centrally before it reaches the Chief Inspector for signature.
          @break
        @case(\App\Models\Document::PENDING_CHIEF)
          Approving signs the letter. Once signed it is frozen and cannot be edited.
          @break
        @case(\App\Models\Document::APPROVED)
          Signed. Assign the reference, print, stamp and dispatch to the owner.
          @break
        @case(\App\Models\Document::ISSUED)
          This letter has been served on the owner. It cannot be returned or
          amended. A correction requires a new letter on the same case.
          @break
        @default
          Submitting sends the letter and its report to the Director of Inspection.
      @endswitch
    </div>
  </div>
  <div class="ap-right">
    @if($canReturn && ! in_array($letter->status, [\App\Models\Document::DRAFT, \App\Models\Document::RETURNED], true))
      <button type="button" class="btn btn-ghost" id="return-btn">Return for Revision</button>
    @endif
    @if($canAct && $actionLabel)
      <form method="post" action="{{ $letter->status === \App\Models\Document::APPROVED
            ? route('letter.issue', $letter)
            : ($letter->status === \App\Models\Document::DRAFT || $letter->status === \App\Models\Document::RETURNED
                ? route('letter.submit', $letter)
                : route('letter.advance', $letter)) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-success">{{ $actionLabel }}</button>
      </form>
    @endif
  </div>
</section>

@if($canReturn)
<section id="return-form" class="no-print" style="display:none">
  <h3>Return for revision</h3>
  <div class="desc">
    Say which part needs correcting. The letter goes back to
    {{ $letter->creator?->name }} with this attached.
  </div>
  <form method="post" action="{{ route('letter.return', $letter) }}">
    @csrf

    <label style="display:block;margin-bottom:8px;font-weight:600">
      What needs changing?
    </label>
    <select name="flagged_section" required
            style="width:100%;padding:10px;margin-bottom:14px;
                   border:1px solid var(--border);font-family:var(--f-body)">
      <option value="">Choose the part that is wrong</option>
      <option value="reference_date">Reference number / date</option>
      <option value="addressee">Addressee name / phone / district</option>
      <option value="subject">Subject line (Impamvu)</option>
      <option value="body">Main body wording</option>
      <option value="requirements">Requirements / recommendations list</option>
      <option value="deadline">Deadline period</option>
      <option value="signatory">Signatory name</option>
      <option value="distribution">Distribution list (Bimenyeshejwe)</option>
      <option value="other">Something else</option>
    </select>

    <textarea name="comment" rows="4" required minlength="10"
              placeholder="Exactly what is wrong and what it should say instead…"
              style="width:100%">{{ old('comment') }}</textarea>

    <div style="margin-top:14px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">Return the Letter</button>
      <button type="button" class="btn btn-ghost" id="return-cancel">Cancel</button>
    </div>
  </form>
</section>
@endif
@endif

{{-- ══════════ Tabs ══════════ --}}
<div class="tabs no-print">
  <button class="tab on" data-pane="pane-letter">The Letter</button>
  <button class="tab" data-pane="pane-report">Inspection Report</button>
  <button class="tab" data-pane="pane-history">History</button>
</div>

{{-- ---------- Letter ---------- --}}
<section id="pane-letter" class="pane">

  {{-- ---------- Main letter page ---------- --}}
  <div class="page letter-page">
    @include('partials.letterhead')

    <div class="letter-header">
      <div class="l-ref">Ref N<sup>o</sup> {{ $f['ref'] }}/07.01.14/{{ $f['year'] }}</div>
      <div class="l-date">Kigali, ku wa {{ $f['date'] }}</div>
    </div>

    <div class="l-to">
      <strong>Bwana/Madamu {{ $f['owner'] }}</strong><br>
      <strong>Tel: {{ $f['tel'] }}</strong><br>
      <span class="u-bold">KIGALI</span>
    </div>

    <div class="l-subject"><span class="u-bold">Impamvu</span>: {{ $f['subject'] }}</div>
    <div class="l-salut">{{ $f['salutation'] }}</div>

    <p class="l-body">{{ $sub($cfg['clauses']['authority']) }}</p>

    <p class="l-body">
      Dushingiye ku bugenzuzi bwakozwe ku wa {{ $f['insp_date'] }}
      mu nyubako yanyu ({{ $f['premises'] }})
      iherereye mu murenge wa {{ $f['sector'] }}
      mu kibanza gifite UPI: {{ $f['upi'] }}
      yasuwe bikagaragara ko bimwe mu bikoresho nkenerwa byifashishwa
      mu gukumira inkongi z'umuriro no kurinda umutekano bibura;
    </p>

    <p class="l-body">
      Tubandikiye tubasaba ko mwashyira mu bikorwa ibigaragazwa ku mugereka
      w'iyi baruwa bitarenze iminsi {{ $f['deadline'] }}, mutabikora mugahanwa
      hakurikijwe amategeko.
    </p>

    <div class="l-closing">Mugire amahoro.</div>

    <div class="l-sig">
      @if($signatory?->signature_path && $letter->signatures->count())
        <img src="{{ Storage::disk('public')->url($signatory->signature_path) }}"
             alt="Signature" class="sig-img">
      @endif
      <strong>{{ $f['signatory'] }}</strong><br>
      <strong>{{ data_get($cfg, 'signatory.title', 'Chief Inspector') }}</strong>
      @if($letter->signatures->count())
        <div class="signed">Signed {{ $letter->signatures->first()->signed_at->format('j F Y, H:i') }}</div>
      @endif
    </div>

    <div class="l-copies">
      <span class="u-bold">Bimenyeshejwe:</span>
      @foreach(data_get($cfg, 'distribution', []) as $line)
        <div>- {{ strtr($line, [':district' => $f['district'], ':sector' => $f['sector']]) }}</div>
      @endforeach
    </div>

    <div class="u-bold" style="margin-top:22px">KIGALI</div>

    <div class="l-footer">Umujyi wa Kigali B.P.3527 Kigali&nbsp;|&nbsp;Hotline:3260&nbsp;|&nbsp;Info@kigalicity.gov.rw&nbsp;|&nbsp;www.kigalicity.gov.rw
    </div>
  </div>

  {{-- ---------- Annex page ---------- --}}
  <div class="page annex-page">
    @include('partials.letterhead')

    <div class="u-bold" style="text-align:center;margin-bottom:14px">
      ANNEX: RECOMMENDATIONS / IBISABWA
    </div>
    <ol class="l-reqs">
      @foreach($f['recommendations'] as $r)<li>{{ $r }}</li>@endforeach
    </ol>

    <div class="l-footer">Umujyi wa Kigali B.P.3527 Kigali&nbsp;|&nbsp;Hotline:3260&nbsp;|&nbsp;Info@kigalicity.gov.rw&nbsp;|&nbsp;www.kigalicity.gov.rw
    </div>
  </div>

</section>

{{-- ---------- Report ---------- --}}
<section id="pane-report" class="pane" style="display:none">
  <div class="verdict {{ $tone }}">
    <div>
      <div class="v-label">Overall compliance</div>
      <div class="v-score">{{ round((float) $inspection->compliance, 1) }}%</div>
    </div>
    <div style="text-align:right">
      <div class="v-label">Deliberation</div>
      <div class="v-verdict">{{ $verdict }}</div>
    </div>
  </div>

  <table style="margin-bottom:22px">
    <tbody>
      <tr><td class="k">Premises</td><td><strong>{{ $e->name }}</strong></td>
          <td class="k">UPI</td><td>{{ $e->upis->first()->upi ?? $e->upi ?? '—' }}</td></tr>
      <tr><td class="k">Owner</td><td>{{ $e->owner ?: '—' }}</td>
          <td class="k">Zoning</td><td>{{ $e->zoning ?: '—' }}</td></tr>
      <tr><td class="k">Location</td><td>{{ collect([$e->district, $e->sector, $e->cell])->filter()->implode(' · ') }}</td>
          <td class="k">Inspector</td><td>{{ $inspection->inspector_name }}</td></tr>
    </tbody>
  </table>

  @if($failed->count())
    <h3 style="margin-bottom:14px">Non-compliant items &mdash; {{ $failed->count() }}</h3>
    <ol class="fail-list">
      @foreach($failed as $l)<li>{{ rtrim($l, '. ') }}</li>@endforeach
    </ol>
  @endif

  @foreach($inspection->template->sections as $section)
    @php
      $earned = 0; $possible = 0;
      foreach ($section->items as $it) {
        $a = $answers->get($it->id);
        if (! $a || ! in_array($a->status, ['yes','no'], true)) continue;
        $possible += (float) $it->max_score;
        if ($a->status === 'yes') $earned += (float) $it->max_score;
      }
      $pct = $possible > 0 ? round(100 * $earned / $possible) : null;
    @endphp
    <div class="sec-head">
      <div><span class="sec-no">{{ $section->section_number }}</span> {{ $section->title }}</div>
      @if($pct !== null)<span class="pill {{ \App\Services\InspectionStats::band((float) $pct) }}">{{ $pct }}%</span>@endif
    </div>
    <table class="chk">
      <tbody>
        @foreach($section->items as $item)
          @php $a = $answers->get($item->id); @endphp
          <tr class="{{ optional($a)->status === 'no' ? 'fail' : '' }}">
            <td>{{ rtrim($item->label, '. ') }}
              @if(optional($a)->comment)<br><span class="cmt">{{ $a->comment }}</span>@endif</td>
            <td style="width:118px;text-align:right">
              @if(optional($a)->status === 'yes')<span class="pill good">Compliant</span>
              @elseif(optional($a)->status === 'no')<span class="pill poor">Not compliant</span>
              @elseif(optional($a)->status === 'na')<span class="pill na">N/A</span>
              @else <span style="color:var(--tertiary)">—</span>@endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach

  @if($inspection->observations)
    <h3 style="margin:24px 0 10px">Observations</h3>
    <div class="prose">{!! nl2br(e($inspection->observations)) !!}</div>
  @endif
  @if($inspection->recommendations)
    <h3 style="margin:24px 0 10px">Recommendations</h3>
    <div class="prose">{!! nl2br(e($inspection->recommendations)) !!}</div>
  @endif

  @if($inspection->photos->count())
    <h3 style="margin:24px 0 10px">Photographs</h3>
    <div class="gallery">
      @foreach($inspection->photos as $p)
        <a href="{{ $p->url() }}" target="_blank"><img src="{{ $p->url() }}" alt=""></a>
      @endforeach
    </div>
  @endif
</section>

{{-- ---------- History ---------- --}}
<section id="pane-history" class="pane" style="display:none">
  <h3>Movement history</h3>
  <div class="desc">Recorded permanently and never altered</div>
  @if($letter->transitionLog->count())
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>When</th><th>Who</th><th>From</th><th>To</th><th>Comment</th></tr></thead>
        <tbody>
          @foreach($letter->transitionLog as $t)
            <tr>
              <td style="white-space:nowrap;font-size:12.5px">{{ $t->created_at->format('j M Y, H:i') }}</td>
              <td>{{ $t->actor?->name }}<br><span class="mini-note">{{ $t->actor_role }}</span></td>
              <td style="font-size:12px">{{ Str::headline($t->from_status ?? '—') }}</td>
              <td><span class="pill fair">{{ Str::headline($t->to_status) }}</span></td>
              <td style="font-size:12.5px">{{ $t->comment ?: '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="empty" style="padding:30px"><b>Not yet submitted</b>
      <span>Movements appear here once the letter enters the approval chain.</span></div>
  @endif

  @if($letter->signatures->count())
    <h3 style="margin-top:28px">Signatures</h3>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Signatory</th><th>Role</th><th>Signed</th><th>Integrity</th></tr></thead>
        <tbody>
          @foreach($letter->signatures as $s)
            <tr>
              <td><strong>{{ $s->signer_name }}</strong></td>
              <td>{{ $s->signer_role }}</td>
              <td style="font-size:12.5px">{{ $s->signed_at->format('j M Y, H:i') }}</td>
              <td>
                @if($s->isIntact())
                  <span class="pill good">Unaltered since signing</span>
                @else
                  <span class="pill poor">Content has changed</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>



<script>
(function () {
  document.querySelectorAll('.tab').forEach(function (t) {
    t.addEventListener('click', function () {
      document.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('on'); });
      document.querySelectorAll('.pane').forEach(function (p) { p.style.display = 'none'; });
      t.classList.add('on');
      document.getElementById(t.dataset.pane).style.display = '';
    });
  });

  var rb = document.getElementById('return-btn');
  var rf = document.getElementById('return-form');
  var rc = document.getElementById('return-cancel');
  if (rb && rf) {
    rb.addEventListener('click', function () {
      rf.style.display = '';
      rf.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
  if (rc) rc.addEventListener('click', function () { rf.style.display = 'none'; });
})();
</script>

@endsection

@push('styles')
<style>
  .action-panel{display:flex;justify-content:space-between;align-items:center;gap:20px;
    flex-wrap:wrap;border-left:4px solid var(--primary)}
  .ap-stage{font-family:var(--f-head);font-size:16px;font-weight:600;color:var(--ink)}
  .ap-note{font-size:13.5px;color:var(--body-text);margin-top:3px;max-width:520px}
  .ap-right{display:flex;gap:10px;flex-wrap:wrap}

  .tabs{display:flex;gap:0;margin-bottom:22px;border-bottom:2px solid var(--border)}
  .tab{font-family:var(--f-head);font-size:12.5px;font-weight:600;letter-spacing:.8px;
       text-transform:uppercase;padding:13px 22px;border:0;background:transparent;
       color:var(--tertiary);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;
       transition:all .2s}
  .tab:hover{color:var(--primary)}
  .tab.on{color:var(--primary);border-bottom-color:var(--primary)}

  /* ---- The letter — same A4, two-page structure as letters/{id} (show.blade.php).
         One source of layout as well as one source of content: a reviewer
         sees exactly the pages that will be signed and served. ---- */
  .page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto 20px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 0 0 15mm;
    display: flex;
    flex-direction: column;
    font-family: 'Montserrat', Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.65;
    color: #000;
  }
  .page p, .page div, .page td, .page li, .page span {
    font-family: 'Montserrat', Arial, sans-serif;
    font-size: 11pt;
  }
  .page > .letterhead { margin-top: 22px; }
  .letterhead { margin: 0 0 16pt; }
  .letterhead img { display: block; width: 100%; height: auto; }
  .page > *:not(.letterhead) { padding-left: 22mm; padding-right: 22mm; }
  .annex-page .letterhead { margin-left: 0mm; margin-right: 0mm; width: 100%; }

  .l-footer {
    margin-left: 0mm; margin-right: 0mm;
    padding: 5px 22mm 0 22mm;
    width: 100%; box-sizing: border-box;
    border-top: none;
    font-size: 9pt;
    color: #00B0F0;
    text-align: center;
    font-family: 'Montserrat', Arial, sans-serif;
    white-space: nowrap;
    background: #fff;
    margin-top: auto;
  }

  .l-ref{margin-bottom:22px}
  .l-date{text-align:right;margin-bottom:26px}
  .l-to{margin-bottom:22px;line-height:1.6}
  .l-subject{margin-bottom:20px}
  .l-salut{margin-bottom:18px}
  .l-body{text-align:justify;margin-bottom:16px}
  .l-reqs{margin:0 0 16px 20px;line-height:1.8}
  .l-reqs li{margin-bottom:6px}
  .l-closing{margin:22px 0 40px}
  .l-sig{margin-bottom:34px;line-height:1.5}
  .sig-img{display:block;height:56px;margin-bottom:4px}
  .l-copies div{margin-top:5px}
  .letter-header{display:flex;justify-content:space-between;align-items:center;width:100%}
  .l-ref,.l-date{display:inline-block}
  .u-bold{font-weight:bold;text-decoration:underline}
  .signed{font-family:var(--f-head);font-size:11px;color:var(--success);
          margin-top:8px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}

  .verdict{display:flex;justify-content:space-between;align-items:center;
           padding:18px 22px;margin-bottom:22px;color:#fff}
  .verdict.good{background:var(--success)} .verdict.fair{background:#8BC34A}
  .verdict.weak{background:var(--warning)} .verdict.poor{background:var(--danger)}
  .v-label{font-family:var(--f-head);font-size:10px;letter-spacing:1.2px;text-transform:uppercase;
           opacity:.9;font-weight:600}
  .v-score{font-family:var(--f-head);font-size:38px;font-weight:800;line-height:1.05}
  .v-verdict{font-family:var(--f-head);font-size:19px;font-weight:700}

  td.k{color:var(--tertiary);width:17%;font-size:12px;font-family:var(--f-head);
       text-transform:uppercase;letter-spacing:.5px}
  ol.fail-list{margin:0 0 24px 24px;line-height:2;font-size:14px}
  ol.fail-list li{color:var(--danger);font-weight:600}

  .sec-head{display:flex;justify-content:space-between;align-items:center;gap:12px;
    background:#E8F4FA;color:var(--primary);padding:10px 15px;margin:16px 0 0;
    font-family:var(--f-head);font-size:13px;font-weight:600}
  .sec-no{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
    background:var(--primary);color:#fff;font-size:11px;margin-right:8px}
  table.chk td{font-size:13px;padding:8px 10px}
  table.chk tr.fail td{background:#FDECEA}
  .cmt{color:var(--body-text);font-size:11.5px;font-style:italic}
  .prose{font-size:14px;line-height:1.75}
  .gallery{display:flex;gap:12px;flex-wrap:wrap}
  .gallery img{width:190px;height:132px;object-fit:cover;border:1px solid var(--border)}
  .mini-note{font-size:11px;color:var(--tertiary)}

  textarea{width:100%;padding:12px;border:1px solid var(--border);font-family:var(--f-body);font-size:14px}

  @media print{
    .no-print,.tabs,.topbar,.sidebar,.backdrop,.page-head{display:none!important}
    .pane{display:block!important}
    #pane-history,#pane-report{display:none!important}
    .content{padding:0}
    .page{
      box-shadow:none; margin:0; padding:0 0 20mm; width:100%;
      min-height:100vh; page-break-after:always; display:block;
    }
    .page:last-child{page-break-after:auto}
    .l-footer{
      position:fixed; bottom:0; left:0; width:100%; margin:0;
      padding:5px 22mm; background:#fff; z-index:1000;
      border-top:none; box-sizing:border-box;
    }
    .annex-page .letterhead{margin-left:0;margin-right:0;width:100%}
  }
  @media(max-width:760px){
    .page{padding:24px 18px;font-size:14px}
    .tab{padding:11px 13px;font-size:11px}
  }
</style>
@endpush