<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes_abonnement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 30)->unique();

            // ── Ce qui est commandé ──────────────────────────────────────
            // Le plan peut être retiré du catalogue ou révisé : le nom et le
            // tarif sont copiés ici, sinon une commande passée relirait un
            // prix qu'elle n'a jamais annoncé.
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('service', 30);
            $table->string('plan_nom', 120);
            $table->string('cycle', 12)->default('mensuel');

            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('tca_taux', 5, 2)->default(0);
            $table->decimal('montant_ttc', 12, 2);
            $table->string('devise', 8);

            // ── Ce que le client règle, dans la devise de SA passerelle ──
            // Payer par PayPal se fait en USD, par MonCash en gourdes. Le
            // montant converti et le taux appliqué sont gelés à la commande :
            // le client paie ce qui lui a été montré, même si le taux bouge
            // dans l'heure.
            $table->string('devise_paiement', 8);
            $table->decimal('taux_change', 14, 6)->nullable();
            $table->decimal('montant_a_payer', 14, 2);

            // ── Qui commande ─────────────────────────────────────────────
            // Le compte client est rattaché après vérification de l'adresse,
            // pas à la commande : commander n'exige pas de créer un compte.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('nom_complet', 150);
            $table->string('entreprise', 150)->nullable();
            $table->string('email', 190);
            $table->string('whatsapp', 40);

            // ── Le domaine concerné ──────────────────────────────────────
            // Colonne et non simple clé JSON : c'est le premier critère de
            // recherche de l'équipe, et le doublon qu'il faut repérer avant
            // d'approvisionner deux fois le même nom.
            $table->string('domaine', 253)->nullable();
            $table->string('domaine_origine', 20)->nullable();
            $table->unsignedTinyInteger('duree_annees')->nullable();
            $table->text('besoins')->nullable();
            $table->json('details')->nullable();

            // ── Règlement ────────────────────────────────────────────────
            $table->foreignId('passerelle_id')->nullable()
                ->constrained('passerelles_paiement')->nullOnDelete();
            $table->string('passerelle_nom', 80)->nullable();
            $table->string('mode_paiement', 12)->nullable();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->foreignId('preuve_paiement_id')->nullable()
                ->constrained('preuves_paiement')->nullOnDelete();

            $table->string('statut', 24)->default('nouvelle');

            // L'abonnement né de cette commande. Renseigné une seule fois :
            // c'est ce qui empêche de facturer deux contrats pour une commande.
            $table->foreignId('abonnement_id')->nullable()
                ->constrained('abonnements')->nullOnDelete();

            $table->text('notes_internes')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index(['service', 'statut']);
            // Le rattachement au portail cherche par adresse.
            $table->index('email');
            $table->index('domaine');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes_abonnement');
    }
};
