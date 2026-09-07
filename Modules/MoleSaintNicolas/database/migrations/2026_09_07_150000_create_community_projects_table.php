<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('status', ['planifie', 'en_cours', 'termine'])->default('planifie');
            $table->text('description');

            $table->enum('content_status', ['verified', 'submitted', 'needs_review'])
                ->default('needs_review');
            $table->text('source_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });

        Schema::create('project_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->string('author_name', 100);
            $table->text('body');
            // Modération avant publication : un formulaire public sans compte
            // ni anti-spam ne peut pas publier instantanément — un commentaire
            // n'apparaît qu'après validation par un membre de l'équipe.
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_comments');
        Schema::dropIfExists('community_projects');
    }
};
