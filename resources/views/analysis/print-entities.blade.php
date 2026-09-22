<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Premises not complying — City of Kigali</title>
<style>
  @page{size:A4 landscape;margin:12mm 10mm}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
       color:#333;font-size:10px;line-height:1.45;background:#fff;padding:16px}
  .lh{display:flex;align-items:center;gap:14px;padding-bottom:12px;
      border-bottom:3px solid #34A8DB;margin-bottom:14px}
  .mark{width:42px;height:42px;background:#34A8DB;display:flex;align-items:center;
        justify-content:center;font-weight:800;font-size:13px;color:#fff}
  .rep{font-size:8px;letter-spacing:1.6px;color:#CDB896;font-weight:700}
  .city{font-size:16px;font-weight:800;color:#34A8DB;line-height:1.15}
  .unit{font-size:9.5px;color:#666}
  h1{font-size:14px;color:#34A8DB;text-align:center;margin-bottom:3px}
  .req{text-align:center;font-size:11px;color:#333;font-weight:600;margin-bottom:3px}
  .sub{text-align:center;font-size:9px;color:#666;margin-bottom:13px}
  table{width:100%;border-collapse:collapse;font-size:9px}
  th{background:#34A8DB;color:#fff;text-align:left;padding:5px 6px;font-size:8px;
     text-transform:uppercase;letter-spacing:.4px;font-weight:700;
     -webkit-print-color-adjust:exact;print-color-adjust:exact}
  td{padding:4px 6px;border-bottom:1px solid #E0E0E0;vertical-align:top}
  tr:nth-child(even) td{background:#FAFBFC}
  td.num{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
  .band{display:inline-block;padding:1px 6px;font-size:8px;font-weight:700;color:#fff;
        -webkit-print-color-adjust:exact;print-color-adjust:exact}
  .foot{margin-top:14px;padding-top:8px;border-top:1px solid #E0E0E0;
        font-size:8px;color:#666;display:flex;justify-content:space-between}
  .toolbar{position:fixed;top:10px;right:10px}
  .toolbar button{padding:8px 15px;border:none;background:#34A8DB;color:#fff;
                  font:600 12px system-ui;cursor:pointer}
  @media print{.toolbar{display:none}body{padding:0}}
</style>
</head>
<body>

<div class="toolbar"><button onclick="window.print()">Save as PDF</button></div>

<div class="lh">
  <div class="mark">CoK</div>
  <div>
    <div class="rep">REPUBLIC OF RWANDA</div>
    <div class="city">CITY OF KIGALI</div>
    <div class="unit">Directorate of Inspection</div>
  </div>
</div>

<h1>{{ $type['name'] }} &mdash; premises not complying</h1>
<div class="req">{{ rtrim($item->label, '. ') }}</div>
<div class="sub">
  Section {{ $item->section_number }}: {{ $item->section_title }} &middot;
  {{ $scope }} &middot; {{ $period }} &middot;
  {{ count($rows) }} {{ Str::plural('premises', count($rows)) }} &middot;
  generated {{ now()->format('j F Y, H:i') }}
</div>

<table>
  <thead>
    <tr>
      <th style="width:24px">#</th>
      <th style="width:78px">Case ref</th>
      <th>Premises</th>
      <th style="width:74px">UPI</th>
      <th style="width:100px">Owner</th>
      <th style="width:76px">Telephone</th>
      <th style="width:64px">District</th>
      <th style="width:62px">Sector</th>
      <th style="width:60px">Inspected</th>
      <th style="width:48px;text-align:right">Overall</th>
      <th style="width:78px">Consequence</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $n => $r)
      @php $band = \App\Support\ComplianceBand::for((float) $r->compliance); @endphp
      <tr>
        <td>{{ $n + 1 }}</td>
        <td>{{ $r->case_reference ?: '—' }}</td>
        <td><strong>{{ $r->name }}</strong></td>
        <td>{{ $r->upi ?: '—' }}</td>
        <td>{{ $r->owner ?: '—' }}</td>
        <td>{{ $r->telephone ?: '—' }}</td>
        <td>{{ $r->district ?: '—' }}</td>
        <td>{{ $r->sector ?: '—' }}</td>
        <td>{{ $r->inspection_date }}</td>
        <td class="num">{{ round((float) $r->compliance, 1) }}%</td>
        <td><span class="band" style="background:{{ $band['colour'] }}">{{ $band['short'] }}</span></td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="foot">
  <span>City of Kigali &mdash; Digital Inspection Platform</span>
  <span>Prepared by {{ auth()->user()->name }} &middot; {{ now()->format('j F Y, H:i') }}</span>
</div>

<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
</body>
</html>
