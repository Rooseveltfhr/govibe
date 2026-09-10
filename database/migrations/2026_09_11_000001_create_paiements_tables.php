<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Passerelles : les deux familles dans la même table ──────────
        Schema::table('passerelles_paiement', function (Blueprint $table) {
            // manuel : le client vire puis envoie une preuve, un agent valide.
            // api    : la passerelle répond elle-même si le paiement a eu lieu.
            $table->string('mode', 10)->default('manuel')->after('code');
            $table->string('pilote', 40)->nullable()->after('mode');
            $table->string('environnement', 12)->default('test')->after('pilote');

            // Clés API. Chiffrées au repos, jamais renvoyées au navigateur.
            $table->text('identifiants')->nullable()->after('environnement');

            $table->json('devises_supportees')->nullable()->after('identifiants');
            $table->decimal('frais_pourcent', 5, 3)->default(0)->after('devises_supportees');
            $table->decimal('frais_fixe', 10, 2)->default(0)->after('frais_pourcent');

            $table->boolean('disponible_public')->default(true)->after('frais_fixe');
            // Le cash n'existe qu'en caisse : jamais sélectionnable en ligne,
            // sinon n'importe qui déclare avoir payé en espèces.
            $table->boolean('disponible_caisse')->default(false)->after('disponible_public');
        });

        // ── Taux de change réglés par le super admin ────────────────────
        Schema::create('taux_change', function (Blueprint $table) {
            $table->id();
            $table->string('devise_source', 8);
            $table->string('devise_cible', 8);
            $table->decimal('taux', 14, 6);
            $table->foreignId('defini_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applique_depuis');
            $table->timestamps();

            // L'historique est conservé : une facture émise hier garde son taux.
            $table->index(['devise_source', 'devise_cible', 'applique_depuis']);
        });

        // ── Paiements ───────────────────────────────────────────────────
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 30)->unique();

            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            // Un paiement peut viser une facture, une inscription, une demande
            // d'agent : la même mécanique sert toutes les unités d'affaires.
            $table->nullableMorphs('payable');

            $table->foreignId('passerelle_id')->nullable()
                ->constrained('passerelles_paiement')->nullOnDelete();
            $table->string('mode', 10);
            $table->string('pilote', 40)->nullable();
            $table->string('passerelle_nom', 80)->nullable();

            // Montant demandé, et son équivalent gelé dans l'autre devise :
            // le client règle ce qui était écrit, même trois jours plus tard.
            $table->decimal('montant', 12, 2);
            $table->string('devise', 8);
            $table->decimal('montant_converti', 12, 2)->nullable();
            $table->string('devise_convertie', 8)->nullable();
            $table->decimal('taux_change', 14, 6)->nullable();

            $table->string('statut', 20)->default('initie');
            $table->string('reference_externe', 120)->nullable();
            $table->string('jeton_externe', 190)->nullable();
            $table->string('cle_idempotence', 120)->unique();
            $table->text('echec_motif')->nullable();

            // Réponses de la passerelle, secrets retirés avant écriture.
            $table->json('charge_utile')->nullable();

            $table->foreignId('preuve_paiement_id')->nullable()
                ->constrained('preuves_paiement')->nullOnDelete();

            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approuve_le')->nullable();
            $table->timestamp('paye_le')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('reference_externe');
        });

        // ── Journal : un paiement ne change jamais d'état sans trace ────
        Schema::create('evenements_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('statut_avant', 20)->nullable();
            $table->string('statut_apres', 20)->nullable();
            $table->string('source', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['paiement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_paiement');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('taux_change');

        Schema::table('passerelles_paiement', function (Blueprint $table) {
            $table->dropColumn([
                'mode', 'pilote', 'environnement', 'identifiants',
                'devises_supportees', 'frais_pourcent', 'frais_fixe',
                'disponible_public', 'disponible_caisse',
            ]);
        });
    }
};
