<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Entity;
use App\Models\Fine;
use App\Models\Inspection;
use Illuminate\Http\Request;

/**
 * Universal search.
 *
 * One box, searching everything an officer might be holding: a case
 * reference from a letter, a UPI from a land document, an owner's name,
 * a telephone number, an email address, or the name of the premises.
 *
 * Because the report, letter and fine on a case now share one reference,
 * typing that number returns the whole case together.
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return view('search', [
                'term'    => '',
                'cases'   => collect(),
                'premises'=> collect(),
                'letters' => collect(),
                'fines'   => collect(),
                'total'   => 0,
            ]);
        }

        $user = auth()->user();
        $like = '%' . $term . '%';

        $scope = function ($query, string $column = 'district_id') use ($user) {
            if (! $user->can('inspection.view.all') && $user->district_id) {
                $query->where($column, $user->district_id);
            }
            return $query;
        };

        /* ---- A case reference returns everything on that case ---- */
        $cases = Inspection::with(['entity', 'inspector'])
            ->where(fn ($q) => $q
                ->where('case_reference', 'like', $like)
                ->orWhereHas('entity', fn ($e) => $e
                    ->where('name', 'like', $like)
                    ->orWhere('upi', 'like', $like)
                    ->orWhere('owner', 'like', $like)))
            ->tap(fn ($q) => $scope($q))
            ->orderByDesc('inspection_date')
            ->limit(40)->get();

        /* ---- Premises ---- */
        $premises = Entity::withCount('inspections')
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('upi', 'like', $like)
                ->orWhere('owner', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('telephone', 'like', $like)
                ->orWhereHas('upis', fn ($u) => $u->where('upi', 'like', $like)))
            ->orderBy('name')
            ->limit(40)->get();

        /* ---- Letters ---- */
        $letters = Document::with('creator')
            ->where('type', 'letter')
            ->where(fn ($q) => $q
                ->where('reference_number', 'like', $like)
                ->orWhere('case_reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('subject', 'like', $like))
            ->tap(fn ($q) => $scope($q))
            ->orderByDesc('created_at')
            ->limit(40)->get();

        /* ---- Fines ---- */
        $fines = Fine::with('entity')
            ->where(fn ($q) => $q
                ->where('reference', 'like', $like)
                ->orWhere('case_reference', 'like', $like)
                ->orWhereHas('entity', fn ($e) => $e
                    ->where('name', 'like', $like)
                    ->orWhere('owner', 'like', $like)
                    ->orWhere('upi', 'like', $like)))
            ->tap(fn ($q) => $scope($q))
            ->orderByDesc('proposed_at')
            ->limit(40)->get();

        return view('search', [
            'term'     => $term,
            'cases'    => $cases,
            'premises' => $premises,
            'letters'  => $letters,
            'fines'    => $fines,
            'total'    => $cases->count() + $premises->count() + $letters->count() + $fines->count(),
        ]);
    }
}
