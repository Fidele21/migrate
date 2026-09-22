@extends('layouts.app')
@section('title', 'Non-compliant premises')

@section('content')

@php
  use App\Support\ComplianceBand;
  $q = collect($query)->except('export')->all();
@endphp

<div class="a-head">
  <div>
    <div class="a-crumb">
      <a href="{{ route('type.show', array_merge(['code' => $type['code']], $q)) }}">{{ $type['name'] }}</a>
      &rsaquo; Section {{ $item->section_number }} &rsaquo; Requirement
    </div>
    <h2>{{ rtrim($item->label, '. ') }}</h2>
    <p>{{ $item->section_title }} &middot; {{ $scope }} &middot; {{ $period }}</p>
  </div>
  <div class="a-actions">
    @can('export.excel')
      <a href="{{ route('analysis.item', array_merge(['code' => $type['code'], 'item' => $item->id], $q, ['export' => 'csv'])) }}"
         class="btn btn-ghost">Excel</a>
    @endcan
    @can('export.pdf')
      <a href="{{ route('analysis.item', array_merge(['code' => $type['code'], 'item' => $item->id], $q, ['export' => 'pdf'])) }}"
         target="_blank" class="btn btn-ghost">PDF</a>
    @endcan
    <a href="{{ url()->previous() }}" class="btn btn-primary">Back</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi">
    <span class="k-label">Assessed</span>
    <b class="k-value">{{ $summary['assessed'] }}</b>
    <span class="k-note">premises judged on this</span>
  </div>
  <div class="kpi bad">
    <span class="k-label">Not complying</span>
    <b class="k-value">{{ $summary['failed'] }}</b>
    <span class="k-note">require action</span>
  </div>
  <div class="kpi good">
    <span class="k-label">Complying</span>
    <b class="k-value">{{ $summary['passed'] }}</b>
    <span class="k-note">no action needed</span>
  </div>
  <div class="kpi accent">
    <span class="k-label">Compliance</span>
    <b class="k-value">{{ $summary['compliance'] }}<i>%</i></b>
    <span class="k-note">on this requirement</span>
  </div>
</div>

<section class="panel">
  <div class="p-head">
    <div>
      <h3>Premises not complying</h3>
      <div class="p-desc">{{ count($failing) }} to be visited or written to. Ordered by overall compliance, worst first.</div>
    </div>
  </div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:38px">#</th>
          <th>Premises</th>
          <th>UPI</th>
          <th>Owner</th>
          <th>Contact</th>
          <th>Location</th>
          <th>Inspected</th>
          <th style="text-align:right">Overall</th>
          <th>Consequence</th>
          <th style="text-align:right"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($failing as $n => $r)
          @php $band = ComplianceBand::for((float) $r->compliance); @endphp
          <tr>
            <td style="color:var(--tertiary)">{{ $n + 1 }}</td>
            <td>
              <strong>{{ $r->name }}</strong>
              @if($r->case_reference)<br><span class="mini">{{ $r->case_reference }}</span>@endif
            </td>
            <td style="font-size:12px">{{ $r->upi ?: '—' }}</td>
            <td style="font-size:12.5px">{{ $r->owner ?: '—' }}</td>
            <td style="font-size:12px">
              {{ $r->telephone }}@if($r->telephone && $r->email)<br>@endif{{ $r->email }}
              @if(! $r->telephone && ! $r->email)—@endif
            </td>
            <td style="font-size:12px">{{ collect([$r->district, $r->sector])->filter()->implode(' · ') }}</td>
            <td style="font-size:12.5px;white-space:nowrap">{{ $r->inspection_date }}</td>
            <td class="num">{{ round((float) $r->compliance, 1) }}%</td>
            <td>
              <span class="band-pill" style="--c:{{ $band['colour'] }}">{{ $band['short'] }}</span>
            </td>
            <td class="num">
              <a href="{{ route('inspection.show', $r->inspection_id) }}" class="btn btn-ghost btn-sm">Report</a>
            </td>
          </tr>
          @if($r->comment)
            <tr class="cmt-row">
              <td></td>
              <td colspan="9"><span class="cmt">Inspector's note: {{ $r->comment }}</span></td>
            </tr>
          @endif
        @empty
          <tr><td colspan="10" style="text-align:center;color:var(--success);padding:40px">
            <strong style="font-family:var(--f-head);font-size:16px">Every premises complied with this requirement</strong>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>



@endsection

@push('styles')
<style>
  .a-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;
          flex-wrap:wrap;margin-bottom:22px}
  .a-crumb{font-family:var(--f-head);font-size:11px;font-weight:600;letter-spacing:.8px;
           text-transform:uppercase;color:var(--tertiary);margin-bottom:5px}
  .a-crumb a{color:var(--primary);text-decoration:none}
  .a-head h2{font-family:var(--f-head);font-size:23px;font-weight:700;color:var(--ink);
             line-height:1.3;max-width:820px}
  .a-head p{font-size:14px;color:var(--body-text);margin-top:4px}
  .a-actions{display:flex;gap:9px;flex-wrap:wrap}

  .kpi-row{display:grid;gap:14px;grid-template-columns:repeat(2,1fr);margin-bottom:20px}
  .kpi{background:var(--white);box-shadow:var(--shadow-sm);padding:16px 18px;
       border-top:3px solid var(--primary)}
  .kpi.bad{border-top-color:var(--danger)}
  .kpi.good{border-top-color:var(--success)}
  .kpi.accent{border-top-color:var(--secondary)}
  .k-label{display:block;font-family:var(--f-head);font-size:9.5px;font-weight:600;
           letter-spacing:1px;text-transform:uppercase;color:var(--tertiary);min-height:24px}
  .k-value{display:block;font-family:var(--f-head);font-size:28px;font-weight:800;
           color:var(--ink);line-height:1.05;margin-top:4px}
  .k-value i{font-style:normal;font-size:15px;color:var(--tertiary)}
  .kpi.bad .k-value{color:var(--danger)}
  .kpi.good .k-value{color:var(--success)}
  .kpi.accent .k-value{color:var(--primary)}
  .k-note{display:block;font-size:11px;color:var(--body-text);margin-top:3px}

  .panel{background:var(--white);box-shadow:var(--shadow-sm);padding:22px 24px}
  .p-head h3{font-family:var(--f-head);font-size:15px;font-weight:600;color:var(--ink);margin:0}
  .p-desc{font-size:12.5px;color:var(--body-text);margin:2px 0 16px}

  .band-pill{display:inline-block;padding:3px 10px;font-family:var(--f-head);font-size:10px;
             font-weight:600;letter-spacing:.4px;text-transform:uppercase;
             background:var(--c);color:#fff}
  .mini{font-size:11px;color:var(--tertiary)}
  tr.cmt-row td{border-bottom:1px solid var(--border);padding-top:0}
  .cmt{font-size:12px;color:var(--body-text);font-style:italic}

  @media(min-width:700px){.kpi-row{grid-template-columns:repeat(4,1fr)}}
  @media(max-width:699px){
    .a-head h2{font-size:19px}
    .panel{padding:18px 16px}
  }
</style>
@endpush
