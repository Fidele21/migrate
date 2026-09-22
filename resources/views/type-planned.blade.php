@extends('layouts.app')
@section('title', $type['name'])

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">{{ $type['group'] }}</div>
    <h2>{{ $type['name'] }}</h2>
    <p>{{ $type['blurb'] }}</p>
  </div>
</div>

<section>
  <div class="empty">
    <span class="pill weak" style="margin-bottom:14px;display:inline-block">Planned</span>
    <b>This inspection category is not yet configured</b>
    <span style="display:block;max-width:520px;margin:8px auto 0;line-height:1.6">
      The platform is built so that adding a category is a configuration change rather than
      new code. Every dashboard, filter, chart and record view on this site is generated from
      one shared template.
    </span>
    <div style="margin-top:22px;display:flex;gap:9px;justify-content:center;flex-wrap:wrap">
      <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
      <a href="{{ route('type.show','petrol') }}" class="btn btn-ghost">See a configured example</a>
    </div>
  </div>
</section>

<section>
  <h3>What this category requires</h3>
  <div class="desc">Four inputs, none of which are code</div>
  <div class="tbl-wrap">
    <table>
      <tbody>
        <tr><td><strong>Checklist sections and items</strong></td><td>Agreed with the inspection unit</td></tr>
        <tr><td><strong>Scoring weights</strong></td><td>Ratified before any report is formally issued</td></tr>
        <tr><td><strong>Report and letter templates</strong></td><td>The official CoK documents for this category</td></tr>
        <tr><td><strong>Role permissions</strong></td><td>Who may inspect, verify and sign for this category</td></tr>
      </tbody>
    </table>
  </div>
</section>

<footer>
  City of Kigali &mdash; Digital Inspection Platform &middot; Development build
</footer>

@endsection
