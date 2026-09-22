{{--
  The particulars of a wastewater management system.

  Recorded, not scored. Whether components are present and working is a
  judgement and belongs on the checklist; these are the facts an officer
  writes down about the premises and the system itself.

  System type decides which checklist applies — a sewage treatment plant
  and a septic tank are different pieces of infrastructure, not two
  stages of the same one, the same reasoning construction applies to
  substructure and superstructure.
--}}

@if(($type['code'] ?? null) === 'waste_water')
<section class="cx-block">
  <h3>Premises particulars</h3>
  <div class="desc">
    Recorded as found on site. Leave blank what could not be established
    rather than guessing &mdash; a letter may quote these.
  </div>

  <div class="cx-grid">

    <div class="cx-field">
      <label for="occupation_permit_number">Occupation permit number</label>
      <input type="text" name="occupation_permit_number" id="occupation_permit_number" maxlength="60"
             value="{{ old('occupation_permit_number', $inspection->occupation_permit_number ?? '') }}"
             placeholder="As shown on the permit">
    </div>

    <div class="cx-field">
      <label for="occupation_permit_year">Year permit acquired</label>
      <input type="number" name="occupation_permit_year" id="occupation_permit_year"
             min="1950" max="{{ date('Y') }}"
             value="{{ old('occupation_permit_year', $inspection->occupation_permit_year ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="manager_name">Manager / representative</label>
      <input type="text" name="manager_name" id="manager_name" maxlength="160"
             value="{{ old('manager_name', $inspection->manager_name ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="manager_telephone">Manager / representative telephone</label>
      <input type="text" name="manager_telephone" id="manager_telephone" maxlength="40"
             value="{{ old('manager_telephone', $inspection->manager_telephone ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="wastewater_system_type">Type of wastewater system</label>
      <select name="wastewater_system_type" id="wastewater_system_type" required>
        <option value="">Choose &mdash; this decides the checklist</option>
        <option value="stp"          @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'stp')>Sewage Treatment Plant (STP)</option>
        <option value="septic_tank"  @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'septic_tank')>Septic Tank</option>
        <option value="public_sewer" @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'public_sewer')>Public Sewer</option>
        <option value="soak_pit"     @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'soak_pit')>Soak Pit</option>
        <option value="none"         @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'none')>None</option>
        <option value="other"        @selected(old('wastewater_system_type', $inspection->wastewater_system_type ?? '') === 'other')>Other</option>
      </select>
      <div class="cx-note" id="ww-stage-note">
        A checklist is only required for a Sewage Treatment Plant or a Septic Tank.
      </div>
    </div>

    <div class="cx-field" id="wastewater_system_type_other_field" style="display:none">
      <label for="wastewater_system_type_other">If Other, specify</label>
      <input type="text" name="wastewater_system_type_other" id="wastewater_system_type_other" maxlength="120"
             value="{{ old('wastewater_system_type_other', $inspection->wastewater_system_type_other ?? '') }}">
    </div>

  </div>
</section>

{{-- ══════════ STP-only particulars ══════════ --}}
<section class="cx-block ww-stp-only" style="display:none">
  <h3>Sewage Treatment Plant details</h3>

  <div class="cx-grid">

    <div class="cx-field">
      <label for="stp_type">Type of STP</label>
      <select name="stp_type" id="stp_type">
        <option value="">Not established</option>
        @foreach([
            'package'      => 'Package (pre-built)',
            'activated_sludge' => 'Activated Sludge',
            'mbbr'         => 'MBBR',
            'sbr'          => 'SBR',
            'rbc'          => 'RBC',
            'constructed_wetland' => 'Constructed Wetland',
            'biodigester'  => 'Biodigester',
            'other'        => 'Other',
        ] as $k => $label)
          <option value="{{ $k }}" @selected(old('stp_type', $inspection->stp_type ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field" id="stp_type_other_field" style="display:none">
      <label for="stp_type_other">If Other, specify</label>
      <input type="text" name="stp_type_other" id="stp_type_other" maxlength="120"
             value="{{ old('stp_type_other', $inspection->stp_type_other ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="design_capacity_m3">Design capacity (m&sup3;/day)</label>
      <input type="number" name="design_capacity_m3" id="design_capacity_m3" step="0.01" min="0"
             value="{{ old('design_capacity_m3', $inspection->design_capacity_m3 ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="population_served">Estimated population served</label>
      <input type="number" name="population_served" id="population_served" min="0"
             value="{{ old('population_served', $inspection->population_served ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="water_consumption_m3">Current water consumption (m&sup3;/day)</label>
      <input type="number" name="water_consumption_m3" id="water_consumption_m3" step="0.01" min="0"
             value="{{ old('water_consumption_m3', $inspection->water_consumption_m3 ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="year_constructed">Year constructed</label>
      <input type="number" name="year_constructed" id="year_constructed" min="1950" max="{{ date('Y') }}"
             value="{{ old('year_constructed', $inspection->year_constructed ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="last_maintenance_date">Last maintenance date</label>
      <input type="date" name="last_maintenance_date" id="last_maintenance_date" max="{{ date('Y-m-d') }}"
             value="{{ old('last_maintenance_date', optional($inspection->last_maintenance_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cx-field">
      <label for="last_desludging_date">Date of last desludging</label>
      <input type="date" name="last_desludging_date" id="last_desludging_date" max="{{ date('Y-m-d') }}"
             value="{{ old('last_desludging_date', optional($inspection->last_desludging_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cx-field">
      <label for="desludging_frequency">Desludging frequency</label>
      <select name="desludging_frequency" id="desludging_frequency">
        <option value="">Not established</option>
        @foreach(['monthly'=>'Monthly','quarterly'=>'Quarterly','6_monthly'=>'6-Monthly','annually'=>'Annually','as_needed'=>'As Needed','never'=>'Never'] as $k => $label)
          <option value="{{ $k }}" @selected(old('desludging_frequency', $inspection->desludging_frequency ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field">
      <label for="operational_status">Operational status</label>
      <select name="operational_status" id="operational_status">
        <option value="">Not established</option>
        <option value="functional"           @selected(old('operational_status', $inspection->operational_status ?? '') === 'functional')>Functional</option>
        <option value="partially_functional"  @selected(old('operational_status', $inspection->operational_status ?? '') === 'partially_functional')>Partially Functional</option>
        <option value="non_functional"        @selected(old('operational_status', $inspection->operational_status ?? '') === 'non_functional')>Non-functional</option>
      </select>
    </div>

    <div class="cx-field cx-wide">
      <label for="stp_general_condition">General condition</label>
      <textarea name="stp_general_condition" id="stp_general_condition" rows="2">{{ old('stp_general_condition', $inspection->stp_general_condition ?? '') }}</textarea>
    </div>

    <div class="cx-field">
      <label for="effluent_test_date">Latest effluent test date</label>
      <input type="date" name="effluent_test_date" id="effluent_test_date" max="{{ date('Y-m-d') }}"
             value="{{ old('effluent_test_date', optional($inspection->effluent_test_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cx-field">
      <label for="laboratory_name">Laboratory name</label>
      <input type="text" name="laboratory_name" id="laboratory_name" maxlength="255"
             value="{{ old('laboratory_name', $inspection->laboratory_name ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="final_discharge_point">Final discharge point</label>
      <select name="final_discharge_point" id="final_discharge_point">
        <option value="">Not established</option>
        @foreach(['public_sewer'=>'Public Sewer','public_drainage'=>'Public Drainage','wetland'=>'Wetland','river'=>'River','soak_pit'=>'Soak Pit','reuse'=>'Reuse','other'=>'Other'] as $k => $label)
          <option value="{{ $k }}" @selected(old('final_discharge_point', $inspection->final_discharge_point ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

  </div>
</section>

{{-- ══════════ Septic Tank-only particulars ══════════ --}}
<section class="cx-block ww-septic-only" style="display:none">
  <h3>Septic Tank details</h3>

  <div class="cx-grid">

    <div class="cx-field">
      <label for="septic_tank_type">Septic tank type</label>
      <select name="septic_tank_type" id="septic_tank_type">
        <option value="">Not established</option>
        @foreach(['single_chamber'=>'Single Chamber','two_chamber'=>'Two Chamber','three_chamber'=>'Three Chamber','prefabricated'=>'Prefabricated','unknown'=>'Unknown'] as $k => $label)
          <option value="{{ $k }}" @selected(old('septic_tank_type', $inspection->septic_tank_type ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field">
      <label for="septic_chamber_count">Number of chambers</label>
      <input type="number" name="septic_chamber_count" id="septic_chamber_count" min="1" max="10"
             value="{{ old('septic_chamber_count', $inspection->septic_chamber_count ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="septic_tank_material">Tank material</label>
      <select name="septic_tank_material" id="septic_tank_material">
        <option value="">Not established</option>
        @foreach(['concrete'=>'Concrete','masonry'=>'Masonry','plastic'=>'Plastic','fiberglass'=>'Fiberglass','other'=>'Other'] as $k => $label)
          <option value="{{ $k }}" @selected(old('septic_tank_material', $inspection->septic_tank_material ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field">
      <label for="septic_tank_capacity">Tank capacity (m&sup3; or Litres)</label>
      <input type="number" name="septic_tank_capacity" id="septic_tank_capacity" step="0.01" min="0"
             value="{{ old('septic_tank_capacity', $inspection->septic_tank_capacity ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="septic_year_installed">Year installed</label>
      <input type="number" name="septic_year_installed" id="septic_year_installed" min="1950" max="{{ date('Y') }}"
             value="{{ old('septic_year_installed', $inspection->septic_year_installed ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="distance_to_foundation_m">Distance to nearest building foundation (m)</label>
      <input type="number" name="distance_to_foundation_m" id="distance_to_foundation_m" step="0.1" min="0"
             value="{{ old('distance_to_foundation_m', $inspection->distance_to_foundation_m ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="septic_tank_location">Tank location</label>
      <select name="septic_tank_location" id="septic_tank_location">
        <option value="">Not established</option>
        @foreach(['inside_compound'=>'Inside Compound','outside_compound'=>'Outside Compound','under_building'=>'Under Building','other'=>'Other'] as $k => $label)
          <option value="{{ $k }}" @selected(old('septic_tank_location', $inspection->septic_tank_location ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field">
      <label for="absorption_field_condition">Condition of absorption field</label>
      <select name="absorption_field_condition" id="absorption_field_condition">
        <option value="">Not established</option>
        <option value="good" @selected(old('absorption_field_condition', $inspection->absorption_field_condition ?? '') === 'good')>Good</option>
        <option value="fair" @selected(old('absorption_field_condition', $inspection->absorption_field_condition ?? '') === 'fair')>Fair</option>
        <option value="poor" @selected(old('absorption_field_condition', $inspection->absorption_field_condition ?? '') === 'poor')>Poor</option>
        <option value="not_applicable" @selected(old('absorption_field_condition', $inspection->absorption_field_condition ?? '') === 'not_applicable')>Not Applicable</option>
      </select>
    </div>

    <div class="cx-field">
      <label for="effluent_disposal_method">Effluent disposal after septic tank</label>
      <select name="effluent_disposal_method" id="effluent_disposal_method">
        <option value="">Not established</option>
        @foreach(['soak_pit'=>'Soak Pit / Drain Field','direct_discharge'=>'Direct Discharge to Drain/Ditch','wetland'=>'Wetland','open_ground'=>'Open Ground','reuse'=>'Reuse','unknown'=>'Unknown','emptying'=>'Emptying'] as $k => $label)
          <option value="{{ $k }}" @selected(old('effluent_disposal_method', $inspection->effluent_disposal_method ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field cx-wide">
      <label for="desludging_provider">Vacuum truck / desludging service provider</label>
      <input type="text" name="desludging_provider" id="desludging_provider" maxlength="255"
             value="{{ old('desludging_provider', $inspection->desludging_provider ?? '') }}">
    </div>

    <div class="cx-field">
      <label for="tank_adequacy">Estimated tank adequacy for population served</label>
      <select name="tank_adequacy" id="tank_adequacy">
        <option value="">Not established</option>
        <option value="adequate" @selected(old('tank_adequacy', $inspection->tank_adequacy ?? '') === 'adequate')>Adequate</option>
        <option value="undersized" @selected(old('tank_adequacy', $inspection->tank_adequacy ?? '') === 'undersized')>Undersized</option>
        <option value="unable_to_determine" @selected(old('tank_adequacy', $inspection->tank_adequacy ?? '') === 'unable_to_determine')>Unable to Determine</option>
      </select>
    </div>

    <div class="cx-field cx-wide">
      <label for="septic_observations">Additional observations on septic tank</label>
      <textarea name="septic_observations" id="septic_observations" rows="2">{{ old('septic_observations', $inspection->septic_observations ?? '') }}</textarea>
    </div>

  </div>
</section>
@endif

@push('scripts')
<script>
/* The system type decides which checklist applies and which of the two
   detail sections above shows — exactly the same disabled-as-well-as-
   hidden rule the stage toggle for construction uses, on this select's
   own id rather than sharing "building_status", so the two categories'
   scripts never collide even though both live in this same file. */
(function () {
  var sel = document.getElementById('wastewater_system_type');
  if (!sel) return; 

  var sets = document.querySelectorAll('.stage-set');
  var stpOnly    = document.querySelector('.ww-stp-only');
  var septicOnly = document.querySelector('.ww-septic-only');
  var otherField = document.getElementById('wastewater_system_type_other_field');

  function apply() {
    var v = sel.value;

    /* On create/follow-up, two stage-sets exist ("stp", "septic_tank")
       and must toggle between them. On edit, only one exists, keyed by
       an empty string — the stage was already decided when the
       inspection was created, and there is nothing to toggle between.
       Touching it here would only ever hide the one real checklist,
       since an empty string never matches "stp" or "septic_tank". */
    if (sets.length > 1) {
      sets.forEach(function (set) {
        var on = set.dataset.stage === v;
        set.classList.toggle('on', on);
        set.style.display = on ? '' : 'none';
        set.querySelectorAll('input, select, textarea').forEach(function (el) {
          el.disabled = ! on;
        });
      });
    }

    [[stpOnly, 'stp'], [septicOnly, 'septic_tank']].forEach(function (pair) {
      var el = pair[0], match = pair[1];
      if (!el) return;
      var on = v === match;
      el.style.display = on ? '' : 'none';
      el.querySelectorAll('input, select, textarea').forEach(function (field) {
        field.disabled = ! on;
      });
    });

    if (otherField) {
      otherField.style.display = v === 'other' ? '' : 'none';
      if (v !== 'other') otherField.querySelector('input').value = '';
    }

    document.dispatchEvent(new Event('stage:changed'));
  }

  sel.addEventListener('change', apply);
  apply();
})();

/* STP type "Other" free-text, same pattern as the system type itself. */
(function () {
  var sel = document.getElementById('stp_type');
  var other = document.getElementById('stp_type_other_field');
  if (!sel || !other) return;

  function apply() {
    other.style.display = sel.value === 'other' ? '' : 'none';
    if (sel.value !== 'other') other.querySelector('input').value = '';
  }

  sel.addEventListener('change', apply);
  apply();
})();
</script>
@endpush

@push('styles')
<style>
  .ww-stp-only, .ww-septic-only{border-left-color:#1565C0}
</style>
@endpush