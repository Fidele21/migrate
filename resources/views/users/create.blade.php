@extends('layouts.app')
@section('title', 'Create Account')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Administration</div>
    <h2>Create Account</h2>
    <p>A temporary password is generated and emailed to the new account holder. They will be
       required to change it before they can use the platform.</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('users.index') }}" class="btn btn-ghost">Back to accounts</a>
  </div>
</div>

@if($errors->any())
  <div class="callout">
    <b>Please correct the following</b>
    <p style="font-size:13.5px;font-weight:400">
      @foreach($errors->all() as $e)&bull; {{ $e }}<br>@endforeach
    </p>
  </div>
@endif

<form method="post" action="{{ route('users.store') }}">
  @csrf

  <section>
    <h3>Person</h3>
    <div class="desc">Identity and contact details</div>

    <div style="display:grid;gap:15px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      <div>
        <label class="fl">Full name <span style="color:var(--red)">*</span></label>
        <input class="fi" type="text" name="name" value="{{ old('name') }}" required>
      </div>
      <div>
        <label class="fl">Email address <span style="color:var(--red)">*</span></label>
        <input class="fi" type="email" name="email" value="{{ old('email') }}" required>
      </div>
      <div>
        <label class="fl">Employee number</label>
        <input class="fi" type="text" name="employee_number" value="{{ old('employee_number') }}">
      </div>
      <div>
        <label class="fl">Telephone</label>
        <input class="fi" type="text" name="phone" value="{{ old('phone') }}">
      </div>
    </div>
  </section>

  <section>
    <h3>Authority</h3>
    <div class="desc">The role determines what this person may do. Inspectors, Lead Inspectors
      and Directors are bound to one district; Senior Inspectors, Chief Inspectors and
      Administrators operate across all three.</div>

    <div style="display:grid;gap:15px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      <div>
        <label class="fl">Role <span style="color:var(--red)">*</span></label>
        <select class="fi" name="role" id="role" required>
          <option value="">Select a role</option>
          @foreach($roles as $r)
            <option value="{{ $r }}" @selected(old('role') === $r)>{{ $r }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="fl">District <span id="dreq" style="color:var(--red);display:none">*</span></label>
        <select class="fi" name="district_id" id="district">
          <option value="">All districts</option>
          @foreach($districts as $d)
            <option value="{{ $d->id }}" @selected((string) old('district_id') === (string) $d->id)>{{ $d->name }}</option>
          @endforeach
        </select>
        <div class="hint" id="dhint">City-wide roles are not bound to a district.</div>
      </div>
    </div>
  </section>

  <section>
    <h3>Sign-in details</h3>
    <div class="desc">A temporary password is generated when the account is created and
      emailed to the address above. It is also shown to you once, in case the email does
      not arrive. The holder must replace it before they can use the platform.</div>

    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-success">Create Account</button>
      <a href="{{ route('users.index') }}" class="btn btn-ghost">Cancel</a>
    </div>
  </section>
</form>



<script>
const BOUND = @json($districtBound);
const role = document.getElementById('role');
const dist = document.getElementById('district');
const dreq = document.getElementById('dreq');
const dhint = document.getElementById('dhint');

function sync() {
  const bound = BOUND.includes(role.value);
  dreq.style.display = bound ? 'inline' : 'none';
  dist.required = bound;
  dist.disabled = !bound && role.value !== '';
  if (!bound) dist.value = '';
  dhint.textContent = bound
    ? 'This role must be assigned to exactly one district.'
    : 'City-wide roles are not bound to a district.';
}
role.addEventListener('change', sync);
sync();
</script>

@endsection

@push('styles')
<style>
  .fl{display:block;font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;
      color:var(--muted);margin-bottom:6px}
  .fi{width:100%;padding:10px 12px;border:1px solid #D3D8E0;border-radius:7px;
      font-size:14px;font-family:inherit;background:#fff;color:var(--ink)}
  .fi:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,51,160,.11)}
  .hint{font-size:11.5px;color:var(--muted);margin-top:5px}
</style>
@endpush
