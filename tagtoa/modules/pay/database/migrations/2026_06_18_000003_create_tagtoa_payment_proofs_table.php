<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — Payment proofs
|--------------------------------------------------------------------------
| Preuve de paiement soumise par un client. L'image preuve passe par
| spatie/medialibrary (collection 'proof-image').
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_page_id')
                  ->constrained('tagtoa_payment_pages')
                  ->cascadeOnDelete();
            $table->foreignId('payment_method_id')
                  ->constrained('tagtoa_payment_methods')
                  ->cascadeOnDelete();
            $table->string('payer_name');
            $table->string('payer_phone')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->default('HTG');
            $table->string('reference')->nullable();      // numéro de transaction
            $table->tinyInteger('status')->default(0);    // 0=pending, 1=approved, 2=rejected
            $table->text('note')->nullable();             // note interne du owner
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_payment_proofs');
    }
};
