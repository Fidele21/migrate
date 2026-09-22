<?php

namespace App\Http\Controllers;
use App\Http\Controllers\LetterTemplateController;
use App\Models\Document;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Full editing of letters and reports.
 *
 * The document is generated from the standard clauses, then opened for
 * free editing: any wording, fonts, sizes, images, imported Word
 * material. Editing is permitted only while the document is a draft or
 * has been returned — once signed, the content hash freezes it.
 */
class EditorController extends Controller
{
    /**
     * Open the editor. Generates the starting content on first use.
     *
     * A letter that has an approved template is not edited freely here.
     * Opening this page used to write a competing version of the letter
     * into content_html the moment it loaded — before anyone typed
     * anything — and once that happened, the review and show screens
     * would silently switch to displaying it instead of the version the
     * template produces. Two renderers for the same letter, agreeing by
     * accident or not at all.
     *
     * So a templated letter is edited through its own structured form —
     * subject, deadline, recommendations — the fields already agreed as
     * the editable parts, which feed the one template that also
     * produces the download. This general editor remains exactly as
     * before for reports, and for any letter that has no approved
     * template (an imported document, or a type still awaiting one),
     * where free-text editing is the only option there is.
     */
    public function edit(Document $document)
    {
        abort_unless($document->isEditable(), 422,
            'This document has been signed and can no longer be edited.');
        abort_unless(auth()->user()->can('update', $document), 403);

        if ($document->type === 'letter'
            && LetterTemplateController::isExportable($document->letter_type)) {
            return redirect()->route('letter.edit', $document);
        }

        $inspection = Inspection::with(['entity.upis', 'template.sections.items', 'answers.item', 'team', 'photos'])
            ->find($document->inspection_id);

        // First open: render the standard text and freeze it into the document.
        if (blank($document->content_html)) {
            $document->content_html = $this->generate($document, $inspection);
            $document->save();
        }

        return view('editor.edit', [
            'document'   => $document,
            'inspection' => $inspection,
            'backUrl'    => $document->type === 'letter'
                ? route('letter.show', $document)
                : route('inspection.show', $document->inspection_id),
        ]);
    }
    public function update(Request $request, Document $document)
    {
        abort_unless($document->isEditable(), 422, 'This document can no longer be edited.');
        abort_unless(auth()->user()->can('update', $document), 403);

        $data = $request->validate([
            'content_html'  => ['required', 'string', 'max:2000000'],
            'imported_from' => ['nullable', 'string', 'max:200'],
        ]);

        $document->forceFill([
            'content_html'  => $this->clean($data['content_html']),
            'is_manual'     => true,
            'edited_at'     => now(),
            'edited_by'     => auth()->id(),
            'imported_from' => $data['imported_from'] ?? $document->imported_from,
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['saved' => true, 'at' => now()->format('H:i')]);
        }

        return redirect()->route($document->type === 'letter' ? 'letter.show' : 'inspection.show',
                $document->type === 'letter' ? $document : $document->inspection_id)
            ->with('status', 'Document saved.');
    }

    /** Discard manual edits and rebuild from the inspection. */
    public function regenerate(Document $document)
    {
        abort_unless($document->isEditable(), 422, 'This document can no longer be edited.');
        abort_unless(auth()->user()->can('update', $document), 403);

        $inspection = Inspection::with(['entity.upis', 'template.sections.items', 'answers.item', 'team', 'photos'])
            ->find($document->inspection_id);

        $document->forceFill([
            'content_html'  => $this->generate($document, $inspection),
            'is_manual'     => false,
            'imported_from' => null,
            'edited_at'     => now(),
            'edited_by'     => auth()->id(),
        ])->save();

        return redirect()->route('editor.edit', $document)
            ->with('status', 'Rebuilt from the inspection. Manual edits have been discarded.');
    }

    /**
     * Store an image dropped or pasted into the editor.
     *
     * Files go to disk with a path recorded, rather than being embedded
     * as base64 — which would make every document and every backup
     * many times larger than necessary.
     */
    public function uploadImage(Request $request, Document $document)
    {
        abort_unless(auth()->user()->can('update', $document), 403);

        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/' . $document->id, 'public');

        DB::table('document_assets')->insert([
            'document_id'   => $document->id,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes'    => $file->getSize(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['location' => Storage::disk('public')->url($path)]);
    }

    /* ============================================================== */

    /** Render the standard document to HTML, ready for editing. */
    private function generate(Document $document, ?Inspection $inspection): string
    {
        if (! $inspection) {
            return '<p></p>';
        }

        $view = $document->type === 'letter' ? 'editor.source-letter' : 'editor.source-report';

        return view($view, [
            'document'   => $document,
            'letter'     => $document,
            'inspection' => $inspection,
            'answers'    => $inspection->answers->keyBy('item_id'),
        ])->render();
    }

    /**
     * Remove anything that should not survive into a stored document.
     *
     * Word paste brings a great deal of markup with it, and scripts must
     * never be stored in a document that other officers will open.
     */
    private function clean(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);
        $html = preg_replace('#<!--\[if[^\]]*\]>.*?<!\[endif\]-->#is', '', $html);

        return trim($html);
    }
}
