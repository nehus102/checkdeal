<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outcome extends Model
{
    protected $fillable = ['listing_id', 'submission_id', 'purchase_decision', 'item_received', 'item_conform', 'problem_type', 'label', 'certainty', 'provenance', 'comment'];

    protected $casts = ['item_received' => 'boolean', 'item_conform' => 'boolean'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
