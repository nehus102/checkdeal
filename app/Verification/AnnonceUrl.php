<?php

namespace App\Verification;

/**
 * Normalisation des liens d'annonce et détection de la plateforme.
 *
 * Le lien normalisé sert de clé : deux demandes sur la même annonce partagent
 * une seule fiche. On retire donc ce qui varie d'un partage à l'autre (suivi,
 * fragment, barre finale) sans toucher au chemin, qui porte l'identifiant.
 */
class AnnonceUrl
{
    /** Domaine → plateforme. Une plateforme absente devient « autre ». */
    public const PLATEFORMES = [
        'leboncoin.fr' => 'leboncoin',
        'vinted.' => 'vinted',
        'ebay.' => 'ebay',
        'facebook.com' => 'facebook',
        'backmarket.' => 'backmarket',
        'rakuten.' => 'rakuten',
        'paruvendu.fr' => 'paruvendu',
    ];

    public const LIBELLES = [
        'leboncoin' => 'Leboncoin',
        'vinted' => 'Vinted',
        'ebay' => 'eBay',
        'facebook' => 'Facebook Marketplace',
        'backmarket' => 'Back Market',
        'rakuten' => 'Rakuten',
        'paruvendu' => 'ParuVendu',
        'autre' => 'Autre site',
    ];

    public static function normaliser(string $url): string
    {
        $parties = parse_url(trim($url));
        $hote = strtolower($parties['host'] ?? '');
        $hote = preg_replace('/^(www|m)\./', '', $hote);
        $chemin = rtrim($parties['path'] ?? '', '/');

        return $hote . $chemin;
    }

    public static function plateforme(string $url): string
    {
        $hote = strtolower(parse_url(trim($url), PHP_URL_HOST) ?? '');

        foreach (self::PLATEFORMES as $motif => $plateforme) {
            if (str_contains($hote, $motif)) {
                return $plateforme;
            }
        }

        return 'autre';
    }
}
