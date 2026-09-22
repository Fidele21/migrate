<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $type['name'] }} — Inspection Register — City of Kigali</title>
<style>
  @page{size:A4 landscape;margin:14mm 12mm}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
       color:#12161D;font-size:10.5px;line-height:1.45;background:#fff;padding:18px}

  .letterhead{display:flex;align-items:center;gap:14px;padding-bottom:12px;
              border-bottom:3px solid #0033A0;margin-bottom:16px}
  .mark{width:44px;height:44px;border-radius:7px;background:#F2B705;display:flex;
        align-items:center;justify-content:center;font-weight:800;font-size:15px;color:#0033A0}
  .rep{font-size:8.5px;letter-spacing:.16em;color:#606874;font-weight:700}
  .city{font-size:17px;font-weight:800;color:#0033A0;line-height:1.15}
  .unit{font-size:10px;color:#606874}

  h1{font-size:15px;color:#0033A0;text-align:center;margin-bottom:3px}
  .sub{text-align:center;font-size:9.5px;color:#606874;margin-bottom:12px}

  .filters{background:#F4F6F9;border-radius:6px;padding:8px 12px;margin-bottom:12px;
           font-size:9.5px;color:#3A414C;display:flex;gap:18px;flex-wrap:wrap}
  .filters b{color:#0033A0}

  table{width:100%;border-collapse:collapse;font-size:9.5px}
  th{background:#0033A0;color:#fff;text-align:left;padding:6px 7px;font-size:8.5px;
     text-transform:uppercase;letter-spacing:.05em;font-weight:700;
     -webkit-print-color-adjust:exact;print-color-adjust:exact}
  td{padding:5px 7px;border-bottom:1px solid #E4E7EC;vertical-align:middle}
  tr:nth-child(even) td{background:#FAFBFC}
  td.num{text-align:right;font-variant-numeric:tabular-nums;font-weight:700}
  td.ctr{text-align:center;font-weight:700}

  .band{display:inline-block;padding:1px 6px;border-radius:9px;font-size:8.5px;font-weight:700;
        -webkit-print-color-adjust:exact;print-color-adjust:exact}
  .band.good{background:#E4F5EA;color:#00612B}
  .band.fair{background:#EDF6E0;color:#3F6B12}
  .band.weak{background:#FFF4D6;color:#8A6D00}
  .band.poor{background:#FDE8E6;color:#C0362C}

  .foot{margin-top:16px;padding-top:9px;border-top:1px solid #E4E7EC;
        font-size:8.5px;color:#606874;display:flex;justify-content:space-between}

  .toolbar{position:fixed;top:10px;right:10px;display:flex;gap:8px}
  .toolbar button{padding:8px 15px;border:none;border-radius:6px;background:#0033A0;color:#fff;
                  font:600 12px system-ui;cursor:pointer}
  .toolbar button.alt{background:#E9ECEF;color:#12161D}
  @media print{.toolbar{display:none}body{padding:0}}
</style>
</head>
<body>

<div class="toolbar">
  <button onclick="window.print()">Save as PDF / Print</button>
  <button class="alt" onclick="window.close()">Close</button>
</div>

<div class="letterhead">
  <div class="mark">CoK</div>
  <div>
    <div class="rep">REPUBLIC OF RWANDA</div>
    <div class="city">CITY OF KIGALI</div>
    <div class="unit">Directorate of Inspection</div>
  </div>
</div>

<h1>{{ $type['name'] }} &mdash; Inspection Register</h1>
<div class="sub">
  {{ $rows->count() }} {{ Str::plural('premises', $rows->count()) }} &middot;
  Generated {{ now()->format('j F Y \a\t H:i') }}
</div>

@if(count($filters))
<div class="filters">
  @foreach($filters as $label => $value)
    <span><b>{{ $label }}:</b> {{ $value }}</span>
  @endforeach
</div>
@endif

<table>
  <thead>
    <tr>
      <th style="width:26px">#</th>
      <th style="width:88px">UPI</th>
      <th style="width:72px">Zoning</th>
      <th style="width:120px">Owner</th>
      <th>{{ $type['name'] }} Name</th>
      <th style="width:70px">District</th>
      <th style="width:66px">Sector</th>
      <th style="width:70px">Date</th>
      <th style="width:62px;text-align:right">Compliance</th>
      <th style="width:56px;text-align:center">Inspections</th>
      <th style="width:96px">Deliberation</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $n => $r)
      @php [$verdict, $tone] = \App\Http\Controllers\RegisterController::deliberationFor((float) $r->compliance); @endphp
      <tr>
        <td>{{ $n + 1 }}</td>
        <td>{{ $r->upi ?: '—' }}</td>
        <td>{{ $r->zoning ?: '—' }}</td>
        <td>{{ $r->owner ?: '—' }}</td>
        <td><strong>{{ $r->name }}</strong></td>
        <td>{{ $r->district ?: '—' }}</td>
        <td>{{ $r->sector ?: '—' }}</td>
        <td>{{ $r->last_date }}</td>
        <td class="num">{{ round((float) $r->compliance, 1) }}%</td>
        <td class="ctr">{{ $r->inspections_count }}</td>
        <td><span class="band {{ $tone }}">{{ $verdict }}</span></td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="foot">
  <span>City of Kigali &mdash; Digital Inspection Platform</span>
  <span>Prepared by {{ auth()->user()->name }} &middot; {{ now()->format('j F Y, H:i') }}</span>
</div>

<script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>

</body>
</html>
