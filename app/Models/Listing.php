<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** L'annonce observée. Aucune identité de vendeur n'y est stockée. */
class Listing extends Model
{
    protected $fillable = ['platform', 'url', 'url_normalized', 'title', 'category', 'price_amount', 'currency'];

    protected $casts = ['price_amount' => 'decimal:2'];

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
    }

    public function derniereEvaluation(): ?Assessment
    {
        return $this->assessments()->latest('id')->first();
    }

    /**
     * La dernière valeur relevée pour chaque champ : les observations ne sont
     * jamais corrigées, un nouveau relevé ajoute une ligne.
     *
     * @return array<string, string|null>
     */
    public function observationsCourantes(): array
    {
        return $this->observations()->orderBy('id')->get()
            ->mapWithKeys(fn (Observation $o) => [$o->field => $o->value])
            ->all();
    }
}
