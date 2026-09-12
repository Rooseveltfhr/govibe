<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — les employés du commerce.
 *
 * Un compte TAGTOA est un commerce réel : un patron, et les gens qui tiennent
 * ses caisses. Le patron crée ses employés, leur donne un rôle et un code à
 * 4–6 chiffres qui leur sert à la fois de mot de passe et de confirmation pour
 * les actions sérieuses (remise, retour d'article, suppression au catalogue).
 *
 * Cette table appartient à TAGTOA seul — aucune référence au cœur Biztap.
 * C'est délibéré : le jour où Biztap sera retiré, les employés du commerce ne
 * seront pas à refaire.
 *
 * Le code n'est jamais stocké en clair : `pin_hash` est un bcrypt, et le modèle
 * masque la colonne à la sérialisation (voir Staff::$hidden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_staff', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();

            // owner | manager | cashier — voir Support/Pos/StaffAccess.
            $table->string('role', 20)->default('cashier')->index();
            $table->string('pin_hash');

            // Caisse habituelle. Facultative : un employé peut tourner, et
            // supprimer une caisse ne doit pas emporter la fiche de l'employé.
            $table->foreignId('terminal_id')->nullable()
                ->constrained('tagtoa_pos_terminals')->nullOnDelete();

            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by')->nullable();      // qui l'a enregistré
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Un même employé ne peut pas être enregistré deux fois dans le même
            // commerce sous le même e-mail. L'unicité est volontairement LOCALE :
            // deux commerces différents peuvent employer la même personne.
            $table->unique(['tenant_id', 'email'], 'tagtoa_staff_tenant_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_staff');
    }
};
