<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What is owed, and what happens next, for one fault in one scope.
 *
 * The amount may be null. Three faults in the schedule carry no fine:
 * the sanction is removal at the defaulter's cost, or suspension pending
 * an audit. A null amount is the published position, not missing data.
 */
class FaultSanction extends Model
{
    protected $fillable = [
        'fault_id', 'scope_kind', 'scope', 'liable', 'amount', 'action',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function fault(): BelongsTo
    {
        return $this->belongsTo(Fault::class);
    }

    /** The fine as it should be written in a letter. */
    public function amountLabel(): string
    {
        return $this->amount
            ? 'FRW ' . number_format((float) $this->amount, 0)
            : 'No fine';
    }
}
