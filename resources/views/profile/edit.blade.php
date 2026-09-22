@extends('layouts.app')
@section('title', 'My Account')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $user->roles->pluck('name')->first() }}</div>
    <h2>My Account</h2>
    <p>Your details and the signature applied to reports you sign.</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('mybox') }}" class="btn btn-ghost">My Box</a>
  </div>
</div>

@if(session('status'))
  <div class="note" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif
@if($errors->any())
  <div class="callout"><b>Please correct the following</b>
    <p style="font-size:14px;font-weight:400">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</p></div>
@endif

<div class="grid2">
  <section>
    <h3>Your details</h3>
    <div class="desc">Name and position appear on documents you sign</div>

    <form method="post" action="{{ route('profile.update') }}">
      @csrf @method('PUT')
      <div style="display:grid;gap:16px">
        <div><label>Full name</label>
          <input type="text" name="name" required value="{{ old('name', $user->name) }}"></div>

        <div><label>Position</label>
          <select name="position">
            <option value="">Not set</option>
            @foreach(config('positions') as $group => $list)
              <optgroup label="{{ $group }}">
                @foreach($list as $p)
                  <option value="{{ $p }}" @selected(old('position', $user->position) === $p)>{{ $p }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select></div>

        <div><label>Telephone</label>
          <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"></div>

        <div><label>Employee number</label>
          <input type="text" name="employee_number" value="{{ old('employee_number', $user->employee_number) }}"></div>

        <table style="margin-top:4px">
          <tr><td class="k">Email</td><td>{{ $user->email }}</td></tr>
          <tr><td class="k">Role</td><td>{{ $user->roles->pluck('name')->first() }}</td></tr>
          <tr><td class="k">District</td><td>{{ $user->district?->name ?? 'All districts' }}</td></tr>
        </table>

        <div><button type="submit" class="btn btn-success">Save Details</button></div>
      </div>
    </form>
  </section>

  <section>
    <h3>Your signature</h3>
    <div class="desc">Registered by you alone. It is placed above your name on reports you sign.</div>

    @if($user->signature_path)
      <div class="sig-current">
        <img src="{{ Storage::disk('public')->url($user->signature_path) }}" alt="Your signature">
        <div class="sig-meta">
          Registered {{ $user->signature_registered_at?->format('j F Y') }}
          <form method="post" action="{{ route('profile.signature.destroy') }}"
                onsubmit="return confirm('Remove your signature?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm">Remove</button>
          </form>
        </div>
      </div>
    @endif

    <form method="post" action="{{ route('profile.signature') }}" enctype="multipart/form-data" id="sig-form">
      @csrf

      <div class="tabs-sm">
        <button type="button" class="tsm on" data-pane="draw">Draw it</button>
        <button type="button" class="tsm" data-pane="upload">Upload an image</button>
      </div>

      <div id="pane-draw">
        <div class="pad-wrap">
          <canvas id="pad" width="600" height="200"></canvas>
          <div class="pad-line"></div>
        </div>
        <div class="pad-tools">
          <button type="button" class="btn btn-ghost btn-sm" id="pad-clear">Clear</button>
          <span class="hint">Draw with a mouse, or with a finger on a tablet or phone.</span>
        </div>
        <input type="hidden" name="signature_data" id="signature_data">
      </div>

      <div id="pane-upload" style="display:none">
        <label>Signature image</label>
        <input type="file" name="signature_file" accept="image/png,image/jpeg">
        <div class="hint">A PNG with a transparent background sits best on a document.</div>
      </div>

      <div style="margin-top:18px">
        <button type="submit" class="btn btn-success">{{ $user->signature_path ? 'Replace Signature' : 'Register Signature' }}</button>
      </div>
    </form>
  </section>
</div>



<script>
(function () {
  /* ---- tabs ---- */
  document.querySelectorAll('.tsm').forEach(function (t) {
    t.addEventListener('click', function () {
      document.querySelectorAll('.tsm').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      document.getElementById('pane-draw').style.display   = t.dataset.pane === 'draw'   ? '' : 'none';
      document.getElementById('pane-upload').style.display = t.dataset.pane === 'upload' ? '' : 'none';
    });
  });

  /* ---- signature pad ---- */
  var canvas = document.getElementById('pad');
  var ctx = canvas.getContext('2d');
  var drawing = false, dirty = false, last = null;

  function resize() {
    var ratio = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var data = dirty ? canvas.toDataURL() : null;

    canvas.width  = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#12161D';

    if (data) {
      var img = new Image();
      img.onload = function () { ctx.drawImage(img, 0, 0, rect.width, rect.height); };
      img.src = data;
    }
  }

  function point(e) {
    var rect = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }

  function start(e) { e.preventDefault(); drawing = true; last = point(e); }

  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = point(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    dirty = true;
  }

  function end() { drawing = false; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  document.getElementById('pad-clear').addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    dirty = false;
  });

  document.getElementById('sig-form').addEventListener('submit', function () {
    if (dirty && document.getElementById('pane-draw').style.display !== 'none') {
      document.getElementById('signature_data').value = canvas.toDataURL('image/png');
    }
  });

  window.addEventListener('resize', resize);
  resize();
})();
</script>

@endsection

@push('styles')
<style>
  td.k{color:var(--tertiary);width:34%;font-family:var(--f-head);font-size:11.5px;
       text-transform:uppercase;letter-spacing:.5px}
  .sig-current{background:var(--canvas);padding:18px;margin-bottom:22px;text-align:center}
  .sig-current img{max-width:100%;max-height:110px;background:#fff;padding:8px}
  .sig-meta{display:flex;justify-content:space-between;align-items:center;gap:12px;
            margin-top:12px;font-size:12px;color:var(--tertiary);flex-wrap:wrap}
  .tabs-sm{display:flex;margin-bottom:16px;border-bottom:2px solid var(--border)}
  .tsm{font-family:var(--f-head);font-size:11.5px;font-weight:600;letter-spacing:.7px;
       text-transform:uppercase;padding:10px 16px;border:0;background:transparent;
       color:var(--tertiary);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
  .tsm.on{color:var(--primary);border-bottom-color:var(--primary)}
  .pad-wrap{position:relative;background:#fff;border:1px solid var(--border)}
  #pad{display:block;width:100%;height:200px;touch-action:none;cursor:crosshair}
  .pad-line{position:absolute;left:8%;right:8%;bottom:44px;border-bottom:1px dashed #c9ced6;pointer-events:none}
  .pad-tools{display:flex;align-items:center;gap:14px;margin-top:10px;flex-wrap:wrap}
  .hint{font-size:12px;color:var(--tertiary)}
</style>
@endpush
