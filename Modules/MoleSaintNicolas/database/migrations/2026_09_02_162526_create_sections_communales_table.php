<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections_communales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->unsignedSmallInteger('population_year')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->enum('content_status', ['verified', 'submitted', 'needs_review'])
                ->default('needs_review');
            $table->text('source_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['commune_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections_communales');
    }
};
