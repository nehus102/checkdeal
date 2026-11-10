<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Outcome;
use App\Models\Submission;
use Illuminate\Http\Request;

/**
 * La boucle de retour : ce qui s'est passé après la vérification.
 *
 * Le lien de l'e-mail mène à une page de confirmation, jamais à une écriture
 * directe : les antivirus de messagerie ouvrent les liens, et enregistreraient
 * une réponse que personne n'a donnée.
 */
class RetourController extends Controller
{
    public const CHOIX = [
        'conforme' => "J'ai acheté, tout est conforme",
        'probleme' => "J'ai acheté, il y a un problème",
        'renonce' => "J'ai renoncé",
        'indecis' => 'Pas encore décidé',
    ];

    /** Problème déclaré → libellé de résultat (Data Strategy § 3). */
    public const PROBLEMES = [
        'non_recu' => ['Je n\'ai rien reçu', 'confirmed_fraud'],
        'colis_vide' => ['Colis vide ou autre objet', 'confirmed_fraud'],
        'acompte_perdu' => ['Acompte versé, vendeur disparu', 'confirmed_fraud'],
        'paiement_piege' => ['Faux lien de paiement ou de livraison', 'confirmed_fraud'],
        'contrefacon' => ['Contrefaçon', 'counterfeit_confirmed'],
        'objet_bloque' => ['Objet bloqué ou inutilisable (compte, verrouillage…)', 'confirmed_fraud'],
        'non_conforme' => ['Différent de l\'annonce (état, caractéristiques)', 'misleading_listing'],
        'autre' => ['Autre problème', 'inconclusive'],
    ];

    public function show(string $token, string $choix)
    {
        abort_unless(array_key_exists($choix, self::CHOIX), 404);
        $submission = Submission::where('token', $token)->with('listing')->firstOrFail();

        return view('retour', ['submission' => $submission, 'choix' => $choix]);
    }

    public function store(Request $request, string $token)
    {
        $submission = Submission::where('token', $token)->firstOrFail();

        $donnees = $request->validate([
            'choix' => ['required', 'in:' . implode(',', array_keys(self::CHOIX))],
            'probleme' => ['required_if:choix,probleme', 'nullable', 'in:' . implode(',', array_keys(self::PROBLEMES))],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'preuves' => ['nullable', 'array', 'max:5'],
            'preuves.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ], [
            'probleme.required_if' => 'Choisissez le type de problème.',
        ]);

        $preuves = $request->file('preuves', []);
        $choix = $donnees['choix'];

        $outcome = Outcome::create([
            'listing_id' => $submission->listing_id,
            'submission_id' => $submission->id,
            'purchase_decision' => match ($choix) {
                'conforme', 'probleme' => 'bought',
                'renonce' => 'abandoned',
                default => 'undecided',
            },
            'item_received' => $choix === 'conforme' ? true : null,
            'item_conform' => match ($choix) {
                'conforme' => true,
                'probleme' => false,
                default => null,
            },
            'problem_type' => $choix === 'probleme' ? $donnees['probleme'] : null,
            'label' => $this->libelle($choix, $donnees['probleme'] ?? null, count($preuves) > 0),
            // Une preuve jointe fait passer de « déclaré » à « prouvé ».
            'certainty' => count($preuves) > 0 ? 'evidenced' : 'declared',
            'provenance' => 'requester',
            'comment' => $donnees['commentaire'] ?? null,
        ]);

        foreach ($preuves as $fichier) {
            Evidence::create([
                'listing_id' => $submission->listing_id,
                'submission_id' => $submission->id,
                'outcome_id' => $outcome->id,
                'type' => 'outcome_proof',
                'path' => $fichier->store('preuves'),
                'original_name' => mb_substr($fichier->getClientOriginalName(), 0, 190),
                'mime' => $fichier->getMimeType(),
                'size' => $fichier->getSize(),
                'sha256' => hash_file('sha256', $fichier->getRealPath()),
                'provenance' => 'requester',
                'retention_until' => now()->addMonths(12)->toDateString(),
            ]);
        }

        return view('retour-merci');
    }

    /**
     * Un achat abandonné n'est jamais un indice de fraude, et une fraude sans
     * preuve reste une suspicion.
     */
    private function libelle(string $choix, ?string $probleme, bool $prouve): ?string
    {
        if ($choix === 'conforme') {
            return 'legitimate';
        }
        if ($choix !== 'probleme') {
            return null;
        }

        $libelle = self::PROBLEMES[$probleme][1] ?? 'inconclusive';

        if (!$prouve && in_array($libelle, ['confirmed_fraud', 'counterfeit_confirmed'], true)) {
            return 'suspected_fraud';
        }

        return $libelle;
    }
}
