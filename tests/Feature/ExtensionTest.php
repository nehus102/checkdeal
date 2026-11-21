<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.leboncoin.fr/ad/photo_audio_video/3296710037';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['verif.admin_password' => 'secret']);
    }

    private function envoyer(array $releves = [], array $autres = [])
    {
        return $this->postJson('/api/extension/verifier', array_merge([
            'url' => self::URL,
            'email' => 'acheteur@example.com',
            'consent_followup' => true,
            'releves' => array_merge([
                'title' => 'ZMF Caldera Closed (Ash)',
                'price_amount' => 2300,
                'seller_rating' => 5,
                'seller_rating_max' => 5,
                'seller_review_count' => 1,
                'seller_account_created_at' => '2014-06-01',
                'favorites_count' => 2,
                'seller_badges' => ['Réactif', 'Numéro vérifié'],
                'secure_transaction' => true,
                'extension_index' => 'moyen (50/100)',
            ], $releves),
        ], $autres));
    }

    public function test_la_fiche_entre_dans_la_file_avec_ses_releves(): void
    {
        $reponse = $this->envoyer()->assertCreated()->assertJsonPath('deja_evaluee', false);

        $submission = Submission::firstOrFail();
        $this->assertSame(Submission::CANAL_EXTENSION, $submission->channel);
        $this->assertTrue($submission->consent_followup);
        $this->assertStringEndsWith('/suivi/' . $submission->token, $reponse->json('suivi_url'));

        $obs = $submission->listing->observationsCourantes();
        $this->assertSame('1', $obs['seller_review_count']);
        $this->assertSame('2014-06-01', $obs['seller_account_created_at']);
        $this->assertSame('Réactif, Numéro vérifié', $obs['seller_badges']);
        $this->assertSame('oui', $obs['secure_transaction']);
        $this->assertSame('ZMF Caldera Closed (Ash)', $submission->listing->title);
        $this->assertSame('extension', $submission->listing->observations()->value('observed_by'));
    }

    public function test_la_fiche_analyste_est_pre_remplie(): void
    {
        $this->envoyer();
        $listing = Listing::firstOrFail();

        $this->withSession(['admin' => true])->get("/admin/annonces/{$listing->id}")
            ->assertOk()
            ->assertSee("Relevé par l'extension", false)
            ->assertSee('moyen (50/100)')
            ->assertSee('value="2014-06-01"', false);

        $this->withSession(['admin' => true])->get('/admin')->assertSee('Extension');
    }

    public function test_meme_annonce_renvoyee_n_ajoute_pas_d_observation(): void
    {
        $this->envoyer();
        $avant = Listing::firstOrFail()->observations()->count();

        $this->envoyer(['seller_review_count' => 2]);

        $this->assertSame(2, Submission::count());
        $this->assertSame(1, Listing::count());
        $this->assertSame($avant + 1, Listing::firstOrFail()->observations()->count());
    }

    public function test_email_obligatoire_en_json(): void
    {
        $this->envoyer([], ['email' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertSame(0, Submission::count());
    }

    public function test_photos_retrouvees_a_l_identique(): void
    {
        config(['services.serpapi.key' => 'cle']);
        Http::fake(['serpapi.com/*' => Http::sequence()
            ->push(['visual_matches' => [
                ['link' => 'https://www.vinted.fr/items/1', 'exact_matches' => true],
                ['link' => 'https://www.leboncoin.fr/ad/2', 'exact_matches' => true],
            ]])
            ->push(['visual_matches' => [['link' => 'https://presse.fr/a', 'exact_matches' => false]]])
            ->push([], 500),
        ]);

        $this->postJson('/api/extension/photos', ['images' => [
            'https://img.leboncoin.fr/api/v1/a.jpg',
            'https://img.leboncoin.fr/api/v1/b.jpg',
            'https://img.leboncoin.fr/api/v1/c.jpg',
            'https://ailleurs.example/d.jpg',
        ]])
            ->assertOk()
            ->assertJsonPath('risk_level', 'a_verifier')
            ->assertJsonPath('score', 33)
            ->assertJsonCount(3, 'image_matches')
            ->assertJsonPath('image_matches.0.matches.0.domain', 'vinted.fr')
            ->assertJsonCount(1, 'image_matches.0.matches')
            ->assertJsonCount(0, 'image_matches.2.matches');

        Http::assertSentCount(3);
    }

    public function test_photos_hors_plateforme_refusees_sans_appel(): void
    {
        config(['services.serpapi.key' => 'cle']);
        Http::fake();

        $this->postJson('/api/extension/photos', ['images' => ['https://ailleurs.example/d.jpg', 'http://img.leboncoin.fr/x.jpg']])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_photos_sans_cle_serpapi(): void
    {
        config(['services.serpapi.key' => '']);
        $this->postJson('/api/extension/photos', ['images' => ['https://img.leboncoin.fr/a.jpg']])->assertStatus(503);
    }

    public function test_aucun_champ_inconnu_n_est_stocke(): void
    {
        $this->envoyer(['seller_name' => 'Chun', 'seller_id' => '8bcaf786']);

        $champs = Listing::firstOrFail()->observations()->pluck('field')->all();
        $this->assertNotContains('seller_name', $champs);
        $this->assertNotContains('seller_id', $champs);
    }
}
