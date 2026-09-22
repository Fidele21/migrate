<?php

namespace App\Http\Controllers;

use App\Models\Road;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The road register.
 *
 * A road is recorded, not assessed. There is no compliance figure here
 * and no enforcement band: a road has a length and a surface, and the
 * question a register answers is how much of the City's network is on
 * record and in what condition — not whether any road passes.
 */
class RoadController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'category' => $request->query('category'),
            'district' => $request->query('district'),
            'surface'  => $request->query('surface'),
            'q'        => trim((string) $request->query('q', '')),
        ];

        $roads = Road::query()
            ->when($filters['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($filters['district'], fn ($q, $v) => $q->where('district', $v))
            ->when($filters['surface'],  fn ($q, $v) => $q->where('surface', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('code', 'like', "%{$v}%")
                ->orWhere('start_point', 'like', "%{$v}%")
                ->orWhere('end_point', 'like', "%{$v}%")))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        /* The whole register, not the filtered set: a filtered total is
           useful, but so is knowing what proportion of the network it
           represents. */
        $all = Road::all();

        return view('road.index', [
            'roads'   => $roads,
            'filters' => $filters,
            'active'  => collect($filters)->filter()->count(),

            'figures' => [
                'roads'      => $roads->count(),
                'of_total'   => $all->count(),
                'length'     => round((float) $roads->sum('length_km'), 1),
                'paved'      => round((float) $roads->sum('paved_km'), 1),
                'unpaved'    => round((float) $roads->sum('unpaved_km'), 1),
                'incomplete' => $roads->reject->lengthsAgree()->count(),
                'unlocated'  => $roads->whereNull('start_lat')->count(),
            ],

            'byCategory' => $roads->groupBy('category')
                ->map(fn ($rows, $key) => (object) [
                    'key'     => $key,
                    'label'   => Road::CATEGORIES[$key] ?? $key,
                    'roads'   => $rows->count(),
                    'length'  => round((float) $rows->sum('length_km'), 1),
                    'paved'   => round((float) $rows->sum('paved_km'), 1),
                    'unpaved' => round((float) $rows->sum('unpaved_km'), 1),
                ])
                ->sortByDesc('length')
                ->values(),

            'byDistrict' => $roads->filter(fn ($r) => filled($r->district))
                ->groupBy('district')
                ->map(fn ($rows, $key) => (object) [
                    'name'   => $key,
                    'roads'  => $rows->count(),
                    'length' => round((float) $rows->sum('length_km'), 1),
                    'paved'  => round((float) $rows->sum('paved_km'), 1),
                ])
                ->sortByDesc('length')
                ->values(),
        ]);
    }

    public function create()
    {
        Gate::authorize('road.manage');

        return view('road.form', ['road' => new Road(), 'action' => route('road.store')]);
    }

    public function store(Request $request)
    {
        Gate::authorize('road.manage');

        $road = Road::create($this->validated($request) + ['recorded_by' => auth()->id()]);

        return redirect()->route('road.index')
            ->with('status', $road->label() . ' added to the register.');
    }

    public function edit(Road $road)
    {
        Gate::authorize('road.manage');

        return view('road.form', ['road' => $road, 'action' => route('road.update', $road)]);
    }

    public function update(Request $request, Road $road)
    {
        Gate::authorize('road.manage');

        $road->update($this->validated($request));

        return redirect()->route('road.index')
            ->with('status', $road->label() . ' updated.');
    }

    public function destroy(Request $request, Road $road)
    {
        Gate::authorize('road.manage');

        $request->validate([
            'confirm' => ['required', 'in:DELETE'],
        ], ['confirm.in' => 'Type DELETE to confirm.']);

        $name = $road->label();
        $road->delete();

        return redirect()->route('road.index')
            ->with('status', $name . ' removed from the register.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'    => ['required', 'string', 'in:' . implode(',', array_keys(Road::CATEGORIES))],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:40'],
            'start_point' => ['nullable', 'string', 'max:255'],
            'end_point'   => ['nullable', 'string', 'max:255'],
            'surface'     => ['nullable', 'string', 'in:' . implode(',', array_keys(Road::SURFACES))],

            /* Lengths are not required to agree. A survey may be partial,
               and refusing what an officer measured because the rest is
               unmeasured would lose the measurement. */
            'length_km'   => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'paved_km'    => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'unpaved_km'  => ['nullable', 'numeric', 'min:0', 'max:9999'],

            'district'    => ['nullable', 'string', 'max:60'],
            'sector'      => ['nullable', 'string', 'max:60'],
            'start_lat'   => ['nullable', 'numeric', 'between:-2.5,-1.0'],
            'start_lng'   => ['nullable', 'numeric', 'between:29.0,31.0'],
            'end_lat'     => ['nullable', 'numeric', 'between:-2.5,-1.0'],
            'end_lng'     => ['nullable', 'numeric', 'between:29.0,31.0'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);
    }
}