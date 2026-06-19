<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('tagtoa_loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('points_required');
            $table->decimal('discount_value', 8, 2)->nullable();
            $table->string('discount_type')->default('fixed'); // fixed|percent
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_rewards');
    }
};
