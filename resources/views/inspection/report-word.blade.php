<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>Inspection Report — {{ $inspection->entity->name }}</title>
<!--[if gte mso 9]><xml>
  <w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument>
</xml><![endif]-->
<style>
  @page { size: A4 portrait; margin: 2cm 1.8cm; }
  body { font-family: "Merriweather", Georgia, sans-serif; font-size: 10.5pt; color: #1a1a1a; line-height: 1.4; }

  .lh { border-bottom: 2.5pt solid #34A8DB; padding-bottom: 8pt; margin-bottom: 16pt; }
  .lh .rep { font-size: 8pt; letter-spacing: 2pt; color: #606874; font-weight: bold; }
  .lh .city { font-size: 17pt; font-weight: bold; color: #34A8DB; }
  .lh .unit { font-size: 9.5pt; color: #606874; }

  h1 { font-size: 15pt; color: #34A8DB; text-align: center; margin: 0 0 3pt; }
  .ref { text-align: center; font-size: 8.5pt; color: #606874; margin-bottom: 14pt; }

  h2 { font-size: 11pt; color: #34A8DB; border-bottom: 0.75pt solid #d5d9e0;
       padding-bottom: 3pt; margin: 18pt 0 8pt; }

  table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
  td, th { border: 0.5pt solid #c9ced6; padding: 4.5pt 6pt; vertical-align: top; }
  th { background: #f2f4f7; text-align: left; font-size: 8pt; color: #606874; }
  td.k { background: #f2f4f7; width: 22%; font-size: 8.5pt; color: #606874; }

  .verdict { border: 1.5pt solid #34A8DB; background: #E8F4FA; padding: 10pt 14pt; margin: 12pt 0 16pt; }
  .verdict .score { font-size: 24pt; font-weight: bold; color: #34A8DB; }
  .verdict .lbl { font-size: 8pt; letter-spacing: 1.2pt; color: #606874; font-weight: bold; }
  .verdict .verd { font-size: 13pt; font-weight: bold; color: #34A8DB; }

  .sec { background: #34A8DB; color: #ffffff; padding: 5pt 9pt; font-size: 9.5pt;
         font-weight: bold; margin-top: 12pt; }
  table.chk td { font-size: 9pt; }
  table.chk td.stat { width: 100pt; text-align: right; font-weight: bold; }
  .ok   { color: #2E7D32; }
  .bad  { color: #C62828; }
  .na   { color: #707784; }
  .cmt  { color: #606874; font-size: 8pt; font-style: italic; }

  /* A requirement met by the absence of something, not its presence. */
  .prohibited { color: #B71C1C; font-size: 7.5pt; font-weight: bold;
                text-transform: uppercase; letter-spacing: 0.4pt; }

  .prose { font-size: 10pt; line-height: 1.55; }

  /* ---- Faults ---- */
  .rf-lead { font-size: 9.5pt; color: #444; margin-bottom: 8pt; text-align: justify; }
  table.rf-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 8pt; }
  table.rf-table th { border: 0.5pt solid #c9ced6; background: #f2f4f7; padding: 4.5pt 6pt;
                      text-align: left; font-weight: bold; font-size: 8pt; color: #606874; }
  table.rf-table td { border: 0.5pt solid #c9ced6; padding: 4.5pt 6pt; vertical-align: top; }
  .rf-n { text-align: center; }
  .rf-scope { display: block; font-size: 8pt; color: #666; margin-top: 2pt; }
  .rf-note { font-size: 8pt; color: #444; font-style: italic; margin-top: 3pt; }
  .rf-action { font-size: 8.5pt; color: #333; }
  .rf-amt { text-align: right; }
  .rf-none { color: #B71C1C; font-size: 8.5pt; }
  .rf-total-l { text-align: right; font-weight: bold; background: #f2f4f7; }
  .rf-total { text-align: right; font-weight: bold; background: #f2f4f7; color: #B71C1C; }
  .rf-warn { font-size: 9pt; color: #8A6D00; font-style: italic; margin-bottom: 8pt; }

  .sigs { width: 100%; margin-top: 34pt; }
  .sigs td { border: none; width: 25%; padding-right: 12pt; vertical-align: bottom; }
  .sigline { border-bottom: 0.75pt solid #7d848f; height: 34pt; }
  .signame { font-family: "Montserrat", sans-serif; font-size: 9.5pt; font-weight: bold;
             color: #333333; padding-top: 4pt; }
  .sigrole { font-size: 8pt; font-weight: bold; color: #34A8DB; letter-spacing: 0.4pt;
             text-transform: uppercase; }
  .siginst { font-size: 7.5pt; color: #8a8f98; }

  .foot { margin-top: 22pt; padding-top: 7pt; border-top: 0.5pt solid #d5d9e0;
          font-size: 8pt; color: #606874; text-align: center; }

  /* ---- Wastewater ---- */
  .ww-verd { font-size: 13pt; font-weight: bold; }
  .ww-verd.good { color: #2E7D32; } .ww-verd.weak { color: #A66A00; } .ww-verd.poor { color: #C62828; }
  td.photo { width: 50%; padding: 5pt; vertical-align: top; border: 1px solid #E0E0E0; }
  td.photo img { display: block; margin: 0 auto; max-width: 200pt; max-height: 150pt; width: auto; height: auto; }
  td.photo p { font-size: 8pt; color: #555; margin-top: 4pt; }
</style>
</head>
<body>

@php
  $e = $inspection->entity;

  /* Wastewater has no checklist, no template, and no compliance score —
     the form ends in the inspector's own judgement. Everything that
     depends on a template is skipped for this one category, and its
     own section is produced in its place, further down. */
  $hasChecklist = (bool) $inspection->template;
  $ww = $hasChecklist ? null : $inspection->wastewaterInspection;

  if ($hasChecklist) {
    [$verdict] = $inspection->deliberation();

    /* Counted with prohibition items understood: a requirement asking
       whether something forbidden is present — a kitchen at a petrol
       station — is complied with by answering no. Counting every 'yes' as
       compliant would report the opposite of what was found. */
    $yes = 0; $no = 0; $na = 0;

    foreach ($inspection->template->sections as $s) {
      foreach ($s->items as $it) {
        $a = $answers->get($it->id);
        if (! $a || ! $a->status) continue;
        if ($a->status === 'na') { $na++; continue; }
        $ok = $it->is_prohibition ? $a->status === 'no' : $a->status === 'yes';
        $ok ? $yes++ : $no++;
      }
    }
  }

  $zoneLabel = collect(config('zoning'))->flatMap(fn($g) => $g)->get($e->zoning);

  $faults      = $inspection->faults ?? collect();
  $faultTotal  = (float) $faults->sum('amount');
  $unpriced    = $faults->whereNull('amount');

  /* Sections are numbered as they appear, because which of them appear
     depends on what was recorded. Working it out inline meant a
     three-way ternary that would have become five with faults. */
  $n   = 0;
  $num = function () use (&$n) { return ++$n; };

  $sPremises = $num();
  $sSummary  = $hasChecklist ? $num() : null;
  $sFindings = $hasChecklist ? $num() : null;
  $sWaste    = $hasChecklist ? null : $num();
  $sFaults   = $faults->count() ? $num() : null;
  $sObs      = $inspection->observations ? $num() : null;
  $sRec      = $inspection->recommendations ? $num() : null;
  $sSign     = $num();

  $unitName = match ($inspection->type_code) {
      'petrol'       => 'Petrol Station',
      'construction' => 'Ongoing Construction',
      'waste_water'  => 'Wastewater Management',
      default        => 'Building',
  };
@endphp

<div class="lh">
  <div class="rep">REPUBLIC OF RWANDA</div>
  <div class="city">CITY OF KIGALI</div>
  <div class="unit">Directorate of Inspection &mdash; {{ $unitName }} Inspection</div>
</div>

<h1>INSPECTION REPORT</h1>
<div class="ref">
  @if($inspection->case_reference)
    Ref N<sup>o</sup> {{ $inspection->case_reference }}
  @else
    Reference to be assigned upon issue of the letter
  @endif
  &middot; Visit {{ $inspection->visit_number }} of {{ count($history) }}
  @if($inspection->stage)
    &middot; {{ Str::headline($inspection->stage) }}
  @endif
</div>

<h2>{{ $sPremises }}. Particulars of the premises</h2>
<table>
  <tr><td class="k">Premises</td><td colspan="3"><b>{{ $e->name }}</b></td></tr>
  <tr>
    <td class="k">Parcel (UPI)</td>
    <td>@forelse($e->upis as $u){{ $u->upi }}@if(!$loop->last)<br>@endif @empty {{ $e->upi ?: '—' }} @endforelse</td>
    <td class="k">Zoning</td>
    <td>{{ $e->zoning ?: '—' }}@if($zoneLabel)<br><span class="cmt">{{ $zoneLabel }}</span>@endif</td>
  </tr>
  <tr>
    <td class="k">Owner</td><td>{{ $e->owner ?: '—' }}</td>
    <td class="k">Use</td><td>{{ $e->use_type ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">Telephone</td><td>{{ $e->telephone ?: '—' }}</td>
    <td class="k">Email</td><td>{{ $e->email ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">District</td><td>{{ $e->district ?: '—' }}</td>
    <td class="k">Sector</td><td>{{ $e->sector ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">Cell</td><td>{{ $e->cell ?: '—' }}</td>
    <td class="k">Coordinates</td>
    <td>@if($e->latitude){{ $e->latitude }}, {{ $e->longitude }}@else—@endif</td>
  </tr>
  <tr>
    <td class="k">Date of inspection</td><td>{{ $inspection->inspection_date->format('j F Y') }}</td>
    <td class="k">Inspector</td><td>{{ $inspection->inspector_name }}</td>
  </tr>

  @if($inspection->type_code === 'construction')
    <tr>
      <td class="k">Construction permit</td>
      <td>{{ $inspection->permit_number ?: '—' }}</td>
      <td class="k">Permit expires</td>
      <td>
        @if($inspection->permit_expiry)
          {{ $inspection->permit_expiry->format('j F Y') }}
          @if($inspection->permit_expiry->isPast())
            <br><span class="bad">Expired</span>
          @endif
        @else — @endif
      </td>
    </tr>
    <tr>
      <td class="k">Building category</td><td>{{ $inspection->building_category ?: '—' }}</td>
      <td class="k">Status of works</td><td>{{ $inspection->building_status ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Contractor</td><td>{{ $inspection->contractor ?: '—' }}</td>
      <td class="k">Supervisor</td><td>{{ $inspection->supervisor ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Dwelling units</td><td>{{ $inspection->dwelling_unit ?: '—' }}</td>
      <td class="k">Physical plan</td>
      <td>
        @if($inspection->has_physical_plan === null) —
        @elseif($inspection->has_physical_plan) Yes
        @else <span class="bad">No</span>
        @endif
      </td>
    </tr>
  @endif

  @if($inspection->type_code === 'waste_water' && $ww)
    <tr>
      <td class="k">TIN</td><td>{{ $ww->tin_number ?: '—' }}</td>
      <td class="k">Village</td><td>{{ $ww->village ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Inspection type</td>
      <td>{{ \App\Models\WastewaterInspection::INSPECTION_TYPES[$ww->inspection_type] ?? '—' }}</td>
      <td class="k">System type</td>
      <td>{{ \App\Models\WastewaterInspection::SYSTEM_TYPES[$ww->system_type] ?? $ww->system_type_other ?: '—' }}</td>
    </tr>
  @endif
</table>

@if($hasChecklist)

  <div class="verdict">
    <table style="border:none">
      <tr>
        <td style="border:none;width:50%">
          <div class="lbl">OVERALL COMPLIANCE</div>
          <div class="score">{{ round((float) $inspection->compliance, 1) }}%</div>
          <div class="cmt">{{ $inspection->earned_score }} of {{ $inspection->possible_score }} points</div>
        </td>
        <td style="border:none;text-align:right">
          <div class="lbl">DELIBERATION</div>
          <div class="verd">{{ $verdict }}</div>
        </td>
      </tr>
    </table>
  </div>

  <h2>{{ $sSummary }}. Assessment summary</h2>
  <table>
    <tr>
      <td class="k">Complied</td><td>{{ $yes }}</td>
      <td class="k">Did not comply</td><td>{{ $no }}</td>
    </tr>
    <tr>
      <td class="k">Not applicable</td><td>{{ $na }}</td>
      <td class="k">Checklist</td><td>{{ $inspection->template->name }}, version {{ $inspection->template->version }}</td>
    </tr>
  </table>

  <h2>{{ $sFindings }}. Detailed findings</h2>
  @foreach($inspection->template->sections as $section)
    @php
      $earned = 0; $possible = 0;
      foreach ($section->items as $it) {
        $a = $answers->get($it->id);
        if (! $a || ! in_array($a->status, ['yes','no'], true)) continue;
        $possible += (float) $it->max_score;
        $ok = $it->is_prohibition ? $a->status === 'no' : $a->status === 'yes';
        if ($ok) $earned += (float) $it->max_score;
      }
      $pct = $possible > 0 ? round(100 * $earned / $possible) : null;
    @endphp

    <div class="sec">{{ $section->section_number }}. {{ $section->title }}@if($pct !== null) &mdash; {{ $pct }}%@endif</div>
    <table class="chk">
      @foreach($section->items as $item)
        @php
          $a  = $answers->get($item->id);
          $st = optional($a)->status;
          $ok = $st && in_array($st, ['yes','no'], true)
                ? ($item->is_prohibition ? $st === 'no' : $st === 'yes')
                : null;
        @endphp
        <tr>
          <td>
            {{ rtrim($item->label, '. ') }}
            @if($item->is_prohibition)
              <span class="prohibited">&mdash; prohibited</span>
            @endif
            @if(optional($a)->comment)<br><span class="cmt">{{ $a->comment }}</span>@endif
          </td>
          <td class="stat">
            @if($st === 'na')      <span class="na">N/A</span>
            @elseif($ok === true)  <span class="ok">Complied</span>
            @elseif($ok === false) <span class="bad">Did not comply</span>
            @else                  <span class="na">—</span>
            @endif
          </td>
        </tr>
      @endforeach
    </table>
  @endforeach

@else

  {{-- ══════════ Wastewater ══════════ --}}
  @if($ww)
    @php
      $toneClass = $ww->complianceTone() === 'good' ? 'good' : ($ww->complianceTone() === 'poor' ? 'poor' : 'weak');
      $WW = \App\Models\WastewaterInspection::class;
    @endphp

    <div class="verdict">
      <table style="border:none">
        <tr>
          <td style="border:none;width:60%">
            <div class="lbl">COMPLIANCE STATUS</div>
            <div class="ww-verd {{ $toneClass }}">{{ $ww->complianceLabel() }}</div>
            <div class="cmt">The inspector's own judgement &mdash; not a computed score</div>
          </td>
          <td style="border:none;text-align:right">
            @if($ww->compliance_deadline)
              <div class="lbl">DEADLINE</div>
              <div class="cmt" style="font-size:10pt">{{ $ww->compliance_deadline->format('j F Y') }}</div>
            @endif
            @if($ww->followup_inspection_date)
              <div class="lbl" style="margin-top:6pt">FOLLOW-UP</div>
              <div class="cmt" style="font-size:10pt">{{ $ww->followup_inspection_date->format('j F Y') }}</div>
            @endif
          </td>
        </tr>
      </table>
    </div>

    <h2>{{ $sWaste }}. Wastewater management system</h2>
    <table>
      <tr>
        <td class="k">Design capacity</td><td>{{ $ww->design_capacity_m3 ? $ww->design_capacity_m3.' m³/day' : '—' }}</td>
        <td class="k">Population served</td><td>{{ $ww->population_served ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Year constructed</td><td>{{ $ww->year_constructed ?: '—' }}</td>
        <td class="k">Maintenance company</td><td>{{ $ww->maintenance_company ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Last maintenance</td><td>{{ $ww->last_maintenance_date?->format('j F Y') ?: '—' }}</td>
        <td class="k">Last desludging</td><td>{{ $ww->last_desludging_date?->format('j F Y') ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Desludging frequency</td>
        <td colspan="3">{{ $WW::DESLUDGING_FREQUENCIES[$ww->desludging_frequency] ?? '—' }}</td>
      </tr>
    </table>

    @if($ww->isSepticTank())
      <div class="sec">Septic tank system details</div>
      <table>
        <tr>
          <td class="k">Tank type</td><td>{{ $WW::SEPTIC_TANK_TYPES[$ww->septic_tank_type] ?? '—' }}</td>
          <td class="k">Chambers</td><td>{{ $ww->septic_chamber_count ?: '—' }}</td>
        </tr>
        <tr>
          <td class="k">Material</td><td>{{ $WW::SEPTIC_TANK_MATERIALS[$ww->septic_tank_material] ?? $ww->septic_tank_material_other ?: '—' }}</td>
          <td class="k">Location</td><td>{{ $WW::SEPTIC_TANK_LOCATIONS[$ww->septic_tank_location] ?? $ww->septic_tank_location_other ?: '—' }}</td>
        </tr>
        <tr>
          <td class="k">Tank adequacy</td><td>{{ $WW::TANK_ADEQUACY[$ww->tank_adequacy] ?? '—' }}</td>
          <td class="k">Desludging provider</td><td>{{ $ww->desludging_provider ?: '—' }}</td>
        </tr>
      </table>

      @if($ww->septic_components)
        <table class="chk" style="margin-top:6pt">
          @foreach($WW::SEPTIC_COMPONENTS as $ck => $clabel)
            @if(!empty($ww->septic_components[$ck]))
              <tr>
                <td>{{ $clabel }}</td>
                <td class="stat">
                  @php $cond = $ww->septic_components[$ck]; @endphp
                  <span class="{{ $cond === 'good' ? 'ok' : ($cond === 'poor' ? 'bad' : 'na') }}">
                    {{ $WW::CONDITION_RATINGS[$cond] ?? $cond }}
                  </span>
                </td>
              </tr>
            @endif
          @endforeach
        </table>
      @endif

      <table style="margin-top:6pt">
        @foreach([
          'signs_of_leakage'          => 'Signs of leakage / seepage',
          'signs_of_overflow'         => 'Signs of overflow / ponding',
          'structural_damage_visible' => 'Structural cracks / damage',
          'odour_detected'            => 'Odour detected',
        ] as $field => $label)
          @if(! is_null($ww->$field))
            <tr>
              <td class="k">{{ $label }}</td>
              <td class="{{ $ww->$field ? 'bad' : 'ok' }}">{{ $ww->$field ? 'Yes' : 'No' }}</td>
            </tr>
          @endif
        @endforeach
      </table>

      @if($ww->septic_observations)
        <p class="prose">{!! nl2br(e($ww->septic_observations)) !!}</p>
      @endif
    @endif

    @if($ww->isTreatmentPlant())
      <div class="sec">Operational status</div>
      <table>
        <tr><td class="k">Status</td><td colspan="3">{{ $WW::OPERATIONAL_STATUSES[$ww->operational_status] ?? '—' }}</td></tr>
      </table>

      @if($ww->stp_components)
        <table class="chk" style="margin-top:6pt">
          @foreach($WW::STP_COMPONENTS as $ck => $clabel)
            @if(!empty($ww->stp_components[$ck]))
              <tr>
                <td>{{ $clabel }}</td>
                <td class="stat">
                  @php $cond = $ww->stp_components[$ck]; @endphp
                  <span class="{{ $cond === 'good' ? 'ok' : ($cond === 'poor' ? 'bad' : 'na') }}">
                    {{ $WW::CONDITION_RATINGS[$cond] ?? $cond }}
                  </span>
                </td>
              </tr>
            @endif
          @endforeach
        </table>
      @endif

      @if($ww->general_condition)
        <p class="prose">{!! nl2br(e($ww->general_condition)) !!}</p>
      @endif
    @endif

    <div class="sec">Environmental compliance</div>
    <table>
      <tr>
        <td class="k">Effluent test available</td>
        <td>{{ is_null($ww->effluent_test_available) ? '—' : ($ww->effluent_test_available ? 'Yes' : 'No') }}</td>
        <td class="k">Meets standards</td>
        <td>{{ $WW::EFFLUENT_STANDARDS[$ww->effluent_meets_standards] ?? '—' }}</td>
      </tr>
      <tr>
        <td class="k">Test date</td><td>{{ $ww->effluent_test_date?->format('j F Y') ?: '—' }}</td>
        <td class="k">Laboratory</td><td>{{ $ww->laboratory_name ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Final discharge point</td>
        <td colspan="3">{{ $WW::DISCHARGE_POINTS[$ww->final_discharge_point] ?? $ww->final_discharge_point_other ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Untreated discharge evidence</td>
        <td>{{ is_null($ww->untreated_discharge_evidence) ? '—' : ($ww->untreated_discharge_evidence ? 'Yes' : 'No') }}</td>
        <td class="k">Pollution evidence</td>
        <td>{{ is_null($ww->pollution_evidence) ? '—' : ($ww->pollution_evidence ? 'Yes' : 'No') }}</td>
      </tr>
    </table>

    @if(!empty($ww->pollution_types))
      <p class="prose"><b>Type of pollution:</b>
        {{ collect($ww->pollution_types)->map(fn($p) => $WW::POLLUTION_TYPES[$p] ?? $p)->implode(', ') }}
      </p>
    @endif

    @if($ww->key_findings)
      <div class="sec">Key findings</div>
      <p class="prose">{!! nl2br(e($ww->key_findings)) !!}</p>
    @endif

    @if($ww->environmental_risks)
      <div class="sec">Environmental risks identified</div>
      <p class="prose">{!! nl2br(e($ww->environmental_risks)) !!}</p>
    @endif

    @if(!empty($ww->non_compliances))
      <div class="sec">Observed non-compliances</div>
      <p class="prose">
        {{ collect($ww->non_compliances)->map(fn($n) => $WW::NON_COMPLIANCES[$n] ?? $n)->implode(', ') }}
        @if($ww->non_compliances_other), {{ $ww->non_compliances_other }}@endif
      </p>
    @endif

  @else
    <div class="sec">Wastewater management</div>
    <p class="prose bad">No wastewater detail record was found for this inspection.</p>
  @endif

@endif

{{-- ══════════ Faults ══════════ --}}
@if($faults->count())
<h2>{{ $sFaults }}. Faults observed</h2>

<p class="rf-lead">
  The following contraventions were found. The amounts are those set by the
  administrative sanctions schedule; nothing is owed until this report is
  signed and an enforcement letter issued.
</p>

<table class="rf-table">
  <thead>
    <tr>
      <th style="width:24pt">No</th>
      <th>Fault</th>
      <th style="width:110pt">Sanction</th>
      <th style="width:70pt;text-align:right">Amount (FRW)</th>
    </tr>
  </thead>
  <tbody>
    @foreach($faults as $i => $f)
      <tr>
        <td class="rf-n">{{ $i + 1 }}</td>
        <td>
          <strong>{{ $f->fault?->title ?? '—' }}</strong>
          @if($f->scope)<span class="rf-scope">{{ $f->scope }}</span>@endif
          @if($f->note)<div class="rf-note">{{ $f->note }}</div>@endif
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
      <td class="rf-total">{{ number_format($faultTotal, 0) }}</td>
    </tr>
  </tfoot>
</table>

@if($unpriced->count())
  <p class="rf-warn">
    {{ $unpriced->count() }} {{ Str::plural('fault', $unpriced->count()) }}
    above {{ $unpriced->count() === 1 ? 'carries' : 'carry' }} no fine. The
    sanction is the action stated, not a payment, and is excluded from the
    total.
  </p>
@endif
@endif

@if($sObs)
  <h2>{{ $sObs }}. Observations</h2>
  <div class="prose">{!! nl2br(e($inspection->observations)) !!}</div>
@endif

@if($sRec)
  <h2>{{ $sRec }}. Recommendations</h2>
  <div class="prose">{!! nl2br(e($inspection->recommendations)) !!}</div>
@endif

@if(count($history) > 1)
  <h2>Inspection history</h2>
  <table>
    <tr><th>Visit</th><th>Date</th><th>Inspector</th><th>{{ $hasChecklist ? 'Compliance' : 'Status' }}</th></tr>
    @foreach($history as $h)
      <tr>
        <td>{{ $h->visit_number }}</td>
        <td>{{ $h->inspection_date->format('j M Y') }}</td>
        <td>{{ $h->inspector_name }}</td>
        <td>
          @if($h->template)
            {{ round((float) $h->compliance, 1) }}%
          @else
            @php $hww = $h->wastewaterInspection; @endphp
            {{ $hww?->complianceLabel() ?? '—' }}
          @endif
        </td>
      </tr>
    @endforeach
  </table>
@endif

@if($inspection->team->count())
  <h2>Inspection team</h2>
  <table>
    <tr><th>Name</th><th>Institution</th></tr>
    @foreach($inspection->team as $m)
      <tr><td>{{ $m->name }}</td><td>{{ $m->institution ?: '—' }}</td></tr>
    @endforeach
  </table>
@endif

@php
  $photos = $inspection->photos ?? collect();
@endphp

@if($photos->count())
<h2>Photographic record</h2>

<p style="font-size:9pt;color:#555;margin-bottom:10pt">
  {{ $photos->count() }} {{ Str::plural('photograph', $photos->count()) }}
  taken at the premises on {{ $inspection->inspection_date?->format('j F Y') }}.
</p>

<table style="width:100%;border-collapse:collapse">
  <tr>
    @foreach($photos as $i => $photo)
      @php
        $path = storage_path('app/public/' . $photo->path);
        $data = null;

        /* The upload limit is 12MB, so the guard must clear it or larger
           photographs would silently drop out of the report. */
        if (is_file($path) && filesize($path) < 12_000_000) {
            $mime = mime_content_type($path) ?: 'image/jpeg';
            $data = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }
      @endphp

      <td class="photo">
        @if($data)
          <img src="{{ $data }}" alt="Photograph {{ $i + 1 }}">
        @else
          <p style="font-size:9pt;color:#999">
            Photograph {{ $i + 1 }} could not be embedded.
          </p>
        @endif

        <p>
          <strong>Photograph {{ $i + 1 }}.</strong>
          {{ $photo->caption ?: 'No caption recorded.' }}
        </p>
      </td>

      @if($i % 2 === 1 && ! $loop->last)</tr><tr>@endif
    @endforeach

    @if($photos->count() % 2 === 1)
      <td style="width:50%;border:1px solid #E0E0E0"></td>
    @endif
  </tr>
</table>
@endif

<h2>{{ $sSign }}. Signatures</h2>

<p style="font-size:9.5px;margin-bottom:10pt">
  This inspection was conducted by the following officers, whose signatures appear below.
</p>

@php
  $team = $inspection->team;

  if ($team->isEmpty()) {
      $team = collect([(object) [
          'name'        => $inspection->inspector_name,
          'position'    => $inspection->inspector?->position ?? 'Inspector',
          'institution' => 'City of Kigali',
          'user_id'     => $inspection->inspector_id ?? null,
      ]]);
  }

  $chunks = $team->chunk(3);
@endphp

<table class="sigs">
@foreach($chunks as $chunk)
  <tr>
    @foreach($chunk as $m)
      @php
        /* Preferring the linked account: matching on name alone fails
           whenever a name is recorded differently from the account,
           which is exactly how several officers went unlinked. */
        $signer = ($m->user_id ?? null)
            ? \App\Models\User::find($m->user_id)
            : \App\Models\User::where('name', $m->name)->first();
      @endphp
      <td style="width:33%">
        @if($signer?->signature_path)
          <img src="{{ public_path('storage/'.$signer->signature_path) }}" style="height:38pt">
        @endif
        <div class="sigline"></div>
        <div class="signame">{{ $m->name }}</div>
        <div class="sigrole">{{ $m->position ?: 'Inspector' }}</div>
        @if($m->institution)<div class="siginst">{{ $m->institution }}</div>@endif
      </td>
    @endforeach
    @for($i = $chunk->count(); $i < 3; $i++)<td></td>@endfor
  </tr>
@endforeach

  <tr><td colspan="3" style="height:22pt"></td></tr>

  <tr>
    <td colspan="3">
      <div style="font-size:9.5pt;margin-bottom:8pt">Verified and approved:</div>
    </td>
  </tr>
  <tr>
    <td style="width:33%">
      <div class="sigline"></div>
      <div class="signame">{{ $director?->name ?? '' }}</div>
      <div class="sigrole">Director of Inspection Unit</div>
      <div class="siginst">{{ $inspection->entity->district }} District</div>
    </td>
    <td></td>
    <td></td>
  </tr>
</table>

<div class="foot">
  Generated by the City of Kigali Digital Inspection Platform on {{ now()->format('j F Y \a\t H:i') }}.<br>
  @if($inspection->hasSealedReport())
    This report has been signed and sealed. Its content is fixed.
  @else
    This report remains a draft until signed by the Director of Inspection.
  @endif
</div>

</body>
</html>