{{--
  The City of Kigali letterhead.

  Letters only. A report is the City's internal record of findings; a
  letter is correspondence going out under the City's name, and only the
  second carries a letterhead.

  The artwork spans the full width of the page, across the margins, as it
  does on the printed stationery.

  $forWord — true for a Word export, where the image has to travel inside
             the file rather than be fetched over the network.
--}}

@php
  $forWord = $forWord ?? false;
  $logo    = null;

  $path = storage_path('app/public/branding/letterhead.png');

  if (is_file($path) && filesize($path) < 2_000_000) {
      $logo = $forWord
          ? 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,'
            . base64_encode(file_get_contents($path))
          : \Illuminate\Support\Facades\Storage::disk('public')->url('branding/letterhead.png');
  }
@endphp

@if($logo)
  <div class="letterhead">
    <img src="{{ $logo }}" alt="City of Kigali">
  </div>
@else
  {{-- Stand-in until the official artwork is in place --}}
  <table style="width:100%;border-collapse:collapse;margin-bottom:10pt">
    <tr>
      <td style="width:78pt;vertical-align:middle;text-align:center">
        <div style="width:64pt;height:64pt;background:#34A8DB;color:#fff;
                    font-family:Montserrat,Arial,sans-serif;font-weight:bold;
                    font-size:15pt;line-height:64pt;text-align:center">CoK</div>
      </td>
      <td style="vertical-align:middle;padding-left:12pt">
        <div style="font-family:Montserrat,Arial,sans-serif;font-size:8.5pt;
                    letter-spacing:2pt;color:#CDB896;font-weight:bold">REPUBLIC OF RWANDA</div>
        <div style="font-family:Montserrat,Arial,sans-serif;font-size:19pt;font-weight:bold;
                    color:#34A8DB;line-height:1.15">CITY OF KIGALI</div>
        <div style="font-family:Montserrat,Arial,sans-serif;font-size:9.5pt;color:#555">
          Directorate of Inspection
        </div>
      </td>
    </tr>
  </table>
  <div style="border-bottom:2pt solid #34A8DB;margin-bottom:14pt"></div>
@endif
