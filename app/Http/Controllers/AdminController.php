<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Listing;
use App\Models\Outcome;
use App\Models\Submission;
use App\Verification\AnalyseDescription;
use App\Verification\AnnonceUrl;
use App\Verification\EvaluationService;
use App\Verification\RegleV1;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Le back-office du mode valet : file d'attente, grille, envoi. */
class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $attendu = (string) config('verif.admin_password');

        // Sans mot de passe configuré, personne n'entre : jamais d'accès ouvert par défaut.
        if ($attendu === '' || !hash_equals($attendu, (string) $request->input('password'))) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        $request->session()->regenerate();
        $request->session()->put('admin', true);

        return redirect()->route('admin.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin');

        return redirect()->route('admin.login');
    }

    public function index()
    {
        return view('admin.index', [
            // La plus ancienne d'abord : c'est elle qui fait attendre quelqu'un.
            'enAttente' => Submission::where('status', 'pending')->with('listing')->oldest()->get(),
            'recentes' => Listing::has('assessments')->with(['assessments' => fn ($q) => $q->latest('id')])->latest('updated_at')->limit(20)->get(),
            'stats' => [
                'demandes' => Submission::where('channel', Submission::CANAL_FORMULAIRE)->count(),
                'temoins' => Submission::where('channel', Submission::CANAL_TEMOIN)->count(),
                'retours' => Outcome::whereIn('purchase_decision', ['bought', 'abandoned'])->count(),
                'documentes' => Outcome::whereIn('certainty', ['evidenced', 'verified'])->count(),
            ],
        ]);
    }

    public function show(Listing $listing)
    {
        return $this->formulaire($listing, $listing->observationsCourantes());
    }

    public function evaluer(Request $request, Listing $listing, EvaluationService $evaluations)
    {
        $request->validate([
            'price_amount' => ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/'],
            'seller_rating' => ['nullable', 'numeric', 'min:0'],
            'seller_rating_max' => ['nullable', 'numeric', 'min:1'],
            'seller_review_count' => ['nullable', 'integer', 'min:0'],
            'seller_account_created_at' => ['nullable', 'date'],
            'score_final' => ['nullable', 'integer', 'min:0', 'max:100'],
            'raison_ajustement' => ['nullable', 'required_with:score_final', 'string', 'max:255'],
        ], [
            'raison_ajustement.required_with' => "Notez pourquoi vous ajustez l'indice : c'est ce qui permettra de corriger les règles.",
        ]);

        $obs = EvaluationService::observationsDepuis($listing, $request->all());

        if ($request->input('action') !== 'envoyer') {
            return $this->formulaire($listing, $obs, $evaluations->apercu($obs), $request->all());
        }

        $scoreFinal = $request->filled('score_final') ? (int) $request->input('score_final') : null;
        $evaluations->enregistrer($listing, $obs, $scoreFinal, $request->input('raison_ajustement'), $request->input('conseil'), $request->input('justification'));

        return redirect()->route('admin.index')->with('ok', 'Évaluation enregistrée, résultat envoyé.');
    }

    /** Ajout d'une annonce de l'échantillon témoin, tirée au hasard. */
    public function temoin(Request $request)
    {
        $donnees = $request->validate(['url' => ['required', 'url:http,https', 'max:2000']]);

        $listing = Listing::firstOrCreate(
            ['url_normalized' => AnnonceUrl::normaliser($donnees['url'])],
            ['url' => $donnees['url'], 'platform' => AnnonceUrl::plateforme($donnees['url'])]
        );
        $listing->submissions()->create(['channel' => Submission::CANAL_TEMOIN]);

        return redirect()->route('admin.show', $listing);
    }

    public function preuve(Evidence $evidence)
    {
        abort_if($evidence->path === null || !Storage::exists($evidence->path), 404);

        return Storage::response($evidence->path, $evidence->original_name);
    }

    private function formulaire(Listing $listing, array $obs, ?array $apercu = null, array $saisie = [])
    {
        return view('admin.annonce', [
            'listing' => $listing->load(['evidence', 'submissions', 'outcomes']),
            'obs' => $obs,
            'apercu' => $apercu,
            'saisie' => $saisie,
            'suggestions' => AnalyseDescription::suggestions((string) ($obs['description'] ?? '')),
            'historique' => $listing->assessments()->latest('id')->get(),
        ]);
    }
}
