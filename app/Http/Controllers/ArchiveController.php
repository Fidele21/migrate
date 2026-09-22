<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Inspection;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('inspection.view.own'), 403);

// ---- Determine if we're showing summary or list ----
    // Show summary only when summary=1 is explicitly in the URL
    // Otherwise, if any status or pane is set, show list
    $showSummary = false;
    if ($request->has('summary')) {
        $showSummary = $request->boolean('summary');
    } else {
        // If a status filter is applied, go to list mode
        if ($request->has('letter_status') && $request->query('letter_status') !== 'all') {
            $showSummary = false;
        } elseif ($request->has('report_status') && $request->query('report_status') !== 'all') {
            $showSummary = false;
        } elseif ($request->has('pane')) {
            $showSummary = false;
        } else {
            // If no filters, default to summary
            $showSummary = true;
        }
    }






        // ---- Get filter values ----
        $term     = trim((string) $request->query('q', ''));
        $district = trim((string) $request->query('district', ''));
        $sector   = trim((string) $request->query('sector', ''));
        $cell     = trim((string) $request->query('cell', ''));
        $upi      = trim((string) $request->query('upi', ''));
        $zoning   = trim((string) $request->query('zoning', ''));
        $usage    = trim((string) $request->query('usage', ''));
        $activity = trim((string) $request->query('activity', ''));
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        $letterStatusFilter = $request->query('letter_status', 'all');
        $reportStatusFilter = $request->query('report_status', 'all');

        // ---- Map activity name to type code ----
        $activityCode = null;
        if ($activity) {
            foreach (config('inspection_types.groups') as $group) {
                foreach ($group['types'] as $code => $type) {
                    if (($type['name'] ?? '') === $activity) {
                        $activityCode = $code;
                        break 2;
                    }
                }
            }
            if (!$activityCode) {
                $activityCode = $activity; // fallback
            }
        }

        // ---- LETTERS: Base query ----
        $baseLettersQuery = Document::query()
            ->where('type', 'letter')
            ->when(! $user->can('inspection.view.all') && $user->district_id,
                fn ($q) => $q->where('district_id', $user->district_id))
            ->when($term, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhere('case_reference', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")))
            ->when($dateFrom, fn ($q) => $q->whereDate('issued_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('issued_at', '<=', $dateTo));

        // ---- Apply entity filters via whereHas ----
        $hasEntityFilter = $district || $sector || $cell || $upi || $zoning || $usage;
        if ($hasEntityFilter) {
            $baseLettersQuery->whereHas('inspection.entity', function ($q) use ($district, $sector, $cell, $upi, $zoning, $usage) {
                $q->when($district, fn ($q) => $q->where('district', $district))
                  ->when($sector,   fn ($q) => $q->where('sector', 'like', "%{$sector}%"))
                  ->when($cell,     fn ($q) => $q->where('cell', 'like', "%{$cell}%"))
                  ->when($upi,      fn ($q) => $q->where('upi', 'like', "%{$upi}%"))
                  ->when($zoning,   fn ($q) => $q->where('zoning', 'like', "%{$zoning}%"))
                  ->when($usage,    fn ($q) => $q->where('use_type', 'like', "%{$usage}%"));
            });
        }

        // ---- Apply activity filter (using mapped code) ----
        if ($activityCode) {
            $baseLettersQuery->whereHas('inspection', function ($q) use ($activityCode) {
                $q->where('type_code', $activityCode);
            });
        }

        // ---- REPORTS: Base query ----
        $baseReportsQuery = Inspection::with('entity')
            ->when(! $user->can('inspection.view.all') && $user->district_id,
                fn ($q) => $q->where('district_id', $user->district_id))
            ->when($term, fn ($q) => $q->where(fn ($w) => $w
                ->where('case_reference', 'like', "%{$term}%")
                ->orWhereHas('entity', fn ($e) => $e
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('upi', 'like', "%{$term}%")
                    ->orWhere('owner', 'like', "%{$term}%"))))
            ->when($dateFrom, fn ($q) => $q->whereDate('inspection_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('inspection_date', '<=', $dateTo))
            ->when($activityCode, fn ($q) => $q->where('type_code', $activityCode));

        // ---- Apply entity filters on reports ----
        if ($hasEntityFilter) {
            $baseReportsQuery->whereHas('entity', function ($q) use ($district, $sector, $cell, $upi, $zoning, $usage) {
                $q->when($district, fn ($q) => $q->where('district', $district))
                  ->when($sector,   fn ($q) => $q->where('sector', 'like', "%{$sector}%"))
                  ->when($cell,     fn ($q) => $q->where('cell', 'like', "%{$cell}%"))
                  ->when($upi,      fn ($q) => $q->where('upi', 'like', "%{$upi}%"))
                  ->when($zoning,   fn ($q) => $q->where('zoning', 'like', "%{$zoning}%"))
                  ->when($usage,    fn ($q) => $q->where('use_type', 'like', "%{$usage}%"));
            });
        }

        // ---- LETTERS BY STATUS ----
        $letterStatuses = ['draft', 'revision', 'signed', 'stamped', 'issued'];
        $lettersByStatus = [];
        foreach ($letterStatuses as $status) {
            $q = clone $baseLettersQuery;
            switch ($status) {
                case 'draft':
                    $q->where('status', 'draft')
                      ->whereNull('signature_stage')
                      ->whereNull('scan_path')
                      ->whereNull('issued_at');
                    break;
                case 'revision':
                    $q->where('status', 'draft')
                      ->where('signature_stage', 'pending_director')
                      ->whereNull('scan_path')
                      ->whereNull('issued_at');
                    break;
                case 'signed':
                    $q->where('status', 'approved')
                      ->whereNull('scan_path')
                      ->whereNull('issued_at');
                    break;
                case 'stamped':
                    $q->where('status', 'approved')
                      ->whereNotNull('scan_path')
                      ->whereNull('issued_at');
                    break;
                case 'issued':
                    $q->where('status', Document::ISSUED);
                    break;
            }
            $q->whereNull('deleted_at');
            $lettersByStatus[$status] = $q->count();
        }

        // ---- REPORTS BY STATUS ----
        $reportStatuses = ['draft', 'completed', 'submitted', 'signed'];
        $reportsByStatus = [];
        foreach ($reportStatuses as $status) {
            $q = clone $baseReportsQuery;
            switch ($status) {
                case 'draft':
                    $q->where('status', 'draft');
                    break;
                case 'completed':
                    $q->where('status', 'completed')
                      ->whereDoesntHave('documents', fn($d) => $d->where('type', 'report')->whereNotNull('signature_stage'));
                    break;
                case 'submitted':
                    $q->where('status', 'completed')
                      ->whereHas('documents', fn($d) => $d->where('type', 'report')->where('signature_stage', 'pending_signatures'));
                    break;
                case 'signed':
                    $q->where('status', 'completed')
                      ->whereHas('documents', fn($d) => $d->where('type', 'report')->where('status', 'approved')->where('signature_stage', 'signed'));
                    break;
            }
            $q->whereNull('deleted_at');
            $reportsByStatus[$status] = $q->count();
        }

        // ---- GET RECORDS FOR THE ACTIVE PANE ----
        $activePane = $request->query('pane', 'letters');

        // Letters
        $lettersQuery = clone $baseLettersQuery;
        if ($letterStatusFilter !== 'all' && in_array($letterStatusFilter, $letterStatuses)) {
            switch ($letterStatusFilter) {
                case 'draft':
                    $lettersQuery->where('status', 'draft')
                        ->whereNull('signature_stage')
                        ->whereNull('scan_path')
                        ->whereNull('issued_at');
                    break;
                case 'revision':
                    $lettersQuery->where('status', 'draft')
                        ->where('signature_stage', 'pending_director')
                        ->whereNull('scan_path')
                        ->whereNull('issued_at');
                    break;
                case 'signed':
                    $lettersQuery->where('status', 'approved')
                        ->whereNull('scan_path')
                        ->whereNull('issued_at');
                    break;
                case 'stamped':
                    $lettersQuery->where('status', 'approved')
                        ->whereNotNull('scan_path')
                        ->whereNull('issued_at');
                    break;
                case 'issued':
                    $lettersQuery->where('status', Document::ISSUED);
                    break;
            }
        }
        $lettersQuery->whereNull('deleted_at');
        $letters = $lettersQuery->orderByDesc('issued_at')->get();

        // Reports
        $reportsQuery = clone $baseReportsQuery;
        if ($reportStatusFilter !== 'all' && in_array($reportStatusFilter, $reportStatuses)) {
            switch ($reportStatusFilter) {
                case 'draft':
                    $reportsQuery->where('status', 'draft');
                    break;
                case 'completed':
                    $reportsQuery->where('status', 'completed')
                        ->whereDoesntHave('documents', fn($d) => $d->where('type', 'report')->whereNotNull('signature_stage'));
                    break;
                case 'submitted':
                    $reportsQuery->where('status', 'completed')
                        ->whereHas('documents', fn($d) => $d->where('type', 'report')->where('signature_stage', 'pending_signatures'));
                    break;
                case 'signed':
                    $reportsQuery->where('status', 'completed')
                        ->whereHas('documents', fn($d) => $d->where('type', 'report')->where('status', 'approved')->where('signature_stage', 'signed'));
                    break;
            }
        }
        $reportsQuery->whereNull('deleted_at');
        $reports = $reportsQuery->orderByDesc('inspection_date')->get();

        // ---- DROPDOWN DATA ----
        $districts = Entity::whereNotNull('district')->distinct()->orderBy('district')->pluck('district');
        $zonings = Entity::whereNotNull('zoning')->distinct()->orderBy('zoning')->pluck('zoning');
        $usages = Entity::whereNotNull('use_type')->distinct()->orderBy('use_type')->pluck('use_type');

        // Activities from config
        $activities = collect(config('inspection_types.groups'))
            ->flatMap(fn($group) => collect($group['types'])->filter(fn($type) => $type['live'] ?? false)->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // ---- Sectors & Cells by district ----
        $sectorsByDistrict = Entity::whereNotNull('district')->whereNotNull('sector')
            ->distinct()->orderBy('district')->orderBy('sector')
            ->get(['district', 'sector'])
            ->groupBy('district')
            ->map(fn($items) => $items->pluck('sector')->unique()->values()->toArray())
            ->toArray();

        $cellsByDistrictSector = Entity::whereNotNull('district')->whereNotNull('sector')->whereNotNull('cell')
            ->distinct()->orderBy('district')->orderBy('sector')->orderBy('cell')
            ->get(['district', 'sector', 'cell'])
            ->groupBy('district')
            ->map(fn($items) => $items->groupBy('sector')->map(fn($cells) => $cells->pluck('cell')->unique()->values()->toArray())->toArray())
            ->toArray();

        return view('archive.index', [
            'letters'        => $letters,
            'reports'        => $reports,
            'activePane'     => $activePane,
            'letterStatusFilter' => $letterStatusFilter,
            'reportStatusFilter' => $reportStatusFilter,
            //'lettersByStatus' => $lettersByStatus,
            //'reportsByStatus' => $reportsByStatus,
            'term'           => $term,
            'district'       => $district,
            'sector'         => $sector,
            'cell'           => $cell,
            'upi'            => $upi,
            'zoning'         => $zoning,
            'usage'          => $usage,
            'activity'       => $activity,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'districts'      => $districts,
            'zonings'        => $zonings,
            'usages'         => $usages,
            'activities'     => $activities,
            'sectorsByDistrict' => $sectorsByDistrict,
            'cellsByDistrictSector' => $cellsByDistrictSector,
            'showSummary'    => $showSummary,          // <-- Add this
            'letterCounts'   => $lettersByStatus,      // <-- rename to match view
            'reportCounts'   => $reportsByStatus,      // <-- rename to match view
        ]);
    }

    public function case(string $reference)
    {
        $user = auth()->user();
        abort_unless($user->can('inspection.view.own'), 403);

        $inspections = Inspection::with(['entity', 'inspector'])
            ->where('case_reference', $reference)->get();

        abort_if($inspections->isEmpty(), 404, 'No case with that reference.');

        $letters = Document::where('case_reference', $reference)
            ->orderBy('created_at')->get();

        $fines = class_exists(\App\Models\Fine::class)
            ? \App\Models\Fine::where('case_reference', $reference)->get()
            : collect();

        return view('archive.case', compact('reference', 'inspections', 'letters', 'fines'));
    }

    public function restoreInspection($id)
    {
        $inspection = Inspection::onlyTrashed()->findOrFail($id);
        $inspection->restore();

        Document::onlyTrashed()->where('inspection_id', $id)->restore();

        return redirect()->back()->with('success', 'Inspection and its documents restored.');
    }

    public function restoreDocument($id)
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $document->restore();

        return redirect()->back()->with('success', 'Letter restored.');
    }
}