@extends('layouts.app')
@section('title', $parent ? 'Pass on a share' : 'Assign work')

@php
  $parentType = $parent
      ? \App\Http\Controllers\InspectionTypeController::resolve($parent->type_code)
      : null;
@endphp

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Inspection &middot; Assignments</div>
    <h2>{{ $parent ? 'Pass on a share' : 'Assign work' }}</h2>
  </div>
</div>

@if($errors->any())
  <div class="alert-bad">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif

@if($parent)
  <div class="from-block">
    <div>
      <b>{{ $parentType['name'] }}</b> &mdash; your share is {{ $parent->shareEach() }},
      of which <b>{{ $remaining }}</b> {{ $remaining === 1 ? 'is' : 'are' }} still yours to pass on or do yourself.
    </div>
    <div class="mini">
      Given by {{ $parent->assigner->name ?? '—' }} &middot;
      {{ $parent->starts_on->format('j M') }} &ndash; {{ $parent->due_on->format('j M Y') }}
    </div>
  </div>
@endif

@if($candidates->isEmpty())
  <div class="alert-bad">
    There is nobody for you to assign work to. A Director assigns to the
    inspectors of their own district; a Senior Inspector to Directors; a
    Chief Inspector to Senior Inspectors and Directors.
  </div>
@else

<form method="post" action="{{ route('assignment.store') }}" class="asg-form">
  @csrf
  @if($parent)<input type="hidden" name="parent_id" value="{{ $parent->id }}">@endif

  <div class="step">
    <div class="step-bar"><span class="step-no">1</span> The work</div>
    <div class="step-body">
      <div class="fgrid">

        @if($parent)
          <div>
            <label class="fl">Inspection category</label>
            <input class="fi" type="text" value="{{ $parentType['name'] }}" disabled>
            <div class="hint">Fixed by the assignment this comes from.</div>
          </div>
        @else
          <div>
            <label class="fl">Inspection category</label>
            <select class="fi" name="type_code" required>
              <option value="">Choose the category</option>
              @foreach($types as $groupLabel => $group)
                <optgroup label="{{ $groupLabel }}">
                  @foreach($group as $code => $name)
                    <option value="{{ $code }}" @selected(old('type_code') === $code)>{{ $name }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
          </div>
        @endif

        <div>
          <label class="fl">How many to inspect</label>
          <input class="fi" type="number" name="quantity" min="1"
                 max="{{ $parent ? $remaining : 500 }}" required
                 value="{{ old('quantity', $parent ? $remaining : '') }}">
          <div class="hint">
            @if($parent)
              At most {{ $remaining }} &mdash; what is left of your share.
            @else
              Where several officers of the same rank hold it, this divides evenly between them.
            @endif
          </div>
        </div>

        <div>
          <label class="fl">Starts on</label>
          <input class="fi" type="date" name="starts_on" required
                 value="{{ old('starts_on', $parent ? $parent->starts_on->format('Y-m-d') : date('Y-m-d')) }}"
                 @if($parent) min="{{ $parent->starts_on->format('Y-m-d') }}" max="{{ $parent->due_on->format('Y-m-d') }}" @endif>
        </div>

        <div>
          <label class="fl">Due on</label>
          <input class="fi" type="date" name="due_on" required
                 value="{{ old('due_on', $parent ? $parent->due_on->format('Y-m-d') : '') }}"
                 @if($parent) min="{{ $parent->starts_on->format('Y-m-d') }}" max="{{ $parent->due_on->format('Y-m-d') }}" @endif>
          @if($parent)<div class="hint">Must sit inside {{ $parent->starts_on->format('j M') }} &ndash; {{ $parent->due_on->format('j M Y') }}.</div>@endif
        </div>

      </div>

      <div style="margin-top:16px">
        <label class="fl">What to do</label>
        <textarea class="fi" name="instructions" rows="4"
                  placeholder="Anything the officers should know — an area to focus on, a particular concern, what to look for">{{ old('instructions', $parent?->instructions) }}</textarea>
      </div>
    </div>
  </div>

  <div class="step">
    <div class="step-bar"><span class="step-no">2</span> Who does it
      <span class="opt">Choose one, or several</span></div>
    <div class="step-body">
      <div class="hint" style="margin-bottom:14px">
        Inspectors chosen together work the target between them, and each is
        credited with what they achieve together. Officers of senior rank each
        take an equal share and answer for their own.
      </div>

      <div class="who-grid">
        @foreach($candidates as $c)
          <label class="who-pick">
            <input type="checkbox" name="members[]" value="{{ $c->id }}"
                   @checked(in_array($c->id, old('members', [])))>
            <span>
              <b>{{ $c->name }}</b>
              <em>{{ $c->roles->pluck('name')->implode(', ') }}</em>
            </span>
          </label>
        @endforeach
      </div>
    </div>
  </div>

  <div class="save-bar">
    <div class="sb-note">An officer may hold only one assignment of a category over a given period.</div>
    <div class="sb-actions">
      <a href="{{ $parent ? route('assignment.show', $parent) : route('assignment.index') }}" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-success">{{ $parent ? 'Pass on' : 'Assign' }}</button>
    </div>
  </div>
</form>

@endif

@endsection

@push('styles')
<style>
  .fgrid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
  .fl{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
      letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:6px}
  .hint{font-size:11.5px;color:var(--tertiary);margin-top:5px;line-height:1.5}
  .fi:disabled{background:var(--canvas);color:var(--tertiary)}

  .from-block{background:#f0f7ff;border-left:4px solid var(--primary);padding:14px 18px;
              border-radius:6px;margin-bottom:20px;font-size:13.5px;line-height:1.6}
  .from-block .mini{color:var(--tertiary);font-size:11.5px;margin-top:4px}

  .who-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
  .who-pick{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;
            border:1px solid var(--border);border-radius:8px;cursor:pointer;
            transition:border-color .15s,background .15s}
  .who-pick:hover{border-color:var(--primary);background:#f7fbff}
  .who-pick input{margin-top:3px}
  .who-pick b{display:block;font-size:13.5px}
  .who-pick em{display:block;font-style:normal;font-size:11.5px;color:var(--tertiary);margin-top:2px}

  .alert-bad{background:#fde8e8;color:#b71c1c;padding:14px 18px;border-radius:8px;
             margin-bottom:20px;font-size:13.5px;line-height:1.6}
</style>
@endpush