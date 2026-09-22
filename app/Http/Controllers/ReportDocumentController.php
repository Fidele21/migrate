<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The inspection report as an editable document.
 *
 * An inspection produces a report automatically. Opening it for editing
 * creates a Document of type 'report' holding the generated text, which
 * can then be reworded, formatted, given photographs, or replaced
 * wholesale by a Word file the officer already had.
 *
 * The report then travels the same approval chain as the letter and is
 * signed by the inspection team and the District Inspection Unit
 * Director.
 */
class ReportDocumentController extends Controller
{
    /** Find or create the report document for an inspection. */
    public function open(Inspection $inspection)
    {
        abort_unless(auth()->user()->can('document.draft'), 403);
        abort_unless($inspection->status === 'completed', 422,
            'A report can only be prepared from a completed inspection.');
        abort_unless($inspection->conductedBy(auth()->user()), 403,
            'A report may only be edited by an officer who conducted this inspection.');

        $report = Document::firstOrCreate(
            ['inspection_id' => $inspection->id, 'type' => 'report'],
            [
                'status'         => Document::DRAFT,
                'district_id'    => $inspection->district_id,
                'title'          => $inspection->entity->name,
                'subject'        => 'Inspection report',
                'case_reference' => $inspection->case_reference,
                'created_by'     => auth()->id(),
                'current_holder_id' => auth()->id(),
            ]
        );

        return redirect()->route('editor.edit', $report);
    }

    /**
     * Attach a file to a document.
     *
     * A PDF is held as an attachment rather than converted. A signed PDF
     * re-typed into HTML is no longer the document that was signed, and
     * a report that quietly rewrites an attachment would be worse than
     * one that keeps it whole.
     */
    public function attach(Request $request, Document $document)
    {
        abort_unless($document->isEditable(), 422, 'This document can no longer be changed.');
        abort_unless(auth()->user()->can('update', $document), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
        ], [
            'file.mimes' => 'Attach a PDF, a Word document or an image.',
            'file.max'   => 'The file must be no larger than 20 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/' . $document->id . '/attachments', 'public');

        DB::table('document_assets')->insert([
            'document_id'   => $document->id,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'size_bytes'    => $file->getSize(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return back()->with('status', $file->getClientOriginalName() . ' attached.');
    }

    public function detach(Document $document, int $asset)
    {
        abort_unless($document->isEditable(), 422, 'This document can no longer be changed.');
        abort_unless(auth()->user()->can('update', $document), 403);

        $row = DB::table('document_assets')
            ->where('id', $asset)->where('document_id', $document->id)->first();

        abort_unless($row, 404);

        Storage::disk('public')->delete($row->path);
        DB::table('document_assets')->where('id', $asset)->delete();

        return back()->with('status', 'Attachment removed.');
    }
}
