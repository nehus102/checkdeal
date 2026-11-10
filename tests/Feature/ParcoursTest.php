<?php

namespace Tests\Feature;

use App\Mail\ResultatMail;
use App\Mail\RetourMail;
use App\Models\Listing;
use App\Models\Outcome;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParcoursTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.leboncoin.fr/ad/telephones/2890000000?utm_source=partage';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake();
        config(['verif.admin_password' => 'secret']);
    }

    private function deposer(array $donnees = []): Submission
    {
        $this->post('/verifier', array_merge(['url' => self::URL, 'email' => 'acheteur@example.com', 'consent_followup' => '1'], $donnees))
            ->assertRedirect();

        return Submission::latest('id')->first();
    }

    private function evaluer(Listing $listing, array $donnees): void
    {
        $this->withSession(['admin' => true])
            ->post("/admin/annonces/{$listing->id}", array_merge(['action' => 'envoyer'], $donnees))
            ->assertRedirect('/admin');
    }

    public function test_accueil_et_depot_d_une_demande_avec_capture(): void
    {
        $this->get('/')->assertOk()->assertSee('Vérifier cette annonce');

        $submission = $this->deposer(['captures' => [UploadedFile::fake()->image('annonce.png')]]);

        $this->assertSame('leboncoin', $submission->listing->platform);
        $this->assertSame('pending', $submission->status);
        $this->assertNotSame('acheteur@example.com', $submission->getRawOriginal('email'), "l'e-mail est chiffré en base");
        $this->assertSame(1, $submission->listing->evidence()->count());
        Storage::assertExists($submission->listing->evidence()->first()->path);

        $this->get("/suivi/{$submission->token}")->assertOk()->assertSee('Vérification en cours');
    }

    public function test_evaluation_envoie_le_resultat_et_garde_les_signaux(): void
    {
        $submission = $this->deposer();

        $this->evaluer($submission->listing, [
            'title' => 'iPhone 14 Pro 128 Go',
            'price_amount' => '300',
            'comparable_prices' => '700 ; 720 ; 680',
            'seller_review_count' => '0',
            'flag_off_platform_payment' => 'oui',
        ]);

        $listing = $submission->listing->fresh();
        $evaluation = $listing->derniereEvaluation();
        $this->assertSame(25, $evaluation->final_score);
        $this->assertSame('v1', $evaluation->ruleset_version);
        $this->assertSame(13, $evaluation->signals()->count(), 'un signal par code, présent, absent ou inconnu');
        $this->assertSame('done', $submission->fresh()->status);
        Mail::assertSent(ResultatMail::class, fn ($m) => $m->hasTo('acheteur@example.com'));

        $this->get("/suivi/{$submission->token}")->assertOk()->assertSee('25')->assertSee('Paiement hors plateforme demandé');
    }

    public function test_ajustement_manuel_exige_une_raison_et_reste_trace(): void
    {
        $submission = $this->deposer();

        $this->withSession(['admin' => true])
            ->post("/admin/annonces/{$submission->listing_id}", ['action' => 'envoyer', 'score_final' => '40'])
            ->assertSessionHasErrors('raison_ajustement');

        $this->evaluer($submission->listing, ['score_final' => '40', 'raison_ajustement' => 'Photos volées repérées à la main']);

        $evaluation = $submission->listing->derniereEvaluation();
        $this->assertSame(100, $evaluation->computed_score);
        $this->assertSame(40, $evaluation->final_score);
        $this->assertSame('Photos volées repérées à la main', $evaluation->override_reason);
    }

    public function test_meme_annonce_recente_repond_tout_de_suite(): void
    {
        $premiere = $this->deposer();
        $this->evaluer($premiere->listing, ['price_amount' => '500']);

        $seconde = $this->deposer(['url' => 'https://leboncoin.fr/ad/telephones/2890000000/', 'email' => 'autre@example.com']);

        $this->assertSame($premiere->listing_id, $seconde->listing_id, 'même annonce, une seule fiche');
        $this->assertSame('done', $seconde->status);
        Mail::assertSent(ResultatMail::class, fn ($m) => $m->hasTo('autre@example.com'));
    }

    public function test_les_observations_ne_sont_jamais_ecrasees(): void
    {
        $submission = $this->deposer();
        $this->evaluer($submission->listing, ['price_amount' => '500']);
        $this->evaluer($submission->listing, ['price_amount' => '450']);

        $this->assertSame(['500', '450'], $submission->listing->observations()->where('field', 'price_amount')->orderBy('id')->pluck('value')->all());
        $this->assertSame(2, $submission->listing->assessments()->count());
    }

    public function test_retour_avec_preuve_devient_prouve(): void
    {
        $submission = $this->deposer();
        $this->evaluer($submission->listing, []);

        // Le lien de l'e-mail n'écrit rien : il affiche une confirmation.
        $this->get("/retour/{$submission->token}/probleme")->assertOk();
        $this->assertSame(0, Outcome::count());

        $this->post("/retour/{$submission->token}", [
            'choix' => 'probleme',
            'probleme' => 'non_recu',
            'preuves' => [UploadedFile::fake()->image('virement.png')],
        ])->assertOk();

        $outcome = Outcome::first();
        $this->assertSame('bought', $outcome->purchase_decision);
        $this->assertSame('confirmed_fraud', $outcome->label);
        $this->assertSame('evidenced', $outcome->certainty);
    }

    public function test_retour_sans_preuve_reste_une_suspicion(): void
    {
        $submission = $this->deposer();
        $this->post("/retour/{$submission->token}", ['choix' => 'probleme', 'probleme' => 'non_recu'])->assertOk();

        $this->assertSame('suspected_fraud', Outcome::first()->label);
        $this->assertSame('declared', Outcome::first()->certainty);
    }

    public function test_relances_j3_puis_j10_et_arret_apres_reponse(): void
    {
        $submission = $this->deposer();
        $this->evaluer($submission->listing, []);

        $this->artisan('verif:relances');
        Mail::assertNotSent(RetourMail::class);

        $this->travel(3)->days();
        $this->artisan('verif:relances');
        Mail::assertSent(RetourMail::class, 1);

        $this->travel(7)->days();
        $this->artisan('verif:relances');
        Mail::assertSent(RetourMail::class, 2);

        $this->post("/retour/{$submission->token}", ['choix' => 'renonce']);
        $this->travel(10)->days();
        $this->artisan('verif:relances');
        Mail::assertSent(RetourMail::class, 2);
    }

    public function test_back_office_ferme_sans_session_ni_mot_de_passe(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        config(['verif.admin_password' => '']);
        $this->post('/admin/login', ['password' => ''])->assertSessionHasErrors('password');

        config(['verif.admin_password' => 'secret']);
        $this->post('/admin/login', ['password' => 'secret'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_annonce_temoin(): void
    {
        $this->withSession(['admin' => true])->post('/admin/temoin', ['url' => 'https://www.vinted.fr/items/42-velo'])->assertRedirect();

        $this->assertSame(Submission::CANAL_TEMOIN, Submission::first()->channel);
        $this->assertNull(Submission::first()->email);
    }
}
