{{--
  What a desk review records.

  A review is of an application, not of a place. The officer reads what
  was submitted and judges whether it is complete and sound; for a new
  construction permit, nothing has been built yet.

  The type is chosen here rather than before the form opens, and choosing
  it loads the matching checklist. Seven checklists is too many to hold on
  one page — 122 items — so this reloads rather than toggling. Nothing is
  lost: the type is the first thing an officer establishes, as they would
  pick up the right paper form.
--}}

@if(($type['code'] ?? null) === 'desk_review')
@php
  $deskTypes = [
      'ncp'   => 'New Construction Permit',
      'op'    => 'Occupation Permit',
      'renew' => 'Permit Renewal',
      'mod'   => 'Modification Permit',
      'rwo'   => 'Refurbishment — with structural alteration',
      'rwi'   => 'Refurbishment — without structural alteration',
      'fence' => 'Fence Permit',
  ];

  $chosen = $stage ?? $inspection->stage ?? null;
@endphp

<section class="dr-block">
  <h3>The application</h3>
  <div class="desc">
    What is being reviewed, and what came with it. A review is of an
    application &mdash; for a new construction permit, nothing has been
    built yet.
  </div>

  <div class="dr-type">
    <label for="desk_type">Kind of application</label>
    <select id="desk_type" {{ $inspection?->exists ? 'disabled' : '' }}>
      <option value="">Choose &mdash; this decides the checklist</option>
      @foreach($deskTypes as $k => $label)
        <option value="{{ $k }}" @selected($chosen === $k)>{{ $label }}</option>
      @endforeach
    </select>

    <input type="hidden" name="stage" value="{{ $chosen }}">

    <div class="dr-note">
      @if($inspection?->exists)
        Fixed once the review is recorded &mdash; the answers belong to
        this checklist and cannot be moved to another.
      @else
        Choosing this loads the checklist for that kind of application.
      @endif
    </div>
  </div>

  @if($chosen)
    <div class="dr-grid">
      <div class="dr-field">
        <label for="application_number">Application number</label>
        <input type="text" name="application_number" id="application_number" maxlength="60"
               value="{{ old('application_number', $inspection->application_number ?? '') }}"
               placeholder="As submitted">
      </div>

      <div class="dr-field">
        <label for="reviewed_on">Date of this review</label>
        <input type="date" name="reviewed_on" id="reviewed_on" max="{{ date('Y-m-d') }}"
               value="{{ old('reviewed_on', optional($inspection->reviewed_on ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}">
      </div>

      <div class="dr-field">
        <label for="permit_number">Permit number</label>
        <input type="text" name="permit_number" id="permit_number" maxlength="60"
               value="{{ old('permit_number', $inspection->permit_number ?? '') }}"
               placeholder="Where one has issued">
      </div>

      <div class="dr-field">
        <label for="permit_issued_on">Date permit issued</label>
        <input type="date" name="permit_issued_on" id="permit_issued_on" max="{{ date('Y-m-d') }}"
               value="{{ old('permit_issued_on', optional($inspection->permit_issued_on ?? null)->format('Y-m-d')) }}">
        <div class="dr-note" id="issue-note"></div>
      </div>

      <div class="dr-field">
        <label for="building_category">Building category</label>
        <select name="building_category" id="building_category">
          <option value="">Not established</option>
          @foreach(config('construction.categories', []) as $c)
            <option value="{{ $c }}" @selected(old('building_category', $inspection->building_category ?? '') === $c)>{{ $c }}</option>
          @endforeach
        </select>
      </div>

      <div class="dr-field">
        <label for="has_physical_plan">Physical plan exists</label>
        <select name="has_physical_plan" id="has_physical_plan">
          <option value="">Not established</option>
          <option value="1" @selected(old('has_physical_plan', $inspection->has_physical_plan ?? null) === true || old('has_physical_plan') === '1')>Yes</option>
          <option value="0" @selected(($inspection->has_physical_plan ?? null) === false || old('has_physical_plan') === '0')>No</option>
        </select>
      </div>
    </div>
  @endif
</section>

@push('styles')
<style>
  .dr-block{background:var(--white);padding:24px 26px;margin-bottom:22px;
            box-shadow:var(--shadow-sm);border-left:4px solid var(--primary)}
  .dr-type{max-width:460px;margin-bottom:20px}
  .dr-type label{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                 letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .dr-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
           padding-top:18px;border-top:1px solid var(--border)}
  .dr-field label{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .dr-note{font-size:11.5px;color:var(--tertiary);margin-top:5px;line-height:1.5;min-height:16px}
  .dr-note.bad{color:var(--danger);font-weight:600}
  .dr-note.warn{color:#8A6D00}
</style>
@endpush

@push('scripts')
<script>
/* Choosing the kind of application loads its checklist.
   Seven checklists is 122 items — too many to hold on one page and
   toggle, as construction does with two. Reloading costs nothing here
   because the type is the first thing an officer establishes. */
(function () {
  var sel = document.getElementById('desk_type');
  if (!sel || sel.disabled) return;

  sel.addEventListener('change', function () {
    if (!sel.value) return;

    var u = new URL(window.location.href);
    u.searchParams.set('stage', sel.value);
    window.location = u.toString();
  });
})();

/* A review examines a permit that has already been granted, so the permit
   must precede it. A permit dated after the review is one of the two
   dates being wrong — most often the review date typed as today when the
   file was read weeks ago. */
(function () {
  var issued = document.getElementById('permit_issued_on');
  var review = document.getElementById('reviewed_on');
  var note   = document.getElementById('issue-note');
  if (!issued || !note) return;

  function check() {
    note.className = 'dr-note';
    note.textContent = '';

    if (!issued.value) return;

    var d = new Date(issued.value + 'T00:00:00');
    var today = new Date(); today.setHours(0,0,0,0);

    if (d > today) {
      note.className = 'dr-note bad';
      note.textContent = 'That date is in the future.';
      return;
    }

    if (review && review.value && issued.value > review.value) {
      note.className = 'dr-note bad';
      note.textContent = 'The permit issued after this review. A review examines a '
                       + 'permit already granted, so one of the two dates is wrong.';
    }
  }

  issued.addEventListener('change', check);
  if (review) review.addEventListener('change', check);
  check();
})();
</script>
@endpush
@endif