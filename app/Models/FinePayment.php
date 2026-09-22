<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinePayment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'fine_id', 'amount', 'paid_on', 'method', 'reference', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'created_at' => 'datetime'];
    }

    public function fine(): BelongsTo     { return $this->belongsTo(Fine::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
