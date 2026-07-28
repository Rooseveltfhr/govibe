<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['coworking_desk', 'private_office', 'meeting_room', 'conference_room', 'training_room', 'event_space'])->default('meeting_room');
            $table->integer('capacity')->default(1);
            $table->decimal('price_per_hour', 12, 2)->nullable();
            $table->decimal('price_per_day', 12, 2)->nullable();
            $table->decimal('price_per_month', 12, 2)->nullable();
            $table->json('amenities')->nullable();
            $table->string('floor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
