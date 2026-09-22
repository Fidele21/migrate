<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSection extends Model
{
    protected $fillable = ['template_id', 'section_number', 'title', 'sort_order'];

    public function template(): BelongsTo { return $this->belongsTo(ChecklistTemplate::class, 'template_id'); }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'section_id')->orderBy('sort_order');
    }
}
