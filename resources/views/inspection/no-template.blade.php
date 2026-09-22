@extends('layouts.app')
@section('title', 'Checklist not configured')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['group'] }} &middot; {{ $type['name'] }}</div>
    <h2>Checklist not configured</h2>
  </div>
  <div class="head-actions">
    <a href="{{ route('type.show', $type['code']) }}" class="btn btn-ghost">Back</a>
  </div>
</div>

<section>
  <div class="empty">
    <b>No published checklist for this category</b>
    <span style="display:block;max-width:520px;margin:8px auto 0;line-height:1.6">
      An inspection cannot be recorded until the checklist sections, items and scoring
      weights for {{ $type['name'] }} have been agreed with the inspection unit and published.
    </span>
    <div style="margin-top:20px">
      <a href="{{ route('type.show', 'petrol') }}" class="btn btn-primary">See a configured example</a>
    </div>
  </div>
</section>

@endsection
