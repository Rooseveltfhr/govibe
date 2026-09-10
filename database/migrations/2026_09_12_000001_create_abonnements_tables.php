<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Catalogue commun aux trois services ─────────────────────────
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('service', 30);
            $table->string('nom', 120);
            $table->text('description')->nullable();

            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->decimal('prix_annuel', 10, 2)->nullable();
            $table->string('devise', 8)->default('USD');

            // Aucune valeur par défaut inventée : le taux applicable se saisit
            // dans l'ERP. Un taux codé de mémoire se paie en redressement.
            $table->decimal('tca_taux', 5, 2)->default(0);

            $table->json('quotas')->nullable();
            $table->json('fonctionnalites')->nullable();
            $table->unsignedSmallInteger('essai_jours')->default(0);
            $table->boolean('sur_devis')->default(false);

            $table->boolean('actif')->default(true);
            $table->boolean('mis_en_avant')->default(false);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['service', 'actif', 'ordre']);
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 30)->unique();

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            // Dupliqué pour filtrer sans jointure, et pour survivre à la
            // suppression d'un plan du catalogue.
            $table->string('service', 30);
            $table->string('plan_nom', 120);

            $table->string('statut', 24)->default('essai');
            $table->string('cycle', 12)->default('mensuel');

            // Figés à la souscription : un tarif révisé ne réécrit pas les
            // contrats en cours.
            $table->decimal('prix_unitaire', 10, 2);
            $table->string('devise', 8);
            $table->decimal('tca_taux', 5, 2)->default(0);
            $table->decimal('taux_change_htg', 14, 6)->nullable();

            $table->date('date_debut');
            $table->date('essai_fin')->nullable();
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->date('date_prochaine_facture')->nullable();

            $table->boolean('renouvellement_auto')->default(true);
            $table->timestamp('resilie_le')->nullable();
            $table->date('resiliation_effective_le')->nullable();
            $table->text('motif_resiliation')->nullable();

            $table->text('notes_internes')->nullable();
            $table->timestamps();

            // La requête du cron de facturation : statut + échéance.
            $table->index(['statut', 'date_prochaine_facture']);
            $table->index(['client_id', 'statut']);
        });

        Schema::create('evenements_abonnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('statut_avant', 24)->nullable();
            $table->string('statut_apres', 24)->nullable();
            $table->string('source', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['abonnement_id', 'created_at']);
        });

        // ── La facture existante porte désormais la période couverte ────
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('abonnement_id')->nullable()->after('booking_id')
                ->constrained('abonnements')->nullOnDelete();
            $table->date('periode_debut')->nullable()->after('due_date');
            $table->date('periode_fin')->nullable()->after('periode_debut');

            // Le taux est gelé à l'émission : le client règle ce qui est écrit,
            // même trois jours plus tard.
            $table->string('devise', 8)->default('USD')->after('total');
            $table->decimal('taux_change', 14, 6)->nullable()->after('devise');
            $table->decimal('montant_converti', 15, 2)->nullable()->after('taux_change');
            $table->string('devise_convertie', 8)->nullable()->after('montant_converti');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('abonnement_id');
            $table->dropColumn([
                'periode_debut', 'periode_fin', 'devise',
                'taux_change', 'montant_converti', 'devise_convertie',
            ]);
        });

        Schema::dropIfExists('evenements_abonnement');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('plans');
    }
};
