@extends('layouts.app')
@section('title', $inspection->entity->name)

@section('content')

@php
  use App\Support\ComplianceBand;
  use App\Models\WastewaterInspection as WW;

  $entity = $inspection->entity;
  $report = $inspection->report();
  $sealed = $report && $report->sealed_at;
  $mine   = $inspection->conductedBy(auth()->user());

  /* Wastewater has no checklist and no compliance band — the form ends
     in the inspector's own judgement, not a percentage. Everything
     below that depends on a template is skipped for this one category,
     and its own summary is shown in place of it further down the page. */
  $hasChecklist = (bool) $inspection->template;

  $band = $hasChecklist ? $inspection->band() : null;
  $ww   = $hasChecklist ? null : $inspection->wastewaterInspection;

  $yes = 0; $no = 0; $na = 0;

  if ($hasChecklist) {
    /* Counted with prohibition items understood: a requirement asking
       whether something forbidden is present — a kitchen at a petrol
       station — is complied with by answering no. */
    foreach ($inspection->template->sections as $s) {
      foreach ($s->items as $it) {
        $a = $answers->get($it->id);
        if (! $a || ! $a->status) continue;

        if ($a->status === 'na') { $na++; continue; }

        $complied = $it->is_prohibition ? $a->status === 'no' : $a->status === 'yes';
        $complied ? $yes++ : $no++;
      }
    }
  }
@endphp

<div class="page-head">
  <div>
    <div class="crumb">
      @if($inspection->case_reference)
        Case {{ $inspection->case_reference }} &middot;
      @endif
      {{ $entity->type_code === 'petrol' ? 'Petrol Station' : ($entity->type_code === 'waste_water' ? 'Wastewater Management' : 'Occupied Building') }} &middot;
      Visit {{ $inspection->visit_number }} of {{ count($history) }}
      @if($inspection->isFollowUp()) &middot; Follow-up @endif
    </div>
    <h2>{{ $entity->name }}</h2>
    <p>
      {{ $inspection->inspection_date->format('j F Y') }} &middot;
      {{ collect([$entity->district, $entity->sector, $entity->cell])->filter()->implode(' · ') }}
      &middot; Inspected by {{ $inspection->inspector_name }}
    </p>
  </div>

  <div class="head-actions">
    @if($canEdit && ! $sealed)
      <a href="{{ route('inspection.edit', $inspection) }}" class="btn btn-primary">Edit</a>
    @endif

    <a href="{{ route('inspection.word', $inspection) }}" class="btn btn-success">Export Word</a>
    <button onclick="window.print()" class="btn btn-ghost">Print</button>

    {{-- The report, at whatever stage it has reached --}}
    @can('document.draft')
      @if($inspection->status === 'completed' && $mine)
        @if(! $report)
          <a href="{{ route('report.edit', $inspection) }}" class="btn btn-primary">Draft Report</a>
        @elseif($report->isEditable())
          <a href="{{ route('report.edit', $inspection) }}" class="btn btn-primary">Edit Report</a>
        @endif
      @endif
    @endcan
    {{-- No Propose Fine action: completing the inspection raises the
         proposal from the faults, and editing it adjusts the figure. A
         second way in would let an officer raise a duplicate. --}}

    {{-- A letter states findings; the sealed report evidences them. --}}
    @can('document.draft')
      @if($inspection->status === 'completed' && $mine && $sealed)
        <a href="{{ route('letter.create', $inspection) }}" class="btn btn-primary">Draft Letter</a>
      @endif
    @endcan

    @if(count($history) > 1)
      <a href="{{ route('inspection.show', $history->firstWhere('visit_number', $inspection->visit_number - 1) ?? $inspection) }}"
         class="btn btn-ghost">Previous visit</a>
    @endif

    @can('inspection.followup')
      <a href="{{ route('inspection.followup', [$inspection->type_code, $inspection->entity_id]) }}"
         class="btn btn-ghost">New Follow-up</a>
    @endcan

    <a href="{{ route('register.index', $inspection->type_code) }}" class="btn btn-ghost">Register</a>
  </div>
</div>

@if(session('status'))
  <div class="note ok"><strong>Saved</strong>{{ session('status') }}</div>
@endif

@if(session('warning'))
  <div class="note warn"><strong>Note</strong>{{ session('warning') }}</div>
@endif

@if($errors->any())
  <div class="note bad"><strong>Could not proceed</strong>{{ $errors->first() }}</div>
@endif

@if($inspection->isDraft())
  <div class="note warn">
    <strong>Draft</strong>
    This inspection is still a draft. Complete it before a report or letter can be drawn from it.
  </div>
@endif

{{-- ══════════ Signature ══════════ --}}
@if($report && $inspection->status === 'completed')
  @php
    $progress = $report->signature_stage ? $report->signatureProgress() : null;
    $canSign  = $report->awaitingSignatureFrom(auth()->user());
    $mayBin   = ! $sealed && ! $report->signatures()->exists()
                && ($mine || auth()->user()->can('document.approve'));
  @endphp

  <section class="sign-panel {{ $sealed ? 'sealed' : '' }}">
    <div class="sp-head">
      <div>
        <h3>
          @if($sealed)
            Report signed and sealed
          @elseif($report->signature_stage === 'pending_signatures')
            Report awaiting signature
          @else
            Report in draft
          @endif
        </h3>
        <div class="sp-desc">
          @if($sealed)
            Signed {{ $report->sealed_at->format('j F Y') }}. The content is fixed and
            can no longer be altered.
            @unless($report->sealIntact())
              <strong class="sp-warn">The content has changed since it was signed.</strong>
            @endunless
          @elseif($report->signature_stage === 'pending_signatures')
            Each officer who attended signs, then the Director of Inspection.
            No letter can be transmitted until it is sealed.
          @else
            Submit it for signature when the findings are settled. Editing stops at that point.
          @endif
        </div>
      </div>

      <div class="sp-acts">
        @if(! $report->signature_stage && $mine)
          <form method="post" action="{{ route('report.submit', $report) }}"
                onsubmit="return confirm('Submit this report for signature? It cannot be edited afterwards.')">
            @csrf
            <button type="submit" class="btn btn-primary">Submit for signature</button>
          </form>
        @endif

        @if($mayBin)
          <button type="button" class="btn btn-ghost sp-del"
                  onclick="document.getElementById('del-report').style.display='block';this.style.display='none'">
            Delete report
          </button>
        @endif

        @if($sealed)
          <a href="{{ route('inspection.word', $inspection) }}" class="btn btn-success">Download</a>
        @endif
      </div>
    </div>

    @if($progress)
      <div class="sig-grid">
        @foreach($progress['officers'] as $o)
          <div class="sig {{ $o['signed'] ? 'done' : '' }}">
            <span class="sig-mark">{{ $o['signed'] ? '✓' : '' }}</span>
            <div>
              <b>{{ $o['name'] }}</b>
              <span>{{ $o['position'] ?: 'Inspector' }}@if($o['is_lead']) &middot; Lead @endif</span>
              <em>{{ $o['signed'] ? $o['signed_at']->format('j M Y, H:i') : 'Not yet signed' }}</em>
            </div>
          </div>
        @endforeach

        <div class="sig director {{ $progress['director'] ? 'done' : '' }}">
          <span class="sig-mark">{{ $progress['director'] ? '✓' : '' }}</span>
          <div>
            <b>{{ $progress['director']->signer_name ?? 'Director of Inspection' }}</b>
            <span>Director of Inspection</span>
            <em>
              @if($progress['director'])
                {{ $progress['director']->signed_at->format('j M Y, H:i') }}
              @elseif($progress['awaiting'] > 0)
                Waiting on {{ $progress['awaiting'] }} {{ Str::plural('officer', $progress['awaiting']) }}
              @else
                Ready to sign
              @endif
            </em>
          </div>
        </div>
      </div>
    @endif

    @if($canSign)
      <form method="post" action="{{ route('report.sign', $report) }}" class="sign-form">
        @csrf
        <label for="remark">Remark, if any</label>
        <div class="sign-row">
          <input type="text" name="remark" id="remark" maxlength="500"
                 placeholder="Optional — recorded with your signature">
          <button type="submit" class="btn btn-primary"
                  onclick="return confirm('Sign this report? Your signature records the content as it now stands.')">
            Sign
          </button>
        </div>
        @unless(auth()->user()->signature_path)
          <div class="sp-warn" style="margin-top:8px">
            Register your signature under My Account before signing.
          </div>
        @endunless
      </form>
    @endif

    <div id="del-report" class="del-box" style="display:none">
      <form method="post" action="{{ route('report.destroy', $report) }}">
        @csrf
        @method('DELETE')
        <b>Delete this report permanently</b>
        <p>It cannot be recovered. The deletion is recorded against the case.</p>
        <div class="del-row">
          <input type="text" name="reason" required maxlength="300"
                 placeholder="Reason — e.g. drafted twice by mistake">
          <input type="text" name="confirm" required placeholder="Type DELETE">
          <button type="submit" class="btn btn-danger">Delete</button>
          <button type="button" class="btn btn-ghost"
                  onclick="document.getElementById('del-report').style.display='none';document.querySelector('.sp-del').style.display=''">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </section>
@endif

@if($hasChecklist)

  {{-- ══════════ Verdict ══════════ --}}
  <div class="verdict {{ $band['tone'] }}">
    <div>
      <div class="v-label">Overall compliance</div>
      <div class="v-score">{{ round((float) $inspection->compliance, 1) }}%</div>
      <div class="v-sub">{{ $inspection->earned_score }} of {{ $inspection->possible_score }} points</div>
    </div>
    <div style="text-align:right">
      <div class="v-label">Deliberation</div>
      <div class="v-verdict">{{ $band['label'] }}</div>
      <div class="v-sub">{{ $band['note'] }}</div>
    </div>
  </div>

  <div class="stats">
    <div class="stat g"><b>{{ $yes }}</b><span>Complied</span></div>
    <div class="stat r"><b>{{ $no }}</b><span>Did not comply</span></div>
    <div class="stat"><b>{{ $na }}</b><span>Not applicable</span></div>
    <div class="stat t"><b>{{ count($history) }}</b><span>Visits to this premises</span></div>
    @if(($previousCompare ?? null) !== null)
      <div class="stat {{ $previousCompare >= 0 ? 'g' : 'r' }}">
        <b>{{ $previousCompare >= 0 ? '+' : '' }}{{ round($previousCompare, 1) }}</b>
        <span>Change since last visit</span>
      </div>
    @endif
  </div>

@else

  {{-- ══════════ Wastewater verdict ══════════ --}}
  {{-- No score exists for this category — this is the inspector's own
       judgement, shown with the same visual weight the compliance band
       carries elsewhere, so the page reads consistently, without
       pretending a percentage exists where the form deliberately has
       none. --}}
  @if($ww)
    <div class="verdict {{ $ww->complianceTone() }}">
      <div>
        <div class="v-label">Compliance status</div>
        <div class="v-verdict" style="font-size:26px">{{ $ww->complianceLabel() }}</div>
        <div class="v-sub">The inspector's own judgement &mdash; not a computed score</div>
      </div>
      <div style="text-align:right">
        @if($ww->compliance_deadline)
          <div class="v-label">Compliance deadline</div>
          <div class="v-sub" style="font-size:14px;color:#fff">{{ $ww->compliance_deadline->format('j F Y') }}</div>
        @endif
        @if($ww->followup_inspection_date)
          <div class="v-label" style="margin-top:8px">Follow-up inspection</div>
          <div class="v-sub" style="font-size:14px;color:#fff">{{ $ww->followup_inspection_date->format('j F Y') }}</div>
        @endif
      </div>
    </div>

    <div class="stats">
      <div class="stat t"><b>{{ count($history) }}</b><span>Visits to this premises</span></div>
      @if($ww->system_type)
        <div class="stat"><b style="font-size:15px">{{ WW::SYSTEM_TYPES[$ww->system_type] ?? $ww->system_type }}</b><span>System type</span></div>
      @endif
      @if($ww->isSepticTank() && $ww->tank_adequacy)
        <div class="stat {{ $ww->tank_adequacy === 'adequate' ? 'g' : 'r' }}">
          <b style="font-size:15px">{{ WW::TANK_ADEQUACY[$ww->tank_adequacy] ?? $ww->tank_adequacy }}</b>
          <span>Tank adequacy</span>
        </div>
      @endif
      @if($ww->isTreatmentPlant() && $ww->operational_status)
        <div class="stat {{ $ww->operational_status === 'functional' ? 'g' : 'r' }}">
          <b style="font-size:15px">{{ WW::OPERATIONAL_STATUSES[$ww->operational_status] ?? $ww->operational_status }}</b>
          <span>Operational status</span>
        </div>
      @endif
    </div>
  @else
    <div class="note bad">
      <strong>No wastewater record found</strong>
      This inspection has no accompanying wastewater detail record — it
      may have been created before that table existed, or something did
      not save correctly.
    </div>
  @endif

@endif

<div class="grid2">
  {{-- ---------- Premises ---------- --}}
  <section>
    <h3>Premises</h3>
    <div class="desc">As recorded at this inspection</div>
    <table>
      <tbody>
        <tr><td class="k">Parcels</td><td>
          @forelse($entity->upis as $u)
            <div style="font-variant-numeric:tabular-nums;font-weight:{{ $u->is_primary ? 700 : 400 }}">
              {{ $u->upi }}@if($u->is_primary)<span class="pill good" style="margin-left:7px;font-size:9.5px">primary</span>@endif
            </div>
          @empty
            {{ $entity->upi ?: '—' }}
          @endforelse
        </td></tr>
        <tr><td class="k">Owner</td><td>{{ $entity->owner ?: '—' }}</td></tr>
        @if($ww?->tin_number)
          <tr><td class="k">TIN</td><td>{{ $ww->tin_number }}</td></tr>
        @endif
        <tr><td class="k">Telephone</td><td>{{ $entity->telephone ?: '—' }}</td></tr>
        <tr><td class="k">Email</td><td>{{ $entity->email ?: '—' }}</td></tr>
        <tr><td class="k">Zoning</td><td>
          @if($entity->zoning)
            <strong>{{ $entity->zoning }}</strong>
            @php $z = collect(config('zoning'))->flatMap(fn($g) => $g)->get($entity->zoning); @endphp
            @if($z)<br><span class="soft">{{ $z }}</span>@endif
          @else — @endif
        </td></tr>
        <tr><td class="k">Use</td><td>{{ $entity->use_type ?: '—' }}</td></tr>
        <tr><td class="k">Location</td><td>{{ collect([$entity->district, $entity->sector, $entity->cell])->filter()->implode(' · ') ?: '—' }}</td></tr>
        <tr><td class="k">Coordinates</td><td>
          @if($entity->latitude && $entity->longitude)
            <span style="font-variant-numeric:tabular-nums">{{ $entity->latitude }}, {{ $entity->longitude }}</span>
          @else
            <span class="soft">Not recorded</span>
          @endif
        </td></tr>
      </tbody>
    </table>
  </section>

  {{-- ---------- History ---------- --}}
  <section>
    <h3>Inspection history</h3>
    <div class="desc">{{ count($history) }} {{ Str::plural('visit', count($history)) }} on record</div>
    <table>
      <thead><tr><th>Date</th><th>Inspector</th><th style="text-align:right">{{ $hasChecklist ? 'Score' : 'Status' }}</th><th></th></tr></thead>
      <tbody>
        @foreach($history as $h)
          <tr class="{{ $h->id === $inspection->id ? 'viewing' : '' }}">
            <td>{{ $h->inspection_date->format('j M Y') }}
              <br><span class="soft">Visit {{ $h->visit_number }}@if($h->id === $inspection->id) &middot; viewing @endif</span>
            </td>
            <td style="font-size:12.5px">{{ $h->inspector_name }}</td>
            <td class="num">
              @if($h->template)
                <span class="pill {{ ComplianceBand::tone((float) $h->compliance) }}">
                  {{ round((float) $h->compliance, 1) }}%
                </span>
              @else
                @php $hww = $h->wastewaterInspection; @endphp
                @if($hww)
                  <span class="pill {{ $hww->complianceTone() }}">{{ $hww->complianceLabel() }}</span>
                @else
                  <span class="soft">—</span>
                @endif
              @endif
            </td>
            <td class="num">
              @if($h->id !== $inspection->id)
                <a href="{{ route('inspection.show', $h) }}" class="btn btn-ghost btn-sm">Open</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
</div>

{{-- ---------- Photographs ---------- --}}
@if($inspection->photos->count())
<section>
  <h3>Photographs</h3>
  <div class="desc">{{ $inspection->photos->count() }} recorded on site</div>
  <div class="gallery">
    @foreach($inspection->photos as $p)
      <a href="{{ $p->url() }}" target="_blank" class="shot">
        <img src="{{ $p->url() }}" alt="{{ $p->caption ?: 'Inspection photograph' }}">
        @if($p->caption)<span class="cap">{{ $p->caption }}</span>@endif
      </a>
    @endforeach
  </div>
</section>
@endif

@if($hasChecklist)

  {{-- ---------- Checklist ---------- --}}
  <section>
    <h3>Assessment checklist</h3>
    <div class="desc">{{ $inspection->template->name }} &middot; version {{ $inspection->template->version }}</div>

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

      <div class="sec-head">
        <div><span class="sec-no">{{ $section->section_number }}</span> {{ $section->title }}</div>
        @if($pct !== null)
          <span class="pill {{ ComplianceBand::tone((float) $pct) }}">{{ $pct }}%</span>
        @endif
      </div>

      <table class="chk">
        <tbody>
          @foreach($section->items as $item)
            @php
              $a  = $answers->get($item->id);
              $st = optional($a)->status;
              $ok = $st && in_array($st, ['yes','no'], true)
                    ? ($item->is_prohibition ? $st === 'no' : $st === 'yes')
                    : null;
            @endphp
            <tr class="{{ $ok === false ? 'fail' : '' }}">
              <td>
                {{ rtrim($item->label, '. ') }}
                @if($item->is_prohibition)
                  <span class="prohibited">Prohibited &mdash; must be absent</span>
                @endif
                @if(optional($a)->comment)<br><span class="cmt">{{ $a->comment }}</span>@endif
              </td>
              <td style="width:150px;text-align:right">
                @if($st === 'na')      <span class="pill na">Not applicable</span>
                @elseif($ok === true)  <span class="pill good">Complied</span>
                @elseif($ok === false) <span class="pill poor">Did not comply</span>
                @else                  <span class="soft">Not answered</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endforeach
  </section>

  @include('partials.inspection-faults-panel')

@else

  {{-- ---------- Wastewater detail ---------- --}}
  @if($ww)
  <section>
    <h3>Wastewater Management Inspection</h3>
    <div class="desc">Recorded directly &mdash; nothing here is scored</div>

    <table>
      <tbody>
        @if($ww->village)<tr><td class="k">Village</td><td>{{ $ww->village }}</td></tr>@endif
        @if($ww->inspection_type)<tr><td class="k">Inspection type</td><td>{{ WW::INSPECTION_TYPES[$ww->inspection_type] ?? $ww->inspection_type }}</td></tr>@endif
        @if($ww->design_capacity_m3)<tr><td class="k">Design capacity</td><td>{{ $ww->design_capacity_m3 }} m&sup3;/day</td></tr>@endif
        @if($ww->population_served)<tr><td class="k">Population served</td><td>{{ $ww->population_served }}</td></tr>@endif
        @if($ww->year_constructed)<tr><td class="k">Year constructed</td><td>{{ $ww->year_constructed }}</td></tr>@endif
        @if($ww->maintenance_company)<tr><td class="k">Maintenance company</td><td>{{ $ww->maintenance_company }}</td></tr>@endif
        @if($ww->last_maintenance_date)<tr><td class="k">Last maintenance</td><td>{{ $ww->last_maintenance_date->format('j F Y') }}</td></tr>@endif
        @if($ww->last_desludging_date)<tr><td class="k">Last desludging</td><td>{{ $ww->last_desludging_date->format('j F Y') }}</td></tr>@endif
        @if($ww->desludging_frequency)<tr><td class="k">Desludging frequency</td><td>{{ WW::DESLUDGING_FREQUENCIES[$ww->desludging_frequency] ?? $ww->desludging_frequency }}</td></tr>@endif
      </tbody>
    </table>

    @if($ww->isSepticTank())
      <div class="sec-head"><div>Septic tank system details</div></div>
      <table>
        <tbody>
          @if($ww->septic_tank_type)<tr><td class="k">Tank type</td><td>{{ WW::SEPTIC_TANK_TYPES[$ww->septic_tank_type] ?? $ww->septic_tank_type }}</td></tr>@endif
          @if($ww->septic_chamber_count)<tr><td class="k">Chambers</td><td>{{ $ww->septic_chamber_count }}</td></tr>@endif
          @if($ww->septic_tank_material)<tr><td class="k">Material</td><td>{{ WW::SEPTIC_TANK_MATERIALS[$ww->septic_tank_material] ?? $ww->septic_tank_material_other }}</td></tr>@endif
          @if($ww->septic_tank_location)<tr><td class="k">Location</td><td>{{ WW::SEPTIC_TANK_LOCATIONS[$ww->septic_tank_location] ?? $ww->septic_tank_location_other }}</td></tr>@endif
          @if($ww->tank_adequacy)<tr><td class="k">Tank adequacy</td><td>{{ WW::TANK_ADEQUACY[$ww->tank_adequacy] ?? $ww->tank_adequacy }}</td></tr>@endif
          @if($ww->desludging_provider)<tr><td class="k">Desludging provider</td><td>{{ $ww->desludging_provider }}</td></tr>@endif
        </tbody>
      </table>

      @if($ww->septic_components)
        <table class="chk" style="margin-top:10px">
          <thead><tr><th>Component</th><th style="text-align:right">Condition</th></tr></thead>
          <tbody>
            @foreach(WW::SEPTIC_COMPONENTS as $ck => $clabel)
              @if(!empty($ww->septic_components[$ck]))
                <tr>
                  <td>{{ $clabel }}</td>
                  <td style="text-align:right">
                    <span class="pill {{ $ww->septic_components[$ck] === 'good' ? 'good' : ($ww->septic_components[$ck] === 'poor' ? 'poor' : 'na') }}">
                      {{ WW::CONDITION_RATINGS[$ww->septic_components[$ck]] ?? $ww->septic_components[$ck] }}
                    </span>
                  </td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      @endif

      @php
        $yn = [
          'signs_of_leakage'          => 'Signs of leakage / seepage',
          'signs_of_overflow'         => 'Signs of overflow / ponding',
          'structural_damage_visible' => 'Structural cracks / damage',
          'odour_detected'            => 'Odour detected',
        ];
      @endphp
      <div class="stats" style="margin-top:10px">
        @foreach($yn as $field => $label)
          @if(! is_null($ww->$field))
            <div class="stat {{ $ww->$field ? 'r' : 'g' }}">
              <b>{{ $ww->$field ? 'Yes' : 'No' }}</b><span>{{ $label }}</span>
            </div>
          @endif
        @endforeach
      </div>

      @if($ww->septic_observations)
        <div class="fnd-label" style="margin-top:16px">Septic tank observations</div>
        <div class="prose">{!! nl2br(e($ww->septic_observations)) !!}</div>
      @endif
    @endif

    @if($ww->isTreatmentPlant())
      <div class="sec-head"><div>Operational status</div></div>
      <table>
        <tbody>
          @if($ww->operational_status)<tr><td class="k">Status</td><td>{{ WW::OPERATIONAL_STATUSES[$ww->operational_status] ?? $ww->operational_status }}</td></tr>@endif
        </tbody>
      </table>

      @if($ww->stp_components)
        <table class="chk" style="margin-top:10px">
          <thead><tr><th>Component</th><th style="text-align:right">Condition</th></tr></thead>
          <tbody>
            @foreach(WW::STP_COMPONENTS as $ck => $clabel)
              @if(!empty($ww->stp_components[$ck]))
                <tr>
                  <td>{{ $clabel }}</td>
                  <td style="text-align:right">
                    <span class="pill {{ $ww->stp_components[$ck] === 'good' ? 'good' : ($ww->stp_components[$ck] === 'poor' ? 'poor' : 'na') }}">
                      {{ WW::CONDITION_RATINGS[$ww->stp_components[$ck]] ?? $ww->stp_components[$ck] }}
                    </span>
                  </td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      @endif

      @if($ww->general_condition)
        <div class="fnd-label" style="margin-top:16px">General condition</div>
        <div class="prose">{!! nl2br(e($ww->general_condition)) !!}</div>
      @endif
    @endif

    <div class="sec-head"><div>Environmental compliance</div></div>
    <table>
      <tbody>
        @if(! is_null($ww->effluent_test_available))
          <tr><td class="k">Effluent test available</td><td>{{ $ww->effluent_test_available ? 'Yes' : 'No' }}</td></tr>
        @endif
        @if($ww->effluent_test_date)<tr><td class="k">Latest test date</td><td>{{ $ww->effluent_test_date->format('j F Y') }}</td></tr>@endif
        @if($ww->laboratory_name)<tr><td class="k">Laboratory</td><td>{{ $ww->laboratory_name }}</td></tr>@endif
        @if($ww->effluent_meets_standards)<tr><td class="k">Meets standards</td><td>{{ WW::EFFLUENT_STANDARDS[$ww->effluent_meets_standards] ?? $ww->effluent_meets_standards }}</td></tr>@endif
        @if($ww->final_discharge_point)<tr><td class="k">Final discharge point</td><td>{{ WW::DISCHARGE_POINTS[$ww->final_discharge_point] ?? $ww->final_discharge_point_other }}</td></tr>@endif
        @if(! is_null($ww->untreated_discharge_evidence))
          <tr><td class="k">Untreated discharge evidence</td><td>{{ $ww->untreated_discharge_evidence ? 'Yes' : 'No' }}</td></tr>
        @endif
        @if(! is_null($ww->pollution_evidence))
          <tr><td class="k">Pollution evidence</td><td>{{ $ww->pollution_evidence ? 'Yes' : 'No' }}</td></tr>
        @endif
      </tbody>
    </table>

    @if(!empty($ww->pollution_types))
      <div class="fnd-label" style="margin-top:14px">Type of pollution</div>
      <div>
        @foreach($ww->pollution_types as $pt)
          <span class="pill poor" style="margin:3px 4px 3px 0">{{ WW::POLLUTION_TYPES[$pt] ?? $pt }}</span>
        @endforeach
      </div>
    @endif

    @if($ww->key_findings)
      <div class="fnd-label" style="margin-top:16px">Key findings</div>
      <div class="prose">{!! nl2br(e($ww->key_findings)) !!}</div>
    @endif

    @if($ww->environmental_risks)
      <div class="fnd-label" style="margin-top:16px">Environmental risks identified</div>
      <div class="prose">{!! nl2br(e($ww->environmental_risks)) !!}</div>
    @endif

    @if(!empty($ww->non_compliances))
      <div class="fnd-label" style="margin-top:16px">Observed non-compliances</div>
      <div>
        @foreach($ww->non_compliances as $nc)
          <span class="pill poor" style="margin:3px 4px 3px 0">{{ WW::NON_COMPLIANCES[$nc] ?? $nc }}</span>
        @endforeach
        @if($ww->non_compliances_other)
          <span class="pill poor" style="margin:3px 4px 3px 0">{{ $ww->non_compliances_other }}</span>
        @endif
      </div>
    @endif
  </section>
  @endif

@endif

{{-- ---------- Findings ---------- --}}
@if($inspection->observations || $inspection->recommendations)
<section>
  <h3>Findings</h3>
  @if($inspection->observations)
    <div class="fnd-label">Observations</div>
    <div class="prose">{!! nl2br(e($inspection->observations)) !!}</div>
  @endif
  @if($inspection->recommendations)
    <div class="fnd-label" style="margin-top:18px">Recommendations</div>
    <div class="prose">{!! nl2br(e($inspection->recommendations)) !!}</div>
  @endif
</section>
@endif

{{-- ---------- Team ---------- --}}
@if($inspection->team->count())
<section>
  <h3>Inspection team</h3>
  <div class="desc">Only these officers may draft the report and letter for this visit</div>
  <table>
    <thead><tr><th>Name</th><th>Position</th><th>Institution</th><th>Account</th></tr></thead>
    <tbody>
      @foreach($inspection->team as $m)
        <tr>
          <td><strong>{{ $m->name }}</strong>@if($m->is_lead) <span class="pill good" style="font-size:9.5px">Lead</span>@endif</td>
          <td style="font-size:12.5px">{{ $m->position ?: '—' }}</td>
          <td style="font-size:12.5px">{{ $m->institution ?: '—' }}</td>
          <td style="font-size:12px">
            @if($m->user_id)
              <span class="pill good" style="font-size:9.5px">Linked</span>
            @else
              <span class="pill na" style="font-size:9.5px">No account &mdash; cannot sign</span>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</section>
@endif

{{-- ---------- Delete the inspection ---------- --}}
@php
  // Mirrors InspectionController::destroy(): only an inspector who was on
  // the visit, and only while nothing has been signed or issued.
  $mayDeleteInspection = $mine
      && ! $sealed
      && ! ($report && $report->signatures()->exists())
      && ! $inspection->documents()->where('status', \App\Models\Document::ISSUED)->exists();
@endphp

@if($mayDeleteInspection)
<section class="danger-zone">
  <h3>Remove this inspection</h3>
  <div class="desc">
    For an entry made in error &mdash; a test record, the wrong premises, or the same
    visit entered twice. Once a report is signed or a letter issued, it can no longer
    be removed.
  </div>

  <button type="button" class="link-danger"
          onclick="document.getElementById('del-insp').style.display='block';this.style.display='none'">
    Delete this inspection
  </button>

  <div id="del-insp" class="del-box" style="display:none;margin-top:0">
    <form method="post" action="{{ route('inspection.destroy', $inspection) }}">
      @csrf
      @method('DELETE')
      <b>Delete the whole inspection</b>
      <p>
        The visit, its answers, its photographs and any unsigned report go with it.
        Recorded against your name with the reason you give.
      </p>
      <div class="del-row">
        <input type="text" name="reason" required maxlength="300"
               placeholder="Reason — e.g. test entry, or wrong premises">
        <input type="text" name="confirm" required placeholder="Type DELETE">
        <button type="submit" class="btn btn-danger">Delete inspection</button>
        <button type="button" class="btn btn-ghost"
                onclick="document.getElementById('del-insp').style.display='none';document.querySelector('.link-danger').style.display=''">
          Cancel
        </button>
      </div>
    </form>
  </div>
</section>
@endif

@endsection

@push('styles')
<style>
  /* Names carried over from the earlier stylesheet */
  :root{
    --green:var(--success); --red:var(--danger); --gold:var(--warning);
    --blue:var(--primary);  --muted:var(--tertiary); --line:var(--border);
  }

  .soft{color:var(--tertiary);font-size:11.5px}
  td.k{color:var(--tertiary);width:34%;font-size:12.5px}
  tr.viewing td{background:#EDF6FB}

  .note{padding:14px 18px;margin-bottom:18px;font-size:14px;line-height:1.6;
        border-left:4px solid var(--primary);background:#E8F4FA}
  .note strong{display:block;font-family:var(--f-head);font-size:11px;font-weight:600;
               letter-spacing:.8px;text-transform:uppercase;margin-bottom:4px}
  .note.ok{border-left-color:var(--success);background:#E9F5EE}
  .note.ok strong{color:#00612B}
  .note.warn{border-left-color:var(--warning);background:#FFF8E5}
  .note.warn strong{color:#8A6D00}
  .note.bad{border-left-color:var(--danger);background:#FDECEA}
  .note.bad strong{color:#C62828}

  /* ---- Verdict ---- */
  .verdict{display:flex;justify-content:space-between;align-items:center;gap:18px;
           flex-wrap:wrap;padding:20px 24px;margin-bottom:18px;color:#fff}
  .verdict.good{background:var(--success)} .verdict.fair{background:#8BC34A}
  .verdict.weak{background:var(--warning)} .verdict.poor{background:var(--danger)}
  .verdict.na{background:var(--tertiary)}
  .v-label{font-family:var(--f-head);font-size:10px;letter-spacing:1.2px;
           text-transform:uppercase;opacity:.9;font-weight:600}
  .v-score{font-family:var(--f-head);font-size:38px;font-weight:800;line-height:1.05;
           letter-spacing:-1px}
  .v-sub{font-size:11.5px;opacity:.92;margin-top:2px}
  .v-verdict{font-family:var(--f-head);font-size:19px;font-weight:700}

  /* ---- Checklist ---- */
  .sec-head{display:flex;justify-content:space-between;align-items:center;gap:12px;
    background:#EDF6FB;color:var(--primary);padding:10px 15px;margin:18px 0 0;
    font-family:var(--f-head);font-size:13px;font-weight:600}
  .sec-no{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
    background:var(--primary);color:#fff;font-size:11px;margin-right:8px}
  table.chk td{font-size:13px;padding:9px 10px}
  table.chk tr.fail td{background:#FFF8F7}
  table.chk tr:last-child td{border-bottom:none}
  .cmt{color:var(--tertiary);font-size:11.5px;font-style:italic}
  .pill.na{background:var(--canvas);color:var(--body-text)}
  .prohibited{display:inline-block;margin-left:8px;font-family:var(--f-head);font-size:9.5px;
    font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#B71C1C;
    background:#FDECEA;padding:2px 7px;vertical-align:1px}

  /* ---- Photographs ---- */
  .gallery{display:flex;gap:12px;flex-wrap:wrap}
  .shot{display:block;width:200px;border:1px solid var(--border);overflow:hidden;
        text-decoration:none}
  .shot img{width:100%;height:138px;object-fit:cover;display:block;transition:transform .2s}
  .shot:hover img{transform:scale(1.04)}
  .shot .cap{display:block;padding:7px 9px;font-size:11.5px;color:var(--body-text);
             background:var(--white)}

  .fnd-label{font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:1px;
             text-transform:uppercase;color:var(--primary);margin-bottom:6px}
  .prose{font-size:13.5px;line-height:1.75;color:var(--ink)}

  /* ---- Signature ---- */
  .sign-panel{border-left:4px solid var(--warning)}
  .sign-panel.sealed{border-left-color:var(--success)}
  .sp-head{display:flex;justify-content:space-between;align-items:flex-start;
           gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .sp-desc{font-size:13px;color:var(--body-text);margin-top:3px;max-width:640px;line-height:1.6}
  .sp-warn{color:var(--danger);font-weight:600}
  .sp-acts{display:flex;gap:9px;flex-wrap:wrap}

  .sig-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
            padding-top:14px;border-top:1px solid var(--border)}
  .sig{display:flex;gap:11px;align-items:flex-start;padding:11px 13px;background:var(--canvas)}
  .sig.done{background:#E8F5E9}
  .sig.director{border-left:3px solid var(--primary)}
  .sig-mark{width:22px;height:22px;display:flex;align-items:center;justify-content:center;
            background:var(--border);color:#fff;font-size:13px;font-weight:700;flex-shrink:0}
  .sig.done .sig-mark{background:var(--success)}
  .sig b{display:block;font-family:var(--f-head);font-size:13px;color:var(--ink)}
  .sig span{display:block;font-size:11px;color:var(--tertiary);margin-top:1px}
  .sig em{display:block;font-size:11px;color:var(--body-text);font-style:normal;margin-top:3px}

  .sign-form{margin-top:16px;padding-top:14px;border-top:1px solid var(--border)}
  .sign-row{display:flex;gap:9px}
  .sign-row input{flex:1}

  /* ---- Deletion ---- */
  .danger-zone{border-left:4px solid var(--border)}
  .link-danger{background:none;border:0;color:var(--danger);cursor:pointer;padding:0;
               font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.7px;
               text-transform:uppercase}
  .link-danger:hover{text-decoration:underline}
  .del-box{margin-top:16px;padding:16px 18px;background:#FDECEA;border-left:4px solid var(--danger)}
  .del-box b{font-family:var(--f-head);color:var(--danger);display:block;margin-bottom:4px}
  .del-box p{font-size:12.5px;color:var(--body-text);margin-bottom:12px;max-width:620px}
  .del-row{display:flex;gap:8px;flex-wrap:wrap}
  .del-row input{flex:1;min-width:150px}
  .btn-danger{background:var(--danger);color:#fff}
  .btn-danger:hover{background:#C62828}

  @media print{
    .topbar,.sidebar,.backdrop,.head-actions,.note,
    .sp-acts,.sign-form,.del-box,.danger-zone{display:none!important}
    .content{padding:0}
    section{break-inside:avoid;box-shadow:none;border:1px solid #ddd}
    .verdict,.sec-head,.pill{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
</style>
@endpush