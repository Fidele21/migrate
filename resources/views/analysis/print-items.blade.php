<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $type['name'] }} — {{ $name }} — City of Kigali</title>
<style>
  @page{size:A4 portrait;margin:14mm 12mm}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
       color:#333;font-size:10.5px;line-height:1.45;background:#fff;padding:18px}
  .lh{display:flex;align-items:center;gap:14px;padding-bottom:12px;
      border-bottom:3px solid #34A8DB;margin-bottom:16px}
  .mark{width:44px;height:44px;background:#34A8DB;display:flex;align-items:center;
        justify-content:center;font-weight:800;font-size:14px;color:#fff}
  .rep{font-size:8.5px;letter-spacing:1.6px;color:#CDB896;font-weight:700}
  .city{font-size:17px;font-weight:800;color:#34A8DB;line-height:1.15}
  .unit{font-size:10px;color:#666}
  h1{font-size:15px;color:#34A8DB;text-align:center;margin-bottom:3px}
  .sub{text-align:center;font-size:9.5px;color:#666;margin-bottom:14px}
  table{width:100%;border-collapse:collapse;font-size:9.5px}
  th{background:#34A8DB;color:#fff;text-align:left;padding:6px 7px;font-size:8.5px;
     text-transform:uppercase;letter-spacing:.5px;font-weight:700;
     -webkit-print-color-adjust:exact;print-color-adjust:exact}
  td{padding:5px 7px;border-bottom:1px solid #E0E0E0;vertical-align:top}
  tr:nth-child(even) td{background:#FAFBFC}
  td.num{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
  .bad{color:#C62828}
  .foot{margin-top:16px;padding-top:9px;border-top:1px solid #E0E0E0;
        font-size:8.5px;color:#666;display:flex;justify-content:space-between}
  .toolbar{position:fixed;top:10px;right:10px;display:flex;gap:8px}
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

<h1>{{ $type['name'] }} &mdash; {{ $name }}</h1>
<div class="sub">{{ $scope }} &middot; {{ $period }} &middot; generated {{ now()->format('j F Y, H:i') }}</div>

<table>
  <thead>
    <tr>
      <th style="width:32px">Sect.</th>
      <th>Requirement</th>
      <th style="width:56px;text-align:right">Assessed</th>
      <th style="width:50px;text-align:right">Failed</th>
      <th style="width:46px;text-align:right">N/A</th>
      <th style="width:64px;text-align:right">Compliance</th>
    </tr>
  </thead>
  <tbody>
    @foreach($items as $i)
      <tr>
        <td>{{ $i->section_number }}</td>
        <td>{{ rtrim($i->label, '. ') }}<br><span style="color:#999;font-size:8.5px">{{ $i->section_title }}</span></td>
        <td class="num">{{ $i->assessed }}</td>
        <td class="num {{ $i->failed > 0 ? 'bad' : '' }}">{{ $i->failed }}</td>
        <td class="num">{{ $i->not_applicable }}</td>
        <td class="num">{{ $i->compliance }}%</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="foot">
  <span>City of Kigali &mdash; Digital Inspection Platform</span>
  <span>Prepared by {{ auth()->user()->name }}</span>
</div>

<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
</body>
</html>
