<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parcel identifier belonging to a premises.
 *
 * The UPI has five parts: province, district, sector, cell and parcel
 * number, for example 1/02/05/03/4745. Condominium units append a
 * suffix, for example 1/03/02/04/750-1.
 */
class EntityUpi extends Model
{
    protected $fillable = ['entity_id', 'upi', 'is_primary', 'note'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public const PATTERN = '/^[1-5]\/\d{2}\/\d{2}\/\d{2}\/\d+(-\d+)?$/';

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }

    public static function isValid(string $upi): bool
    {
        return (bool) preg_match(self::PATTERN, trim($upi));
    }

    /** Decode into its five parts, or null if malformed. */
    public function parts(): ?array
    {
        if (! self::isValid($this->upi)) {
            return null;
        }
        [$province, $district, $sector, $cell, $parcel] = explode('/', trim($this->upi));

        return compact('province', 'district', 'sector', 'cell', 'parcel');
    }

    public function isKigali(): bool
    {
        return str_starts_with(trim($this->upi), '1/');
    }
}
