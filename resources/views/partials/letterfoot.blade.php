{{--
  The distribution list and the City's address, closing every letter.

  Who is copied is a matter of record: an enforcement notice is not only
  between the City and the premises, and the list states who else has
  been told.
--}}

@php
  $district = $district ?? '……...';
  $sector   = $sector   ?? '……...';
@endphp

<p style="font-family:Arial;font-size:11pt;margin-top:22pt;margin-bottom:6pt">
  <strong>Bimenyeshejwe:</strong>
</p>

<div style="font-family:Arial;font-size:10.5pt;line-height:1.7">
  @foreach(config('letters.distribution', []) as $line)
    <div>- {{ str_replace([':district', ':sector'], [$district, $sector], $line) }}</div>
  @endforeach
</div>

<p style="font-family:Arial;font-size:11pt;font-weight:bold;margin-top:14pt">KIGALI</p>

<div style="border-top:1pt solid #E0E0E0;margin-top:26pt;padding-top:8pt;
            font-family:Arial;font-size:8.5pt;color:#555;text-align:center">
  Umujyi wa Kigali B.P. 3527 Kigali &nbsp;|&nbsp; Hotline: 3260 &nbsp;|&nbsp;
  Info@kigalicity.gov.rw &nbsp;|&nbsp; www.kigalicity.gov.rw
</div>
