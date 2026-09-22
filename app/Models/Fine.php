<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fine extends Model
{
    use SoftDeletes;

    public const PROPOSED  = 'proposed';
    public const CONFIRMED = 'confirmed';
    public const PART_PAID = 'part_paid';
    public const PAID      = 'paid';
    public const WAIVED    = 'waived';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'inspection_id', 'document_id', 'entity_id', 'district_id', 'reference',
        'status', 'amount', 'amount_paid', 'currency', 'reason', 'legal_basis',
        'due_date', 'proposed_by', 'proposed_at', 'confirmed_by', 'confirmed_at',
        'confirm_note', 'settled_at', 'recorded_by', 'payment_reference',
        'payment_method', 'payment_note','case_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'amount_paid'  => 'decimal:2',
            'due_date'     => 'date',
            'proposed_at'  => 'datetime',
            'confirmed_at' => 'datetime',
            'settled_at'   => 'datetime',
        ];
    }

    public function entity(): BelongsTo     { return $this->belongsTo(Entity::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
    public function document(): BelongsTo   { return $this->belongsTo(Document::class); }
    public function proposer(): BelongsTo   { return $this->belongsTo(User::class, 'proposed_by'); }
    public function confirmer(): BelongsTo  { return $this->belongsTo(User::class, 'confirmed_by'); }

    public function payments(): HasMany
    {
        return $this->hasMany(FinePayment::class)->orderByDesc('paid_on');
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [self::CONFIRMED, self::PART_PAID], true);
    }

    public function isOverdue(): bool
    {
        return $this->isPayable()
            && $this->due_date
            && $this->due_date->isPast();
    }

    /** Recalculate status from what has actually been received. */
    public function refreshSettlement(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->status = self::CONFIRMED;
            $this->settled_at = null;
        } elseif ($paid + 0.001 >= (float) $this->amount) {
            $this->status = self::PAID;
            $this->settled_at = $this->settled_at ?? now();
        } else {
            $this->status = self::PART_PAID;
            $this->settled_at = null;
        }

        $this->save();
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::PAID      => 'good',
            self::PART_PAID => 'weak',
            self::CONFIRMED => 'poor',
            self::WAIVED, self::CANCELLED => 'na',
            default         => 'fair',
        };
    }
    
        /**
     * Whether the amount may still be adjusted or the fine waived.
     *
     * The window opens when the report is sealed — before that the faults
     * themselves can be corrected, which is the proper remedy — and
     * closes when the letter is signed, because from that point the
     * figure has been stated to the premises.
     *
     * Adjusting after service would leave the City holding one number and
     * the owner holding another.
     */
    public function isAdjustable(): bool
    {
        $inspection = $this->inspection;

        if (! $inspection || ! $inspection->hasSealedReport()) {
            return false;
        }

        if ($this->letterSigned()) {
            return false;
        }

        return in_array($this->status, [self::PROPOSED, self::CONFIRMED], true);
    }

    /**
     * Whether the enforcement letter carrying this fine has been signed.
     *
     * A letter that has been signed states the figure. Whether it has
     * physically been served is a separate question the platform cannot
     * answer, so the signature is taken as the point of no return.
     */
    public function letterSigned(): bool
    {
        if ($this->document && $this->document->sealed_at) {
            return true;
        }

        return $this->inspection
            ? $this->inspection->documents()
                ->where('type', 'letter')
                ->whereNotNull('sealed_at')
                ->exists()
            : false;
    }

    /** The reason a fine was reduced or waived, where it was. */
    public function wasAdjusted(): bool
    {
        return $this->original_amount !== null
            && (float) $this->original_amount !== (float) $this->amount;
    }

    /** Who reduced or waived the fine, where anyone did. */
    public function adjuster(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
