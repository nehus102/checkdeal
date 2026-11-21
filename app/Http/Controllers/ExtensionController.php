<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Verification\DepotDemande;
use App\Verification\RecherchePhotos;
use Illuminate\Http\Request;

/**
 * L'extension navigateur envoie la fiche qu'elle a lue sur la page de
 * l'annonce, et la demande entre dans la même file que le formulaire du site.
 * Pas de session ni de cookie : la réponse donne le lien de suivi privé.
 */
class ExtensionController extends Controller
{
    public function verifier(Request $request, DepotDemande $depot)
    {
        $donnees = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2000'],
            'email' => ['required', 'email', 'max:190'],
            'consent_followup' => ['nullable', 'boolean'],
            'releves' => ['nullable', 'array'],
            'releves.title' => ['nullable', 'string', 'max:255'],
            'releves.price_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'releves.description' => ['nullable', 'string', 'max:10000'],
            'releves.seller_rating' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'releves.seller_rating_max' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'releves.seller_review_count' => ['nullable', 'integer', 'min:0'],
            'releves.seller_account_created_at' => ['nullable', 'date_format:Y-m-d'],
            'releves.favorites_count' => ['nullable', 'integer', 'min:0'],
            'releves.seller_badges' => ['nullable', 'array', 'max:10'],
            'releves.seller_badges.*' => ['string', 'max:60'],
            'releves.secure_transaction' => ['nullable', 'boolean'],
            'releves.extension_index' => ['nullable', 'string', 'max:60'],
        ], [
            'url.required' => "Le lien de l'annonce manque.",
            'email.required' => 'Indiquez votre e-mail pour recevoir le résultat.',
            'email.email' => "Cet e-mail n'est pas valide.",
        ]);

        $submission = $depot->deposer($donnees['url'], $donnees['email'], $request->boolean('consent_followup'), Submission::CANAL_EXTENSION);
        $depot->relever($submission->listing, $this->enTexte($donnees['releves'] ?? []));
        $dejaEvaluee = $depot->repondreSiDejaEvaluee($submission);

        return response()->json([
            'suivi_url' => route('demande.suivi', $submission->token),
            'deja_evaluee' => $dejaEvaluee,
        ], 201);
    }

    /** Les photos de l'annonce retrouvées à l'identique ailleurs sur le web. */
    public function photos(Request $request, RecherchePhotos $recherche)
    {
        if (! RecherchePhotos::configuree()) {
            return response()->json(['message' => "La recherche de photos n'est pas encore disponible."], 503);
        }

        $donnees = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['string', 'max:2000'],
        ]);

        $images = array_values(array_filter($donnees['images'], [RecherchePhotos::class, 'imageAutorisee']));
        if (! $images) {
            return response()->json(['message' => "Ces photos ne viennent pas d'une annonce prise en charge."], 422);
        }

        return response()->json($recherche->analyser($images));
    }

    /**
     * Les observations sont du texte : on fixe ici le format de chaque relevé.
     *
     * @return array<string, string|null>
     */
    private function enTexte(array $releves): array
    {
        $texte = [];
        foreach ($releves as $champ => $valeur) {
            $texte[$champ] = match (true) {
                $valeur === null => null,
                is_bool($valeur) || $champ === 'secure_transaction' => filter_var($valeur, FILTER_VALIDATE_BOOLEAN) ? 'oui' : 'non',
                is_array($valeur) => implode(', ', array_map('trim', $valeur)) ?: null,
                default => trim((string) $valeur),
            };
        }

        return $texte;
    }
}
