<?php

namespace App\Mail;

use App\Models\Submission;
use App\Verification\RegleV1;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResultatMail extends Mailable
{
    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre vérification d'annonce est prête");
    }

    public function content(): Content
    {
        $evaluation = $this->submission->listing->derniereEvaluation();

        return new Content(markdown: 'mail.resultat', with: [
            'evaluation' => $evaluation,
            'verdict' => RegleV1::verdict($evaluation->final_score),
            'lien' => route('demande.suivi', $this->submission->token),
        ]);
    }
}
