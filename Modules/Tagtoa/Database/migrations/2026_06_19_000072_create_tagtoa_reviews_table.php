<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA REVIEWS — avis clients (note + commentaire) attachés à une ressource
 * publique (menu, booking, site, event…). Modération côté marchand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('subject_type', 24)->index();      // menu|booking|site|event|pay|links
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('subject_alias')->nullable();       // pour affichage/lien rapide
            $table->unsignedTinyInteger('rating');             // 1..5
            $table->string('author_name', 120);
            $table->string('author_phone', 40)->nullable();
            $table->string('author_email', 160)->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 12)->default('pending')->index(); // pending|approved|rejected
            $table->boolean('is_featured')->default(false);
            $table->text('reply')->nullable();                 // réponse du marchand
            $table->timestamp('replied_at')->nullable();
            $table->string('client_uuid', 64)->nullable()->unique(); // idempotence
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_reviews');
    }
};
