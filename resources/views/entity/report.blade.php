@extends('layouts.app')
@section('title', 'Inspection Report')

@section('content')

@php [$verdict, $tone] = \App\Services\LegacyStats::deliberation((float) $inspection->score); @endphp

<div class="page-head no-print">
  <div>
    <div class="crumb">Inspection Report</div>
    <h2>{{ $entity->name }}</h2>
    <p>{{ $inspection->inspection_date }} &middot; {{ $entity->district }}
       @if($entity->sector) &middot; {{ $entity->sector }}@endif</p>
  </div>
  <div class="head-actions">
    @if($canEdit)<a href="#" class="btn btn-primary">Edit Report</a>@endif
    <a href="#" class="btn btn-ghost">Export PDF</a>
    <a href="#" class="btn btn-ghost">Export Word</a>
    <button onclick="window.print()" class="btn btn-ghost">Print</button>
    <a href="{{ route('entity.show', $entity->id) }}" class="btn btn-ghost">Back</a>
  </div>
</div>

<div class="paper">

  <div class="letterhead">
    <div class="lh-mark">CoK</div>
    <div class="lh-text">
      <div class="lh-rep">REPUBLIC OF RWANDA</div>
      <div class="lh-city">CITY OF KIGALI</div>
      <div class="lh-unit">Directorate of Inspection &mdash; {{ $entity->type_name }} Inspection</div>
    </div>
  </div>

  <h1 class="doc-title">Inspection Report</h1>
  <div class="doc-ref">Report reference to be assigned on approval</div>

  <table class="meta">
    <tbody>
      <tr><th>Premises</th><td>{{ $entity->name }}</td><th>UPI</th><td>{{ $entity->upi ?: '—' }}</td></tr>
      <tr><th>Owner</th><td>{{ $entity->owner ?: '—' }}</td><th>Zoning</th><td>{{ $entity->zoning ?: '—' }}</td></tr>
      <tr><th>Telephone</th><td>{{ $entity->telephone ?: '—' }}</td><th>Email</th><td>{{ $entity->email ?: '—' }}</td></tr>
      <tr><th>District</th><td>{{ $entity->district ?: '—' }}</td><th>Sector</th><td>{{ $entity->sector ?: '—' }}</td></tr>
      <tr><th>Date of inspection</th><td>{{ $inspection->inspection_date }}</td>
          <th>Visit number</th><td>{{ count($history) - collect($history)->search(fn($h) => $h->id === $inspection->id) }} of {{ count($history) }}</td></tr>
      <tr><th>Inspector</th><td>{{ $inspection->inspector_name ?: '—' }}</td>
          <th>Owner representative</th><td>{{ $inspection->owner_rep_name ?: '—' }}</td></tr>
    </tbody>
  </table>

  <div class="verdict-band {{ $tone }}">
    <div>
      <div class="vb-label">Overall compliance</div>
      <div class="vb-score">{{ $inspection->score }}%</div>
    </div>
    <div style="text-align:right">
      <div class="vb-label">Deliberation</div>
      <div class="vb-verdict">{{ $verdict }}</div>
    </div>
  </div>

  <h2 class="sec">1. Assessment summary</h2>
  <table class="meta">
    <tbody>
      <tr><th>Items compliant</th><td>{{ $inspection->yes_count }}</td>
          <th>Items not compliant</th><td>{{ $inspection->no_count }}</td></tr>
      <tr><th>Not applicable</th><td>{{ $inspection->na_count }}</td>
          <th>Sections assessed</th><td>{{ count($checklist) }}</td></tr>
    </tbody>
  </table>

  <h2 class="sec">2. Detailed findings</h2>
  @foreach($checklist as $section)
    @php $pct = $section['possible'] > 0 ? round(100 * $section['earned'] / $section['possible']) : null; @endphp
    <div class="sec-head">
      <div><span class="sec-no">{{ $section['number'] }}</span> {{ $section['title'] }}</div>
      @if($pct !== null)<span class="pill {{ \App\Services\LegacyStats::band((float) $pct) }}">{{ $pct }}%</span>@endif
    </div>
    <table class="chk">
      <tbody>
      @foreach($section['items'] as $item)
        <tr class="{{ $item->status === 'no' ? 'fail' : '' }}">
          <td>{{ rtrim($item->label, '. ') }}
            @if($item->comment)<br><span class="cmt">{{ $item->comment }}</span>@endif</td>
          <td style="width:112px;text-align:right">
            @if($item->status === 'yes')   <span class="pill good">Compliant</span>
            @elseif($item->status === 'no')<span class="pill poor">Not compliant</span>
            @elseif($item->status === 'na')<span class="pill na">N/A</span>
            @else <span style="color:var(--muted)">—</span>@endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endforeach

  @if($inspection->observations)
    <h2 class="sec">3. Observations</h2>
    <div class="prose">{!! nl2br(e($inspection->observations)) !!}</div>
  @endif

  @if($inspection->recommendations)
    <h2 class="sec">4. Recommendations</h2>
    <div class="prose">{!! nl2br(e($inspection->recommendations)) !!}</div>
  @endif

  @if($inspection->owner_recommendations)
    <h2 class="sec">5. Representations by the owner</h2>
    <div class="prose">{!! nl2br(e($inspection->owner_recommendations)) !!}</div>
  @endif

  @if(count($team))
    <h2 class="sec">6. Inspection team</h2>
    <table class="meta">
      <thead><tr><th style="background:#F7F9FB">Name</th><th style="background:#F7F9FB">Institution</th></tr></thead>
      <tbody>
        @foreach($team as $m)
          <tr><td colspan="2" style="padding:0">
            <table style="width:100%"><tr>
              <td style="width:50%;border:none">{{ $m->name }}</td>
              <td style="border:none">{{ $m->institution ?: '—' }}</td>
            </tr></table>
          </td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="signatures">
    <div class="sig"><div class="sig-line"></div><div class="sig-role">Inspector</div>
      <div class="sig-name">{{ $inspection->inspector_name ?: '' }}</div></div>
    <div class="sig"><div class="sig-line"></div><div class="sig-role">Director of Inspection</div>
      <div class="sig-name">{{ $entity->district }} District</div></div>
    <div class="sig"><div class="sig-line"></div><div class="sig-role">Senior Inspector</div>
      <div class="sig-name">City of Kigali</div></div>
    <div class="sig"><div class="sig-line"></div><div class="sig-role">Chief Inspector</div>
      <div class="sig-name">City of Kigali</div></div>
  </div>

  <div class="doc-foot">
    Generated by the City of Kigali Digital Inspection Platform on {{ now()->format('j F Y \a\t H:i') }}.
    This report is a draft until signed by the Chief Inspector.
  </div>

</div>



@endsection

@push('styles')
<style>
  .paper{background:#fff;border:1px solid var(--line);border-radius:10px;
         padding:44px 48px;box-shadow:var(--sh);max-width:900px;margin:0 auto}
  .letterhead{display:flex;align-items:center;gap:16px;padding-bottom:16px;
              border-bottom:3px solid var(--blue);margin-bottom:26px}
  .lh-mark{width:52px;height:52px;border-radius:8px;background:var(--gold);display:flex;
           align-items:center;justify-content:center;font-weight:800;font-size:17px;color:var(--blue)}
  .lh-rep{font-size:10px;letter-spacing:.16em;color:var(--muted);font-weight:700}
  .lh-city{font-size:19px;font-weight:800;color:var(--blue);letter-spacing:-.01em;line-height:1.2}
  .lh-unit{font-size:11.5px;color:var(--muted)}
  .doc-title{font-size:22px;color:var(--blue);text-align:center;margin-bottom:3px;letter-spacing:-.01em}
  .doc-ref{text-align:center;font-size:11px;color:var(--muted);margin-bottom:24px}
  table.meta{width:100%;border-collapse:collapse;margin-bottom:22px;font-size:12.5px}
  table.meta th{width:15%;background:#F7F9FB;text-align:left;padding:8px 10px;
                border:1px solid var(--line);font-size:10px;letter-spacing:.05em;color:var(--muted)}
  table.meta td{padding:8px 10px;border:1px solid var(--line);width:35%}
  .verdict-band{display:flex;justify-content:space-between;align-items:center;
                border-radius:9px;padding:16px 20px;margin-bottom:26px;color:#fff}
  .verdict-band.good{background:var(--green)} .verdict-band.fair{background:#5FAE2E}
  .verdict-band.weak{background:#C9930A}      .verdict-band.poor{background:var(--red)}
  .vb-label{font-size:10px;letter-spacing:.12em;text-transform:uppercase;opacity:.85;font-weight:700}
  .vb-score{font-size:34px;font-weight:800;line-height:1.1;letter-spacing:-.03em}
  .vb-verdict{font-size:17px;font-weight:700}
  h2.sec{font-size:13px;color:var(--blue);text-transform:uppercase;letter-spacing:.08em;
         margin:26px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--line)}
  .sec-head{display:flex;justify-content:space-between;align-items:center;gap:12px;
    background:var(--blue);color:#fff;padding:8px 13px;border-radius:6px;margin:14px 0 0;
    font-size:12.5px;font-weight:700}
  .sec-no{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;
    border-radius:5px;background:rgba(255,255,255,.2);font-size:10.5px;margin-right:7px}
  table.chk{width:100%;border-collapse:collapse;font-size:12.5px}
  table.chk td{padding:7px 10px;border-bottom:1px solid #F1F3F6}
  table.chk tr.fail td{background:#FFF8F7}
  .cmt{color:var(--muted);font-size:11px;font-style:italic}
  .pill.na{background:#EEF1F5;color:#606874}
  .prose{font-size:13px;line-height:1.7;padding:4px 2px}
  .signatures{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;margin-top:44px}
  .sig-line{border-bottom:1px solid #9AA1AC;height:38px;margin-bottom:7px}
  .sig-role{font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--blue)}
  .sig-name{font-size:11px;color:var(--muted)}
  .doc-foot{margin-top:34px;padding-top:14px;border-top:1px solid var(--line);
            font-size:10.5px;color:var(--muted);text-align:center;line-height:1.6}
  @media print{
    .no-print,.topbar,.sidebar,.backdrop{display:none!important}
    body{background:#fff}
    .content{padding:0}
    .paper{border:none;box-shadow:none;padding:0;max-width:100%}
    .sec-head,.verdict-band{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
  @media(max-width:760px){
    .paper{padding:24px 20px}
    .signatures{grid-template-columns:1fr 1fr}
    table.meta th{width:auto} table.meta td{width:auto}
  }
</style>
@endpush
