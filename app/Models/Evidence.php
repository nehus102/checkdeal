<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une pièce brute, immuable. `path` passe à null quand elle est purgée. */
class Evidence extends Model
{
    protected $table = 'evidence';

    protected $fillable = ['listing_id', 'submission_id', 'outcome_id', 'type', 'path', 'original_name', 'mime', 'size', 'sha256', 'provenance', 'contains_personal_data', 'retention_until'];

    protected $casts = ['contains_personal_data' => 'boolean', 'retention_until' => 'date'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
