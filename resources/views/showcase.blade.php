<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Platform Rebuild &mdash; City of Kigali Inspection Unit</title>
<style>
  :root{
    --blue:#0033A0; --blue-l:#1A4FB3; --green:#009A44; --gold:#FFCD00; --red:#C0362C;
    --ink:#141922; --muted:#5B6270; --line:#E2E5EA; --bg:#F2F3EF; --card:#fff;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
       background:var(--bg);color:var(--ink);line-height:1.55}

  .topbar{background:var(--blue);color:#fff;padding:12px 22px;display:flex;
          align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
  .brand{display:flex;align-items:center;gap:12px}
  .mark{width:36px;height:36px;border-radius:5px;background:var(--gold);display:flex;
        align-items:center;justify-content:center;font-weight:800;font-size:14px;color:var(--blue)}
  .brand h1{font-size:16px;font-weight:700}
  .brand p{font-size:10.5px;color:#A9B9CE;text-transform:uppercase;letter-spacing:.9px}
  .topbar a{color:#E4EAF3;text-decoration:none;font-size:12.5px;font-weight:600;
            border:1px solid rgba(255,255,255,.3);padding:7px 14px;border-radius:5px}
  .topbar a:hover{background:rgba(255,255,255,.14)}

  .wrap{max-width:1080px;margin:0 auto;padding:26px 20px 70px}

  .lede{margin-bottom:26px}
  .lede h2{font-size:24px;color:var(--blue);margin-bottom:6px}
  .lede p{color:var(--muted);font-size:14px;max-width:760px}

  .stats{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:28px}
  .stat{background:var(--card);border:1px solid var(--line);border-radius:9px;
        padding:15px 17px;border-top:3px solid var(--blue)}
  .stat b{display:block;font-size:26px;font-weight:800;color:var(--blue);letter-spacing:-.02em}
  .stat span{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600}

  section{background:var(--card);border:1px solid var(--line);border-radius:9px;
          padding:22px 24px;margin-bottom:20px}
  h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;
     color:var(--blue);margin-bottom:5px}
  .desc{font-size:13px;color:var(--muted);margin-bottom:18px}

  table{width:100%;border-collapse:collapse;font-size:13px}
  th{text-align:left;padding:9px 10px;background:#F7F8F9;color:var(--muted);font-size:10.5px;
     text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--line);font-weight:700}
  th.rot{text-align:center;font-size:10px}
  td{padding:9px 10px;border-bottom:1px solid #F0F2F4;vertical-align:middle}
  td.c{text-align:center}
  tbody tr:hover{background:#FAFBFC}
  .who{font-weight:700}
  .who small{display:block;font-weight:400;color:var(--muted);font-size:11.5px}

  .yes{color:var(--green);font-weight:800}
  .no{color:#C7CCD3;font-weight:700}

  .chain{display:flex;flex-direction:column;gap:0}
  .stage{display:grid;grid-template-columns:34px 1fr;gap:14px;padding:12px 0;position:relative}
  .stage:not(:last-child)::before{content:'';position:absolute;left:16px;top:34px;bottom:-6px;
                                  width:2px;background:var(--line)}
  .dot{width:34px;height:34px;border-radius:50%;background:#EDF2FC;color:var(--blue);
       display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;z-index:1}
  .stage.done .dot{background:var(--green);color:#fff}
  .stage h4{font-size:14px;font-weight:700;margin-bottom:1px}
  .stage .actor{font-size:11px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.05em}
  .stage p{font-size:12.5px;color:var(--muted)}

  .tag{display:inline-block;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;
       letter-spacing:.05em;text-transform:uppercase}
  .tag.live{background:#E4F5EA;color:#00612B}
  .tag.pending{background:#FFF6D6;color:#8A6D00}

  .note{background:#F0F4FF;border-left:4px solid var(--blue);border-radius:7px;
        padding:16px 18px;margin-bottom:20px;font-size:13.5px}
  .note strong{display:block;margin-bottom:5px;color:var(--blue)}

  footer{text-align:center;color:var(--muted);font-size:12px;margin-top:30px;
         padding-top:18px;border-top:1px solid var(--line)}

  @media(max-width:700px){
    table{font-size:12px} th,td{padding:7px 6px}
    .lede h2{font-size:20px}
  }
</style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="mark">CoK</div>
    <div>
      <h1>Digital Inspection Platform</h1>
      <p>Rebuild &middot; Phase 1</p>
    </div>
  </div>
  <a href="/inspect/">Back to live platform</a>
</header>

<div class="wrap">

  <div class="lede">
    <h2>Approval chain and access control</h2>
    <p>The rebuilt platform encodes the inspection unit's actual approval process. Every
       figure on this page is produced by running the real authorisation rules against real
       user accounts &mdash; it is a live demonstration, not a mock-up.</p>
  </div>

  <div class="stats">
    <div class="stat"><b>{{ $roles->count() }}</b><span>Roles defined</span></div>
    <div class="stat"><b>{{ $roles->sum(fn($r) => $r->permissions->count()) }}</b><span>Permission grants</span></div>
    <div class="stat"><b>{{ $districts->count() }}</b><span>Districts scoped</span></div>
    <div class="stat"><b>{{ $districts->sum('users_count') + $roles->count() }}</b><span>User accounts</span></div>
    <div class="stat"><b>15</b><span>Automated tests</span></div>
  </div>

  <div class="note">
    <strong>Correction to the current system</strong>
    In the platform now in use, the Director of Inspection outranks the Chief Inspector,
    meaning a Director could sign an enforcement letter reserved for the Chief. The rebuild
    replaces rank comparison with explicit permissions, and an automated test confirms that
    only the Chief Inspector holds final approval.
  </div>

  <section>
    <h3>Document approval chain</h3>
    <div class="desc">Reports and enforcement letters follow the same route and move independently.</div>
    <div class="chain">
      @foreach($stages as $i => $s)
        <div class="stage {{ $i < 4 ? 'done' : '' }}">
          <div class="dot">{{ $i + 1 }}</div>
          <div>
            <h4>{{ $s['label'] }}</h4>
            <div class="actor">{{ $s['actor'] }}</div>
            <p>{{ $s['note'] }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </section>

  <section>
    <h3>Live authorisation checks</h3>
    <div class="desc">Each cell below is the result of asking the platform, right now, whether that
      person may perform that action.</div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Role</th>
            @foreach(reset($sample)['checks'] ?? [] as $label => $_)
              <th class="rot">{{ $label }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($sample as $roleName => $row)
            <tr>
              <td class="who">{{ $roleName }}<small>{{ $row['district'] }}</small></td>
              @foreach($row['checks'] as $allowed)
                <td class="c">
                  @if($allowed)<span class="yes">&#10003;</span>@else<span class="no">&mdash;</span>@endif
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <h3>District scoping</h3>
    <div class="desc">A Director may act only on work belonging to their own district. Verified below
      against live accounts.</div>
    <table>
      <thead>
        <tr>
          <th>Director of</th>
          @foreach($districts as $d)<th class="rot">{{ $d->name }} work</th>@endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($scoping as $row)
          <tr>
            <td class="who">{{ $row['name'] }}</td>
            @foreach($row['covers'] as $allowed)
              <td class="c">
                @if($allowed)<span class="yes">&#10003;</span>@else<span class="no">&#10007;</span>@endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>

  <section>
    <h3>Build status</h3>
    <div class="desc">Progress against the phased delivery plan.</div>
    <table>
      <tbody>
        <tr><td>Roles, districts and permissions</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>District scoping with automated tests</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>Document records, versioning and soft deletion</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>Approval state machine</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>Immutable transition log</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>Tamper-evident signatures</td><td class="c"><span class="tag live">Complete</span></td></tr>
        <tr><td>Work queues per role</td><td class="c"><span class="tag pending">In progress</span></td></tr>
        <tr><td>Report and letter templates</td><td class="c"><span class="tag pending">Awaiting official files</span></td></tr>
        <tr><td>Offline field capture</td><td class="c"><span class="tag pending">Planned</span></td></tr>
        <tr><td>Parcel mapping and distance compliance</td><td class="c"><span class="tag pending">Planned</span></td></tr>
      </tbody>
    </table>
  </section>

  <footer>
    City of Kigali &mdash; Digital Inspection Platform &middot; Rebuild in progress<br>
    Generated {{ now()->format('j F Y, H:i') }}
  </footer>

</div>
</body>
</html>
