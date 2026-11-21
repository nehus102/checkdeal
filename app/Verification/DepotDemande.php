<?php

namespace App\Verification;

use App\Models\Listing;
use App\Models\Submission;

/**
 * Le dépôt d'une demande, quel que soit le canal : formulaire du site ou
 * extension navigateur. Une seule fiche par annonce (lien normalisé), une
 * demande par personne.
 */
class DepotDemande
{
    /**
     * Relevés que l'extension lit sur la page de l'annonce. Les champs de la
     * grille (EvaluationService::CHAMPS) pré-remplissent la fiche de
     * l'analyste ; les autres sont affichés à côté, à titre d'information.
     * Jamais de nom ni d'identifiant de vendeur.
     */
    public const RELEVES_EXTENSION = [
        'title', 'price_amount', 'description',
        'seller_rating', 'seller_rating_max', 'seller_review_count', 'seller_account_created_at',
        'favorites_count', 'seller_badges', 'secure_transaction', 'extension_index',
    ];

    public function __construct(private EvaluationService $evaluations) {}

    public function deposer(string $url, string $email, bool $consentement, string $canal): Submission
    {
        $listing = Listing::firstOrCreate(
            ['url_normalized' => AnnonceUrl::normaliser($url)],
            ['url' => $url, 'platform' => AnnonceUrl::plateforme($url)]
        );

        return $listing->submissions()->create([
            'channel' => $canal,
            'email' => $email,
            'consent_followup' => $consentement,
        ]);
    }

    /**
     * Ajoute les relevés de l'extension comme observations. Une valeur
     * identique à la valeur courante n'ajoute rien : renvoyer deux fois la
     * même annonce ne remplit pas l'historique.
     *
     * @param  array<string, string|null>  $releves
     */
    public function relever(Listing $listing, array $releves): void
    {
        $courantes = $listing->observationsCourantes();

        foreach (self::RELEVES_EXTENSION as $champ) {
            $valeur = $releves[$champ] ?? null;
            if ($valeur === null || $valeur === '' || ($courantes[$champ] ?? null) === $valeur) {
                continue;
            }
            $listing->observations()->create(['field' => $champ, 'value' => $valeur, 'observed_by' => 'extension']);
        }

        // Le titre et le prix servent à la file d'attente : on ne remplace
        // jamais ce que l'analyste a déjà saisi.
        $listing->update([
            'title' => $listing->title ?: mb_substr((string) ($releves['title'] ?? ''), 0, 255) ?: null,
            'price_amount' => $listing->price_amount ?? (is_numeric($releves['price_amount'] ?? null) ? $releves['price_amount'] : null),
        ]);
    }

    /** Annonce déjà vérifiée récemment : le résultat part tout de suite. */
    public function repondreSiDejaEvaluee(Submission $submission): bool
    {
        $recente = $submission->listing->assessments()
            ->where('created_at', '>=', now()->subDays(EvaluationService::VALIDITE_JOURS))
            ->exists();

        if ($recente) {
            $this->evaluations->repondre($submission);
        }

        return $recente;
    }
}
