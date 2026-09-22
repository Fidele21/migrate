<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Work given out: so many inspections of one category, by a date, by
 * named officers.
 *
 * Progress is counted rather than recorded. Nothing marks an inspection
 * as belonging to an assignment — the inspections that satisfy it are
 * ordinary records, counted by category, officer and date. An officer
 * who inspects a petrol station during the period of their petrol
 * assignment has advanced it by doing their job, not by also telling
 * the platform they did.
 *
 * That is why one officer may not hold two assignments of the same
 * category over the same period: a single inspection would count toward
 * both, and neither figure would mean anything.
 *
 * An assignment may be passed down a share at a time. Thirty stations
 * to three Directors is ten each, owned separately; a Director's ten may
 * go to the inspectors of their district, whole or in parts. Work done
 * on a share counts toward the share above it, so a Director whose
 * inspectors finish their ten has finished their ten.
 */
class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    public const ACTIVE    = 'active';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    /** Holders work one shared target between them. */
    public const TEAM = 'team';

    /** Each holder owns an equal share and answers for it alone. */
    public const INDIVIDUAL = 'individual';

    protected $fillable = [
        'parent_id',
        'type_code',
        'quantity',
        'sharing',
        'starts_on',
        'due_on',
        'instructions',
        'assigned_by',
        'assigned_at',
        'district_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on'   => 'date',
            'due_on'      => 'date',
            'assigned_at' => 'datetime',
        ];
    }

    /* ==============================================================
       Relations
       ============================================================== */

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignment_members')
                    ->withTimestamps();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Shares carved out of this one and passed further down. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /* ==============================================================
       Shares
       ============================================================== */

    /**
     * What one holder owns of this assignment.
     *
     * Where holders work as a team the target is theirs together, so
     * each one's share is the whole of it. Where they hold it
     * individually it divides by however many there are — thirty to
     * three Directors is ten each. An uneven split is two assignments,
     * not one: the officer giving out the work says so plainly rather
     * than the platform guessing at a remainder.
     */
    public function shareEach(): int
    {
        if ($this->sharing === self::TEAM) {
            return $this->quantity;
        }

        $count = max(1, $this->members->count());

        return (int) ceil($this->quantity / $count);
    }

    /**
     * Of one holder's share, how much they have already passed on.
     *
     * A Director with ten who has given four to one team and three to
     * another has seven out and three still their own to do.
     */
    public function delegatedBy(int $userId): int
    {
        return (int) $this->children()
            ->where('assigned_by', $userId)
            ->where('status', '!=', self::CANCELLED)
            ->sum('quantity');
    }

    /** What a holder has left to give out of their own share. */
    public function undelegatedBy(int $userId): int
    {
        return max(0, $this->shareEach() - $this->delegatedBy($userId));
    }

    /* ==============================================================
       Progress
       ============================================================== */

    /**
     * Inspections a given officer has recorded toward this assignment
     * themselves, within its period.
     */
    public function doneBy(int $userId): int
    {
        return Inspection::where('type_code', $this->type_code)
            ->where('status', 'completed')
            ->where('inspector_id', $userId)
            ->whereBetween('inspection_date', [$this->starts_on, $this->due_on])
            ->count();
    }

    /**
     * What has been done toward one holder's share — their own
     * inspections, plus everything done on the shares they passed down.
     *
     * A Director who gave their ten to inspectors and did none
     * themselves has still met their ten when those inspectors finish.
     * The work was theirs to see done, not theirs to do personally.
     */
    public function doneFor(int $userId): int
    {
        $own = $this->doneBy($userId);

        $delegated = $this->children()
            ->where('assigned_by', $userId)
            ->where('status', '!=', self::CANCELLED)
            ->get()
            ->sum(fn ($child) => $child->done());

        return $own + $delegated;
    }

    /**
     * What has been done toward the whole assignment.
     *
     * For a team, that is what the team has done between them. For
     * individually-held work it is every holder's progress added
     * together, since each is working their own share of the same job.
     */
    public function done(): int
    {
        if ($this->sharing === self::TEAM) {
            $own = Inspection::where('type_code', $this->type_code)
                ->where('status', 'completed')
                ->whereIn('inspector_id', $this->members->pluck('id'))
                ->whereBetween('inspection_date', [$this->starts_on, $this->due_on])
                ->count();

            $delegated = $this->children()
                ->where('status', '!=', self::CANCELLED)
                ->get()
                ->sum(fn ($child) => $child->done());

            return $own + $delegated;
        }

        return (int) $this->members->sum(fn ($m) => $this->doneFor($m->id));
    }

    /**
     * How far along the whole assignment is.
     *
     * Where holders work as a team every one of them is credited with
     * this same figure: eight of ten is eighty per cent for each of
     * them, not eighty divided among them. They worked it together.
     */
    public function progress(): float
    {
        if ($this->quantity < 1) {
            return 0.0;
        }

        return round(100 * min($this->done(), $this->quantity) / $this->quantity, 1);
    }

    /**
     * How far along one holder is against their own share.
     *
     * For a team this is the team's figure, the same for everyone on
     * it. For individually-held work it is that officer's own share and
     * whatever was done on it, theirs alone.
     */
    public function progressFor(int $userId): float
    {
        if ($this->sharing === self::TEAM) {
            return $this->progress();
        }

        $share = $this->shareEach();

        if ($share < 1) {
            return 0.0;
        }

        return round(100 * min($this->doneFor($userId), $share) / $share, 1);
    }

    public function isMet(): bool
    {
        return $this->done() >= $this->quantity;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::ACTIVE
            && $this->due_on->isPast()
            && ! $this->isMet();
    }

    public function daysLeft(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->due_on, false);
    }

    /** ['8 of 10', 'weak'] — a short verdict and a tone for it. */
    public function standing(): array
    {
        if ($this->status === self::CANCELLED) {
            return ['Cancelled', 'muted'];
        }

        if ($this->isMet()) {
            return ['Met', 'good'];
        }

        if ($this->isOverdue()) {
            return ['Overdue', 'poor'];
        }

        return [$this->done() . ' of ' . $this->quantity, 'weak'];
    }

    /* ==============================================================
       Scopes and guards
       ============================================================== */

    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    public function scopeFor($query, int $userId)
    {
        return $query->whereHas('members', fn ($q) => $q->where('users.id', $userId));
    }

    /**
     * Whether this officer already holds an assignment of this category
     * over a period that overlaps the one proposed.
     *
     * One inspection cannot honestly count toward two targets, so the
     * platform does not let two exist to count it toward. This holds
     * however the second assignment arises — passed down from above, or
     * given directly.
     */
    public static function clashesFor(int $userId, string $typeCode, $startsOn, $dueOn, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('type_code', $typeCode)
            ->where('status', self::ACTIVE)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->where(fn ($q) => $q
                ->whereBetween('starts_on', [$startsOn, $dueOn])
                ->orWhereBetween('due_on', [$startsOn, $dueOn])
                ->orWhere(fn ($inner) => $inner
                    ->where('starts_on', '<=', $startsOn)
                    ->where('due_on', '>=', $dueOn)))
            ->exists();
    }
}