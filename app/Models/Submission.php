<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Submission extends Model
{
    public const CANAL_FORMULAIRE = 'b2c_form';
    public const CANAL_TEMOIN = 'control_sample';

    protected $fillable = ['listing_id', 'channel', 'email', 'token', 'consent_followup', 'status', 'answered_at', 'followups_sent', 'last_followup_at'];

    protected $casts = [
        'email' => 'encrypted',
        'consent_followup' => 'boolean',
        'answered_at' => 'datetime',
        'last_followup_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Submission $s) => $s->token ??= Str::random(48));
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
    }

    public function estTraitee(): bool
    {
        return $this->status === 'done';
    }
}
