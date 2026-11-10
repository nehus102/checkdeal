<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = ['listing_id', 'ruleset_version', 'computed_score', 'final_score', 'override_reason', 'confidence', 'rationale', 'advice', 'assessor'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
