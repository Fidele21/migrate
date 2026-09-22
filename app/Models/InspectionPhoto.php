<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InspectionPhoto extends Model
{
    protected $fillable = [
        'inspection_id', 'path', 'original_name', 'caption', 'sort_order', 'size_bytes',
    ];

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected static function booted(): void
    {
        // Remove the file when the record goes.
        static::deleting(function (self $photo) {
            Storage::disk('public')->delete($photo->path);
        });
    }
}
