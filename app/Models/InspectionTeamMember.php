<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of the team that conducted the site inspection.
 *
 * The report is signed by these people together with the District
 * Inspection Unit Director, each shown with their position. The
 * enforcement letter carries only the Chief Inspector's signature.
 */
class InspectionTeamMember extends Model
{
    protected $table = 'inspection_team';

    protected $fillable = [
        'inspection_id', 'name', 'position', 'institution', 'is_lead','user_id',
    ];

    protected function casts(): array
    {
        return ['is_lead' => 'boolean'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
