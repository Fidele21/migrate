<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = ['section_id', 'item_code', 'label', 'max_score', 'sort_order', 'allows_na', 'is_prohibition' => 'boolean'];

    protected function casts(): array
    {
        return ['max_score' => 'decimal:2', 'allows_na' => 'boolean','is_prohibition' => 'boolean'];
    }

    public function section(): BelongsTo { return $this->belongsTo(ChecklistSection::class, 'section_id'); }
}
