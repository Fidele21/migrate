@extends('layouts.app')
@section('title', $name . ' — ' . $type['name'])

@section('content')

@php $q = collect($query)->except('export')->all(); @endphp



<div class="a-head">
  <div>
    <div class="a-crumb">
      <a href="{{ route('type.show', array_merge(['code' => $type['code']], $q)) }}">{{ $type['name'] }}</a>
      &rsaquo; {{ $name }}
    </div>
    <h2>{{ $name }}</h2>
    <p>{{ $blurb }} &middot; {{ $scope }} &middot; {{ $period }}</p>
  </div>
  <div class="a-actions">
    @can('export.excel')
      <a href="{{ route('analysis.group', array_merge(['code' => $type['code'], 'group' => $group], $q, ['export' => 'csv'])) }}"
         class="btn btn-ghost">Excel</a>
    @endcan
    @can('export.pdf')
      <a href="{{ route('analysis.group', array_merge(['code' => $type['code'], 'group' => $group], $q, ['export' => 'pdf'])) }}"
         target="_blank" class="btn btn-ghost">PDF</a>
    @endcan
    <a href="{{ route('type.show', array_merge(['code' => $type['code']], $q)) }}" class="btn btn-primary">Back</a>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi">
    <span class="k-label">Criteria</span>
    <b class="k-value">{{ $summary['criteria'] }}</b>
    <span class="k-note">in this grouping</span>
  </div>
  <div class="kpi">
    <span class="k-label">Assessments</span>
    <b class="k-value">{{ number_format($summary['assessed']) }}</b>
    <span class="k-note">answers recorded</span>
  </div>
  <div class="kpi bad">
    <span class="k-label">Failures</span>
    <b class="k-value">{{ number_format($summary['failed']) }}</b>
    <span class="k-note">items not complied with</span>
  </div>
  <div class="kpi accent">
    <span class="k-label">Compliance</span>
    <b class="k-value">{{ $summary['compliance'] }}<i>%</i></b>
    <span class="k-note">across this grouping</span>
  </div>
  <div class="kpi bad">
    <span class="k-label">Weakest criterion</span>
    <b class="k-value">{{ $summary['worst'] }}<i>%</i></b>
    <span class="k-note">lowest single requirement</span>
  </div>
</div>

<section class="panel">
  <div class="p-head">
    <div>
      <h3>Requirements</h3>
      <div class="p-desc">Click any requirement to see the premises that failed it</div>
    </div>
  </div>

  <div class="tbl-wrap">
    <table class="crit">
      <thead>
        <tr>
          <th style="width:52px">Sect.</th>
          <th>Requirement</th>
          <th style="text-align:right;width:78px">Assessed</th>
          <th style="text-align:right;width:78px">Failed</th>
          <th style="width:150px">Compliance</th>
          <th style="text-align:right;width:96px"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $i)
          @php
            $pct  = (int) $i->compliance;
            $tone = $pct >= 98 ? 'good' : ($pct >= 70 ? 'weak' : 'poor');
          @endphp
          <tr class="{{ $i->failed > 0 ? 'has-fail' : '' }}">
            <td class="sect">{{ $i->section_number }}</td>
            <td>
              <div class="c-label">{{ rtrim($i->label, '. ') }}</div>
              <div class="c-sect">{{ $i->section_title }}</div>
            </td>
            <td class="num">{{ $i->assessed }}</td>
            <td class="num {{ $i->failed > 0 ? 'fail' : '' }}">{{ $i->failed }}</td>
            <td>
              <div class="c-bar"><div class="c-fill {{ $tone }}" style="width:{{ $pct }}%"></div></div>
              <div class="c-pct">{{ $pct }}%</div>
            </td>
            <td class="num">
              @if($i->failed > 0)
                <a href="{{ route('analysis.item', array_merge(['code' => $type['code'], 'item' => $i->item_id], $q)) }}"
                   class="btn btn-ghost btn-sm">{{ $i->failed }} premises</a>
              @else
                <span class="all-ok">All complied</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;color:var(--tertiary);padding:34px">
            No criteria assessed in this selection.</td></tr>
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
  .a-head h2{font-family:var(--f-head);font-size:26px;font-weight:700;color:var(--ink);line-height:1.2}
  .a-head p{font-size:14px;color:var(--body-text);margin-top:4px}
  .a-actions{display:flex;gap:9px;flex-wrap:wrap}

  .kpi-row{display:grid;gap:14px;grid-template-columns:repeat(2,1fr);margin-bottom:20px}
  .kpi{background:var(--white);box-shadow:var(--shadow-sm);padding:16px 18px;
       border-top:3px solid var(--primary)}
  .kpi.bad{border-top-color:var(--danger)}
  .kpi.accent{border-top-color:var(--secondary)}
  .k-label{display:block;font-family:var(--f-head);font-size:9.5px;font-weight:600;
           letter-spacing:1px;text-transform:uppercase;color:var(--tertiary);min-height:24px}
  .k-value{display:block;font-family:var(--f-head);font-size:28px;font-weight:800;
           color:var(--ink);line-height:1.05;margin-top:4px}
  .k-value i{font-style:normal;font-size:15px;color:var(--tertiary)}
  .kpi.bad .k-value{color:var(--danger)}
  .kpi.accent .k-value{color:var(--primary)}
  .k-note{display:block;font-size:11px;color:var(--body-text);margin-top:3px}

  .panel{background:var(--white);box-shadow:var(--shadow-sm);padding:22px 24px}
  .p-head h3{font-family:var(--f-head);font-size:15px;font-weight:600;color:var(--ink);margin:0}
  .p-desc{font-size:12.5px;color:var(--body-text);margin:2px 0 16px}

  table.crit td{vertical-align:middle;padding:11px 12px}
  tr.has-fail{background:#FFFAF9}
  .sect{font-family:var(--f-head);font-weight:700;color:var(--tertiary);text-align:center}
  .c-label{font-size:13.5px;color:var(--ink);line-height:1.45}
  .c-sect{font-size:11px;color:var(--tertiary);margin-top:2px}
  .c-bar{height:6px;background:var(--canvas);overflow:hidden;margin-bottom:4px}
  .c-fill{height:100%}
  .c-fill.good{background:var(--success)}
  .c-fill.weak{background:var(--warning)}
  .c-fill.poor{background:var(--danger)}
  .c-pct{font-family:var(--f-head);font-size:11.5px;font-weight:700;color:var(--ink)}
  td.num.fail{color:var(--danger);font-weight:800}
  .all-ok{font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.5px;
          text-transform:uppercase;color:var(--success)}

  @media(min-width:700px){.kpi-row{grid-template-columns:repeat(5,1fr)}}
  @media(max-width:699px){
    .a-head h2{font-size:21px}
    .panel{padding:18px 16px}
  }
</style>
@endpush
