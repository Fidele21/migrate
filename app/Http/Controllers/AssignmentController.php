<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giving out work, passing it down, and seeing how it stands.
 *
 * Who may assign to whom is a chain, one level at a time: a Chief
 * Inspector tasks Senior Inspectors and Directors, a Senior Inspector
 * tasks Directors, and a Director tasks the inspectors of their own
 * district. Nobody reaches past the level below them.
 *
 * Work passes down the same chain. A Director given ten stations may
 * hand them to their inspectors — all ten to one team, or four to one
 * and six to another, or six out and four kept. What their inspectors
 * finish counts as theirs finished: the work was theirs to see done,
 * not theirs to do personally.
 */
class AssignmentController extends Controller
{
    private const MAY_ASSIGN_TO = [
        'Chief Inspector'        => ['Senior Inspector', 'Director of Inspection'],
        'Senior Inspector'       => ['Director of Inspection'],
        'Director of Inspection' => ['Inspector'],
    ];

    /**
     * Officers of these ranks each own a share and answer for it alone.
     * Everyone else works a shared target as a team.
     *
     * A Director cannot share a target with another Director: they
     * inspect in different districts, and a figure covering both tells
     * neither of them how they are doing.
     */
    private const HOLD_INDIVIDUALLY = ['Senior Inspector', 'Director of Inspection'];

    public function index(Request $request)
    {
        $user = auth()->user();

        $given = $this->canAssign($user)
            ? Assignment::with(['members', 'assigner', 'children'])
                ->where('assigned_by', $user->id)
                ->orderByDesc('due_on')
                ->get()
            : collect();

        $held = Assignment::with(['members', 'assigner', 'children', 'parent'])
            ->for($user->id)
            ->orderByDesc('due_on')
            ->get();

        return view('assignment.index', [
            'given'     => $given,
            'held'      => $held,
            'canAssign' => $this->canAssign($user),
        ]);
    }

    /**
     * How many assignments an officer holds that still want work.
     *
     * Read by My Box for its alert. Counts what is theirs to act on:
     * active, not yet met, and — for a Director who has passed
     * everything down — not already fully delegated.
     */
    public static function openCountFor(User $user): int
    {
        return Assignment::with(['members', 'children'])
            ->for($user->id)
            ->active()
            ->get()
            ->filter(fn ($a) => $a->doneFor($user->id) < $a->shareEach())
            ->count();
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        /* Passing down a share of something already held, or giving out
           work of one's own. */
        $parent = $request->filled('from')
            ? Assignment::with('members')->find($request->integer('from'))
            : null;

        if ($parent) {
            $this->authorizeDelegation($parent, $user);
        } else {
            abort_unless($this->canAssign($user), 403,
                'Your role does not assign work to others.');
        }

        return view('assignment.create', [
            'parent'     => $parent,
            'remaining'  => $parent?->undelegatedBy($user->id),
            'types'      => $this->assignableTypes(),
            'candidates' => $this->candidatesFor($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $parent = $request->filled('parent_id')
            ? Assignment::with('members')->find($request->integer('parent_id'))
            : null;

        if ($parent) {
            $this->authorizeDelegation($parent, $user);
        } else {
            abort_unless($this->canAssign($user), 403,
                'Your role does not assign work to others.');
        }

        $data = $request->validate([
            'parent_id'    => ['nullable', 'integer', 'exists:assignments,id'],
            'type_code'    => ['required', 'string', 'max:40'],
            'quantity'     => ['required', 'integer', 'min:1', 'max:500'],
            'starts_on'    => ['required', 'date'],
            'due_on'       => ['required', 'date', 'after_or_equal:starts_on'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'members'      => ['required', 'array', 'min:1', 'max:12'],
            'members.*'    => ['integer', 'exists:users,id'],
        ]);

        /* A share passed down keeps the parent's category and cannot
           run past its dates — work counted toward a target must fall
           inside the period that target covers. */
        if ($parent) {
            $data['type_code'] = $parent->type_code;

            if ($data['quantity'] > $parent->undelegatedBy($user->id)) {
                throw ValidationException::withMessages([
                    'quantity' => 'You have only ' . $parent->undelegatedBy($user->id)
                        . ' left to pass on from your share of ' . $parent->shareEach() . '.',
                ]);
            }

            if ($data['starts_on'] < $parent->starts_on->toDateString()
                || $data['due_on'] > $parent->due_on->toDateString()) {
                throw ValidationException::withMessages([
                    'due_on' => 'These dates must sit inside the assignment they come from — '
                        . $parent->starts_on->format('j M Y') . ' to ' . $parent->due_on->format('j M Y') . '.',
                ]);
            }
        }

        $allowed = $this->candidatesFor($user)->pluck('id')->all();

        foreach ($data['members'] as $id) {
            if (! in_array((int) $id, $allowed, true)) {
                throw ValidationException::withMessages([
                    'members' => 'One of those officers is not yours to assign work to.',
                ]);
            }
        }

        $clashing = [];

        foreach ($data['members'] as $id) {
            if (Assignment::clashesFor((int) $id, $data['type_code'], $data['starts_on'], $data['due_on'])) {
                $clashing[] = User::find($id)?->name ?? ('User ' . $id);
            }
        }

        if ($clashing) {
            throw ValidationException::withMessages([
                'members' => count($clashing) === 1
                    ? $clashing[0] . ' already holds an assignment of this kind over these dates.'
                    : implode(', ', $clashing) . ' already hold assignments of this kind over these dates.',
            ]);
        }

        return DB::transaction(function () use ($data, $user, $parent) {
            $members = User::with('roles')->whereIn('id', $data['members'])->get();

            $districtId = $user->district_id ?? $members->first()?->district_id;

            $assignment = Assignment::create([
                'parent_id'    => $parent?->id,
                'type_code'    => $data['type_code'],
                'quantity'     => $data['quantity'],
                'sharing'      => $this->sharingFor($members),
                'starts_on'    => $data['starts_on'],
                'due_on'       => $data['due_on'],
                'instructions' => $data['instructions'] ?? null,
                'assigned_by'  => $user->id,
                'assigned_at'  => now(),
                'district_id'  => $districtId,
                'status'       => Assignment::ACTIVE,
            ]);

            $assignment->members()->sync($data['members']);

            return redirect()->route('assignment.show', $assignment)
                ->with('status', $parent ? 'Share passed on.' : 'Assignment given out.');
        });
    }

    public function show(Assignment $assignment)
    {
        $user = auth()->user();

        $isHolder = $assignment->members->contains('id', $user->id);

        $mayView = $assignment->assigned_by === $user->id
            || $isHolder
            || $user->can('inspection.view.all')
            || ($user->can('inspection.view.district') && $user->coversDistrict($assignment->district_id));

        abort_unless($mayView, 403);

        $perMember = $assignment->members->map(fn ($m) => [
            'user'      => $m,
            'own'       => $assignment->doneBy($m->id),
            'total'     => $assignment->doneFor($m->id),
            'share'     => $assignment->shareEach(),
            'progress'  => $assignment->progressFor($m->id),
            'delegated' => $assignment->delegatedBy($m->id),
        ]);

        /* A holder may pass on what they have not already passed on,
           provided their own rank has someone below it to pass to. */
        $canDelegate = $isHolder
            && $assignment->status === Assignment::ACTIVE
            && $this->canAssign($user)
            && $this->candidatesFor($user)->isNotEmpty()
            && $assignment->undelegatedBy($user->id) > 0;

        return view('assignment.show', [
            'assignment'  => $assignment->load(['members', 'assigner', 'district', 'children.members', 'parent']),
            'perMember'   => $perMember,
            'canManage'   => $assignment->assigned_by === $user->id,
            'canDelegate' => $canDelegate,
            'myShare'     => $isHolder ? $assignment->shareEach() : null,
            'myDone'      => $isHolder ? $assignment->doneFor($user->id) : null,
            'myRemaining' => $isHolder ? $assignment->undelegatedBy($user->id) : null,
        ]);
    }

    public function cancel(Assignment $assignment)
    {
        abort_unless($assignment->assigned_by === auth()->id(), 403,
            'Only the officer who gave this assignment may cancel it.');

        $assignment->update(['status' => Assignment::CANCELLED]);

        return redirect()->route('assignment.index')
            ->with('status', 'Assignment cancelled.');
    }

    /* ==============================================================
       Who may assign to whom
       ============================================================== */

    private function canAssign(User $user): bool
    {
        foreach (array_keys(self::MAY_ASSIGN_TO) as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Passing down a share requires holding it, and having somewhere to
     * pass it to.
     */
    private function authorizeDelegation(Assignment $parent, User $user): void
    {
        abort_unless($parent->members->contains('id', $user->id), 403,
            'You may only pass on an assignment you hold.');

        abort_unless($this->canAssign($user), 403,
            'Your role does not assign work to others.');

        abort_unless($parent->status === Assignment::ACTIVE, 422,
            'This assignment is no longer active.');

        abort_unless($parent->undelegatedBy($user->id) > 0, 422,
            'You have already passed on your whole share.');
    }

    private function candidatesFor(User $user)
    {
        $roles = [];

        foreach (self::MAY_ASSIGN_TO as $role => $targets) {
            if ($user->hasRole($role)) {
                $roles = array_merge($roles, $targets);
            }
        }

        if (! $roles) {
            return collect();
        }

        return User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_unique($roles)))
            ->when($user->district_id, fn ($q) => $q->where('district_id', $user->district_id))
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether these officers work a shared target or each own a share.
     *
     * Senior ranks hold their own: two Directors on one target would be
     * measured by a figure covering both their districts, which tells
     * neither of them anything. Inspectors work together.
     */
    private function sharingFor($members): string
    {
        foreach ($members as $m) {
            foreach (self::HOLD_INDIVIDUALLY as $role) {
                if ($m->hasRole($role)) {
                    return Assignment::INDIVIDUAL;
                }
            }
        }

        return Assignment::TEAM;
    }

    private function assignableTypes(): array
    {
        $out = [];

        foreach (config('inspection_types.groups') as $group) {
            foreach ($group['types'] as $code => $type) {
                if ($type['live'] ?? false) {
                    $out[$group['label']][$code] = $type['name'];
                }
            }
        }

        return $out;
    }
}