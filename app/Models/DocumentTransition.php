<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of a document through the approval chain.
 *
 * Insert only. UPDATED_AT is disabled because a row that can be
 * modified is not an audit trail.
 */
class DocumentTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id', 'from_status', 'to_status',
        'actor_id', 'actor_role', 'comment', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function actor(): BelongsTo    { return $this->belongsTo(User::class, 'actor_id'); }
}
