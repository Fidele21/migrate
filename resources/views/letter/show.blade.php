@extends('layouts.app')
@section('title', 'Letter — ' . $letter->title)

@section('content')

@php
  $cfg  = config('letters');
  $type = $cfg['types'][$letter->letter_type] ?? $cfg['types']['enforcement'];
  $e    = $inspection?->entity;
  $D    = \App\Models\Document::class;

  $stages = [
    ['key' => $D::DRAFT,            'label' => 'Draft',           'who' => 'Inspector'],
    ['key' => $D::PENDING_DIRECTOR, 'label' => 'District review', 'who' => 'Director of Inspection'],
    ['key' => $D::PENDING_SENIOR,   'label' => 'Central review',  'who' => 'Senior Inspector'],
    ['key' => $D::PENDING_CHIEF,    'label' => 'Signature',       'who' => 'Chief Inspector'],
    ['key' => $D::APPROVED,         'label' => 'Registry',        'who' => 'Secretary'],
    ['key' => $D::ISSUED,           'label' => 'Issued',          'who' => 'Served'],
  ];

  $order = array_column($stages, 'key');
  $now   = array_search($letter->status, $order, true);
  if ($now === false) { $now = 0; }

  $failed = $inspection?->answers->where('status', 'no')
      ->map(fn ($a) => $a->item?->label)->filter()->values() ?? collect();

  $sub = function (string $text) use ($letter, $inspection, $e, $cfg, $type) {
      if (! $inspection || ! $e) { return $text; }
      return strtr($text, [
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
  };

  /* The signing role, not a person. Who holds it changes; the post does
     not. If no active holder exists the letter still renders — an empty
     signature block is a visible problem, a blank page is not. */
  $signatory = \App\Models\User::whereHas('roles',
        fn ($q) => $q->where('name', $cfg['signatory']['role'] ?? 'Chief Inspector'))
      ->where('is_active', true)
      ->first();
  $lastMove  = $letter->transitionLog->last();

  /* The same values the Word export is filled with, computed once here
     rather than inside the branch below. The annex page reads $f
     regardless of which branch the letter page takes — a letter type
     with no approved template yet takes the content_html branch above,
     and without this, $f would be undefined by the time the annex tries
     to read $f['recommendations']. */
  $f = \App\Http\Controllers\LetterTemplateController::fields($letter);
@endphp

{{-- ══════════ Header (UI) ══════════ --}}
<div class="page-head no-print">
  <div>
    <div class="crumb">
      {{ $type['name'] }} &middot; {{ $type['name_rw'] }}
      @if($letter->case_reference) &middot; Case {{ $letter->case_reference }} @endif
    </div>
    <h2>{{ $letter->title }}</h2>
    <p>
      @if($letter->reference_number)
        Ref N<sup>o</sup> {{ $letter->reference_number }}
      @else
        Reference assigned by the registry after signature
      @endif
      &middot; drafted by {{ $letter->creator?->name }}
      @if($letter->deadline_date) &middot; deadline {{ $letter->deadline_date->format('j F Y') }} @endif
    </p>
  </div>

  <div class="head-actions">
    @if($letter->isEditable() && auth()->user()->can('update', $letter))
      <a href="{{ route('editor.edit', $letter) }}" class="btn btn-primary">Edit</a>
    @endif

    <a href="{{ route('letter.review', $letter) }}" class="btn btn-primary">Review &amp; Submit</a>
    <a href="{{ route('letter.docx', $letter) }}" class="btn btn-success">Export Word</a>
    <button onclick="window.print()" class="btn btn-ghost">Print</button>

    @if($inspection)
      <a href="{{ route('inspection.show', $inspection) }}" class="btn btn-ghost">Inspection</a>
    @endif
  </div>
</div>

@if(session('status'))
  <div class="note no-print" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif

{{-- ══════════ Where it stands ══════════ --}}
@if($letter->isAwaitingReview())
  <div class="note no-print" style="border-left-color:var(--warning);background:#FFF8E5">
    <strong style="color:#8A6D00">With a reviewer</strong>
    Submitted {{ $letter->submitted_at?->diffForHumans() }} and now
    @switch($letter->status)
      @case($D::PENDING_DIRECTOR) with the Director of Inspection. @break
      @case($D::PENDING_SENIOR)   with the Senior Inspector at the CoK office. @break
      @case($D::PENDING_CHIEF)    awaiting the Chief Inspector's signature. @break
    @endswitch
    It cannot be edited or resubmitted until it is approved or returned to you.
  </div>

@elseif($letter->status === $D::APPROVED)
  <div class="note no-print" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Signed</strong>
    Approved and signed by the Chief Inspector on
    {{ $letter->approved_at?->format('j F Y') }}. The content is frozen.
    It is with the registry for a reference number, printing and dispatch.
  </div>

@elseif($letter->status === $D::ISSUED)
  <div class="note no-print" style="border-left-color:var(--primary)">
    <strong>Issued</strong>
    Served on {{ $letter->dispatched_to ?: 'the owner' }}
    @if($letter->dispatched_at) on {{ $letter->dispatched_at->format('j F Y') }}@endif.
    A letter that has been served cannot be recalled or amended — a correction
    requires a new letter on the same case.
  </div>

@elseif($letter->status === $D::RETURNED)
  <div class="callout no-print">
    <b>Returned to you</b>
    <p style="font-size:14.5px">{{ $lastMove?->comment ?: 'No reason was recorded.' }}</p>
    <small>Returned by {{ $lastMove?->actor?->name }},
      {{ $lastMove?->created_at->diffForHumans() }}. Correct it and submit again.</small>
  </div>

@elseif($letter->status === $D::DRAFT)
  <div class="note no-print">
    <strong>Draft</strong>
    Not yet submitted. Edit it freely, then use <em>Review &amp; Submit</em> to send it,
    with its inspection report, to the Director of Inspection.
  </div>
@endif

@if($letter->is_manual)
  <div class="note no-print" style="border-left-color:var(--tertiary)">
    <strong style="color:#A08B68">Edited manually</strong>
    This letter has been edited and no longer updates from the inspection.
    @if($letter->imported_from) Imported from {{ $letter->imported_from }}. @endif
    Last edited {{ $letter->edited_at?->diffForHumans() }}.
  </div>
@endif

{{-- ══════════ Approval chain ══════════ --}}
<section class="no-print">
  <h3>Approval chain</h3>
  <div class="desc">Only the Chief Inspector signs enforcement correspondence</div>

  <div class="chain">
    @foreach($stages as $i => $s)
      <div class="stg {{ $i < $now ? 'done' : ($i === $now ? 'here' : '') }}">
        <div class="dot">{!! $i < $now ? '&#10003;' : $i + 1 !!}</div>
        <div class="lbl">{{ $s['label'] }}</div>
        <div class="who">{{ $s['who'] }}</div>
      </div>
      @if(! $loop->last)<div class="link {{ $i < $now ? 'done' : '' }}"></div>@endif
    @endforeach
  </div>
</section>

{{-- ══════════ The letter (pages) ══════════ --}}

<div class="note">
  <strong>The document served</strong>
  This is a reading of the letter. What is printed, signed and served is
  the Word document — open it before approving.
</div>

{{-- ---------- Main letter page ---------- --}}
<div class="page letter-page">
  @include('partials.letterhead')

  @if(!\App\Http\Controllers\LetterTemplateController::isTemplated($letter) && filled($letter->content_html))
    {{-- Non‑templated letter --}}
    {!! $letter->content_html !!}
    {{-- Footer for this page --}}
    <div class="l-footer">
      Umujyi wa Kigali B.P.3527 Kigali &nbsp;|&nbsp; Hotline:3260&nbsp;|&nbsp;
      Info@kigalicity.gov.rw &nbsp;|&nbsp; www.kigalicity.gov.rw
    </div>
  @else
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

    {{-- Footer for the letter page --}}
    <div class="l-footer">Umujyi wa Kigali B.P.3527 Kigali&nbsp;|&nbsp;Hotline:3260&nbsp;|&nbsp;Info@kigalicity.gov.rw&nbsp;|&nbsp;www.kigalicity.gov.rw
    </div>
  @endif
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

  {{-- Footer for the annex page --}}
  <div class="l-footer">Umujyi wa Kigali B.P.3527 Kigali&nbsp;|&nbsp;Hotline:3260&nbsp;|&nbsp;Info@kigalicity.gov.rw&nbsp;|&nbsp;www.kigalicity.gov.rw
  </div>
</div>

{{-- ══════════ Registry ══════════ --}}
@can('letter.reference')
<section class="no-print">
  <h3>Registry and dispatch</h3>
  <div class="desc">Reference, printing, stamping and delivery to the owner</div>
  <div class="tbl-wrap">
    <table>
      <tbody>
        <tr>
          <td class="k">Reference assigned</td>
          <td>{{ $letter->reference_number ?: 'Not yet assigned' }}
            @if($letter->referenced_at)<br><span class="mini">{{ $letter->referenced_at->format('j M Y, H:i') }}</span>@endif</td>
        </tr>
        <tr>
          <td class="k">Printed</td>
          <td>{{ $letter->printed_at?->format('j M Y, H:i') ?: 'Not yet printed' }}</td>
        </tr>
        <tr>
          <td class="k">Stamped copy</td>
          <td>
            @if($letter->scan_path)
              <a href="{{ Storage::disk('public')->url($letter->scan_path) }}" target="_blank" class="pill good">View</a>
            @else Not yet uploaded @endif
          </td>
        </tr>
        <tr>
          <td class="k">Dispatched</td>
          <td>{{ $letter->dispatched_at?->format('j M Y, H:i') ?: 'Not yet dispatched' }}
            @if($letter->dispatched_to)<br><span class="mini">{{ $letter->dispatched_to }}</span>@endif</td>
        </tr>
      </tbody>
    </table>
  </div>
  @if($letter->status === $D::APPROVED)
    <div style="margin-top:16px">
      <a href="{{ route('secretary.desk') }}" class="btn btn-primary">Open the Registry</a>
    </div>
  @endif
</section>
@endcan

{{-- ══════════ History ══════════ --}}
@if($letter->transitionLog->count())
<section class="no-print">
  <h3>History</h3>
  <div class="desc">Every movement of this letter, recorded permanently</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Comment</th></tr></thead>
      <tbody>
        @foreach($letter->transitionLog as $t)
          <tr>
            <td style="white-space:nowrap;font-size:12.5px">{{ $t->created_at->format('j M Y, H:i') }}</td>
            <td>{{ $t->actor?->name }}<br><span class="mini">{{ $t->actor_role }}</span></td>
            <td><span class="pill fair">{{ Str::headline($t->to_status) }}</span></td>
            <td style="font-size:12.5px">{{ $t->comment ?: '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($letter->signatures->count())
    <h3 style="margin-top:28px">Signature</h3>
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
@endif

@endsection

@push('styles')
<style>
  /* ---- Page setup (A4) ---- */
  @page {
    size: A4;
    margin: 20mm 20mm 25mm 20mm; /* extra bottom margin for fixed footer */
  }

  /* ---- Chain (UI) ---- */
  .chain{display:flex;align-items:flex-start;flex-wrap:wrap}
  .stg{text-align:center;flex:1;min-width:96px}
  .dot{width:38px;height:38px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;
       background:var(--canvas);color:var(--tertiary);font-family:var(--f-head);font-weight:700;
       font-size:14px;border:2px solid var(--border)}
  .stg.done .dot{background:var(--success);border-color:var(--success);color:#fff}
  .stg.here .dot{background:var(--primary);border-color:var(--primary);color:#fff;
                 box-shadow:0 0 0 5px rgba(52,168,219,.18)}
  .stg .lbl{font-family:var(--f-head);font-size:12.5px;font-weight:600;color:var(--ink)}
  .stg.here .lbl{color:var(--primary)}
  .stg .who{font-size:11px;color:var(--tertiary);margin-top:2px}
  .link{flex:0 0 26px;height:2px;background:var(--border);margin-top:19px}
  .link.done{background:var(--success)}

  /* ---- Each A4 page ---- */
  .page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto 20px;      /* spacing between pages on screen */
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 0 0 15mm;       /* bottom padding for content, footer pushes it */
    display: flex;
    flex-direction: column;
    font-family: 'Montserrat', Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.65;
    color: #000;
  }

  /* Shared content styling */
  .page p,
  .page div,
  .page td,
  .page li,
  .page span {
    font-family: 'Montserrat', Arial, sans-serif;
    font-size: 11pt;
  }

  /* ---- Letterhead (full‑width) ---- */
  .page > .letterhead {
    margin-top: 22px;        /* matches annex header top padding */
  }
  .letterhead {
    margin: 0 0 16pt;
  }
  .letterhead img {
    display: block;
    width: 100%;
    height: auto;
  }

  /* Body content indentation (except letterhead) */
  .page > *:not(.letterhead) {
    padding-left: 22mm;
    padding-right: 22mm;
  }

  /* ---- Annex letterhead: also full‑width ---- */
  .annex-page .letterhead {
    margin-left: 0mm;
    margin-right: 0mm;
    width: 100%;
  }

  /* ---- Footer inside each page ---- */
  .l-footer {
    margin-left: 0mm;
    margin-right: 0mm;
    padding: 5px 22mm 0 22mm;
    width: 100%;
    box-sizing: border-box;
    border-top: none;
    font-size: 5pt;
    color: #00B0F0;
    text-align: center;
    font-family: 'Montserrat', Arial, sans-serif;
    white-space: nowrap;
    background: #fff;
    margin-top: auto;        /* pushes footer to bottom of the flex column */
  }

  /* ---- Other letter elements ---- */
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
  .letter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
  }
  .l-ref, .l-date { display: inline-block; }
  .u-bold{font-weight:bold;text-decoration:underline}
  .signed{font-family:var(--f-head);font-size:11px;color:var(--success);margin-top:8px;
          font-weight:600;letter-spacing:.5px;text-transform:uppercase}

  td.k{color:var(--tertiary);width:32%;font-family:var(--f-head);font-size:11.5px;
       text-transform:uppercase;letter-spacing:.5px}
  .mini{font-size:11.5px;color:var(--tertiary)}

  /* ---- Print styles ---- */
  @media print {
    .no-print,
    .topbar,
    .sidebar,
    .backdrop {
      display: none !important;
    }
    .content { padding: 0; }
    .page {
      box-shadow: none;
      margin: 0;
      padding: 0 0 20mm;    /* bottom padding to avoid overlap with fixed footer */
      width: 100%;
      min-height: 100vh;    /* ensures each page fills the viewport height */
      page-break-after: always; /* each .page becomes a new printed page */
      display: block;        /* flex might cause issues in some print engines */
    }
    /* Last page shouldn't force a break after */
    .page:last-child {
      page-break-after: auto;
    }

    /* Fixed footer on every printed page */
    .l-footer {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      margin: 0;
      padding: 5px 22mm;
      background: #fff;
      z-index: 1000;
      border-top: none;
      box-sizing: border-box;
    }

    /* Ensure annex letterhead stays full‑width in print */
    .annex-page .letterhead {
      margin-left: 0;
      margin-right: 0;
      width: 100%;
    }
  }

  /* ---- Responsive (screen) ---- */
  @media(max-width:760px){
    .page { padding: 24px 18px; font-size: 14px; }
    .link { display: none; }
    .stg { min-width: 80px; margin-bottom: 14px; }
  }
</style>
@endpush