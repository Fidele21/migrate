{{-- Starting content for a letter, rendered once then freely editable. --}}
@php
  $cfg  = config('letters');
  $type = $cfg['types'][$letter->letter_type] ?? $cfg['types']['enforcement'];
  $e    = $inspection->entity;

  $failed = $inspection->answers->where('status', 'no')
      ->map(fn ($a) => $a->item?->label)->filter()->values();

  $sub = fn (string $t) => strtr($t, [
      ':authority'       => $cfg['authority'][$inspection->type_code] ?? $cfg['authority']['building'],
      ':inspection_date' => $inspection->inspection_date->translatedFormat('j F Y'),
      ':premises'        => mb_strtoupper($e->name),
      ':sector'          => $e->sector,
      ':district'        => $e->district,
      ':upi'             => $e->upis->first()->upi ?? $e->upi ?? '—',
      ':deadline'        => $letter->deadline_days ?? $type['deadline'],
      ':prior_ref'       => $letter->prior_reference ?? '—',
      ':prior_date'      => $letter->prior_date?->format('d/m/Y') ?? '—',
      ':reply_date'      => $letter->reply_date?->format('d/m/Y') ?? '—',
  ]);

  $signatory = \App\Models\User::role($cfg['signatory']['role'])->where('is_active', true)->first();
@endphp
<div class="page">
<p>Ref N<sup>o</sup> {{ $letter->reference_number ?: str_repeat('.', 22) }}/{{ $cfg['reference']['registry_code'] }}/{{ now()->format('y') }}</p>

<p style="text-align:right">Kigali, ku wa {{ $letter->issued_at?->format('d/m/Y') ?: str_repeat('.', 16) }}</p>

<p><strong>{{ mb_strtoupper($e->owner ?: $e->name) }}</strong>@if($e->telephone)<br><strong>Tel: {{ $e->telephone }}</strong>@endif</p>

<p><span class="u-bold">KIGALI</span></p>

<p><span class="u-bold">Impamvu</span>: {{ $letter->subject }}</p>

<p>{{ $cfg['salutation'][$letter->salutation ?? 'company'] }}</p>

@foreach($type['body'] as $clause)
  @if($clause === 'requirements' && $failed->count())
<p style="text-align:justify">{{ $sub($cfg['clauses']['requirements']) }}</p>
<ol>
@foreach($failed as $l)  <li>{{ rtrim($l, '. ') }};</li>
@endforeach
</ol>
  @else
<p style="text-align:justify">{{ $sub($cfg['clauses'][$clause] ?? '') }}</p>
  @endif
@endforeach

@if($letter->body_html)
<p style="text-align:justify">{{ strip_tags($letter->body_html) }}</p>
@endif

<p>{{ $cfg['closing'] }}</p>

<p>&nbsp;</p>

<p><strong>{{ $signatory?->name ?? '' }}</strong><br><strong>{{ $cfg['signatory']['title'] }}</strong></p>

<p>&nbsp;</p>

<p><span class="u-bold">Bimenyeshejwe:</span></p>
@foreach($cfg['copies'] as $line)
<p style="margin:2pt 0">- {{ strtr($line, [':district' => $e->district]) }}</p>
@endforeach

<p><span class="u-bold">KIGALI</span></p>
</div>
