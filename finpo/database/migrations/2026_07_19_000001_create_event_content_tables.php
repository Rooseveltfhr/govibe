<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('position')->nullable();
            $table->string('institution')->nullable();
            $table->string('country')->default('Haïti');
            $table->string('category')->default('government'); // config finpo.speaker_categories
            $table->string('photo_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('topic')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
            $table->string('website')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('program_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('type')->default('panel');      // config finpo.session_types
            $table->string('track')->nullable();           // catégorie thématique
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['day', 'starts_at']);
        });

        Schema::create('program_session_speaker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_session_id')->constrained('program_sessions')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('speakers')->cascadeOnDelete();
            $table->string('role')->nullable(); // modérateur, panéliste…
            $table->unique(['program_session_id', 'speaker_id'], 'session_speaker_unique');
        });

        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('tag')->default('Actualité'); // Annonce, Article, Mise à jour…
            $table->string('cover_url')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('photo'); // photo|video
            $table->string('url');
            $table->string('thumb_url')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('edition')->default(2024);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('news_posts');
        Schema::dropIfExists('program_session_speaker');
        Schema::dropIfExists('program_sessions');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('speakers');
    }
};
