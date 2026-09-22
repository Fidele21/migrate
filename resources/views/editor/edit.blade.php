@extends('layouts.app')
@section('title', 'Edit — ' . $document->title)

@section('content')

@php
  $isLetter = $document->type === 'letter';
  $assets = \Illuminate\Support\Facades\DB::table('document_assets')
      ->where('document_id', $document->id)->orderByDesc('id')->get();
@endphp

<div class="page-head no-print">
  <div>
    <div class="crumb">
      {{ $isLetter ? 'Enforcement correspondence' : 'Inspection report' }} &middot; Editing
      @if($document->case_reference) &middot; Case {{ $document->case_reference }} @endif
    </div>
    <h2>{{ $document->title }}</h2>
    <p>
      @if($document->is_manual)
        <strong>Edited manually</strong> — this document no longer follows the inspection.
        @if($document->imported_from) Imported from {{ $document->imported_from }}. @endif
      @else
        Follows the standard wording generated from the inspection.
      @endif
    </p>
  </div>
</div>

{{-- ---------- Toolbar ---------- --}}
<div class="ed-actions no-print">
  <div class="ea-group">
    <button type="button" class="btn btn-success" id="btn-save">Save</button>
    <a href="{{ $backUrl }}" class="btn btn-ghost">Done</a>
  </div>
  <div class="ea-group">
    <button type="button" class="btn btn-ghost" id="btn-import">Import Word</button>
    <label class="btn btn-ghost" style="margin:0;cursor:pointer;text-transform:uppercase">
      Attach File
      <input type="file" id="attach-input" hidden accept=".pdf,.doc,.docx,image/*">
    </label>
    <form method="post" action="{{ route('editor.regenerate', $document) }}" style="display:inline"
          onsubmit="return confirm('Rebuild from the inspection? Manual edits will be discarded.')">
      @csrf<button type="submit" class="btn btn-ghost">Rebuild</button>
    </form>
  </div>
  <div class="ed-status" id="ed-status">Ready</div>
</div>

@if(session('status'))
  <div class="note no-print" style="border-left-color:var(--success);background:#E8F5E9">
    <strong style="color:#2E7D32">Done</strong>{{ session('status') }}
  </div>
@endif
@if($errors->any())
  <div class="callout no-print"><b>Could not proceed</b>
    <p style="font-size:14px;font-weight:400">{{ $errors->first() }}</p></div>
@endif

<div class="ed-hint no-print">
  Paste from Word directly, or use <strong>Import Word</strong> to replace the whole document.
  Drag an image onto the page to place it. Use <strong>Attach File</strong> for a PDF or a
  signed copy that should travel with the document rather than be retyped.
</div>

<input type="file" id="docx-input" accept=".docx" hidden>

<form method="post" action="{{ route('editor.update', $document) }}" id="editor-form">
  @csrf @method('PUT')
  <input type="hidden" name="imported_from" id="imported_from" value="{{ $document->imported_from }}">
  <textarea name="content_html" id="editor">{!! $document->content_html !!}</textarea>
</form>

<form method="post" action="{{ route('document.attach', $document) }}" id="attach-form"
      enctype="multipart/form-data" style="display:none">@csrf</form>

{{-- ---------- Attachments ---------- --}}
@if($assets->count())
<section class="no-print">
  <h3>Attachments</h3>
  <div class="desc">Held whole and travelling with this document. A PDF is never retyped.</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>File</th><th>Size</th><th>Added</th><th></th></tr></thead>
      <tbody>
        @foreach($assets as $a)
          <tr>
            <td><strong>{{ $a->original_name ?: basename($a->path) }}</strong></td>
            <td style="font-size:12.5px">{{ $a->size_bytes ? number_format($a->size_bytes / 1024) . ' KB' : '—' }}</td>
            <td style="font-size:12.5px">{{ \Carbon\Carbon::parse($a->created_at)->format('j M Y, H:i') }}</td>
            <td class="num">
              <a href="{{ Storage::disk('public')->url($a->path) }}" target="_blank" class="btn btn-ghost btn-sm">Open</a>
              <form method="post" action="{{ route('document.detach', [$document, $a->id]) }}"
                    style="display:inline" onsubmit="return confirm('Remove this attachment?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm">Remove</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif


@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script>
(function () {
  var status = document.getElementById('ed-status');
  var form   = document.getElementById('editor-form');

  function setStatus(t, c) { status.textContent = t; status.className = 'ed-status ' + (c || ''); }

  tinymce.init({
    selector: '#editor',
    height: 900,
    menubar: 'edit view insert format table',
    branding: false, promotion: false,
    plugins: 'lists advlist table link image searchreplace pagebreak charmap ' +
             'visualblocks preview fullscreen wordcount code',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor | ' +
             'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
             'table image link pagebreak | removeformat searchreplace fullscreen code',

    font_family_formats:
      'Times New Roman=times new roman,times,serif;Merriweather=merriweather,georgia,serif;' +
      'Montserrat=montserrat,sans-serif;Calibri=calibri,sans-serif;' +
      'Arial=arial,helvetica,sans-serif;Garamond=garamond,serif;Georgia=georgia,serif',
    font_size_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 30pt 36pt',

    content_style:
      '@import url("https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Montserrat:wght@500;600;700;800&display=swap");' +
      'body{background:#F4F5F3;margin:0;padding:24px}' +
      '.page{background:#fff;width:21cm;min-height:29.7cm;padding:2.5cm 2.2cm;margin:0 auto;' +
      'box-shadow:0 2px 14px rgba(0,0,0,.12);font-family:"Times New Roman",serif;' +
      'font-size:12pt;line-height:1.6;color:#000}' +
      '.page img{max-width:100%;height:auto}table{border-collapse:collapse}' +
      'td,th{border:1px solid #999;padding:4pt 6pt}' +
      '.u-bold{font-weight:bold;text-decoration:underline}' +
      '@media(max-width:900px){.page{width:auto;padding:1.4cm 1cm}}',

    paste_data_images: true,
    paste_webkit_styles: 'all',
    automatic_uploads: true,

    images_upload_handler: function (blobInfo) {
      return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('file', blobInfo.blob(), blobInfo.filename());
        fd.append('_token', '{{ csrf_token() }}');
        fetch('{{ route('editor.image', $document) }}', { method: 'POST', body: fd })
          .then(function (r) { return r.ok ? r.json() : Promise.reject('Upload failed'); })
          .then(function (j) { resolve(j.location); })
          .catch(function (e) { reject({ message: String(e), remove: true }); });
      });
    },

    setup: function (ed) {
      ed.on('Dirty', function () { setStatus('Unsaved changes', ''); });
      ed.on('init',  function () { setStatus('Ready', ''); });
    }
  });

  function save() {
    setStatus('Saving…', 'saving');
    tinymce.triggerSave();
    fetch(form.action, {
      method: 'POST', body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
    .then(function (j) { setStatus('Saved at ' + j.at, 'saved'); tinymce.activeEditor.setDirty(false); })
    .catch(function () { setStatus('Could not save — try again', 'error'); });
  }

  document.getElementById('btn-save').addEventListener('click', save);
  setInterval(function () { if (tinymce.activeEditor && tinymce.activeEditor.isDirty()) save(); }, 120000);

  window.addEventListener('beforeunload', function (e) {
    if (tinymce.activeEditor && tinymce.activeEditor.isDirty()) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- Word import ---------- */
  var input = document.getElementById('docx-input');
  document.getElementById('btn-import').addEventListener('click', function () { input.click(); });

  input.addEventListener('change', function () {
    var file = input.files[0];
    if (!file) return;
    if (!confirm('Replace the current content with "' + file.name + '"?')) { input.value = ''; return; }

    setStatus('Converting ' + file.name + '…', 'saving');
    var reader = new FileReader();

    reader.onload = function (e) {
      mammoth.convertToHtml(
        { arrayBuffer: e.target.result },
        { convertImage: mammoth.images.imgElement(function (image) {
            return image.read('base64').then(function (data) {
              return { src: 'data:' + image.contentType + ';base64,' + data };
            });
          }) })
      .then(function (result) {
        tinymce.activeEditor.setContent('<div class="page">' + result.value + '</div>');
        document.getElementById('imported_from').value = file.name;
        tinymce.activeEditor.setDirty(true);
        var warn = result.messages.filter(function (m) { return m.type === 'warning'; }).length;
        setStatus('Imported ' + file.name + (warn ? ' — ' + warn + ' formatting note(s)' : ''), 'saved');
      })
      .catch(function () { setStatus('Could not read that file', 'error'); })
      .finally(function () { input.value = ''; });
    };
    reader.readAsArrayBuffer(file);
  });

  /* ---------- Attach a PDF or other file ---------- */
  var attach = document.getElementById('attach-input');
  var attachForm = document.getElementById('attach-form');

  attach.addEventListener('change', function () {
    if (!attach.files.length) return;
    var fd = new FormData(attachForm);
    fd.append('file', attach.files[0]);
    setStatus('Attaching ' + attach.files[0].name + '…', 'saving');

    fetch(attachForm.action, {
      method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { if (r.ok) { location.reload(); } else { throw new Error(); } })
    .catch(function () { setStatus('Could not attach that file', 'error'); });
  });
})();
</script>
@endsection

@push('styles')
<style>
  .ed-actions{background:var(--white);padding:14px 20px;margin-bottom:14px;display:flex;
              justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;
              box-shadow:var(--shadow-sm);border-left:4px solid var(--primary);
              position:sticky;top:var(--topbar-h);z-index:60}
  .ea-group{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
  .ed-status{font-family:var(--f-head);font-size:11.5px;font-weight:600;letter-spacing:.7px;
             text-transform:uppercase;color:var(--tertiary)}
  .ed-status.saving{color:var(--warning)} .ed-status.saved{color:var(--success)}
  .ed-status.error{color:var(--danger)}
  .ed-hint{font-size:12.5px;color:var(--body-text);margin-bottom:16px;line-height:1.6}
  .tox-tinymce{border:1px solid var(--border)!important;border-radius:0!important}
</style>
@endpush
