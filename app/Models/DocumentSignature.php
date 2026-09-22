<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signature bound to the exact content that was signed.
 *
 * content_hash is a SHA-256 of the document at the moment of signing.
 * If the document is altered afterwards the hash no longer matches,
 * and the alteration becomes detectable.
 */
class DocumentSignature extends Model
{
    public const CREATED_AT = 'signed_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id', 'signer_id', 'signer_role', 'signer_name',
        'content_hash', 'signature_method', 'signature_path', 'ip_address','stage', 'position', 'remark',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function signer(): BelongsTo   { return $this->belongsTo(User::class, 'signer_id'); }

    /** Does this signature still match the document as it stands now? */
    public function isIntact(): bool
    {
        return hash_equals($this->content_hash, $this->document->computeHash());
    }
}
