<?php

namespace App\Verification;

use App\Mail\ResultatMail;
use App\Models\Assessment;
use App\Models\Listing;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Enregistre une évaluation et prévient les demandeurs.
 *
 * Les observations ne sont jamais corrigées : un champ dont la valeur change
 * ajoute une ligne, et l'évaluation garde ses signaux et sa version de règles.
 */
class EvaluationService
{
    /** Champs relevés par l'analyste, communs à toutes les annonces. */
    public const CHAMPS = [
        'title', 'category', 'price_amount', 'comparable_prices',
        'seller_rating', 'seller_rating_max', 'seller_review_count', 'seller_account_created_at',
        'description',
    ];

    /** Une évaluation plus récente que ça répond tout de suite aux nouvelles demandes. */
    public const VALIDITE_JOURS = 7;

    public function __construct(private RegleV1 $regles) {}

    /**
     * Les observations soumises, complétées par les valeurs déjà connues.
     *
     * @param  array<string, mixed>  $saisie
     * @return array<string, string|null>
     */
    public static function observationsDepuis(Listing $listing, array $saisie): array
    {
        $obs = $listing->observationsCourantes();

        foreach (array_merge(self::CHAMPS, array_map(fn ($c) => 'flag_' . $c, array_keys(RegleV1::DRAPEAUX))) as $champ) {
            if (array_key_exists($champ, $saisie)) {
                $valeur = is_string($saisie[$champ]) ? trim(self::enUtf8($saisie[$champ])) : $saisie[$champ];
                $obs[$champ] = $valeur === '' ? null : $valeur;
            }
        }

        return $obs;
    }

    /**
     * Un texte collé depuis un outil mal configuré peut arriver en Windows-1252 :
     * toutes les expressions régulières en /u échouent alors sans bruit, et les
     * suggestions disparaissent.
     */
    public static function enUtf8(string $texte): string
    {
        return mb_check_encoding($texte, 'UTF-8') ? $texte : mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
    }

    public function apercu(array $obs): array
    {
        return $this->regles->evaluer($obs, Carbon::today());
    }

    /**
     * Enregistre l'évaluation, clôt les demandes en attente et leur envoie le
     * résultat. `$scoreFinal` permet à l'analyste d'ajuster l'indice calculé,
     * avec une raison : c'est ce qui dira plus tard quels poids corriger.
     */
    public function enregistrer(Listing $listing, array $obs, ?int $scoreFinal, ?string $raison, ?string $conseil, ?string $justification): Assessment
    {
        $resultat = $this->apercu($obs);
        $final = $scoreFinal ?? $resultat['score'];

        $assessment = DB::transaction(function () use ($listing, $obs, $resultat, $final, $raison, $conseil, $justification) {
            $courantes = $listing->observationsCourantes();
            foreach ($obs as $champ => $valeur) {
                if (($courantes[$champ] ?? null) !== $valeur && ($valeur !== null || array_key_exists($champ, $courantes))) {
                    $listing->observations()->create(['field' => $champ, 'value' => $valeur]);
                }
            }

            $listing->update([
                'title' => $obs['title'] ?? $listing->title,
                'category' => $obs['category'] ?? $listing->category,
                'price_amount' => is_numeric(str_replace(',', '.', (string) ($obs['price_amount'] ?? ''))) ? str_replace(',', '.', $obs['price_amount']) : $listing->price_amount,
            ]);

            $assessment = $listing->assessments()->create([
                'ruleset_version' => $resultat['version'],
                'computed_score' => $resultat['score'],
                'final_score' => max(0, min(100, $final)),
                'override_reason' => $final !== $resultat['score'] ? $raison : null,
                'confidence' => $resultat['confiance'],
                'rationale' => $justification,
                'advice' => $conseil,
            ]);

            foreach ($resultat['signaux'] as $s) {
                $assessment->signals()->create([
                    'listing_id' => $listing->id,
                    'code' => $s['code'],
                    'status' => $s['statut'],
                    'value' => $s['valeur'],
                    'points' => $s['points'],
                ]);
            }

            return $assessment;
        });

        $listing->submissions()->where('status', 'pending')->get()->each(fn (Submission $s) => $this->repondre($s));

        return $assessment;
    }

    /** Clôt une demande et envoie le résultat, s'il y a une adresse. */
    public function repondre(Submission $submission): void
    {
        $submission->update(['status' => 'done', 'answered_at' => now()]);

        if ($submission->email) {
            Mail::to($submission->email)->send(new ResultatMail($submission));
        }
    }
}
