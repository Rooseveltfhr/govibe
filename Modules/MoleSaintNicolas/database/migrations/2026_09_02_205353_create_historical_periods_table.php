<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('start_year')->nullable();
            $table->integer('end_year')->nullable();
            $table->unsignedInteger('display_order')->default(0);
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
        Schema::dropIfExists('historical_periods');
    }
};
