@extends('layouts.app')
@section('title', 'Road Register')

@section('content')

@php
  $q = collect($filters)->filter()->all();
@endphp

{{-- ══════════ Filter bar ══════════ --}}
<form method="get" class="fbar" id="fpanel">
  <div class="fb-id">
    <span class="fb-crumb">Road Inspection</span>
    <span class="fb-name">Road Register</span>
    <span class="fb-scope">
      {{ $figures['roads'] }} of {{ $figures['of_total'] }}
      {{ Str::plural('road', $figures['of_total']) }} on record
    </span>
  </div>

  <div class="fb-fields">
    <div class="fb-group">
      <label for="category">Category</label>
      <select name="category" id="category">
        <option value="">All categories</option>
        @foreach(\App\Models\Road::CATEGORIES as $k => $label)
          <option value="{{ $k }}" @selected(($filters['category'] ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="fb-group">
      <label for="district">District</label>
      <select name="district" id="district">
        <option value="">All districts</option>
        @foreach(array_keys(config('kigali')) as $d)
          <option value="{{ $d }}" @selected(($filters['district'] ?? '') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    <div class="fb-group">
      <label for="surface">Surface</label>
      <select name="surface" id="surface">
        <option value="">Any surface</option>
        @foreach(\App\Models\Road::SURFACES as $k => $label)
          <option value="{{ $k }}" @selected(($filters['surface'] ?? '') === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="fb-group">
      <label for="q">Search</label>
      <input type="text" name="q" id="q" value="{{ $filters['q'] }}"
             placeholder="Name, code or point">
    </div>

    @can('road.manage')
      <div class="fb-group fb-end">
        <label>&nbsp;</label>
        <a href="{{ route('road.create') }}" class="fb-add">Add Road</a>
      </div>
    @endcan

    <div class="fb-group fb-end">
      <label>&nbsp;</label>
      <a href="{{ route('road.index') }}" class="fb-reset {{ $active ? 'live' : '' }}">
        Reset @if($active)<i>{{ $active }}</i>@endif
      </a>
    </div>
  </div>
</form>

@if($figures['of_total'] === 0)

<section class="panel" style="padding:60px 24px">
  <div class="empty">
    <b>No roads on the register</b>
    <span>
      A road is recorded rather than assessed &mdash; its category, its
      length, and how much of it is paved. Nothing here is scored.
    </span>
    <div style="margin-top:20px">
      @can('road.manage')
        <a href="{{ route('road.create') }}" class="btn btn-success">Add the first road</a>
      @endcan
    </div>
  </div>
</section>

@else

{{-- ══════════ Figures ══════════ --}}
<div class="kpi-row">
  <div class="kpi">
    <span class="k-label">Roads</span>
    <b class="k-value">{{ $figures['roads'] }}</b>
    <span class="k-note">on record</span>
  </div>

  <div class="kpi accent">
    <span class="k-label">Total length</span>
    <b class="k-value">{{ number_format($figures['length'], 1) }}<i>km</i></b>
    <span class="k-note">across the selection</span>
  </div>

  <div class="kpi good">
    <span class="k-label">Paved</span>
    <b class="k-value">{{ number_format($figures['paved'], 1) }}<i>km</i></b>
    <span class="k-note">
      {{ $figures['length'] > 0 ? round(100 * $figures['paved'] / $figures['length']) : 0 }}% of length
    </span>
  </div>

  <div class="kpi">
    <span class="k-label">Unpaved</span>
    <b class="k-value">{{ number_format($figures['unpaved'], 1) }}<i>km</i></b>
    <span class="k-note">
      {{ $figures['length'] > 0 ? round(100 * $figures['unpaved'] / $figures['length']) : 0 }}% of length
    </span>
  </div>

  <div class="kpi {{ $figures['incomplete'] ? 'bad' : '' }}">
    <span class="k-label">Lengths unaccounted</span>
    <b class="k-value">{{ $figures['incomplete'] }}</b>
    <span class="k-note">paved and unpaved do not sum</span>
  </div>

  <div class="kpi {{ $figures['unlocated'] ? 'bad' : '' }}">
    <span class="k-label">Not located</span>
    <b class="k-value">{{ $figures['unlocated'] }}</b>
    <span class="k-note">no start coordinates</span>
  </div>
</div>

<div class="grid2">

  {{-- ══════════ By category ══════════ --}}
  <section>
    <h3>By category</h3>
    <div class="desc">Length on record, longest first</div>

    @foreach($byCategory as $c)
      @php
        $share = $figures['length'] > 0 ? 100 * $c->length / $figures['length'] : 0;
        $paved = $c->length > 0 ? 100 * $c->paved / $c->length : 0;
      @endphp
      <div class="rd-row">
        <div class="rd-name">
          {{ $c->label }}
          <span>{{ $c->roads }} {{ Str::plural('road', $c->roads) }}</span>
        </div>
        <div class="rd-bar">
          <div class="rd-paved" style="width:{{ $share * $paved / 100 }}%"
               title="{{ number_format($c->paved, 1) }} km paved"></div>
          <div class="rd-unpaved" style="width:{{ $share * (100 - $paved) / 100 }}%"
               title="{{ number_format($c->unpaved, 1) }} km unpaved"></div>
        </div>
        <div class="rd-km">{{ number_format($c->length, 1) }}</div>
      </div>
    @endforeach

    <div class="rd-key">
      <span><i class="k-paved"></i>Paved</span>
      <span><i class="k-unpaved"></i>Unpaved or unmeasured</span>
      <span class="rd-unit">kilometres</span>
    </div>
  </section>

  {{-- ══════════ By district ══════════ --}}
  <section>
    <h3>By district</h3>
    <div class="desc">Where each road begins &mdash; a road may run through more than one</div>

    @if($byDistrict->count())
      <table>
        <thead>
          <tr>
            <th>District</th>
            <th class="num">Roads</th>
            <th class="num">Length</th>
            <th class="num">Paved</th>
            <th class="num">Share paved</th>
          </tr>
        </thead>
        <tbody>
          @foreach($byDistrict as $d)
            <tr>
              <td><strong>{{ $d->name }}</strong></td>
              <td class="num">{{ $d->roads }}</td>
              <td class="num">{{ number_format($d->length, 1) }} km</td>
              <td class="num">{{ number_format($d->paved, 1) }} km</td>
              <td class="num">
                {{ $d->length > 0 ? round(100 * $d->paved / $d->length) : 0 }}%
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="empty" style="padding:30px">
        <span>No district recorded against any road in this selection.</span>
      </div>
    @endif
  </section>
</div>

{{-- ══════════ The register ══════════ --}}
<section>
  <h3>Roads on record</h3>
  <div class="desc">
    {{ $roads->count() }} {{ Str::plural('road', $roads->count()) }} in this selection
  </div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Road</th>
          <th>Category</th>
          <th>From &mdash; to</th>
          <th>Surface</th>
          <th class="num">Length</th>
          <th class="num">Paved</th>
          <th class="num">Unpaved</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($roads as $r)
          <tr>
            <td>
              <strong>{{ $r->name }}</strong>
              @if($r->code)<br><span class="mini">{{ $r->code }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $r->categoryLabel() }}</td>
            <td style="font-size:12.5px">
              {{ $r->start_point ?: '—' }}
              @if($r->end_point) &rarr; {{ $r->end_point }} @endif
              @if($r->district)<br><span class="mini">{{ $r->district }}</span>@endif
            </td>
            <td style="font-size:12.5px">{{ $r->surfaceLabel() }}</td>
            <td class="num">{{ $r->length_km ? number_format((float) $r->length_km, 1) : '—' }}</td>
            <td class="num">{{ $r->paved_km ? number_format((float) $r->paved_km, 1) : '—' }}</td>
            <td class="num">
              {{ $r->unpaved_km ? number_format((float) $r->unpaved_km, 1) : '—' }}
              @unless($r->lengthsAgree())
                <br><span class="gap" title="Paved and unpaved do not account for the whole length">
                  {{ $r->unaccountedKm() > 0 ? '+' : '' }}{{ number_format($r->unaccountedKm(), 1) }} km
                </span>
              @endunless
            </td>
            <td class="num">
              @can('road.manage')
                <a href="{{ route('road.edit', $r) }}" class="btn btn-ghost btn-sm">Edit</a>
              @endcan
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="text-align:center;color:var(--tertiary);padding:30px">
              No road matches this selection.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@if($figures['incomplete'])
  <div class="note warn">
    <strong>Lengths unaccounted</strong>
    {{ $figures['incomplete'] }}
    {{ Str::plural('road', $figures['incomplete']) }}
    {{ $figures['incomplete'] === 1 ? 'has' : 'have' }} a paved and unpaved
    length that does not add up to the total. That is often a partial
    survey rather than an error &mdash; the figure is shown so the gap can
    be filled rather than overlooked.
  </div>
@endif

@endif

@endsection

@push('styles')
<style>
  .kpi-row{display:grid;gap:10px;grid-template-columns:repeat(2,1fr);margin-bottom:16px}
  .kpi{background:var(--white);box-shadow:var(--shadow-sm);padding:12px 15px;
       border-top:3px solid var(--primary);display:block}
  .kpi.good{border-top-color:var(--success)}
  .kpi.bad{border-top-color:var(--danger)}
  .kpi.accent{border-top-color:var(--secondary)}
  .k-label{display:block;font-family:var(--f-head);font-size:8.5px;font-weight:600;
           letter-spacing:.9px;text-transform:uppercase;color:var(--tertiary);line-height:1.3}
  .k-value{display:block;font-family:var(--f-head);font-size:23px;font-weight:800;
           color:var(--ink);line-height:1.1;margin-top:2px}
  .k-value i{font-style:normal;font-size:12px;color:var(--tertiary);margin-left:2px}
  .kpi.good .k-value{color:var(--success)}
  .kpi.bad .k-value{color:var(--danger)}
  .kpi.accent .k-value{color:var(--primary)}
  .k-note{display:block;font-size:10px;color:var(--body-text);margin-top:1px}

  @media(min-width:700px){.kpi-row{grid-template-columns:repeat(3,1fr)}}
  @media(min-width:1100px){.kpi-row{grid-template-columns:repeat(6,1fr)}}

  /* ---- Category bars ---- */
  .rd-row{display:grid;grid-template-columns:minmax(120px,180px) 1fr 60px;gap:12px;
          align-items:center;padding:9px 0;border-bottom:1px solid var(--border)}
  .rd-row:last-of-type{border-bottom:none}
  .rd-name{font-family:var(--f-head);font-size:13px;font-weight:600;color:var(--ink);
           line-height:1.3}
  .rd-name span{display:block;font-family:var(--f-body);font-weight:400;
                font-size:11px;color:var(--tertiary)}
  .rd-bar{display:flex;height:20px;background:var(--canvas);overflow:hidden}
  .rd-paved{background:var(--success)}
  .rd-unpaved{background:var(--tertiary)}
  .rd-km{text-align:right;font-family:var(--f-head);font-size:13px;font-weight:700;
         color:var(--ink);font-variant-numeric:tabular-nums}

  .rd-key{display:flex;gap:14px;align-items:center;margin-top:14px;padding-top:12px;
          border-top:1px solid var(--border);font-family:var(--f-head);font-size:9.5px;
          font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--tertiary)}
  .rd-key i{display:inline-block;width:10px;height:10px;margin-right:5px;vertical-align:-1px}
  .k-paved{background:var(--success)}
  .k-unpaved{background:var(--tertiary)}
  .rd-unit{margin-left:auto;text-transform:none;letter-spacing:0;font-weight:400}

  .gap{font-size:10.5px;color:var(--danger);font-family:var(--f-head);font-weight:600}
  .mini{font-size:11px;color:var(--tertiary)}

  .fb-add{display:inline-flex;align-items:center;height:35px;padding:0 15px;
          background:var(--success);color:#fff;text-decoration:none;
          font-family:var(--f-head);font-size:10.5px;font-weight:600;letter-spacing:.8px;
          text-transform:uppercase;transition:background .2s;white-space:nowrap}
  .fb-add:hover{background:var(--success-hover)}
  .fb-group input[type=text]{width:100%;padding:8px 11px;border:1px solid var(--border);
                             font-family:var(--f-body);font-size:13px;height:35px}
  .fb-group input[type=text]:focus{outline:none;border-color:var(--primary)}

  @media(max-width:920px){.grid2{grid-template-columns:1fr}}
</style>
@endpush