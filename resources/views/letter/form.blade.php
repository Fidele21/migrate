@extends('layouts.app')
@section('title', $letter ? 'Edit Letter' : 'Draft Letter')

@section('content')

@php $e = $inspection?->entity; @endphp

<div class="page-head">
  <div>
    <div class="crumb">Enforcement correspondence</div>
    <h2>{{ $letter ? 'Edit Letter' : 'Draft Letter' }}</h2>
    <p>{{ $e?->name }} &middot; inspected {{ $inspection?->inspection_date->format('j F Y') }}
       &middot; {{ round((float) $inspection?->compliance, 1) }}% compliance</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('inspection.show', $inspection) }}" class="btn btn-ghost">Back to report</a>
  </div>
</div>

@if($errors->any())
  <div class="callout">
    <b>Please correct the following</b>
    <p style="font-size:14px;font-weight:400">
      @foreach($errors->all() as $err)&bull; {{ $err }}<br>@endforeach
    </p>
  </div>
@endif

<form method="post" action="{{ $action }}">
  @csrf
  @if($letter)@method('PUT')@endif

  <section>
    <h3>Letter type</h3>
    <div class="desc">Determines which paragraphs appear and what the letter requires</div>

    <div class="type-grid">
      @foreach($types as $key => $t)
        <label class="type-card">
          <input type="radio" name="letter_type" value="{{ $key }}"
                 @checked(old('letter_type', $letter?->letter_type ?? 'enforcement') === $key)
                 data-subject="{{ $t['subject'] }}" data-deadline="{{ $t['deadline'] }}">
          <b>{{ $t['name'] }}</b>
          <span class="rw">{{ $t['name_rw'] }}</span>
          @if($t['deadline'])<span class="dl">{{ $t['deadline'] }} days</span>@endif
        </label>
      @endforeach
    </div>
  </section>

  <section>
    <h3>Particulars</h3>
    <div class="desc">These fill the reference block and opening of the letter</div>

    <div class="fgrid">
      <div class="f2">
        <label>Impamvu (subject)</label>
        <input type="text" name="subject" id="subject" required
               value="{{ old('subject', $letter?->subject) }}">
      </div>
      <div>
        <label>Salutation</label>
        <select name="salutation">
          <option value="company" @selected(old('salutation', $letter?->salutation) === 'company')>Bwana/Madamu (organisation)</option>
          <option value="male"    @selected(old('salutation', $letter?->salutation) === 'male')>Bwana</option>
          <option value="female"  @selected(old('salutation', $letter?->salutation) === 'female')>Madamu</option>
        </select>
      </div>
      <div>
        <label>Days allowed</label>
        <input type="number" name="deadline_days" id="deadline" min="1" max="365"
               value="{{ old('deadline_days', $letter?->deadline_days ?? 30) }}">
      </div>
    </div>
  </section>

  <section id="prior-block">
    <h3>Earlier correspondence</h3>
    <div class="desc">Where this letter answers or follows a previous notice</div>

    <div class="fgrid">
      <div>
        <label>Prior reference</label>
        <input type="text" name="prior_reference" placeholder="8210/07.01.14/26"
               value="{{ old('prior_reference', $letter?->prior_reference) }}">
      </div>
      <div>
        <label>Prior letter dated</label>
        <input type="date" name="prior_date"
               value="{{ old('prior_date', $letter?->prior_date?->format('Y-m-d')) }}">
      </div>
      <div>
        <label>Owner replied on</label>
        <input type="date" name="reply_date"
               value="{{ old('reply_date', $letter?->reply_date?->format('Y-m-d')) }}">
      </div>
    </div>
  </section>



  <div class="save-bar">
    <div class="sb-note">The letter is drafted in Kinyarwanda using the unit's standard wording.
      Review the generated document before submitting it for verification.</div>
    <div class="sb-actions">
      <a href="{{ route('inspection.show', $inspection) }}" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-success">{{ $letter ? 'Save Letter' : 'Create Draft' }}</button>
    </div>
  </div>
</form>



<script>
(function () {
  var subject = document.getElementById('subject');
  var deadline = document.getElementById('deadline');
  var prior = document.getElementById('prior-block');

  document.querySelectorAll('input[name=letter_type]').forEach(function (r) {
    r.addEventListener('change', function () {
      if (!subject.value || subject.dataset.auto !== 'no') {
        subject.value = r.dataset.subject;
        subject.dataset.auto = 'yes';
      }
      if (r.dataset.deadline && r.dataset.deadline !== '0') deadline.value = r.dataset.deadline;
      prior.style.display = (r.value === 'enforcement') ? 'none' : '';
    });
  });

  subject.addEventListener('input', function () { subject.dataset.auto = 'no'; });

  var checked = document.querySelector('input[name=letter_type]:checked');
  if (checked) {
    if (!subject.value) subject.value = checked.dataset.subject;
    prior.style.display = (checked.value === 'enforcement') ? 'none' : '';
  }
})();
</script>

@endsection

@push('styles')
<style>
  .type-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
  .type-card{display:block;border:1px solid var(--border);padding:18px;cursor:pointer;
             transition:all .2s;margin:0;text-transform:none;color:inherit}
  .type-card:hover{border-color:var(--primary)}
  .type-card input{position:absolute;opacity:0}
  .type-card:has(input:checked){border-color:var(--primary);background:#E8F4FA;border-width:2px}
  .type-card b{display:block;font-family:var(--f-head);font-size:15px;font-weight:600;color:var(--ink)}
  .type-card .rw{display:block;font-family:var(--f-body);font-size:12.5px;color:var(--body-text);
                 font-style:italic;margin-top:3px;text-transform:none;letter-spacing:0}
  .type-card .dl{display:inline-block;margin-top:8px;font-family:var(--f-head);font-size:10.5px;
                 font-weight:600;letter-spacing:.5px;background:var(--canvas);color:var(--tertiary);
                 padding:2px 9px;text-transform:uppercase}
  .fgrid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
  .fgrid .f2{grid-column:span 2}
  @media(max-width:660px){.fgrid .f2{grid-column:span 1}}
  ol.reqs{margin:0 0 0 20px;font-size:14px;line-height:1.9}
  .save-bar{position:sticky;bottom:0;background:var(--white);padding:18px 22px;
    display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;
    box-shadow:0 -3px 16px rgba(0,0,0,.07)}
  .sb-note{font-size:13px;color:var(--body-text);flex:1;min-width:220px}
  .sb-actions{display:flex;gap:10px}
</style>
@endpush
