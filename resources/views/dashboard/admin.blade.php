@extends('layouts.app')
@section('title', 'Administration')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Administrator</div>
    <h2>Good day, {{ Str::before(auth()->user()->name, ' ') }}</h2>
    <p>Accounts, roles and the state of the platform. This role manages access and never
       records inspections or signs documents.</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('users.create') }}" class="btn btn-success">New Account</a>
    <a href="{{ route('users.index') }}" class="btn btn-ghost">All Accounts</a>
    <a href="{{ route('showcase') }}" class="btn btn-ghost">Access Control</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><b>{{ $summary['total'] }}</b><span>Accounts</span></div>
  <div class="stat g"><b>{{ $summary['active'] }}</b><span>Active</span></div>
  <div class="stat r"><b>{{ $summary['inactive'] }}</b><span>Disabled</span></div>
  <div class="stat y"><b>{{ $summary['unset'] }}</b><span>Password not set</span></div>
  <div class="stat y"><b>{{ $summary['nosig'] }}</b><span>No signature</span></div>
  <div class="stat t"><b>{{ $summary['never'] }}</b><span>Never signed in</span></div>
</div>

<div class="grid2">
  <section>
    <h3>Accounts by role</h3>
    <div class="desc">Who holds what</div>
    @foreach($byRole as $role => $n)
      <div class="row">
        <div class="nm">{{ $role }}</div>
        <div class="track"><div class="fill {{ $role === 'Administrator' ? 'weak' : 'good' }}"
             style="width:{{ round(100 * $n / max($byRole->max(), 1)) }}%"></div></div>
        <div class="v">{{ $n }}</div>
      </div>
    @endforeach
  </section>

  <section>
    <h3>Accounts by district</h3>
    <div class="desc">Coverage across the city</div>
    @foreach($byDistrict as $name => $n)
      <div class="row">
        <div class="nm">{{ $name }}</div>
        <div class="track"><div class="fill good"
             style="width:{{ round(100 * $n / max($byDistrict->max(), 1)) }}%"></div></div>
        <div class="v">{{ $n }}</div>
      </div>
    @endforeach
  </section>
</div>

@if($stale->count())
<section class="urgent">
  <h3>Needing attention</h3>
  <div class="desc">Active accounts that have never been used, or whose password is still the one issued</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Issue</th><th></th></tr></thead>
      <tbody>
        @foreach($stale as $u)
          <tr>
            <td><strong>{{ $u->name }}</strong></td>
            <td style="font-size:12.5px">{{ $u->email }}</td>
            <td style="font-size:12.5px">{{ $u->roles->pluck('name')->first() }}</td>
            <td>
              @if(! $u->last_login_at)<span class="pill weak">Never signed in</span>
              @elseif($u->must_change_password)<span class="pill weak">Password not set</span>@endif
            </td>
            <td class="num"><a href="{{ route('users.index') }}" class="btn btn-ghost btn-sm">Accounts</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<section>
  <h3>Recent activity</h3>
  <div class="desc">Most recently signed in</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Name</th><th>Role</th><th>District</th><th>Signature</th><th>Last signed in</th></tr></thead>
      <tbody>
        @foreach($recent as $u)
          <tr>
            <td><strong>{{ $u->name }}</strong><br><span class="mini">{{ $u->email }}</span></td>
            <td style="font-size:12.5px">{{ $u->roles->pluck('name')->first() }}</td>
            <td style="font-size:12.5px">{{ $u->district?->name ?? 'All' }}</td>
            <td>@if($u->signature_path)<span class="pill good">Registered</span>
                @else<span class="pill weak">Not set</span>@endif</td>
            <td style="font-size:12.5px">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<section>
  <h3>Platform</h3>
  <div class="desc">What the database holds</div>
  <div class="counts">
    <div><span>Premises</span><strong>{{ number_format($counts['entities']) }}</strong></div>
    <div><span>Inspections</span><strong>{{ number_format($counts['inspections']) }}</strong></div>
    <div><span>Documents</span><strong>{{ number_format($counts['documents']) }}</strong></div>
    <div><span>Fines</span><strong>{{ number_format($counts['fines']) }}</strong></div>
  </div>
  <div class="note" style="margin:20px 0 0">
    <strong>A reminder about backups</strong>
    Thirteen inspections were lost on 5 August when a database was imported over the live one.
    Confirm the nightly backup is running with <code>crontab -l</code>.
  </div>
</section>



@endsection

@push('styles')
<style>
  section.urgent{border-left:4px solid var(--warning)}
  .counts{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:var(--border)}
  .counts > div{background:var(--white);padding:18px 20px}
  .counts span{display:block;font-family:var(--f-head);font-size:10.5px;letter-spacing:.8px;
               text-transform:uppercase;color:var(--tertiary);font-weight:600}
  .counts strong{display:block;font-family:var(--f-head);font-size:24px;color:var(--primary);margin-top:4px}
  .mini{font-size:11.5px;color:var(--tertiary)}
  code{background:var(--canvas);padding:1px 6px;font-size:12.5px}
</style>
@endpush
