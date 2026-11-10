<?php

namespace App\Mail;

use App\Http\Controllers\RetourController;
use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** « Avez-vous acheté ? » : la question qui donne sa valeur à la base. */
class RetourMail extends Mailable
{
    public function __construct(public Submission $submission, public int $numero) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->numero === 1 ? 'Alors, cette annonce ?' : 'Une dernière question sur cette annonce');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.retour', with: [
            'choix' => collect(RetourController::CHOIX)->map(fn ($libelle, $code) => [
                'libelle' => $libelle,
                'lien' => route('retour.show', [$this->submission->token, $code]),
            ]),
            'titre' => $this->submission->listing->title,
        ]);
    }
}
