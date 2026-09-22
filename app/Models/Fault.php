<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fault an inspector may record against a premises.
 *
 * Faults are not checklist items. A checklist asks whether a requirement
 * is met and scores the answer; a fault is a specific contravention with
 * a sanction attached, drawn from the schedule in the Urban Planning
 * Code.
 *
 * A premises can score well and still carry a fault — a site with good
 * safety practice built without a permit is compliant on the checklist
 * and unlawful nonetheless.
 */
class Fault extends Model
{
    use HasFactory;

    protected $fillable = ['type_code', 'code', 'title', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(FaultSanction::class)->orderBy('id');
    }

    public function scopeForType($q, string $code)
    {
        return $q->where('type_code', $code)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * The sanction that applies to a premises of a given category.
     *
     * A sanction scoped to "all" applies whatever the category. Where a
     * fault is scoped by road class or protected area instead, the caller
     * supplies that value rather than a building category.
     */
    public function sanctionFor(?string $scope): ?FaultSanction
    {
        $all = $this->sanctions->firstWhere('scope_kind', 'all');

        if (blank($scope)) {
            return $all ?? ($this->sanctions->count() === 1 ? $this->sanctions->first() : null);
        }

        $match = $this->sanctions->first(
            fn ($s) => str_contains(strtolower($s->scope), strtolower(trim($scope)))
        );

        return $match ?? $all;
    }

    /** Whether this fault's sanction depends on something the officer must choose. */
    public function needsScope(): bool
    {
        return $this->sanctions->count() > 1
            && ! $this->sanctions->contains('scope_kind', 'all');
    }

    /** What kind of thing the scope is, for labelling the chooser. */
    public function scopeKind(): string
    {
        return $this->sanctions->first()?->scope_kind ?? 'all';
    }
}
