<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('tagtoa_loyalty_programs')->cascadeOnDelete();
            $table->string('public_token', 40)->unique();
            $table->string('card_number', 19)->unique();
            $table->text('card_number_encrypted')->nullable();
            $table->string('cvc');
            $table->date('expiry_date');
            $table->string('cardholder_name');
            $table->string('cardholder_phone')->nullable();
            $table->string('cardholder_email')->nullable();
            $table->decimal('balance', 10, 2)->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_cards');
    }
};
