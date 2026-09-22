<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A road on the City's register.
 *
 * Recorded, not scored. A road has a length and a surface; it is not
 * compliant or non-compliant, and giving it a percentage would put a
 * number on a dashboard that means nothing.
 *
 * Condition surveys will be recorded against a road later. This is the
 * inventory they will refer to.
 */
class Road extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'national'      => 'National Road',
        'district_1'    => 'District Road — Class 1',
        'district_2'    => 'District Road — Class 2',
        'specific'      => 'Specific Road',
        'main_corridor' => 'Main Corridor Road',
        'local'         => 'Local Road',
    ];

    public const SURFACES = [
        'paved'       => 'Paved',
        'unpaved'     => 'Unpaved',
        'cobblestone' => 'Cobblestone',
        'mixed'       => 'Mixed',
    ];

    protected $fillable = [
        'category', 'name', 'code', 'start_point', 'end_point', 'surface',
        'length_km', 'paved_km', 'unpaved_km', 'district', 'sector',
        'start_lat', 'start_lng', 'end_lat', 'end_lng', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'length_km'  => 'decimal:3',
            'paved_km'   => 'decimal:3',
            'unpaved_km' => 'decimal:3',
            'start_lat'  => 'decimal:7',
            'start_lng'  => 'decimal:7',
            'end_lat'    => 'decimal:7',
            'end_lng'    => 'decimal:7',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function surfaceLabel(): string
    {
        return self::SURFACES[$this->surface] ?? ($this->surface ?: '—');
    }

    /** How the road reads in a list: its code where it has one. */
    public function label(): string
    {
        return $this->code ? $this->code . ' — ' . $this->name : $this->name;
    }

    /**
     * Whether the paved and unpaved lengths account for the whole road.
     *
     * Not enforced on save: a survey may be partial, and refusing to
     * record what an officer measured because the rest is unmeasured
     * would lose the measurement. Flagged instead, so the gap is visible.
     */
    public function lengthsAgree(): bool
    {
        if (! $this->length_km) {
            return true;
        }

        $parts = (float) $this->paved_km + (float) $this->unpaved_km;

        return abs($parts - (float) $this->length_km) < 0.05;
    }

    public function unaccountedKm(): float
    {
        return round((float) $this->length_km
            - (float) $this->paved_km
            - (float) $this->unpaved_km, 3);
    }

    public function scopeCorridors($q)
    {
        return $q->where('category', 'main_corridor')->orderBy('name');
    }
}