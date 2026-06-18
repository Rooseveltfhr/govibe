<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA LOYALTY — Cards
|--------------------------------------------------------------------------
| Carte NFC de fidélité. card_number = 16 chiffres (Luhn-valide, préfixe
| TAGTOA 4297). card_number_encrypted = chiffré (Crypt). cvc = hashé (Hash).
| public_token = identifiant opaque pour l'URL publique (jamais le n° réel).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('tagtoa_loyalty_programs')->cascadeOnDelete();
            $table->string('public_token', 40)->unique();  // URL publique: /loyalty/card/{token}
            $table->string('card_number', 19)->unique();   // 16 chiffres (stockage clair indexable)
            $table->text('card_number_encrypted')->nullable();
            $table->string('cvc');                         // hashé
            $table->date('expiry_date');
            $table->string('cardholder_name');
            $table->string('cardholder_phone')->nullable();
            $table->string('cardholder_email')->nullable();
            $table->decimal('balance', 10, 2)->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->tinyInteger('status')->default(1);     // 1=active, 0=suspended, 2=expired
            $table->tinyInteger('delivery_type')->default(0); // 0=pickup, 1=home, 2=authorized_point
            $table->text('delivery_address')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_cards');
    }
};
