<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA CARD — carte NFC prépayée « closed-loop », valable chez TOUS les
 * marchands TAGTOA. L'UID n'est jamais stocké en clair : uid_hash (lookup) +
 * uid_enc (chiffré). balance_minor = solde en unités mineures (entier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_card_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();   // marchand émetteur (null = plateforme)
            $table->string('uid_hash', 64)->unique();           // sha256 de l'UID normalisé
            $table->text('uid_enc')->nullable();                // UID chiffré (récupérable si besoin)
            $table->string('code', 32)->nullable()->index();    // code lisible imprimé sur la carte
            $table->string('holder_name')->nullable();
            $table->string('holder_phone', 40)->nullable();
            $table->string('currency', 8)->default('HTG');
            $table->bigInteger('balance_minor')->default(0);    // solde (unités mineures)
            $table->string('pin_hash')->nullable();             // bcrypt du PIN (autorisation de dépense)
            $table->string('status', 12)->default('active');    // active | blocked | lost
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_card_accounts');
    }
};
