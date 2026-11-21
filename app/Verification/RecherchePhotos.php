<?php

namespace App\Verification;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Photos de l'annonce retrouvées à l'identique ailleurs sur le web : recherche
 * visuelle Google Lens via SerpApi (jamais de requête directe vers Google).
 * Repris du prototype FastAPI (scam cheker/backend) pour n'avoir qu'un serveur.
 *
 * Seules les correspondances EXACTES comptent : une photo de produit connu
 * reprise par des sites de presse ou de marque est normale (testé sur un vrai
 * ampli : 59 correspondances, aucune arnaque). On montre les liens et
 * l'acheteur juge. `engine=google_reverse_image` renvoyait toujours vide.
 */
class RecherchePhotos
{
    public const MAX_IMAGES = 5;

    /** Seules les photos hébergées par une plateforme connue : sinon notre quota SerpApi servirait de proxy gratuit. */
    public const HOTES_AUTORISES = ['img.leboncoin.fr'];

    private const SERPAPI_URL = 'https://serpapi.com/search';

    public static function configuree(): bool
    {
        return (string) config('services.serpapi.key') !== '';
    }

    public static function imageAutorisee(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::HOTES_AUTORISES, true);
    }

    /**
     * @param  list<string>  $images
     * @return array{score:int, risk_level:string, signals:list<array>, image_matches:list<array>}
     */
    public function analyser(array $images): array
    {
        $images = array_values(array_slice($images, 0, self::MAX_IMAGES));

        // En parallèle : 5 photos en série dépasseraient le délai de la popup.
        $reponses = $images ? Http::pool(fn (Pool $pool) => array_map(
            fn (string $image) => $pool->timeout(15)->get(self::SERPAPI_URL, [
                'engine' => 'google_lens',
                'type' => 'visual_matches',
                'url' => $image,
                'api_key' => config('services.serpapi.key'),
            ]),
            $images
        )) : [];

        $correspondances = [];
        foreach ($images as $i => $image) {
            $correspondances[] = ['image_url' => $image, 'matches' => $this->correspondances($reponses[$i] ?? null)];
        }

        return $this->noter($correspondances);
    }

    /**
     * Une photo en échec (timeout, quota, photo non indexée) compte comme « pas
     * de correspondance » : elle ne fait jamais échouer toute l'analyse.
     */
    private function correspondances(mixed $reponse): array
    {
        if (! $reponse instanceof Response || ! $reponse->successful()) {
            return [];
        }

        $matches = [];
        foreach ((array) $reponse->json('visual_matches', []) as $resultat) {
            $lien = $resultat['link'] ?? null;
            $domaine = $lien ? preg_replace('/^www\./', '', strtolower((string) parse_url($lien, PHP_URL_HOST))) : '';
            if ($domaine === '' || str_contains($domaine, 'leboncoin.fr')) {
                continue;
            }
            $matches[] = ['domain' => $domaine, 'link' => $lien, 'exact_match' => ! empty($resultat['exact_matches'])];
        }

        return $matches;
    }

    /** Même barème que le prototype : 2 photos identiques ailleurs = très suspect, 1 = à vérifier. */
    private function noter(array $correspondances): array
    {
        $verifiees = count($correspondances);
        if ($verifiees === 0) {
            return [
                'score' => 0, 'risk_level' => 'indetermine', 'image_matches' => [],
                'signals' => [['type' => 'photo_exact_match', 'severity' => 'info', 'label' => 'Aucune photo à analyser', 'detail' => "L'annonce ne contenait aucune image exploitable."]],
            ];
        }

        $touchees = array_values(array_filter($correspondances, fn ($c) => collect($c['matches'])->contains('exact_match', true)));
        $nombre = count($touchees);
        $score = (int) round($nombre / $verifiees * 100);

        [$risque, $score] = match (true) {
            $nombre >= 2 => ['tres_eleve', max($score, 70)],
            $nombre === 1 => ['a_verifier', max($score, 25)],
            default => ['faible', $score],
        };

        $signal = $nombre
            ? ['type' => 'photo_exact_match', 'severity' => $score >= 40 ? 'danger' : 'warning', 'label' => "{$nombre} photo(s) sur {$verifiees} retrouvée(s) à l'identique ailleurs", 'detail' => 'Vérifiez vous-même les liens ci-dessous.']
            : ['type' => 'photo_exact_match', 'severity' => 'info', 'label' => 'Aucune correspondance exacte trouvée', 'detail' => "{$verifiees} photo(s) vérifiée(s), aucune copie identique détectée ailleurs."];

        return ['score' => $score, 'risk_level' => $risque, 'signals' => [$signal], 'image_matches' => $correspondances];
    }
}
