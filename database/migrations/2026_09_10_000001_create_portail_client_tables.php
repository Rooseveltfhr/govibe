<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comptes du portail client, dans leur propre table et derrière leur propre
     * garde.
     *
     * Séparés de users, qui porte le personnel : à cent mille comptes clients
     * pour une vingtaine d'employés, une erreur d'habilitation dans une table
     * partagée ouvrirait la comptabilité, les fiches de prospection et les
     * preuves de paiement. Ce sont deux surfaces d'authentification distinctes,
     * pas deux rôles sur la même.
     */
    public function up(): void
    {
        Schema::create('comptes_portail', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Le compte pilote un client : c'est lui qui porte la facturation.
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('nom', 150);
            $table->string('email', 190)->unique();
            $table->string('telephone', 40)->nullable();
            $table->string('password');

            $table->timestamp('email_verifie_le')->nullable();
            $table->string('jeton_verification', 64)->nullable()->unique();

            // Un compte désactivé garde son historique mais ne peut plus entrer.
            $table->boolean('actif')->default(true);

            $table->timestamp('dernier_login_le')->nullable();
            $table->string('dernier_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('client_id');
        });

        // Journal des connexions : sert au support, et rend visible une série
        // de tentatives échouées qui, sans trace, passerait inaperçue.
        Schema::create('connexions_portail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_portail_id')->nullable()
                ->constrained('comptes_portail')->nullOnDelete();
            $table->string('email', 190);
            $table->boolean('reussie');
            $table->string('motif', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
            $table->index(['ip', 'created_at']);
        });

        // Rattachement des services déjà vendus au compte client, pour que le
        // portail montre un historique et non une page vide.
        foreach (['demandes_agent_ia', 'inscriptions_session'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('client_id')->nullable()->after('id')
                        ->constrained('clients')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['demandes_agent_ia', 'inscriptions_session'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('client_id');
                });
            }
        }

        Schema::dropIfExists('connexions_portail');
        Schema::dropIfExists('comptes_portail');
    }
};
