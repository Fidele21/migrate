<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\Document;
use App\Models\DocumentTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * The registry — the Secretary's stage.
 *
 * Once the Chief Inspector has signed, the letter comes here to be
 * given its reference number, printed, stamped, scanned back in and
 * dispatched to the premises owner.
 *
 * The reference is assigned at this point rather than at drafting, so a
 * letter that is abandoned never consumes a number from the registry.
 */
class SecretaryController extends Controller
{
    /** The registry desk: everything awaiting handling. */
    public function desk(Request $request)
    {
        abort_unless(auth()->user()->can('letter.reference'), 403);

        $letters = Document::with(['creator', 'signatures.signer'])
            ->where('type', 'letter')
            ->whereIn('status', [Document::APPROVED, Document::ISSUED])
            ->orderByDesc('approved_at')
            ->get();

        return view('secretary.desk', [
            /**'awaiting'  => $letters->whereNull('reference_number'),*/
            'awaiting' => $letters->filter(fn ($l) => blank($l->reference_number)),
            'toPrint'   => $letters->filter(fn ($l) => $l->reference_number && ! $l->printed_at),
            'toScan'    => $letters->filter(fn ($l) => $l->printed_at && ! $l->scan_path),
            'toDispatch'=> $letters->filter(fn ($l) => $l->scan_path && ! $l->dispatched_at),
            'issued'    => $letters->whereNotNull('dispatched_at'),
            'lastSerial' => $this->previewReference(),
        ]);
    }

    /**
     * Record the reference number taken from the registry.
     *
     * The registry book is kept outside this platform and is shared
     * across the City, so the serial cannot be generated here. The
     * Secretary types the number they have been given; the registry code
     * and the year are fixed.
     */
    public function assignReference(Request $request, Document $letter)
    {
        abort_unless(auth()->user()->can('letter.reference'), 403);

        abort_unless(in_array($letter->status, [Document::APPROVED, Document::ISSUED], true), 422,
            'A reference is recorded only after the Chief Inspector has signed.');

        abort_if($letter->reference_number, 422, 'This letter already carries a reference.');

        $year = now()->format('y');

        $data = $request->validate([
            'serial' => [
                'required', 'integer', 'min:1', 'max:999999',
                function ($attribute, $value, $fail) use ($year) {
                    $exists = Document::where('serial', $value)
                        ->whereYear('issued_at', now()->year)
                        ->exists();
                    if ($exists) {
                        $fail('Number ' . $value . ' has already been used this year.');
                    }
                },
            ],
        ], [
            'serial.required' => 'Enter the number from the registry.',
            'serial.integer'  => 'The registry number must be a whole number.',
        ]);

        DB::transaction(function () use ($letter, $data, $year) {

            $serial    = (int) $data['serial'];
            $reference = $serial . '/' . config('letters.reference.registry_code') . '/' . $year;

            $inspection = \App\Models\Inspection::find($letter->inspection_id);
            $case = $inspection?->case_reference ?: $reference;

            $letter->forceFill([
                'serial'           => $serial,
                'reference_number' => $reference,
                'case_reference'   => $case,
                'referenced_at'    => now(),
                'referenced_by'    => auth()->id(),
                'issued_at'        => $letter->issued_at ?? now(),
            ])->save();
            
                    /* The fine follows the letter. Confirming it here rather than on
           a separate screen: the Secretary is attesting that what went
           out carries this figure, which is exactly what a confirmation
           means, and a step that can be forgotten is a fine that never
           becomes payable. */
           $this->confirmFineFor($letter);

           if ($inspection && blank($inspection->case_reference)) {
                $inspection->forceFill(['case_reference' => $case])->save();
            }

            if ($inspection) {
                \App\Models\Fine::where('inspection_id', $inspection->id)
                    ->whereNull('case_reference')
                    ->update(['case_reference' => $case]);
            }

            $this->log($letter, 'reference.assigned',
                'Reference ' . $reference . ' recorded from the registry'
                . ($case !== $reference ? ' on case ' . $case : ' — case opened') . '.');
        });

       /* return back()->with('status', 'Reference ' . $letter->reference_number . ' recorded.');*/
        
        
                $fine = \App\Models\Fine::where('inspection_id', $letter->inspection_id)
            ->where('status', \App\Models\Fine::CONFIRMED)
            ->latest('confirmed_at')->first();

        return redirect()->route('secretary.desk')->with('status',
            'Reference ' . $letter->reference_number . ' recorded.'
            . ($fine && $fine->confirmed_at->isToday()
                ? ' The fine of FRW ' . number_format((float) $fine->amount, 0)
                  . ' is confirmed and now payable.'
                : ''));
        
    }
    /** Record that the letter has been printed for signature and stamping. */
    public function markPrinted(Document $letter)
    {
        abort_unless(auth()->user()->can('letter.print'), 403);
        abort_unless($letter->reference_number, 422, 'Assign a reference before printing.');

        $letter->forceFill(['printed_at' => now()])->save();
        $this->log($letter, 'letter.printed', 'Printed for signature and stamping.');

        return back()->with('status', 'Recorded as printed.');
    }

    /** Upload the signed and stamped copy. */
    public function uploadScan(Request $request, Document $letter)
    {
        abort_unless(auth()->user()->can('letter.upload_scan'), 403);

        $request->validate([
            'scan' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:15360'],
        ], [
            'scan.required' => 'Choose the scanned copy to upload.',
            'scan.mimes'    => 'The scan must be a PDF or an image.',
            'scan.max'      => 'The file must be no larger than 15 MB.',
        ]);

        if ($letter->scan_path) {
            Storage::disk('public')->delete($letter->scan_path);
        }

        $path = $request->file('scan')->store('letters/' . $letter->id, 'public');

        $letter->forceFill(['scan_path' => $path])->save();
        $this->log($letter, 'letter.scanned', 'Signed and stamped copy uploaded.');

        return back()->with('status', 'Scanned copy uploaded.');
    }

    /** Send to the owner and record the dispatch. */
    public function dispatch(Request $request, Document $letter)
    {
        abort_unless(auth()->user()->can('letter.dispatch'), 403);
        abort_unless($letter->reference_number, 422, 'Record the registry reference before dispatching.');
        abort_unless($letter->scan_path, 422, 'Upload the signed copy before dispatching.');
        abort_unless($letter->reference_number, 422,
            'Record the registry reference before dispatching.');

        $data = $request->validate([
            'to'     => ['required', 'email', 'max:180'],
            'cc'     => ['nullable', 'string', 'max:400'],
            'method' => ['required', 'in:email,hand,post,both'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $sent = false;

        if (in_array($data['method'], ['email', 'both'], true)) {
            $sent = $this->send($letter, $data);
        }

        DB::transaction(function () use ($letter, $data, $sent) {
            $letter->forceFill([
                'status'         => Document::ISSUED,
                'dispatched_at'  => now(),
                'dispatched_to'  => $data['to'],
                'issued_at'      => $letter->issued_at ?? now(),
            ])->save();

            $this->log($letter, 'letter.dispatched',
                match ($data['method']) {
                    'email' => 'Sent by email to ' . $data['to'] . ($sent ? '' : ' (delivery not confirmed)'),
                    'hand'  => 'Delivered by hand to ' . $data['to'],
                    'post'  => 'Sent by post to ' . $data['to'],
                    'both'  => 'Sent by email and delivered by hand to ' . $data['to'],
                } . ($data['note'] ? ' — ' . $data['note'] : ''),
                Document::ISSUED
            );
        });

        return redirect()->route('secretary.desk')
            ->with('status', 'Letter ' . $letter->reference_number . ' dispatched to ' . $data['to'] . '.');
    }

    /* ============================================================== */

    /**
     * Attempt delivery. A failure is recorded rather than thrown — the
     * letter has still been issued, and the registry should show that
     * the email did not go through rather than losing the dispatch.
     */
    private function send(Document $letter, array $data): bool
    {
        try {
            $cc = collect(explode(',', (string) ($data['cc'] ?? '')))
                ->map(fn ($e) => trim($e))->filter()->all();

            $path = Storage::disk('public')->path($letter->scan_path);

            Mail::raw($this->emailBody($letter), function ($m) use ($letter, $data, $cc, $path) {
                $m->to($data['to'])
                  ->subject('City of Kigali — Ref ' . $letter->reference_number . ' — ' . $letter->subject);
                if ($cc) {
                    $m->cc($cc);
                }
                if (is_readable($path)) {
                    $m->attach($path);
                }
            });

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
    
        /**
     * Confirm the proposed fine on the inspection this letter belongs to.
     *
     * Silent where there is nothing to confirm — many letters carry no
     * fine, and an enforcement notice for a premises that owes nothing is
     * a normal thing.
     */
    private function confirmFineFor(Document $letter): void
    {
        $fine = \App\Models\Fine::where('inspection_id', $letter->inspection_id)
            ->where('status', \App\Models\Fine::PROPOSED)
            ->first();

        if (! $fine) {
            return;
        }

        $fine->forceFill([
            'status'         => \App\Models\Fine::CONFIRMED,
            'document_id'    => $letter->id,
            'confirmed_by'   => auth()->id(),
            'confirmed_at'   => now(),
            'confirm_note'   => 'Confirmed on assigning reference '
                                . $letter->reference_number . ' to the letter.',
            'reference'      => $fine->reference ?: $this->fineReferenceFor($fine, $letter),
            'case_reference' => $fine->case_reference ?: $letter->case_reference,

            /* Where nothing set a date, thirty days from the letter. A
               fine with no due date can never be overdue, and would sit
               in the register indefinitely without anyone noticing. */
            'due_date'       => $fine->due_date ?: now()->addDays(30),
        ])->save();
    }

    /** A fine takes the letter's reference, so the two can be matched. */
    private function fineReferenceFor(\App\Models\Fine $fine, Document $letter): string
    {
        $base = $letter->reference_number
            . '/' . config('letters.reference.registry_code')
            . '/' . now()->format('y');

        $n = \App\Models\Fine::where('inspection_id', $fine->inspection_id)
            ->whereNotNull('reference')->count() + 1;

        return $n > 1 ? $base . '-F' . $n : $base;
    }

    private function emailBody(Document $letter): string
    {
        return implode("\n\n", [
            'Bwana/Madamu,',
            'Mwakiriye ibaruwa ya Umujyi wa Kigali ifite Ref N° ' . $letter->reference_number . '.',
            'Impamvu: ' . $letter->subject,
            $letter->deadline_date
                ? 'Igihe ntarengwa: ' . $letter->deadline_date->format('d/m/Y') . '.'
                : '',
            'Ibaruwa yuzuye iri ku mugereka.',
            'Mugire amahoro.',
            "Umujyi wa Kigali\nUbugenzuzi",
        ]);
    }

    private function log(Document $letter, string $action, string $comment, ?string $to = null): void
    {
        DocumentTransition::create([
            'document_id' => $letter->id,
            'from_status' => $letter->getOriginal('status') ?? $letter->status,
            'to_status'   => $to ?? $letter->status,
            'actor_id'    => auth()->id(),
            'actor_role'  => auth()->user()->roles->pluck('name')->first() ?? 'Secretary',
            'comment'     => $comment,
            'ip_address'  => request()->ip(),
        ]);
    }

    /** The last number used, offered as a hint only. */
    private function previewReference(): ?int
    {
        return Document::whereYear('issued_at', now()->year)->max('serial');
    }
}
