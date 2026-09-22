@extends('layouts.app')
@section('title', 'Search')

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">Search</div>
    <h2>Find a premises</h2>
    <p>Search by premises name, UPI, owner, telephone or email address.</p>
  </div>
</div>

<section>
  <form method="get" style="display:flex;gap:9px;flex-wrap:wrap">
    <input type="text" name="q" value="{{ $term }}" autofocus
           placeholder="Name, UPI, owner, telephone or email"
           style="flex:1;min-width:240px;padding:11px 14px;border:1px solid #D3D8E0;
                  border-radius:7px;font-size:14.5px;font-family:inherit">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
</section>

@if($term !== '')
<section>
  <h3>{{ count($results) }} {{ Str::plural('result', count($results)) }}</h3>
  <div class="desc">Matching "{{ $term }}"</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th>Premises</th><th>Type</th><th>UPI</th><th>Owner</th><th>Contact</th>
            <th>District</th><th style="text-align:center">Inspections</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($results as $r)
          <tr>
            <td><strong>{{ $r->name }}</strong></td>
            <td style="font-size:12.5px">{{ $r->type_name }}</td>
            <td style="font-size:12px">{{ $r->upi ?: '—' }}</td>
            <td>{{ $r->owner ?: '—' }}</td>
            <td style="font-size:12px">
              {{ $r->telephone ?: '' }}@if($r->telephone && $r->email)<br>@endif{{ $r->email ?: '' }}
              @if(!$r->telephone && !$r->email)—@endif
            </td>
            <td>{{ $r->district ?: '—' }}</td>
            <td style="text-align:center"><strong>{{ $r->inspection_count }}</strong></td>
            <td class="num"><a href="{{ route('entity.show', $r->entity_id) }}" class="btn btn-ghost btn-sm">Open</a></td>
          </tr>
        @empty
          <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">
            Nothing matches that search.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endif

@endsection
