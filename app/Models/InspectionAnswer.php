<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAnswer extends Model
{
    protected $fillable = ['inspection_id', 'item_id', 'status', 'comment'];

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
    public function item(): BelongsTo       { return $this->belongsTo(ChecklistItem::class, 'item_id'); }
    /**
     * Did this answer comply?
     *
     * Most items ask whether something required is present, so "yes"
     * complies. A prohibition item asks whether something forbidden is
     * present — a kitchen at a petrol station — so "no" complies.
     */
    public function complied(): bool
    {
        if (! in_array($this->status, ['yes', 'no'], true)) {
            return false;
        }

        return $this->item?->is_prohibition
            ? $this->status === 'no'
            : $this->status === 'yes';
    }

    /** How the answer should read on a report. */
    public function verdict(): string
    {
        return match (true) {
            $this->status === 'na'  => 'Not applicable',
            $this->complied()       => 'Complies',
            default                 => 'Does not comply',
        };
    }
}
