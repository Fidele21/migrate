@extends('layouts.app')
@section('title', 'User Accounts')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Administration</div>
    <h2>User Accounts</h2>
    <p>Accounts are created here and the credentials handed to the user, who is required to
       set their own password on first sign-in.</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('users.create') }}" class="btn btn-success">Create Account</a>
  </div>
</div>

@if(session('reset_password'))
  <div class="reset-card">
    <div class="rc-head">Temporary password issued</div>

    @if(session('reset_delivered'))
      <p class="rc-note" style="border-left:3px solid var(--green);padding-left:10px">
        <strong style="color:#00612B">Emailed to {{ session('reset_email') }}.</strong>
        {{ session('reset_user') }} can sign in without your help. The password is repeated
        below only in case the email does not reach them.
      </p>
    @else
      <p class="rc-note" style="border-left:3px solid var(--red);padding-left:10px">
        <strong style="color:var(--red)">The email could not be sent.</strong>
        The password below is the only copy — give it to {{ session('reset_user') }}
        directly. It is shown once and cannot be recovered; if it is lost, reset again.
      </p>
    @endif

    <p class="rc-note">
      They must set their own password when they sign in.
    </p>
    <div class="rc-grid">
      <div>
        <span>Account</span>
        <strong>{{ session('reset_email') }}</strong>
      </div>
      <div>
        <span>Temporary password</span>
        <strong class="rc-pw" id="temp-pw">{{ session('reset_password') }}</strong>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button type="button" class="btn btn-ghost" id="copy-pw">Copy</button>
      </div>
    </div>
  </div>

  

  <script>
    document.getElementById('copy-pw').addEventListener('click', function () {
      var t = document.getElementById('temp-pw').textContent.trim();
      navigator.clipboard.writeText(t).then(function () {
        var b = document.getElementById('copy-pw');
        b.textContent = 'Copied';
        setTimeout(function () { b.textContent = 'Copy'; }, 2000);
      });
    });
  </script>
@endif

@if(session('status'))
  <div class="note" style="border-left-color:var(--green);background:#E9F5EE">
    <strong style="color:#00612B">Done</strong>{{ session('status') }}
  </div>
@endif

@if($errors->any())
  <div class="callout"><b>Not permitted</b><p>{{ $errors->first() }}</p></div>
@endif

<div class="stats">
  <div class="stat"><b>{{ $users->count() }}</b><span>Accounts</span></div>
  <div class="stat g"><b>{{ $users->where('is_active', true)->count() }}</b><span>Active</span></div>
  <div class="stat r"><b>{{ $users->where('is_active', false)->count() }}</b><span>Deactivated</span></div>
  <div class="stat y"><b>{{ $users->where('must_change_password', true)->count() }}</b><span>Awaiting first sign-in</span></div>
  <div class="stat t"><b>{{ $districts->count() }}</b><span>Districts</span></div>
</div>

<section>
  <h3>All accounts</h3>
  <div class="desc">Deactivating preserves the account so it remains visible in the audit trail.</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>District</th>
            <th>Status</th><th>Last sign-in</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($users as $u)
          <tr>
            <td><strong>{{ $u->name }}</strong>
              @if($u->employee_number)<br><span style="color:var(--muted);font-size:11.5px">{{ $u->employee_number }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $u->email }}</td>
            <td>{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</td>
            <td>{{ $u->district?->name ?? 'All districts' }}</td>
            <td>
              @if(! $u->is_active)
                <span class="pill poor">Deactivated</span>
              @elseif($u->must_change_password)
                <span class="pill weak">Password not set</span>
              @else
                <span class="pill good">Active</span>
              @endif
            </td>
            <td style="font-size:12.5px;color:var(--muted)">
              {{ $u->last_login_at?->format('j M Y, H:i') ?? 'Never' }}
            </td>
            <td class="num">
              <a href="{{ route('users.edit', $u) }}" class="btn btn-ghost btn-sm">Edit</a>
              @if($u->id !== auth()->id())
                <form method="post" action="{{ route('users.reset', $u) }}" style="display:inline"
                      onsubmit="return confirm('Issue a new temporary password for {{ $u->name }}? Any current password will stop working immediately.')">
                  @csrf
                  <button type="submit" class="btn btn-ghost btn-sm">Reset Password</button>
                </form>
                <form method="post" action="{{ route('users.toggle', $u) }}" style="display:inline">
                  @csrf
                  <button type="submit" class="btn btn-ghost btn-sm">
                    {{ $u->is_active ? 'Deactivate' : 'Reactivate' }}
                  </button>
                </form>
              @else
                <span style="color:var(--muted);font-size:11.5px">You</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<footer>City of Kigali &mdash; Digital Inspection Platform &middot; User administration</footer>

@endsection

@push('styles')
<style>
    .reset-card{background:var(--white);padding:24px 26px;margin-bottom:24px;
                box-shadow:var(--shadow-sm);border-left:4px solid var(--success)}
    .rc-head{font-family:var(--f-head);font-size:16px;font-weight:600;color:var(--success)}
    .rc-note{font-size:13.5px;color:var(--body-text);margin:6px 0 18px;max-width:640px;line-height:1.65}
    .rc-grid{display:grid;grid-template-columns:1.4fr 1fr auto;gap:22px;align-items:end}
    @media(max-width:700px){.rc-grid{grid-template-columns:1fr}}
    .rc-grid span{display:block;font-family:var(--f-head);font-size:10.5px;font-weight:600;
                  letter-spacing:.8px;text-transform:uppercase;color:var(--tertiary);margin-bottom:5px}
    .rc-grid strong{font-family:var(--f-head);font-size:17px;color:var(--ink)}
    .rc-pw{font-size:24px!important;letter-spacing:2px;color:var(--primary)!important;
           background:var(--canvas);padding:8px 16px;display:inline-block}
  </style>
@endpush
