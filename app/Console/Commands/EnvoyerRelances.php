<?php

namespace App\Console\Commands;

use App\Mail\RetourMail;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Relances de la boucle de retour : J+3 puis J+10 après le résultat, tant que
 * le demandeur n'a pas dit s'il a acheté ou renoncé. « Pas encore décidé »
 * n'arrête pas les relances.
 */
class EnvoyerRelances extends Command
{
    protected $signature = 'verif:relances {--dry-run : Affiche sans envoyer}';

    protected $description = 'Envoie les questions de suivi « avez-vous acheté ? »';

    public function handle(): int
    {
        $envoyes = 0;

        foreach (config('verif.relances_jours') as $index => $delai) {
            $candidats = Submission::where('channel', Submission::CANAL_FORMULAIRE)
                ->where('status', 'done')
                ->where('consent_followup', true)
                ->where('followups_sent', $index)
                ->where('answered_at', '<=', now()->subDays($delai))
                ->whereDoesntHave('outcomes', fn ($q) => $q->whereIn('purchase_decision', ['bought', 'abandoned']))
                ->with('listing')
                ->get();

            foreach ($candidats as $submission) {
                if ($this->option('dry-run')) {
                    $this->line('Relance ' . ($index + 1) . " → demande #{$submission->id}");
                    continue;
                }
                Mail::to($submission->email)->send(new RetourMail($submission, $index + 1));
                $submission->update(['followups_sent' => $index + 1, 'last_followup_at' => now()]);
                $envoyes++;
            }
        }

        $this->info("{$envoyes} relance(s) envoyée(s).");

        return self::SUCCESS;
    }
}
