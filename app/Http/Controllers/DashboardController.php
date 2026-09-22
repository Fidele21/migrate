<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Fine;
use App\Models\Inspection;
use App\Models\User;
use App\Services\InspectionStats;
use App\Support\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard, chosen by role.
 *
 * A Secretary has no use for compliance charts and a Recovery Officer has
 * no use for checklist sections. Each role opens to the work that is
 * theirs, and to nothing that is not.
 *
 * Reporting periods follow the Rwandan government financial year, which
 * runs from 1 July to 30 June.
 */
class DashboardController extends Controller
{
    public function __construct(private InspectionStats $stats) {}

    public function index()
    {
        $user = auth()->user();
        $role = $user->roles->pluck('name')->first() ?? 'Inspector';

        return match ($role) {
            'Secretary'        => $this->secretary($user),
            'Recovery Officer' => $this->recovery($user),
            'Administrator'    => $this->administrator($user),
            'Chief Inspector',
            'Senior Inspector',
            'Director of Inspection' => $this->supervisor($user, $role),
            default            => $this->inspector($user, $role),
        };
    }

    /* ==============================================================
       Inspector and Lead Inspector — their own field work
       ============================================================== */
    private function inspector(User $user, string $role)
    {
        $scope = $user->isCityWide() ? [] : ['district' => $user->district?->name];

        $mine = Inspection::where('inspector_id', $user->id);

        return view('dashboard.inspector', [
            'role'     => $role,
            'district' => $user->district?->name,
            'mine' => [
                'drafts'     => (clone $mine)->where('status', 'draft')->count(),
                'completed'  => (clone $mine)->where('status', 'completed')->count(),
                'this_month' => (clone $mine)->where('status', 'completed')
                                    ->whereMonth('inspection_date', now()->month)
                                    ->whereYear('inspection_date', now()->year)->count(),
                'mean'       => round((float) (clone $mine)->where('status', 'completed')->avg('compliance'), 1),
            ],
            'returned' => $this->docs(fn ($q) => $q->where('created_by', $user->id)
                                ->where('status', Document::RETURNED))->count(),
            'inReview' => $this->docs(fn ($q) => $q->where('created_by', $user->id)
                                ->whereIn('status', [Document::PENDING_DIRECTOR,
                                    Document::PENDING_SENIOR, Document::PENDING_CHIEF]))->count(),
            'drafts'   => (clone $mine)->with('entity')->where('status', 'draft')
                                ->orderByDesc('updated_at')->limit(5)->get(),
            'recent'   => (clone $mine)->with('entity')->where('status', 'completed')
                                ->orderByDesc('inspection_date')->limit(6)->get(),
            'overview'     => $this->stats->overview($scope),
            'distribution' => $this->stats->distribution($scope),
            'failures'     => $this->stats->topFailures($scope, 5),
        ]);
    }

    /* ==============================================================
       Director, Senior and Chief — the Overview across all activities
       ============================================================== */
    private function supervisor(User $user, string $role)
    {
        $request = request();

        /* An activity chosen here opens that activity's own dashboard,
           carrying the location and period with it. There is one
           dashboard per activity, reached either from this filter or
           from the sidebar — never two versions of the same screen. */
        if ($activity = $request->query('activity')) {
            return redirect()->route('type.show', array_merge(
                ['code' => $activity],
                array_filter([
                    'district' => $request->query('district'),
                    'sector'   => $request->query('sector'),
                    'cell'     => $request->query('cell'),
                    'period'   => $request->query('period'),
                    'from'     => $request->query('from'),
                    'to'       => $request->query('to'),
                ])
            ));
        }

        /* A Director is bound to their own district whatever the URL
           says; the scope is enforced here, not in the view. */
        $district = $user->isCityWide()
            ? $request->query('district')
            : $user->district?->name;

        [$from, $to, $periodLabel] = FiscalPeriod::resolve(
            $request->query('period'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = array_filter([
            'district' => $district,
            'sector'   => $request->query('sector'),
            'cell'     => $request->query('cell'),
            'from'     => $from?->toDateString(),
            'to'       => $to?->toDateString(),
        ]);

        /* ---- What is waiting on this person ---- */
        $queue = [];
        /*
        if ($user->can('document.verify.district')) {
            $queue['Awaiting my verification'] =
                $this->docs(fn ($q) => $q->where('status', Document::PENDING_DIRECTOR)
                    ->when(! $user->isCityWide(), fn ($b) => $b->where('district_id', $user->district_id)))->count();
        }
        if ($user->can('document.verify.city')) {
            $queue['Awaiting central verification'] =
                $this->docs(fn ($q) => $q->where('status', Document::PENDING_SENIOR))->count();
        }
        if ($user->can('document.approve')) {
            $queue['Awaiting my signature'] =
                $this->docs(fn ($q) => $q->where('status', Document::PENDING_CHIEF))->count();
        }
        if ($user->can('fine.confirm')) {
            $queue['Fines to confirm'] = $this->fines(fn ($q) => $q->where('status', Fine::PROPOSED))->count();
        } */

        return view('dashboard.supervisor', [
            'role'      => $role,
            'district'  => $user->district?->name,
            'cityWide'  => $user->isCityWide(),
            'scopeName' => $district ?: 'All districts',
            'queue'     => $queue,

            'filters' => [
                'district' => $district,
                'sector'   => $request->query('sector'),
                'cell'     => $request->query('cell'),
                'period'   => $request->query('period'),
                'from'     => $request->query('from'),
                'to'       => $request->query('to'),
            ],
            'periodLabel'   => $periodLabel,
            'periodOptions' => FiscalPeriod::options($this->earliestYear()),
            'activities'    => $this->activityList(),

            'pending' => $this->docs(fn ($q) => $q->with('creator')
                            ->whereIn('status', [Document::PENDING_DIRECTOR,
                                Document::PENDING_SENIOR, Document::PENDING_CHIEF])
                            ->when(! $user->isCityWide(), fn ($b) => $b->where('district_id', $user->district_id))
                            ->orderBy('submitted_at'))->limit(8)->get(),

            'overview'  => $this->stats->overview($filters),
            'byType'    => $this->typeSummary($filters),
            'drafts'    => Inspection::where('status', 'draft')
                            ->when(! $user->isCityWide(), fn ($q) => $q->where('district_id', $user->district_id))
                            ->count(),
        ]);
    }

    /* ==============================================================
       Secretary — the registry, nothing else
       ============================================================== */
    private function secretary(User $user)
    {
        $letters = $this->docs(fn ($q) => $q->where('type', 'letter')
            ->whereIn('status', [Document::APPROVED, Document::ISSUED])
            ->orderByDesc('approved_at'))->get();

        return view('dashboard.secretary', [
            'role'       => 'Secretary',
            'awaiting'   => $letters->filter(fn ($l) => blank($l->reference_number)),
            'toPrint'    => $letters->filter(fn ($l) => $l->reference_number && ! $l->printed_at),
            'toScan'     => $letters->filter(fn ($l) => $l->printed_at && ! $l->scan_path),
            'toDispatch' => $letters->filter(fn ($l) => $l->scan_path && ! $l->dispatched_at),
            'issuedMonth'=> $letters->filter(fn ($l) => $l->dispatched_at
                                && $l->dispatched_at->isSameMonth(now()))->count(),
            'issuedTotal'=> $letters->filter(fn ($l) => $l->dispatched_at)->count(),
            'recent'     => $letters->filter(fn ($l) => $l->dispatched_at)->take(8),
            'nextRef'    => $this->lastSerial(),
        ]);
    }

    /* ==============================================================
       Recovery Officer — money, nothing else
       ============================================================== */
    private function recovery(User $user)
    {
        if (! Schema::hasTable('fines')) {
            return view('dashboard.recovery', ['role' => 'Recovery Officer', 'empty' => true]);
        }

        $all     = Fine::with('entity')->get();
        $payable = $all->filter(fn ($f) => $f->isPayable());

        $months = DB::table('fine_payments')
            ->selectRaw("DATE_FORMAT(paid_on, '%Y-%m') AS month, SUM(amount) AS total")
            ->where('paid_on', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')->orderBy('month')->get();

        return view('dashboard.recovery', [
            'role'  => 'Recovery Officer',
            'empty' => false,
            'summary' => [
                'outstanding' => $payable->sum(fn ($f) => $f->outstanding()),
                'collected'   => (float) $all->sum('amount_paid'),
                'overdue'     => $payable->filter(fn ($f) => $f->isOverdue())->count(),
                'overdue_sum' => $payable->filter(fn ($f) => $f->isOverdue())->sum(fn ($f) => $f->outstanding()),
                'payable'     => $payable->count(),
                'settled'     => $all->where('status', Fine::PAID)->count(),
                'this_month'  => (float) DB::table('fine_payments')
                                    ->whereMonth('paid_on', now()->month)
                                    ->whereYear('paid_on', now()->year)->sum('amount'),
            ],
            'overdue' => $payable->filter(fn ($f) => $f->isOverdue())
                            ->sortBy('due_date')->take(10),
            'dueSoon' => $payable->filter(fn ($f) => ! $f->isOverdue() && $f->due_date
                            && $f->due_date->lte(now()->addDays(14)))->sortBy('due_date')->take(8),
            'months'     => $months,
            'byDistrict' => $payable->groupBy(fn ($f) => $f->entity->district ?? '—')
                            ->map(fn ($g) => ['count' => $g->count(), 'sum' => $g->sum(fn ($f) => $f->outstanding())]),
        ]);
    }

    /* ==============================================================
       Administrator — accounts and the system
       ============================================================== */
    private function administrator(User $user)
    {
        $users = User::with('roles', 'district')->get();

        return view('dashboard.admin', [
            'role'  => 'Administrator',
            'users' => $users,
            'summary' => [
                'total'    => $users->count(),
                'active'   => $users->where('is_active', true)->count(),
                'inactive' => $users->where('is_active', false)->count(),
                'unset'    => $users->where('must_change_password', true)->count(),
                'nosig'    => $users->whereNull('signature_path')->count(),
                'never'    => $users->whereNull('last_login_at')->count(),
            ],
            'byRole' => $users->groupBy(fn ($u) => $u->roles->pluck('name')->first() ?? '— none —')
                            ->map->count()->sortDesc(),
            'byDistrict' => $users->groupBy(fn ($u) => $u->district?->name ?? 'All districts')->map->count(),
            'recent' => $users->sortByDesc('last_login_at')->take(8),
            'stale'  => $users->filter(fn ($u) => $u->is_active
                            && ($u->must_change_password || ! $u->last_login_at))->take(10),
            'counts' => [
                'inspections' => Inspection::count(),
                'documents'   => Schema::hasTable('documents') ? Document::count() : 0,
                'fines'       => Schema::hasTable('fines') ? Fine::count() : 0,
                'entities'    => DB::table('entities')->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    /* ==============================================================
       Helpers
       ============================================================== */

    /** Every inspection category, grouped as the sidebar groups them. */
    private function activityList(): array
    {
        $out = [];

        foreach (config('inspection_types.groups') as $group) {
            $out[$group['label']] = collect($group['types'])
                ->map(fn ($t, $code) => ['code' => $code, 'name' => $t['name'], 'live' => $t['live']])
                ->values()->all();
        }

        return $out;
    }

    /**
     * Volume and mean compliance per category, within the chosen period
     * and location. InspectionStats::byType() is unfiltered, so the
     * figures are gathered here instead.
     */
    private function typeSummary(array $filters): array
    {
        $out = [];

        foreach (config('inspection_types.groups') as $group) {
            foreach ($group['types'] as $code => $t) {
                /* Premises, not inspections — each judged on its latest
                   visit. Counting inspections here would make the Overview
                   disagree with the dashboard it links to. */
                $rows   = $this->stats->latestPerPremises(array_merge($filters, ['type' => $code]));
                $scores = collect($rows)->pluck('compliance')->map(fn ($v) => (float) $v);

                $out[] = (object) [
                    'code'        => $code,
                    'name'        => $t['name'],
                    'live'        => $t['live'],
                    'inspections' => count($rows),
                    'compliance'  => $scores->count() ? round($scores->avg(), 1) : null,
                ];
            }
        }

        return $out;
    }

    /**
     * The financial year of the earliest inspection, so the period
     * selector offers only years that could hold records.
     */
    private function earliestYear(): int
    {
        $first = Inspection::min('inspection_date');

        return $first
            ? FiscalPeriod::yearOf(Carbon::parse($first))
            : FiscalPeriod::yearOf();
    }

    /**
     * Guards for tables that may not exist yet, so a dashboard does not
     * fail merely because a later migration has not been run.
     */
    private function docs(callable $fn)
    {
        if (! Schema::hasTable('documents')) {
            return Document::whereRaw('1 = 0');
        }
        return $fn(Document::query());
    }

    private function fines(callable $fn)
    {
        if (! Schema::hasTable('fines')) {
            return Fine::whereRaw('1 = 0');
        }
        return $fn(Fine::query());
    }

    /**
     * The last registry number recorded this year, shown to the
     * Secretary as a hint. The registry is kept outside this platform,
     * so the next number cannot be known here.
     */
    private function lastSerial(): ?int
    {
        if (! Schema::hasTable('documents')) {
            return null;
        }

        return Document::whereYear('issued_at', now()->year)->max('serial');
    }
}