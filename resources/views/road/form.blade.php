@extends('layouts.app')
@section('title', $road->exists ? 'Edit road' : 'Add a road')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Road Inspection &middot; Register</div>
    <h2>{{ $road->exists ? $road->label() : 'Add a road' }}</h2>
    <p>
      A road is recorded, not assessed. There is no score here &mdash; what
      matters is its category, where it runs, and how much of it is paved.
    </p>
  </div>
  <div class="head-actions">
    <a href="{{ route('road.index') }}" class="btn btn-ghost">Back to register</a>
  </div>
</div>

@if($errors->any())
  <div class="note bad">
    <strong>Could not save</strong>
    @foreach($errors->all() as $e)&bull; {{ $e }}<br>@endforeach
  </div>
@endif

<form method="post" action="{{ $action }}" id="road-form">
  @csrf
  @if($road->exists)@method('PUT')@endif

  <section>
    <h3>Identification</h3>
    <div class="desc">What the road is and where it runs</div>

    <div class="rf-grid">
      <div class="rf-field">
        <label for="category">Category</label>
        <select name="category" id="category" required>
          <option value="">Choose</option>
          @foreach(\App\Models\Road::CATEGORIES as $k => $label)
            <option value="{{ $k }}" @selected(old('category', $road->category) === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="rf-field rf-wide">
        <label for="name">Name of road</label>
        <input type="text" name="name" id="name" required maxlength="255"
               value="{{ old('name', $road->name) }}"
               placeholder="As the City names it">
      </div>

      <div class="rf-field">
        <label for="code">Code</label>
        <input type="text" name="code" id="code" maxlength="40"
               value="{{ old('code', $road->code) }}"
               placeholder="KN 1 Rd, RN3">
      </div>

      <div class="rf-field rf-wide">
        <label for="start_point">Starting point</label>
        <input type="text" name="start_point" id="start_point" maxlength="255"
               value="{{ old('start_point', $road->start_point) }}"
               placeholder="Where the road begins">
      </div>

      <div class="rf-field rf-wide">
        <label for="end_point">End point</label>
        <input type="text" name="end_point" id="end_point" maxlength="255"
               value="{{ old('end_point', $road->end_point) }}"
               placeholder="Where it ends">
      </div>

      <div class="rf-field">
        <label for="district">District</label>
        <select name="district" id="district">
          <option value="">Not recorded</option>
          @foreach(array_keys(config('kigali')) as $d)
            <option value="{{ $d }}" @selected(old('district', $road->district) === $d)>{{ $d }}</option>
          @endforeach
        </select>
        <div class="rf-note">Where it begins &mdash; a road may run through several</div>
      </div>

      <div class="rf-field">
        <label for="sector">Sector</label>
        <select name="sector" id="sector"><option value="">Not recorded</option></select>
      </div>
    </div>
  </section>

  <section>
    <h3>Surface and length</h3>
    <div class="desc">
      Paved and unpaved should account for the whole road. Where they do not,
      the gap is recorded rather than refused &mdash; a partial survey is
      still worth having.
    </div>

    <div class="rf-grid">
      <div class="rf-field">
        <label for="surface">Status</label>
        <select name="surface" id="surface">
          <option value="">Not recorded</option>
          @foreach(\App\Models\Road::SURFACES as $k => $label)
            <option value="{{ $k }}" @selected(old('surface', $road->surface) === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="rf-field">
        <label for="length_km">Total length (km)</label>
        <input type="number" name="length_km" id="length_km" step="0.001" min="0" max="9999"
               value="{{ old('length_km', $road->length_km) }}">
      </div>

      <div class="rf-field">
        <label for="paved_km">Paved (km)</label>
        <input type="number" name="paved_km" id="paved_km" step="0.001" min="0" max="9999"
               value="{{ old('paved_km', $road->paved_km) }}">
      </div>

      <div class="rf-field">
        <label for="unpaved_km">Unpaved (km)</label>
        <input type="number" name="unpaved_km" id="unpaved_km" step="0.001" min="0" max="9999"
               value="{{ old('unpaved_km', $road->unpaved_km) }}">
      </div>
    </div>

    <div class="rf-sum" id="rf-sum"></div>
  </section>

  <section>
    <h3>Position</h3>
    <div class="desc">
      Optional, but a register with no positions cannot be mapped. Capture
      at each end of the road, or type coordinates read from elsewhere.
    </div>

    <div class="rf-grid">
      <div class="rf-field">
        <label for="start_lat">Start latitude</label>
        <input type="number" name="start_lat" id="start_lat" step="0.0000001"
               value="{{ old('start_lat', $road->start_lat) }}" placeholder="-1.9441">
      </div>
      <div class="rf-field">
        <label for="start_lng">Start longitude</label>
        <input type="number" name="start_lng" id="start_lng" step="0.0000001"
               value="{{ old('start_lng', $road->start_lng) }}" placeholder="30.0619">
      </div>
      <div class="rf-field rf-btn">
        <label>&nbsp;</label>
        <button type="button" class="btn btn-ghost" data-capture="start">Capture start</button>
      </div>

      <div class="rf-field">
        <label for="end_lat">End latitude</label>
        <input type="number" name="end_lat" id="end_lat" step="0.0000001"
               value="{{ old('end_lat', $road->end_lat) }}">
      </div>
      <div class="rf-field">
        <label for="end_lng">End longitude</label>
        <input type="number" name="end_lng" id="end_lng" step="0.0000001"
               value="{{ old('end_lng', $road->end_lng) }}">
      </div>
      <div class="rf-field rf-btn">
        <label>&nbsp;</label>
        <button type="button" class="btn btn-ghost" data-capture="end">Capture end</button>
      </div>
    </div>

    <div class="rf-note" id="gps-note"></div>
  </section>

  <section>
    <h3>Notes</h3>
    <div class="desc">Anything the columns above do not cover</div>
    <textarea name="notes" rows="3" maxlength="1000">{{ old('notes', $road->notes) }}</textarea>
  </section>

  <div class="rf-actions">
    <button type="submit" class="btn btn-success">
      {{ $road->exists ? 'Save changes' : 'Add to register' }}
    </button>
    <a href="{{ route('road.index') }}" class="btn btn-ghost">Cancel</a>
  </div>
</form>

@if($road->exists)
  @can('road.manage')
    <section class="danger-zone">
      <h3>Remove from the register</h3>
      <div class="desc">
        For a road entered in error. Soft deleted, so it can be recovered.
      </div>

      <button type="button" class="link-danger"
              onclick="document.getElementById('del-road').style.display='block';this.style.display='none'">
        Remove this road
      </button>

      <div id="del-road" class="del-box" style="display:none">
        <form method="post" action="{{ route('road.destroy', $road) }}">
          @csrf
          @method('DELETE')
          <b>Remove {{ $road->label() }}</b>
          <p>It will no longer appear on the register or in any road chooser.</p>
          <div class="del-row">
            <input type="text" name="confirm" required placeholder="Type DELETE">
            <button type="submit" class="btn btn-danger">Remove</button>
          </div>
        </form>
      </div>
    </section>
  @endcan
@endif

@endsection

@push('styles')
<style>
  .rf-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
  .rf-field.rf-wide{grid-column:span 2}
  @media(max-width:700px){.rf-field.rf-wide{grid-column:span 1}}
  .rf-field label{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .rf-note{font-size:11.5px;color:var(--tertiary);margin-top:5px;line-height:1.5}
  .rf-btn button{width:100%;height:44px}

  .rf-sum{margin-top:14px;padding:11px 15px;font-size:12.5px;line-height:1.6;
          background:var(--canvas);border-left:3px solid var(--border);min-height:20px}
  .rf-sum.ok{background:#E9F5EE;border-left-color:var(--success);color:#1F5C23}
  .rf-sum.gap{background:#FFF8E5;border-left-color:var(--warning);color:#6B4A00}
  .rf-sum.over{background:#FDECEA;border-left-color:var(--danger);color:#8B2A20}

  .rf-actions{display:flex;gap:10px;margin-bottom:22px}

  .danger-zone{border-left:4px solid var(--border)}
  .link-danger{background:none;border:0;color:var(--danger);cursor:pointer;padding:0;
               font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.7px;
               text-transform:uppercase}
  .link-danger:hover{text-decoration:underline}
  .del-box{margin-top:16px;padding:16px 18px;background:#FDECEA;border-left:4px solid var(--danger)}
  .del-box b{font-family:var(--f-head);color:var(--danger);display:block;margin-bottom:4px}
  .del-box p{font-size:12.5px;color:var(--body-text);margin-bottom:12px}
  .del-row{display:flex;gap:8px;flex-wrap:wrap}
  .del-row input{flex:1;min-width:150px}
  .btn-danger{background:var(--danger);color:#fff}
  .btn-danger:hover{background:#C62828}
</style>
@endpush

@push('scripts')
<script>
/* The lengths, checked as they are typed.
   Not enforced: a partial survey is still worth recording, and refusing
   what an officer measured because the rest is unmeasured would lose the
   measurement. Shown so the gap can be filled rather than overlooked. */
(function () {
  var total   = document.getElementById('length_km');
  var paved   = document.getElementById('paved_km');
  var unpaved = document.getElementById('unpaved_km');
  var out     = document.getElementById('rf-sum');
  if (!total || !out) return;

  function n(el) { return parseFloat(el.value) || 0; }

  function check() {
    var t = n(total), p = n(paved), u = n(unpaved);

    if (!t && !p && !u) { out.className = 'rf-sum'; out.textContent = ''; return; }

    var parts = p + u;
    var diff  = Math.round((t - parts) * 1000) / 1000;

    if (!t) {
      out.className = 'rf-sum gap';
      out.textContent = 'Total length not recorded. Paved and unpaved come to '
                      + parts.toFixed(3) + ' km.';
    } else if (Math.abs(diff) < 0.05) {
      out.className = 'rf-sum ok';
      out.textContent = 'Paved and unpaved account for the whole road.';
    } else if (diff > 0) {
      out.className = 'rf-sum gap';
      out.textContent = diff.toFixed(3) + ' km unaccounted for. That is fine if the '
                      + 'survey was partial — it will be flagged on the register.';
    } else {
      out.className = 'rf-sum over';
      out.textContent = 'Paved and unpaved come to ' + parts.toFixed(3)
                      + ' km, which is ' + Math.abs(diff).toFixed(3)
                      + ' km more than the total. One of the three is wrong.';
    }
  }

  [total, paved, unpaved].forEach(function (el) {
    if (el) el.addEventListener('input', check);
  });

  check();
})();

/* Capturing a position at either end of the road. */
(function () {
  var note = document.getElementById('gps-note');

  document.querySelectorAll('[data-capture]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var which = btn.dataset.capture;

      if (!navigator.geolocation) {
        note.textContent = 'This device cannot report its position.';
        return;
      }

      note.textContent = 'Reading position…';

      navigator.geolocation.getCurrentPosition(function (pos) {
        var lat = pos.coords.latitude, lng = pos.coords.longitude;

        /* Kigali, roughly. A reading far outside it is the device
           guessing from the network rather than the satellites, and
           recording it would put the road in the wrong country. */
        if (lat < -2.5 || lat > -1.0 || lng < 29.0 || lng > 31.0) {
          note.textContent = 'That position is outside Kigali — the device may be '
                           + 'guessing from the network. Not recorded.';
          return;
        }

        document.getElementById(which + '_lat').value = lat.toFixed(7);
        document.getElementById(which + '_lng').value = lng.toFixed(7);
        note.textContent = which === 'start' ? 'Start position recorded.' : 'End position recorded.';
      }, function () {
        note.textContent = 'Could not read the position. Type the coordinates if you have them.';
      }, { enableHighAccuracy: true, timeout: 12000 });
    });
  });
})();

/* Sector follows district. */
(function () {
  var d = document.getElementById('district');
  var s = document.getElementById('sector');
  if (!d || !s || !window.KIGALI) return;

  var saved = @json(old('sector', $road->sector ?? ''));

  function fill(keep) {
    var sectors = KIGALI[d.value] ? Object.keys(KIGALI[d.value]) : [];
    s.innerHTML = '<option value="">Not recorded</option>';
    sectors.forEach(function (v) {
      var o = document.createElement('option');
      o.value = v; o.textContent = v;
      if (keep && v === saved) o.selected = true;
      s.appendChild(o);
    });
    s.disabled = sectors.length === 0;
  }

  d.addEventListener('change', function () { fill(false); });
  fill(true);
})();
</script>
@endpush