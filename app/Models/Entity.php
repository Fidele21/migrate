<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type_code', 'name', 'upi', 'owner', 'telephone', 'email',
        'use_type', 'zoning', 'district_id', 'district', 'sector', 'cell',
        'village', 'latitude', 'longitude', 'legacy_id',
    ];

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->orderByDesc('inspection_date')->orderByDesc('id');
    }

    public function districtRecord(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function latestInspection(): ?Inspection
    {
        return $this->inspections()->first();
    }
    public function upis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EntityUpi::class)->orderByDesc('is_primary');
    }

    /**
     * Find an existing premises or create one.
     *
     * Matches on UPI first, then name plus district. This is what stops
     * a repeat visit creating a duplicate record and severing the
     * inspection history.
     */
    public static function findOrCreateFrom(array $data): self
    {
        $query = static::where('type_code', $data['type_code']);

        if (! empty($data['upi'])) {
            $found = (clone $query)->where('upi', trim($data['upi']))->first();
        } else {
            $found = (clone $query)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])
                ->where('district', $data['district'] ?? null)
                ->first();
        }

        if ($found) {
            $found->fill($data)->save();
            return $found;
        }

        return static::create($data);
    }
}
