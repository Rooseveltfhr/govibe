<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            // Beaucoup d'événements historiques n'ont qu'une année connue, pas une date
            // précise : happened_on n'est renseigné que quand la source est explicite.
            $table->date('happened_on')->nullable();
            $table->integer('circa_year')->nullable();
            $table->text('description')->nullable();

            $table->enum('content_status', ['verified', 'submitted', 'needs_review'])
                ->default('needs_review');
            $table->text('source_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_events');
    }
};
