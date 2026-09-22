<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The fine register as a spreadsheet.
 *
 * Written as CSV with a byte order mark rather than through a library.
 * Excel opens it, the unit can sort and total it, and it needs no
 * dependency on a host where composer cannot run.
 *
 * The mark matters: without it Excel reads the file as Latin-1 and every
 * Kinyarwanda name with an apostrophe or accent comes out wrong.
 */
class FineExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user->can('fine.view'), 403);

        $query = Fine::with(['entity', 'inspection.faults.fault', 'document'])
            ->orderBy('district_id')
            ->orderBy('reference');

        if (! $user->can('inspection.view.all')) {
            $query->where('district_id', $user->district_id);
        }

        if ($s = $request->query('status'))   $query->where('status', $s);
        if ($f = $request->query('from'))     $query->whereDate('proposed_at', '>=', $f);
        if ($t = $request->query('to'))       $query->whereDate('proposed_at', '<=', $t);

        if ($d = $request->query('district')) {
            $query->whereHas('entity', fn ($q) => $q->where('district', $d));
        }

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(fn ($w) => $w
                ->where('reference', 'like', "%{$term}%")
                ->orWhereHas('entity', fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('owner', 'like', "%{$term}%")
                    ->orWhere('upi', 'like', "%{$term}%")));
        }

        $fines = $query->get();

        $name = 'Fines-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($fines) {
            $out = fopen('php://output', 'w');

            /* Excel reads a file without this as Latin-1. */
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'No', 'Owner', 'Entity', 'Telephone', 'UPI',
                'District', 'Sector', 'Cell', 'Building category',
                'Fault', 'Fine amount (FRW)',
                'Date of inspection', 'Letter status', 'Date letter issued',
                'Fine status', 'Paid (FRW)', 'Outstanding (FRW)', 'Due date',
            ]);

            foreach ($fines as $n => $fine) {
                $e = $fine->entity;
                $i = $fine->inspection;

                /* Every fault on one line, because a spreadsheet row is a
                   fine and a fine may rest on several. Splitting them
                   across rows would double-count the amount. */
                $faults = $i?->faults
                    ->map(fn ($f) => $f->fault?->title ?? '—')
                    ->implode('; ') ?: '—';

                $letter = $fine->document;

                fputcsv($out, [
                    $n + 1,
                    $e?->owner ?: '—',
                    $e?->name ?: '—',
                    $e?->telephone ?: '—',
                    $e?->upi ?: '—',
                    $e?->district ?: '—',
                    $e?->sector ?: '—',
                    $e?->cell ?: '—',
                    $i?->building_category ?: '—',
                    $faults,
                    number_format((float) $fine->amount, 0, '.', ''),
                    $i?->inspection_date?->format('Y-m-d') ?: '—',
                    $letter?->issued_at ? 'Issued' : 'Not issued',
                    $letter?->issued_at?->format('Y-m-d') ?: '—',
                    ucfirst(str_replace('_', ' ', $fine->status)),
                    number_format((float) $fine->amount_paid, 0, '.', ''),
                    number_format($fine->outstanding(), 0, '.', ''),
                    $fine->due_date?->format('Y-m-d') ?: '—',
                ]);
            }

            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
