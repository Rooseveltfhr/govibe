<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deux tables séparées : le catalogue (ce que GOVIBE vend) et les
     * demandes (ce qu'un client commande). Le catalogue évolue sans toucher
     * aux demandes déjà passées, qui figent le nom et les prix du jour.
     *
     * Cette forme est celle qu'attend LOUVIA : un catalogue d'agents devient
     * un catalogue de modèles, une demande devient une instance d'agent
     * rattachée à un compte client. Les colonnes de déploiement (canal,
     * numéro WhatsApp, fournisseur) vivent déjà sur la demande pour que la
     * reprise soit un déplacement de lignes, pas une refonte.
     */
    public function up(): void
    {
        Schema::create('agents_ia', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('nom', 120);
            $table->string('categorie', 60)->nullable();
            $table->string('icone', 60)->default('fa-robot');
            $table->string('description_courte', 400);

            // Ce que l'agent sait faire, et par où il parle. Des listes qui
            // changent au gré du commercial : du JSON, pas des tables.
            $table->json('capacites')->nullable();
            $table->json('canaux')->nullable();

            // Deux prix distincts : l'installation est ponctuelle, le service
            // mensuel couvre l'infrastructure, l'usage IA et le support.
            // Afficher le seul frais d'installation ferait croire à un
            // paiement unique.
            $table->decimal('prix_installation', 10, 2)->nullable();
            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->string('devise', 8)->default('USD');
            $table->boolean('sur_devis')->default(false);

            // Encadré affiché sur la fiche : un agent de clinique doit dire
            // qu'il ne pose aucun diagnostic.
            $table->text('avertissement')->nullable();

            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        Schema::create('demandes_agent_ia', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();

            $table->foreignId('agent_ia_id')->nullable()->constrained('agents_ia')->nullOnDelete();
            // Nom et prix figés : le catalogue peut changer après la commande.
            $table->string('agent_nom', 120);
            $table->decimal('prix_installation', 10, 2)->nullable();
            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->string('devise', 8)->default('USD');
            $table->boolean('sur_devis')->default(false);

            // L'entreprise
            $table->string('entreprise', 200);
            $table->string('responsable', 150);
            $table->string('email', 190);
            $table->string('telephone', 40);
            $table->string('secteur', 80)->nullable();
            $table->string('pays', 80)->nullable();
            $table->string('ville', 120)->nullable();
            $table->string('site_web', 255)->nullable();

            // Le besoin
            $table->text('objectifs')->nullable();
            $table->text('a_automatiser')->nullable();
            $table->string('volume_conversations', 40)->nullable();
            $table->string('langues', 120)->nullable();
            $table->string('canal', 40)->nullable();
            $table->json('integrations')->nullable();
            $table->text('message')->nullable();

            // Le paiement. Aucune passerelle n'encaisse en ligne aujourd'hui :
            // le client choisit un moyen, paie hors ligne et envoie sa preuve.
            $table->string('moyen_paiement', 60)->nullable();
            $table->string('moyen_paiement_nom', 80)->nullable();
            $table->string('statut_paiement', 30)->default('en_attente');

            $table->string('statut', 30)->default('nouvelle');

            // Déploiement. Le fournisseur d'IA est une note interne : le
            // client achète un Agent GOVIBE, pas un abonnement à un tiers.
            $table->string('fournisseur', 80)->nullable();
            $table->string('numero_whatsapp', 40)->nullable();
            $table->string('url_agent', 255)->nullable();
            $table->timestamp('deploye_le')->nullable();

            $table->text('notes_internes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('statut_paiement');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_agent_ia');
        Schema::dropIfExists('agents_ia');
    }
};
