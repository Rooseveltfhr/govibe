<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_page_id')->constrained('tagtoa_payment_pages')->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->string('qr_path')->nullable();
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
