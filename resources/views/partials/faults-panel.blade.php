{{--
  Faults found on site.

  Distinct from the checklist. A checklist asks whether a requirement is
  met and scores the answer; a fault is a specific contravention with a
  penalty from the schedule in the Urban Planning Code. A site can score
  well and still carry one — good safety practice, built without a permit.

  Nothing here raises a fine. The amounts are what the schedule says would
  be owed; nothing is owed until the report is sealed and a letter issued,
  which is how every other finding on this platform behaves.

  Expects: $inspection (may be null on a new form), $type
--}}

@if(($type['code'] ?? null) === 'construction')
@php
  $faults = \App\Models\Fault::forType('construction')->with('sanctions')->get();

  $chosen = $inspection?->faults?->keyBy('fault_id') ?? collect();

  $category = old('building_category', $inspection->building_category ?? '');
@endphp

<section class="fx-block">
  <div class="fx-head">
    <div>
      <h3>Faults found</h3>
      <div class="desc">
        Contraventions under the Urban Planning Code, separate from the
        checklist above. Select only what was actually observed.
      </div>
    </div>
    <div class="fx-total" id="fx-total">
      <span>Schedule would set</span>
      <b id="fx-amount">FRW 0</b>
      <em id="fx-count">no faults selected</em>
    </div>
  </div>

  <div class="fx-warn" id="fx-nocat" style="display:none">
    <strong>Building category not set</strong>
    Most sanctions depend on it. Choose the category above and the amounts
    will resolve.
  </div>

  <div class="fx-list">
    @foreach($faults as $f)
      @php
        $has  = $chosen->has($f->id);
        $rec  = $chosen->get($f->id);
        $kind = $f->scopeKind();

        /* Resolved here, not inline: a multi-line expression inside
           {!! !!} is more than Blade's parser will reliably take. */
        $rows = $f->sanctions->map(fn ($s) => [
            'id'     => $s->id,
            'kind'   => $s->scope_kind,
            'scope'  => $s->scope,
            'amount' => $s->amount ? (float) $s->amount : null,
            'action' => $s->action,
            'liable' => $s->liable,
        ])->toJson();
      @endphp

      <div class="fx-item {{ $has ? 'on' : '' }}" data-fault="{{ $f->id }}">
        <label class="fx-pick">
          <input type="checkbox" name="faults[{{ $f->id }}][selected]" value="1"
                 class="fx-check" @checked($has)>
          <span class="fx-code">{{ $f->code }}</span>
          <span class="fx-title">{{ $f->title }}</span>
        </label>

        <div class="fx-detail">
          @if($kind !== 'building_category' && $kind !== 'all')
            <div class="fx-scope">
              <label for="scope-{{ $f->id }}">
                {{ $kind === 'road_class' ? 'Road class' : 'Protected area' }}
              </label>
              <select name="faults[{{ $f->id }}][scope]" id="scope-{{ $f->id }}" class="fx-scope-sel">
                <option value="">Choose</option>
                @foreach($f->sanctions as $s)
                  <option value="{{ $s->scope }}" data-amount="{{ $s->amount ?? '' }}"
                          @selected($rec?->scope === $s->scope)>{{ $s->scope }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="fx-sanction" id="sanction-{{ $f->id }}"></div>

          <input type="text" name="faults[{{ $f->id }}][note]" class="fx-note"
                 maxlength="300" placeholder="What was observed, if it needs saying"
                 value="{{ $rec?->note }}">
        </div>

        {{-- The schedule for this fault, read by the script. --}}
        <script type="application/json" class="fx-data" data-fault="{{ $f->id }}">{!! $rows !!}</script>
      </div>
    @endforeach
  </div>

  <div class="fx-foot">
    Nothing is owed until the report is signed and a letter issued. These
    amounts are what the schedule sets, recorded with the inspection.
  </div>
</section>

@push('styles')
<style>
  .fx-block{background:var(--white);padding:24px 26px;margin-bottom:22px;
            box-shadow:var(--shadow-sm);border-left:4px solid var(--danger)}
  .fx-head{display:flex;justify-content:space-between;align-items:flex-start;
           gap:20px;flex-wrap:wrap;margin-bottom:16px}
  .fx-head .desc{margin-bottom:0;max-width:560px}

  .fx-total{text-align:right;flex-shrink:0;padding:10px 16px;background:var(--canvas);
            border-left:3px solid var(--border)}
  .fx-total.live{border-left-color:var(--danger);background:#FDECEA}
  .fx-total span{display:block;font-family:var(--f-head);font-size:9px;font-weight:600;
                 letter-spacing:1px;text-transform:uppercase;color:var(--tertiary)}
  .fx-total b{display:block;font-family:var(--f-head);font-size:22px;font-weight:800;
              color:var(--ink);line-height:1.2;margin-top:2px}
  .fx-total.live b{color:var(--danger)}
  .fx-total em{display:block;font-style:normal;font-size:11px;color:var(--body-text);margin-top:2px}

  .fx-warn{padding:11px 15px;margin-bottom:14px;background:#FFF8E5;
           border-left:3px solid var(--warning);font-size:12.5px;color:#6B4A00}
  .fx-warn strong{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:#8A6D00;margin-bottom:3px}

  .fx-list{border-top:1px solid var(--border)}
  .fx-item{border-bottom:1px solid var(--border);padding:2px 0}
  .fx-item.on{background:#FFF9F8}

  .fx-pick{display:flex;align-items:flex-start;gap:11px;padding:11px 4px;cursor:pointer;
           margin:0;text-transform:none;letter-spacing:0}
  .fx-pick input{margin-top:2px;accent-color:var(--danger);cursor:pointer;flex-shrink:0}
  .fx-code{font-family:var(--f-head);font-size:11px;font-weight:700;color:var(--tertiary);
           min-width:22px;flex-shrink:0;padding-top:1px}
  .fx-title{font-family:var(--f-body);font-size:13px;color:var(--ink);line-height:1.5;
            font-weight:400;text-transform:none;letter-spacing:0}
  .fx-item.on .fx-title{font-weight:600}

  .fx-detail{display:none;padding:0 4px 14px 44px}
  .fx-item.on .fx-detail{display:block}

  .fx-scope{margin-bottom:10px;max-width:340px}
  .fx-scope label{font-size:9.5px;margin-bottom:4px}
  .fx-scope select{font-size:13px;padding:7px 10px}

  .fx-sanction{font-size:12.5px;line-height:1.6;color:var(--body-text);margin-bottom:10px;
               padding:9px 13px;background:var(--white);border-left:3px solid var(--border)}
  .fx-sanction.has{border-left-color:var(--danger)}
  .fx-sanction b{font-family:var(--f-head);color:var(--danger);display:block;
                 font-size:14px;margin-bottom:3px}
  .fx-sanction .liable{font-size:11px;color:var(--tertiary);display:block;margin-top:4px}

  .fx-note{font-size:13px;padding:8px 11px;max-width:100%}

  .fx-foot{margin-top:16px;padding-top:14px;border-top:1px solid var(--border);
           font-size:12px;color:var(--tertiary);line-height:1.6}
</style>
@endpush

@push('scripts')
<script>
/* Faults and what the schedule sets.
   Nothing here raises a fine — the figures show what would be owed once a
   letter issues, so an officer knows on site what is at stake. */
(function () {
  var block = document.querySelector('.fx-block');
  if (!block) return;

  var catEl   = document.getElementById('building_category');
  var totalEl = document.getElementById('fx-amount');
  var countEl = document.getElementById('fx-count');
  var boxEl   = document.getElementById('fx-total');
  var noCat   = document.getElementById('fx-nocat');

  var schedule = {};
  block.querySelectorAll('.fx-data').forEach(function (el) {
    try { schedule[el.dataset.fault] = JSON.parse(el.textContent); } catch (e) {}
  });

  function money(n) {
    return 'FRW ' + n.toLocaleString('en-US');
  }

  /* The sanction that applies: one scoped to every category if there is
     one, otherwise the row matching what was chosen. */
  function resolve(faultId, category, scopeChoice) {
    var rows = schedule[faultId] || [];
    if (!rows.length) return null;

    var all = rows.find(function (r) { return r.kind === 'all'; });
    if (all) return all;

    if (rows[0].kind !== 'building_category') {
      if (!scopeChoice) return null;
      return rows.find(function (r) { return r.scope === scopeChoice; }) || null;
    }

    if (!category) return null;

    var hit = rows.find(function (r) {
      return r.scope.toLowerCase().indexOf(category.toLowerCase()) !== -1;
    });

    return hit || null;
  }

  function refresh() {
    var category = catEl ? catEl.value : '';
    var total = 0, n = 0, needCat = false;

    block.querySelectorAll('.fx-item').forEach(function (item) {
      var id    = item.dataset.fault;
      var check = item.querySelector('.fx-check');
      var out   = document.getElementById('sanction-' + id);
      var sel   = item.querySelector('.fx-scope-sel');

      item.classList.toggle('on', check.checked);

      if (!check.checked) { out.innerHTML = ''; out.className = 'fx-sanction'; return; }

      n++;

      var s = resolve(id, category, sel ? sel.value : null);

      if (!s) {
        out.className = 'fx-sanction';
        if (sel && !sel.value) {
          out.innerHTML = 'Choose the ' + (sel.previousElementSibling
                          ? sel.previousElementSibling.textContent.trim().toLowerCase()
                          : 'scope') + ' to see the sanction.';
        } else {
          out.innerHTML = 'No sanction in the schedule for this category.';
          needCat = !category;
        }
        return;
      }

      out.className = 'fx-sanction has';
      out.innerHTML =
        '<b>' + (s.amount ? money(s.amount) : 'No fine') + '</b>' +
        s.action +
        (s.liable ? '<span class="liable">Liable: ' + s.liable + '</span>' : '');

      if (s.amount) total += s.amount;
    });

    totalEl.textContent = money(total);
    countEl.textContent = n === 0 ? 'no faults selected'
                        : n + (n === 1 ? ' fault selected' : ' faults selected');
    boxEl.classList.toggle('live', n > 0);
    noCat.style.display = needCat ? 'block' : 'none';
  }

  block.addEventListener('change', refresh);
  if (catEl) catEl.addEventListener('change', refresh);
  refresh();
})();
</script>
@endpush
@endif