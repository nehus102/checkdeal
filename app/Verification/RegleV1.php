<?php

namespace App\Verification;

use Carbon\Carbon;

/**
 * Règles de scoring, version 1. Agnostiques au produit : elles ne lisent que
 * les dimensions communes à toutes les annonces (vendeur, prix, description,
 * photos, échanges).
 *
 * Calcul pur : des observations en entrée, des signaux et un indice en sortie.
 * Changer un seuil ou un poids, c'est créer une nouvelle version : les
 * évaluations passées gardent la leur et peuvent être rejouées.
 *
 * Les seuils sont des valeurs de départ, à calibrer sur les premiers cas et sur
 * l'échantillon témoin (Data Strategy § 4).
 */
class RegleV1
{
    public const VERSION = 'v1';

    /** En dessous, il n'y a pas assez de comparables pour juger un prix. */
    public const COMPARABLES_MIN = 3;

    /**
     * Signaux déclarés par l'analyste (oui / non / non vérifié), avec leur
     * poids de risque. Les signaux calculés sont dans `evaluer()`.
     */
    public const DRAPEAUX = [
        'off_platform_payment' => 25,
        'image_reused' => 20,
        'off_platform_contact' => 15,
        'description_copy' => 15,
        'spec_inconsistency' => 12,
        'stock_photo' => 10,
        'urgency_pressure' => 8,
        'remote_only' => 8,
    ];

    /** Libellé lisible et conseil, par code de signal. */
    public const CATALOGUE = [
        'price_below_market' => ['Prix nettement sous le marché', "Un prix très bas est l'appât le plus courant. Demandez pourquoi, et méfiez-vous d'une urgence à conclure."],
        'seller_rating_low' => ['Note du vendeur faible', 'Lisez les avis négatifs : ils décrivent souvent le problème que vous risquez.'],
        'seller_no_reviews' => ['Vendeur sans historique', "Sans avis, rien ne prouve que ce vendeur a déjà conclu une vente. Privilégiez la remise en main propre ou le paiement sécurisé de la plateforme."],
        'account_new' => ['Compte très récent', 'Les comptes créés pour une arnaque ont souvent quelques jours. Restez sur la plateforme pour échanger et payer.'],
        'description_vague' => ['Description très courte', "Demandez des précisions et des photos supplémentaires : un vrai vendeur connaît son objet."],
        'off_platform_payment' => ['Paiement hors plateforme demandé', "Ne payez jamais par virement, coupon, crypto ou paiement « entre proches » : vous n'auriez aucun recours."],
        'image_reused' => ['Photos trouvées ailleurs', "Demandez une photo de l'objet avec un papier portant votre prénom et la date."],
        'off_platform_contact' => ['Contact hors plateforme demandé', "Restez dans la messagerie de la plateforme : c'est elle qui vous protège en cas de litige."],
        'description_copy' => ['Description copiée', "Le même texte circule sur d'autres annonces : signe fréquent d'annonce fantôme."],
        'spec_inconsistency' => ['Incohérences dans l\'annonce', 'Vérifiez chaque caractéristique annoncée auprès du fabricant avant de payer.'],
        'stock_photo' => ['Pas de photo réelle', "Des visuels officiels uniquement : demandez des photos de l'objet lui-même."],
        'urgency_pressure' => ['Pression pour conclure vite', "L'urgence sert à vous empêcher de vérifier. Prenez le temps."],
        'remote_only' => ['Remise en main propre refusée', 'Si la distance le permet, insistez pour voir et tester avant de payer.'],
    ];

    /** Conseil donné à tout le monde, quel que soit le score. */
    public const CONSEIL_GENERAL = "Payez uniquement via le paiement sécurisé de la plateforme, ou en main propre après avoir vérifié l'objet.";

    /**
     * @param  array<string, string|null>  $obs  observations courantes de l'annonce
     * @return array{version: string, score: int, risque: int, confiance: string, mediane: ?float, signaux: list<array{code: string, statut: string, valeur: ?string, points: int}>}
     */
    public function evaluer(array $obs, Carbon $aujourdhui): array
    {
        $signaux = [];

        // Prix comparé à la médiane des comparables saisis.
        $prix = self::nombre($obs['price_amount'] ?? null);
        $comparables = self::liste($obs['comparable_prices'] ?? null);
        $mediane = count($comparables) >= self::COMPARABLES_MIN ? self::mediane($comparables) : null;
        if ($prix === null || $mediane === null || $mediane <= 0) {
            $signaux[] = self::signal('price_below_market', 'unknown');
        } else {
            $ecart = ($prix - $mediane) / $mediane * 100;
            $points = match (true) {
                $ecart <= -40 => 30,
                $ecart <= -25 => 20,
                $ecart <= -15 => 8,
                default => 0,
            };
            $signaux[] = self::signal('price_below_market', $points > 0 ? 'present' : 'absent', sprintf('%+.0f %%', $ecart), $points);
        }

        // Nombre d'avis, puis note ramenée sur 1 quelle que soit l'échelle
        // (étoiles sur 5, pourcentage d'avis positifs…).
        $avis = self::nombre($obs['seller_review_count'] ?? null);
        if ($avis === null) {
            $signaux[] = self::signal('seller_no_reviews', 'unknown');
        } else {
            $points = $avis < 1 ? 20 : ($avis < 3 ? 10 : 0);
            $signaux[] = self::signal('seller_no_reviews', $points > 0 ? 'present' : 'absent', (string) (int) $avis, $points);
        }

        $note = self::nombre($obs['seller_rating'] ?? null);
        $echelle = self::nombre($obs['seller_rating_max'] ?? null) ?: 5.0;
        if ($note === null || $avis === null || $avis < 3) {
            // Une note sur deux avis ne dit rien : le signal reste inconnu.
            $signaux[] = self::signal('seller_rating_low', 'unknown');
        } else {
            $ratio = $note / $echelle;
            $signaux[] = self::signal('seller_rating_low', $ratio < 0.8 ? 'present' : 'absent', rtrim(rtrim(number_format($note, 1, ',', ''), '0'), ',') . ' / ' . (int) $echelle, $ratio < 0.8 ? 15 : 0);
        }

        // Ancienneté du compte.
        $creation = self::date($obs['seller_account_created_at'] ?? null);
        if ($creation === null) {
            $signaux[] = self::signal('account_new', 'unknown');
        } else {
            $jours = (int) $creation->diffInDays($aujourdhui, false);
            $points = $jours < 30 ? 20 : ($jours < 180 ? 8 : 0);
            $signaux[] = self::signal('account_new', $points > 0 ? 'present' : 'absent', $jours . ' j', $points);
        }

        // Description : seule sa longueur est jugée automatiquement, le reste
        // passe par les drapeaux confirmés par l'analyste.
        $description = trim((string) ($obs['description'] ?? ''));
        if ($description === '') {
            $signaux[] = self::signal('description_vague', 'unknown');
        } else {
            $longueur = mb_strlen($description);
            $signaux[] = self::signal('description_vague', $longueur < 60 ? 'present' : 'absent', $longueur . ' car.', $longueur < 60 ? 5 : 0);
        }

        foreach (self::DRAPEAUX as $code => $poids) {
            $valeur = $obs['flag_' . $code] ?? null;
            $statut = match ($valeur) {
                'oui' => 'present',
                'non' => 'absent',
                default => 'unknown',
            };
            $signaux[] = self::signal($code, $statut, null, $statut === 'present' ? $poids : 0);
        }

        $risque = min(100, array_sum(array_column($signaux, 'points')));

        return [
            'version' => self::VERSION,
            'score' => 100 - $risque,
            'risque' => $risque,
            'confiance' => self::confiance([$prix !== null && $mediane !== null, $avis !== null, $note !== null, $creation !== null, $description !== '']),
            'mediane' => $mediane,
            'signaux' => $signaux,
        ];
    }

    /** Le verdict affiché, d'après l'indice de confiance final. */
    public static function verdict(int $score): array
    {
        return match (true) {
            $score >= 70 => ['Plutôt fiable', 'vert'],
            $score >= 40 => ['Prudence', 'orange'],
            default => ['Risque élevé', 'rouge'],
        };
    }

    /**
     * La confiance dans l'indice dépend du nombre de dimensions de base
     * connues : sans prix ni vendeur, un indice élevé ne veut rien dire.
     *
     * @param  list<bool>  $connues
     */
    private static function confiance(array $connues): string
    {
        $n = count(array_filter($connues));

        return $n >= 5 ? 'haute' : ($n >= 3 ? 'moyenne' : 'faible');
    }

    private static function signal(string $code, string $statut, ?string $valeur = null, int $points = 0): array
    {
        return ['code' => $code, 'statut' => $statut, 'valeur' => $valeur, 'points' => $points];
    }

    private static function nombre(?string $valeur): ?float
    {
        if ($valeur === null || trim($valeur) === '') {
            return null;
        }
        $propre = str_replace([' ', "\u{00A0}", '€', ','], ['', '', '', '.'], trim($valeur));

        return is_numeric($propre) ? (float) $propre : null;
    }

    /** « 450 ; 520 ; 480 », « 450, 520 », ou une valeur par ligne. */
    private static function liste(?string $valeur): array
    {
        // Une virgule suivie d'un espace sépare deux prix ; collée, c'est une décimale.
        $morceaux = preg_split('/[;\n\r]+|,\s+/', (string) $valeur) ?: [];

        return array_values(array_filter(array_map(fn ($m) => self::nombre($m), $morceaux), fn ($n) => $n !== null && $n > 0));
    }

    private static function mediane(array $valeurs): float
    {
        sort($valeurs);
        $n = count($valeurs);
        $milieu = intdiv($n, 2);

        return $n % 2 ? $valeurs[$milieu] : ($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2;
    }

    private static function date(?string $valeur): ?Carbon
    {
        if ($valeur === null || trim($valeur) === '') {
            return null;
        }
        try {
            return Carbon::parse($valeur)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
