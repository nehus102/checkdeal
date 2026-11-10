<?php

namespace Tests\Unit;

use App\Verification\AnalyseDescription;
use App\Verification\AnnonceUrl;
use App\Verification\RegleV1;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RegleV1Test extends TestCase
{
    private function evaluer(array $obs): array
    {
        return (new RegleV1())->evaluer($obs, Carbon::parse('2026-11-10'));
    }

    private function signal(array $resultat, string $code): array
    {
        return collect($resultat['signaux'])->firstWhere('code', $code);
    }

    public function test_annonce_vide_donne_100_avec_une_fiabilite_faible(): void
    {
        $r = $this->evaluer([]);

        $this->assertSame(100, $r['score']);
        $this->assertSame('faible', $r['confiance']);
        $this->assertSame('unknown', $this->signal($r, 'price_below_market')['statut']);
    }

    public function test_annonce_typique_d_arnaque(): void
    {
        $r = $this->evaluer([
            'price_amount' => '300',
            'comparable_prices' => '700 ; 720 ; 680',
            'seller_review_count' => '0',
            'seller_account_created_at' => '2026-11-05',
            'description' => 'iPhone neuf urgent',
            'flag_off_platform_payment' => 'oui',
        ]);

        // 30 (prix −57 %) + 20 (0 avis) + 20 (5 jours) + 5 (description courte) + 25 (virement) = 100
        $this->assertSame(0, $r['score']);
        $this->assertSame('present', $this->signal($r, 'price_below_market')['statut']);
        $this->assertSame('-57 %', $this->signal($r, 'price_below_market')['valeur']);
        $this->assertSame('Risque élevé', RegleV1::verdict($r['score'])[0]);
    }

    public function test_vendeur_etabli_et_prix_normal(): void
    {
        $r = $this->evaluer([
            'price_amount' => '690',
            'comparable_prices' => '700, 720, 680, 710',
            'seller_rating' => '4,8',
            'seller_rating_max' => '5',
            'seller_review_count' => '57',
            'seller_account_created_at' => '2019-03-01',
            'description' => str_repeat('Très bon état, facture et boîte d\'origine. ', 3),
        ]);

        $this->assertSame(100, $r['score']);
        $this->assertSame('haute', $r['confiance']);
        $this->assertSame(705.0, $r['mediane']);
        $this->assertSame('absent', $this->signal($r, 'seller_rating_low')['statut']);
    }

    public function test_note_en_pourcentage_comme_ebay(): void
    {
        $r = $this->evaluer(['seller_rating' => '72', 'seller_rating_max' => '100', 'seller_review_count' => '40']);

        $this->assertSame('present', $this->signal($r, 'seller_rating_low')['statut']);
        $this->assertSame(85, $r['score']);
    }

    public function test_une_note_sur_deux_avis_reste_inconnue(): void
    {
        $r = $this->evaluer(['seller_rating' => '1', 'seller_review_count' => '2']);

        $this->assertSame('unknown', $this->signal($r, 'seller_rating_low')['statut']);
        $this->assertSame(10, $this->signal($r, 'seller_no_reviews')['points']);
    }

    public function test_moins_de_trois_comparables_ne_jugent_pas_le_prix(): void
    {
        $r = $this->evaluer(['price_amount' => '100', 'comparable_prices' => '700 ; 720']);

        $this->assertSame('unknown', $this->signal($r, 'price_below_market')['statut']);
    }

    public function test_drapeau_non_verifie_ne_compte_pas(): void
    {
        $r = $this->evaluer(['flag_image_reused' => '', 'flag_stock_photo' => 'non']);

        $this->assertSame('unknown', $this->signal($r, 'image_reused')['statut']);
        $this->assertSame('absent', $this->signal($r, 'stock_photo')['statut']);
        $this->assertSame(100, $r['score']);
    }

    public function test_normalisation_des_liens(): void
    {
        $this->assertSame(
            'leboncoin.fr/ad/telephones_objets_connectes/2890000000',
            AnnonceUrl::normaliser('https://www.leboncoin.fr/ad/telephones_objets_connectes/2890000000/?utm_source=x#photos')
        );
        $this->assertSame('vinted', AnnonceUrl::plateforme('https://www.vinted.fr/items/123-veste'));
        $this->assertSame('ebay', AnnonceUrl::plateforme('https://www.ebay.fr/itm/123'));
        $this->assertSame('autre', AnnonceUrl::plateforme('https://exemple.com/annonce'));
    }

    public function test_suggestions_dans_la_description(): void
    {
        $s = AnalyseDescription::suggestions("Urgent ! Contactez-moi sur WhatsApp au 06 12 34 56 78, paiement par virement uniquement.");

        $this->assertArrayHasKey('off_platform_contact', $s);
        $this->assertArrayHasKey('off_platform_payment', $s);
        $this->assertArrayHasKey('urgency_pressure', $s);
        $this->assertArrayNotHasKey('remote_only', $s);

        // Texte arrivé en Windows-1252 : la détection fonctionne quand même.
        $latin = mb_convert_encoding('Vélo urgent, paiement par virement', 'Windows-1252', 'UTF-8');
        $this->assertArrayHasKey('off_platform_payment', AnalyseDescription::suggestions($latin));
    }
}
