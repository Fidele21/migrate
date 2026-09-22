<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fault found at an inspection.
 *
 * The amount and the action are copied from the schedule when the fault
 * is recorded, not read through the relation afterwards. The schedule may
 * be amended; what was found and what was owed on the day must not change
 * with it.
 */
class InspectionFault extends Model
{
    protected $fillable = [
        'inspection_id', 'fault_id', 'fault_sanction_id',
        'scope', 'amount', 'action', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function fault(): BelongsTo
    {
        return $this->belongsTo(Fault::class);
    }

    public function sanction(): BelongsTo
    {
        return $this->belongsTo(FaultSanction::class, 'fault_sanction_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function amountLabel(): string
    {
        return $this->amount
            ? 'FRW ' . number_format((float) $this->amount, 0)
            : 'No fine';
    }
}
