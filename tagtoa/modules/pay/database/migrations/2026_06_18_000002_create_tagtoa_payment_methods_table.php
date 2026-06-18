<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — Payment methods
|--------------------------------------------------------------------------
| Une méthode de paiement (MonCash, NatCash, Zelle, PayPal, etc.) rattachée
| à une page de paiement. L'image QR éventuelle passe par spatie/medialibrary
| (collection 'payment-qr') — pas de colonne fichier ici.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_page_id')
                  ->constrained('tagtoa_payment_pages')
                  ->cascadeOnDelete();
            $table->string('type');                       // moncash|natcash|zelle|paypal|stripe|crypto|bank|cash|cod|binance|coinbase
            $table->string('label')->nullable();          // "Mon compte MonCash"
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable(); // numéro / adresse wallet
            $table->text('instructions')->nullable();
            $table->boolean('requires_proof')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_payment_methods');
    }
};
