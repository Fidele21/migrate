@extends('layouts.app')
@section('title', 'Case ' . $reference)

@section('content')

<div class="page-head">
  <div>
    <div class="crumb">E-Archive</div>
    <h2>Case {{ $reference }}</h2>
    <p>{{ $inspections->first()?->entity->name }} &middot;
       {{ $inspections->count() }} {{ Str::plural('inspection', $inspections->count()) }} &middot;
       {{ $letters->count() }} {{ Str::plural('letter', $letters->count()) }}
       @if($fines->count()) &middot; {{ $fines->count() }} {{ Str::plural('fine', $fines->count()) }}@endif</p>
  </div>
  <div class="head-actions">
    <a href="{{ route('archive.index') }}" class="btn btn-ghost">Archive</a>
  </div>
</div>

<section>
  <h3>Inspections</h3>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Date</th><th>Visit</th><th>Inspector</th><th style="text-align:right">Compliance</th><th></th></tr></thead>
      <tbody>
        @foreach($inspections as $i)
          <tr>
            <td>{{ $i->inspection_date->format('j M Y') }}</td>
            <td>{{ $i->visit_number }}@if($i->visit_type === 'followup') <span class="pill weak">Follow-up</span>@endif</td>
            <td style="font-size:12.5px">{{ $i->inspector_name }}</td>
            <td class="num"><span class="pill {{ \App\Services\InspectionStats::band((float) $i->compliance) }}">{{ round((float) $i->compliance, 1) }}%</span></td>
            <td class="num">
              <a href="{{ route('inspection.show', $i) }}" class="btn btn-ghost btn-sm">Report</a>
              <a href="{{ route('inspection.word', $i) }}" class="btn btn-ghost btn-sm">Word</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<section>
  <h3>Correspondence</h3>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Issued</th><th>Stamped copy</th><th></th></tr></thead>
      <tbody>
        @forelse($letters as $l)
          <tr>
            <td><strong>{{ $l->reference_number ?: 'Not referenced' }}</strong></td>
            <td style="font-size:12.5px">{{ config('letters.types.'.$l->letter_type.'.name', ucfirst($l->letter_type ?? 'Letter')) }}</td>
            <td><span class="pill {{ $l->status === 'issued' ? 'good' : 'fair' }}">{{ Str::headline($l->status) }}</span></td>
            <td style="font-size:12.5px">{{ $l->issued_at?->format('j M Y') ?: '—' }}</td>
            <td>@if($l->scan_path)
                  <a href="{{ Storage::disk('public')->url($l->scan_path) }}" target="_blank" class="pill good">View</a>
                @else <span class="pill weak">—</span>@endif</td>
            <td class="num">
              <a href="{{ route('letter.show', $l) }}" class="btn btn-ghost btn-sm">Open</a>
              <a href="{{ route('letter.docx', $l) }}" class="btn btn-ghost btn-sm">Word</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" style="text-align:center;color:var(--tertiary);padding:26px">No correspondence on this case.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@if($fines->count())
<section>
  <h3>Fines</h3>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Reference</th><th style="text-align:right">Amount</th>
                 <th style="text-align:right">Outstanding</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach($fines as $f)
          <tr>
            <td><strong>{{ $f->reference }}</strong></td>
            <td class="num">{{ number_format((float) $f->amount) }}</td>
            <td class="num">{{ $f->isPayable() ? number_format($f->outstanding()) : '—' }}</td>
            <td><span class="pill {{ \App\Models\Fine::statusTone($f->status) }}">{{ Str::headline($f->status) }}</span></td>
            <td class="num"><a href="{{ route('fine.show', $f) }}" class="btn btn-ghost btn-sm">Open</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@endsection
