{{--
  The particulars of a construction site.

  Recorded, not scored. Whether the permit is valid and whether the works
  match the drawings are judgements and belong on the checklist; these are
  the facts an officer writes down.

  Held against the visit rather than the premises, because they change
  between visits: a contractor is replaced, a permit renewed, a shell
  becomes a finished floor.
--}}

@if(($type['code'] ?? null) === 'construction')
<section class="cx-block">
  <h3>Site particulars</h3>
  <div class="desc">
    Recorded as found on site. Leave blank what could not be established
    rather than guessing &mdash; a letter may quote these.
  </div>

  <div class="cx-grid">

    <div class="cx-field">
      <label for="permit_number">Construction permit number</label>
      <input type="text" name="permit_number" id="permit_number" maxlength="60"
             value="{{ old('permit_number', $inspection->permit_number ?? '') }}"
             placeholder="As shown on the permit">
    </div>

    <div class="cx-field">
      <label for="permit_expiry">Permit expires</label>
      <input type="date" name="permit_expiry" id="permit_expiry"
             value="{{ old('permit_expiry', optional($inspection->permit_expiry ?? null)->format('Y-m-d')) }}">
      <div class="cx-note" id="permit-note"></div>
    </div>

    <div class="cx-field">
      <label for="building_category">Building category</label>
      <select name="building_category" id="building_category">
        <option value="">Not established</option>
        @foreach(config('construction.categories', [
            'Category 1',
            'Category 2',
            'Category 3',
            'Category 4',
            'Category 5',
        ]) as $c)
          <option value="{{ $c }}" @selected(old('building_category', $inspection->building_category ?? '') === $c)>{{ $c }}</option>
        @endforeach
      </select>
    </div>
    
        <div class="cx-field">
      <label for="on_main_corridor">On a main corridor</label>
      <select name="on_main_corridor" id="on_main_corridor">
        <option value="">Not established</option>
        <option value="1" @selected(old('on_main_corridor', $inspection->on_main_corridor ?? null) === true || old('on_main_corridor') === '1')>Yes</option>
        <option value="0" @selected(($inspection->on_main_corridor ?? null) === false || old('on_main_corridor') === '0')>No</option>
      </select>
    </div>

    <div class="cx-field" id="corridor-field" style="display:none">
      <label for="corridor_road">Which corridor</label>
      <select name="corridor_road" id="corridor_road">
        <option value="">Choose the road</option>
        @foreach(config('construction.corridors', []) as $road)
          <option value="{{ $road }}" @selected(old('corridor_road', $inspection->corridor_road ?? '') === $road)>{{ $road }}</option>
        @endforeach
      </select>
    </div>

    <div class="cx-field">
      <label for="has_physical_plan">Physical plan exists</label>
      <select name="has_physical_plan" id="has_physical_plan">
        <option value="">Not established</option>
        <option value="1" @selected(old('has_physical_plan', $inspection->has_physical_plan ?? null) === 1 || old('has_physical_plan') === '1')>Yes</option>
        <option value="0" @selected(($inspection->has_physical_plan ?? null) === 0 || old('has_physical_plan') === '0')>No</option>
      </select>
    </div>

    <div class="cx-field cx-wide">
      <label for="contractor">Contractor &mdash; company and professional</label>
      <input type="text" name="contractor" id="contractor" maxlength="255"
             value="{{ old('contractor', $inspection->contractor ?? '') }}"
             placeholder="Company, and the professional answerable">
    </div>

    <div class="cx-field cx-wide">
      <label for="supervisor">Supervisor &mdash; company and professional</label>
      <input type="text" name="supervisor" id="supervisor" maxlength="255"
             value="{{ old('supervisor', $inspection->supervisor ?? '') }}"
             placeholder="Company, and the professional answerable">
    </div>

    <div class="cx-field">
      <label for="building_status">Status of the works</label>
      <select name="building_status" id="building_status" required>
        <option value="">Choose &mdash; this decides the checklist</option>
        <option value="Substructure"   @selected(old('building_status', $inspection->building_status ?? '') === 'Substructure')>Substructure</option>
        <option value="Superstructure" @selected(old('building_status', $inspection->building_status ?? '') === 'Superstructure')>Superstructure</option>
      </select>
      <div class="cx-note" id="stage-note">
        Substructure covers foundation works; superstructure covers everything above ground.
      </div>
    </div>
    <div class="cx-field">
      <label for="dwelling_unit">Dwelling units</label>
      <input type="text" name="dwelling_unit" id="dwelling_unit" maxlength="80"
             value="{{ old('dwelling_unit', $inspection->dwelling_unit ?? '') }}"
             placeholder="Number and kind, e.g. 12 apartments">
    </div>

  </div>
</section>

@push('scripts')
<script>
/* An expired permit is the single most consequential fact on this form,
   so it is flagged as the date is entered rather than discovered later. */
(function () {
  var el   = document.getElementById('permit_expiry');
  var note = document.getElementById('permit-note');
  if (!el || !note) return;

  function check() {
    if (!el.value) { note.textContent = ''; note.className = 'cx-note'; return; }

    var d = new Date(el.value + 'T00:00:00');
    var today = new Date(); today.setHours(0,0,0,0);
    var days = Math.round((d - today) / 86400000);

    if (days < 0) {
      note.className = 'cx-note bad';
      note.textContent = 'Expired ' + Math.abs(days) + ' day' + (Math.abs(days) === 1 ? '' : 's') + ' ago.';
    } else if (days <= 60) {
      note.className = 'cx-note warn';
      note.textContent = 'Expires in ' + days + ' day' + (days === 1 ? '' : 's') + '.';
    } else {
      note.className = 'cx-note ok';
      note.textContent = 'Valid.';
    }
  }

  el.addEventListener('change', check);
  el.addEventListener('input', check);
  check();
})();

  /* The road only matters if the site is on a corridor. Asking for it
     otherwise invites an answer that means nothing. */
  (function () {
    var on   = document.getElementById('on_main_corridor');
    var road = document.getElementById('corridor-field');
    if (!on || !road) return;

    function apply() {
      var yes = on.value === '1';
      road.style.display = yes ? '' : 'none';
      if (!yes) road.querySelector('select').value = '';
    }

    on.addEventListener('change', apply);
    apply();
  })();
</script>
@endpush

@push('styles')
<style>
  .cx-block{background:var(--white);padding:24px 26px;margin-bottom:22px;
            box-shadow:var(--shadow-sm);border-left:4px solid var(--tertiary)}
  .cx-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
  .cx-field.cx-wide{grid-column:span 2}
  @media(max-width:700px){.cx-field.cx-wide{grid-column:span 1}}
  .cx-field label{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .cx-note{font-size:11.5px;color:var(--tertiary);margin-top:5px;min-height:16px}
  .cx-note.ok{color:var(--success)}
  .cx-note.warn{color:#8A6D00}
  .cx-note.bad{color:var(--danger);font-weight:600}
</style>
@endpush
@endif