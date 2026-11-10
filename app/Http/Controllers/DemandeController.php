<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Listing;
use App\Models\Submission;
use App\Verification\AnnonceUrl;
use App\Verification\EvaluationService;
use App\Verification\RegleV1;
use Illuminate\Http\Request;

/** Le parcours public : déposer un lien, attendre, lire le résultat. */
class DemandeController extends Controller
{
    public function accueil()
    {
        return view('accueil');
    }

    public function store(Request $request, EvaluationService $evaluations)
    {
        $donnees = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2000'],
            'email' => ['required', 'email', 'max:190'],
            'consent_followup' => ['nullable', 'boolean'],
            'captures' => ['nullable', 'array', 'max:5'],
            'captures.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'url.required' => "Collez le lien de l'annonce.",
            'url.url' => "Ce lien n'est pas valide : copiez l'adresse complète de l'annonce.",
            'email.required' => 'Indiquez votre e-mail pour recevoir le résultat.',
            'captures.max' => '5 captures au maximum.',
            'captures.*.max' => 'Chaque capture doit faire moins de 5 Mo.',
        ]);

        $listing = Listing::firstOrCreate(
            ['url_normalized' => AnnonceUrl::normaliser($donnees['url'])],
            ['url' => $donnees['url'], 'platform' => AnnonceUrl::plateforme($donnees['url'])]
        );

        $submission = $listing->submissions()->create([
            'channel' => Submission::CANAL_FORMULAIRE,
            'email' => $donnees['email'],
            'consent_followup' => $request->boolean('consent_followup'),
        ]);

        foreach ($request->file('captures', []) as $fichier) {
            Evidence::create([
                'listing_id' => $listing->id,
                'submission_id' => $submission->id,
                'type' => 'listing_screenshot',
                'path' => $fichier->store('preuves'),
                'original_name' => mb_substr($fichier->getClientOriginalName(), 0, 190),
                'mime' => $fichier->getMimeType(),
                'size' => $fichier->getSize(),
                'sha256' => hash_file('sha256', $fichier->getRealPath()),
                'provenance' => 'requester',
                'retention_until' => now()->addMonths(12)->toDateString(),
            ]);
        }

        // Annonce déjà vérifiée récemment : le résultat part tout de suite.
        $recente = $listing->assessments()->where('created_at', '>=', now()->subDays(EvaluationService::VALIDITE_JOURS))->exists();
        if ($recente) {
            $evaluations->repondre($submission);
        }

        return redirect()->route('demande.suivi', $submission->token);
    }

    /** Page de suivi : « en cours », puis le résultat. Lien privé par jeton. */
    public function suivi(string $token)
    {
        $submission = Submission::where('token', $token)->with('listing')->firstOrFail();
        $evaluation = $submission->estTraitee() ? $submission->listing->derniereEvaluation()?->load('signals') : null;

        return view('suivi', [
            'submission' => $submission,
            'listing' => $submission->listing,
            'evaluation' => $evaluation,
            'verdict' => $evaluation ? RegleV1::verdict($evaluation->final_score) : null,
        ]);
    }
}
