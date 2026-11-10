<?php

namespace App\Verification;

/**
 * Repérage de signaux dans le texte d'une annonce ou d'un échange.
 *
 * Ce ne sont que des SUGGESTIONS, pré-cochées dans le back-office : l'analyste
 * confirme chaque drapeau. Un mot-clé n'est pas une preuve (« pas de paiement
 * par virement » contient « virement »).
 */
class AnalyseDescription
{
    private const MOTIFS = [
        'off_platform_contact' => [
            '/whats\s?app|telegram|signal\b/iu',
            '/[\w.+-]+@[\w-]+\.[\w.]+/u',
            '/(?:\+33|0)\s?[67](?:[\s.-]?\d{2}){4}/u',
            '/contact(?:ez)?[- ]moi (?:par|sur) (?:mail|sms|t[ée]l)/iu',
        ],
        'off_platform_payment' => [
            '/virement|western\s?union|mandat\s?cash|moneygram|pcs|transcash|neosurf/iu',
            '/paypal\s+(?:entre\s+)?(?:amis|proches)|crypto|bitcoin|usdt/iu',
        ],
        'urgency_pressure' => [
            '/urgent|vite|premier\s+(?:arriv|qui)|d[ée]part\s+(?:à|a)\s+l\'?[ée]tranger|d[ée]m[ée]nagement|aujourd\'?hui\s+seulement/iu',
        ],
        'remote_only' => [
            '/envoi\s+uniquement|pas\s+de\s+(?:remise\s+en\s+)?main\s+propre|exp[ée]dition\s+seulement/iu',
        ],
    ];

    /**
     * @return array<string, list<string>>  drapeau → extraits trouvés
     */
    public static function suggestions(string $texte): array
    {
        $texte = EvaluationService::enUtf8($texte);
        $trouve = [];

        foreach (self::MOTIFS as $drapeau => $motifs) {
            foreach ($motifs as $motif) {
                if (preg_match_all($motif, $texte, $m)) {
                    $trouve[$drapeau] = array_values(array_unique(array_merge($trouve[$drapeau] ?? [], $m[0])));
                }
            }
        }

        return $trouve;
    }
}
