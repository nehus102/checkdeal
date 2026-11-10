<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le modèle de la Data Strategy, agnostique au produit : observation (ce qui
 * est vu), signal (comment on l'interprète), évaluation (versionnée), résultat
 * (ce qui s'est réellement passé). Rien n'est écrasé : une réévaluation ajoute
 * une ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        // L'annonce observée, sans identité de vendeur.
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->text('url');
            $table->string('url_normalized', 500)->unique();
            $table->string('title')->nullable();
            $table->string('category', 100)->nullable();
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });

        // Qui a demandé quoi, et par quel canal. `control_sample` : annonces
        // tirées au hasard pour mesurer les taux de base des signaux.
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('b2c_form');
            $table->text('email')->nullable(); // chiffré par le modèle
            $table->string('token', 64)->unique();
            $table->boolean('consent_followup')->default(false);
            $table->string('status', 20)->default('pending'); // pending | done
            $table->timestamp('answered_at')->nullable();
            $table->unsignedTinyInteger('followups_sent')->default(0);
            $table->timestamp('last_followup_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // Ce qui s'est réellement passé, avec sa certitude et sa provenance.
        Schema::create('outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_decision', 20); // bought | abandoned | undecided
            $table->boolean('item_received')->nullable();
            $table->boolean('item_conform')->nullable();
            $table->string('problem_type', 40)->nullable();
            $table->string('label', 30)->nullable();
            $table->string('certainty', 20)->default('declared'); // declared | evidenced | verified
            $table->string('provenance', 20)->default('requester');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // Les pièces brutes : immuables, empreinte et échéance de conservation.
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outcome_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('path')->nullable(); // null une fois purgée
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('provenance', 20)->default('requester');
            $table->boolean('contains_personal_data')->default(true);
            $table->date('retention_until')->nullable();
            $table->timestamps();
        });

        // Couche 1 : ce qui est constaté, une ligne par champ et par relevé.
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('field', 60);
            $table->text('value')->nullable();
            $table->string('observed_by', 40)->default('analyst');
            $table->timestamps();
            $table->index(['listing_id', 'field']);
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('ruleset_version', 20);
            $table->unsignedTinyInteger('computed_score'); // indice de confiance calculé
            $table->unsignedTinyInteger('final_score');    // après ajustement éventuel
            $table->string('override_reason')->nullable();
            $table->string('confidence', 10); // faible | moyenne | haute
            $table->text('rationale')->nullable();
            $table->text('advice')->nullable();
            $table->string('assessor', 20)->default('human');
            $table->timestamps();
        });

        // Couche 2 : l'interprétation, rattachée à l'évaluation qui l'a produite.
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('status', 10); // present | absent | unknown
            $table->string('value')->nullable();
            $table->unsignedTinyInteger('points')->default(0);
            $table->timestamps();
            $table->index(['code', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['signals', 'assessments', 'observations', 'evidence', 'outcomes', 'submissions', 'listings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
