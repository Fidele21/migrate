<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change password &mdash; CoK Digital Inspection Platform</title>
<style>
  :root{--blue:#0033A0;--blue-l:#2B5BC4;--gold:#F2B705;--red:#C0362C;
        --ink:#12161D;--muted:#606874;--line:#E4E7EC}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);
       line-height:1.55;min-height:100vh;display:flex;align-items:center;justify-content:center;
       padding:24px;background:linear-gradient(140deg,#0033A0 0%,#00256F 55%,#001A4F 100%)}
  .shell{width:100%;max-width:430px}
  .brand{display:flex;align-items:center;gap:13px;justify-content:center;margin-bottom:24px}
  .mark{width:46px;height:46px;border-radius:9px;background:var(--gold);display:flex;
        align-items:center;justify-content:center;font-weight:800;font-size:17px;color:var(--blue)}
  .brand h1{color:#fff;font-size:18px;font-weight:700;line-height:1.2}
  .brand p{color:#9FB3D1;font-size:10px;text-transform:uppercase;letter-spacing:1.2px;font-weight:600}
  .card{background:#fff;border-radius:13px;padding:30px;box-shadow:0 18px 48px rgba(0,10,40,.28)}
  .card h2{font-size:19px;color:var(--blue);margin-bottom:4px}
  .card .sub{font-size:13px;color:var(--muted);margin-bottom:20px}
  .field{margin-bottom:15px}
  .field label{display:block;font-size:11px;font-weight:800;letter-spacing:.07em;
               text-transform:uppercase;color:var(--muted);margin-bottom:6px}
  .field input{width:100%;padding:11px 13px;border:1px solid #D3D8E0;border-radius:8px;
               font-size:14.5px;font-family:inherit}
  .field input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,51,160,.11)}
  .hint{font-size:11.5px;color:var(--muted);margin-top:5px}
  button{width:100%;padding:12px;background:var(--blue);color:#fff;border:none;border-radius:8px;
         font:700 14.5px inherit;font-family:inherit;cursor:pointer;margin-top:6px}
  button:hover{background:var(--blue-l)}
  .errors{background:#FDECEA;border-left:4px solid var(--red);border-radius:7px;
          padding:11px 14px;margin-bottom:18px;font-size:13px;color:#8E1F16}
  .errors li{list-style:none}
  .notice{background:#FFF6DF;border-left:4px solid var(--gold);border-radius:7px;
          padding:12px 15px;margin-bottom:19px;font-size:13px;color:#6B5200;line-height:1.55}
  .out{text-align:center;margin-top:18px}
  .out button{background:none;color:#9FB3D1;font:600 12px inherit;font-family:inherit;
              padding:0;width:auto;text-decoration:underline;cursor:pointer}
</style>
</head>
<body>
<div class="shell">
  <div class="brand">
    <div class="mark">CoK</div>
    <div><h1>Digital Inspection Platform</h1><p>City of Kigali</p></div>
  </div>

  <div class="card">
    <h2>Change your password</h2>
    <div class="sub">Choose a password only you know.</div>

    @if($forced)
      <div class="notice">
        Your account was created with a password issued by the administrator.
        Please set your own before continuing, so that no one else knows your credentials.
      </div>
    @endif

    @if($errors->any())
      <div class="errors">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</div>
    @endif

    <form method="post" action="{{ route('password.update') }}">
      @csrf
      <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" type="password" name="current_password" required autofocus autocomplete="current-password">
      </div>
      <div class="field">
        <label for="password">New password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        <div class="hint">At least 8 characters, and different from your current one.</div>
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
      </div>
      <button type="submit">Change password</button>
    </form>
  </div>

  <div class="out">
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out instead</button></form>
  </div>
</div>
</body>
</html>
