<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in &mdash; Inspection Data Management System</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%2334A8DB'/><text x='50' y='68' font-size='40' font-family='sans-serif' font-weight='800' fill='%23FFFFFF' text-anchor='middle'>CoK</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  /* ==========================================================
     City of Kigali design system
     Sky blue primary, yellow and green accents, tan labels.
     Montserrat headings, Merriweather body. Square corners.
     ========================================================== */
  :root{
    --primary:#34A8DB;
    --primary-dark:#2980B9;
    --primary-hover:#248FC2;
    --secondary:#FFEB3B;
    --success:#4CAF50;
    --warning:#F39C12;
    --danger:#E74C3C;
    --tertiary:#CDB896;
    --ink:#333333;
    --body-text:#555555;
    --canvas:#F7F9FB;
    --white:#FFFFFF;
    --border:#E0E0E0;
    --f-head:'Montserrat',sans-serif;
    --f-body:'Merriweather',serif;
    --shadow:0.5084rem 1.1419rem 2.5rem 0 rgb(0 0 0 / 8%);
  }

  *{box-sizing:border-box;margin:0;padding:0}

  body{font-family:var(--f-body);font-size:15px;line-height:1.6;color:var(--body-text);
       background:var(--canvas);min-height:100vh;display:flex;flex-direction:column;
       -webkit-font-smoothing:antialiased}

  h1,h2,h3{font-family:var(--f-head);color:var(--ink)}

  /* ---------- Layout ---------- */
  .wrap{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:100vh}
  @media(max-width:900px){.wrap{grid-template-columns:1fr}}

  /* ---------- Left: identity ---------- */
  .side{background:var(--primary);color:#fff;padding:56px 60px;display:flex;
        flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
  @media(max-width:900px){.side{padding:34px 26px;min-height:auto}}

  .side::after{content:'';position:absolute;right:-140px;bottom:-140px;width:420px;height:420px;
               background:rgba(255,255,255,.06);transform:rotate(45deg)}

  .brand{display:flex;align-items:center;gap:16px;position:relative;z-index:2}
  .mark{width:56px;height:56px;background:var(--white);display:flex;align-items:center;
        justify-content:center;font-family:var(--f-head);font-weight:800;font-size:17px;
        color:var(--primary);flex-shrink:0}
  .brand .rep{font-family:var(--f-head);font-size:10px;font-weight:600;letter-spacing:2.4px;
              text-transform:uppercase;opacity:.85}
  .brand .city{font-family:var(--f-head);font-size:21px;font-weight:800;line-height:1.2}

  .side-mid{position:relative;z-index:2;padding:52px 0}
  .side-mid h1{font-family:var(--f-head);font-size:34px;font-weight:700;color:#fff;
               line-height:1.25;letter-spacing:-.5px;margin-bottom:14px}
  .side-mid p{font-size:15px;opacity:.94;max-width:400px;line-height:1.75}

  .points{list-style:none;margin-top:32px}
  .points li{display:flex;gap:13px;align-items:flex-start;margin-bottom:15px;
             font-size:14px;opacity:.95;max-width:400px}
  .points i{width:7px;height:7px;background:var(--secondary);flex-shrink:0;margin-top:8px}

  .side-foot{position:relative;z-index:2;font-family:var(--f-head);font-size:11px;
             letter-spacing:.8px;opacity:.8}

  /* ---------- Right: the form ---------- */
  .panel{display:flex;align-items:center;justify-content:center;padding:56px 40px}
  @media(max-width:900px){.panel{padding:40px 24px}}

  .form-box{width:100%;max-width:400px}

  .form-box h2{font-family:var(--f-head);font-size:27px;font-weight:700;margin-bottom:6px}
  .form-box .lead{font-size:14.5px;margin-bottom:34px}

  label{display:block;font-family:var(--f-head);font-size:12px;font-weight:600;
        letter-spacing:.5px;text-transform:uppercase;color:var(--tertiary);margin-bottom:8px}

  input[type=email],input[type=password],input[type=text]{
    width:100%;font-family:var(--f-body);font-size:15px;padding:13px 15px;
    border:1px solid var(--border);background:var(--white);color:var(--ink);
    transition:border-color .2s,box-shadow .2s}
  input:focus{outline:none;border-color:var(--primary);
              box-shadow:0 0 0 3px rgba(52,168,219,.14)}

  .field{margin-bottom:22px}

  .pw-wrap{position:relative}
  .pw-toggle{position:absolute;right:0;top:0;bottom:0;padding:0 15px;border:0;background:none;
             cursor:pointer;font-family:var(--f-head);font-size:11px;font-weight:600;
             letter-spacing:.6px;text-transform:uppercase;color:var(--tertiary)}
  .pw-toggle:hover{color:var(--primary)}

  .remember{display:flex;align-items:center;gap:10px;margin-bottom:28px;cursor:pointer}
  .remember input{width:17px;height:17px;accent-color:var(--primary);cursor:pointer}
  .remember span{font-size:14px;color:var(--body-text);text-transform:none;
                 font-family:var(--f-body);letter-spacing:0}

  .btn{width:100%;font-family:var(--f-head);font-size:13px;font-weight:600;letter-spacing:1px;
       text-transform:uppercase;text-align:center;padding:15px 24px;border:0;cursor:pointer;
       background:var(--primary);color:#fff;transition:background .25s}
  .btn:hover{background:var(--primary-hover)}
  .btn:active{transform:translateY(1px)}
  .btn[disabled]{background:var(--tertiary);cursor:not-allowed}

  /* ---------- Messages ---------- */
  .alert{padding:15px 18px;margin-bottom:24px;font-size:14px;line-height:1.6;
         border-left:4px solid var(--danger);background:#FDECEA;color:#8B2A20}
  .alert strong{display:block;font-family:var(--f-head);font-size:12px;font-weight:600;
                letter-spacing:.8px;text-transform:uppercase;color:var(--danger);margin-bottom:4px}
  .alert.ok{border-left-color:var(--success);background:#E8F5E9;color:#1F5C23}
  .alert.ok strong{color:#2E7D32}

  .help{margin-top:30px;padding-top:22px;border-top:1px solid var(--border);
        font-size:13px;line-height:1.7;color:var(--body-text)}
  .help strong{font-family:var(--f-head);font-size:11.5px;font-weight:600;letter-spacing:.7px;
               text-transform:uppercase;color:var(--tertiary);display:block;margin-bottom:5px}

  footer{padding:20px 40px;text-align:center;font-family:var(--f-head);font-size:11px;
         letter-spacing:.6px;color:var(--tertiary);background:var(--white);
         border-top:1px solid var(--border)}
  @media(max-width:900px){footer{padding:16px 20px}}
</style>
</head>
<body>

<div class="wrap">

  {{-- ---------- Identity ---------- --}}
  <aside class="side">
    <div class="brand">
      <div class="mark">CoK</div>
      <div>
        <div class="rep">Republic of Rwanda</div>
        <div class="city">Kigali City</div>
      </div>
    </div>

    <div class="side-mid">
      <h1>Inspection Data Management System</h1>
      <p>The Inspection Team records site visits, issues enforcement
         correspondence and keeps the City's inspection record in one place.</p>

      <ul class="points">
        <li><i></i><span>Inspections recorded on site, with photographs and coordinates</span></li>
        <li><i></i><span>Reports and letters generated from the findings themselves</span></li>
        <li><i></i><span>Every document signed, referenced and archived</span></li>
      </ul>
    </div>

    <div class="side-foot">CoK Inspection Unit &middot; {{ date('Y') }}</div>
  </aside>

  {{-- ---------- Form ---------- --}}
  <main class="panel">
    <div class="form-box">

      <h2>Sign in</h2>
      <p class="lead">Use the account issued to you by the administrator.</p>

      @if(session('status'))
        <div class="alert ok">
          <strong>Notice</strong>
          {{ session('status') }}
        </div>
      @endif

      @if($errors->any())
        <div class="alert">
          <strong>Could not sign in</strong>
          {{ $errors->first() }}
        </div>
      @endif

      <form method="post" action="{{ route('login') }}" id="login-form" novalidate>
        @csrf

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" name="email" id="email" required autofocus
                 autocomplete="username" value="{{ old('email') }}"
                 placeholder="name@kigalicity.gov.rw">
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="password" required
                   autocomplete="current-password">
            <button type="button" class="pw-toggle" id="pw-toggle"
                    aria-label="Show password">Show</button>
          </div>
        </div>

        <label class="remember">
          <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
          <span>Keep me signed in on this device</span>
        </label>

        <button type="submit" class="btn" id="submit">Sign in</button>
      </form>

      <div class="help">
        <strong>No account, or forgotten password</strong>
        Accounts are created by the platform administrator, who will also reset a
        password. There is no self-registration.
      </div>

    </div>
  </main>

</div>

<footer>
  Kigali City &mdash; Inspection Data Management System &middot; Authorised users only
</footer>

<script>
(function () {
  var pw = document.getElementById('password');
  var tg = document.getElementById('pw-toggle');

  tg.addEventListener('click', function () {
    var showing = pw.type === 'text';
    pw.type = showing ? 'password' : 'text';
    tg.textContent = showing ? 'Show' : 'Hide';
    tg.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    pw.focus();
  });

  document.getElementById('login-form').addEventListener('submit', function () {
    var b = document.getElementById('submit');
    b.disabled = true;
    b.textContent = 'Signing in…';
  });
})();
</script>

</body>
</html>
