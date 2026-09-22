@extends('layouts.app')
@section('title', 'Propose a Fine')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Enforcement</div>
    <h2>Propose a Fine</h2>
    <p>A proposal becomes payable only once a Senior or Chief Inspector confirms it.</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('fine.index') }}" class="btn btn-ghost">Cancel</a>
  </div>
</div>

@if($errors->any())
  <div class="callout"><b>Please correct the following</b>
    <p style="font-size:14px;font-weight:400">
      @foreach($errors->all() as $e)&bull; {{ $e }}<br>@endforeach</p></div>
@endif

<form method="post" action="{{ route('fine.store') }}">
  @csrf
  @if($letter)<input type="hidden" name="document_id" value="{{ $letter->id }}">@endif

  <section>
    <h3>Inspection</h3>
    <div class="desc">The fine attaches to the inspection that gave rise to it</div>

    @if($inspection)
      <input type="hidden" name="inspection_id" value="{{ $inspection->id }}">
      <div class="picked">
        <strong>{{ $inspection->entity->name }}</strong><br>
        <span class="mini">{{ $inspection->entity->district }} &middot;
          inspected {{ $inspection->inspection_date->format('j F Y') }} &middot;
          {{ round((float) $inspection->compliance, 1) }}% compliance</span>
      </div>
    @else
      <label>Choose an inspection</label>
      <select name="inspection_id" required>
        <option value="">Select…</option>
        @foreach(\App\Models\Inspection::with('entity')->where('status','completed')
                  ->orderByDesc('inspection_date')->limit(100)->get() as $i)
          <option value="{{ $i->id }}">
            {{ $i->entity->name }} — {{ $i->inspection_date->format('j M Y') }} — {{ round((float) $i->compliance, 1) }}%
          </option>
        @endforeach
      </select>
    @endif
  </section>

  <section>
    <h3>The penalty</h3>
    <div class="desc">Amount, grounds and the date by which it must be settled</div>

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <div><label>Amount (RWF)</label>
        <input type="number" name="amount" step="1" min="1" required value="{{ old('amount') }}"></div>
      <div><label>Due date</label>
        <input type="date" name="due_date" min="{{ date('Y-m-d', strtotime('+1 day')) }}"
               value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}"></div>
      <div style="grid-column:1/-1"><label>Legal basis</label>
        <input type="text" name="legal_basis" value="{{ old('legal_basis') }}"
               placeholder="Iteka rya Minisitiri No 03/Cab.M/019, igika cya…"></div>
      <div style="grid-column:1/-1"><label>Reason</label>
        <textarea name="reason" rows="5" required
                  placeholder="What was found, and why a penalty is proposed">{{ old('reason') }}</textarea></div>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px">
      <button type="submit" class="btn btn-success">Propose the Fine</button>
      <a href="{{ route('fine.index') }}" class="btn btn-ghost">Cancel</a>
    </div>
  </section>
</form>



@endsection

@push('styles')
<style>
  .picked{background:var(--canvas);padding:16px 18px;border-left:3px solid var(--primary)}
  .mini{font-size:12px;color:var(--tertiary)}
</style>
@endpush
